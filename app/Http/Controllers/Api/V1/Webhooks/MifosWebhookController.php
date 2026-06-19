<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Modules\Loans\Events\ExternalLoanStatusUpdated;
use App\Modules\Loans\Jobs\SyncExternalLoanStatusJob;
use App\Modules\Loans\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MifosWebhookController
{
    public function __invoke(Request $request): Response
    {
        return $this->handle($request);
    }

    public function handle(Request $request): Response
    {
        $secret = config('mifos.webhook.secret');

        // Verify webhook secret if configured
        if ($secret && $request->header('X-Mifos-Signature') !== $secret) {
            Log::warning("MifosWebhook: Invalid signature", [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        // Verify allowed IPs if configured
        $allowedIps = config('mifos.webhook.allowed_ips', []);
        if (! empty($allowedIps) && ! in_array($request->ip(), $allowedIps)) {
            Log::warning("MifosWebhook: IP not allowed", [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'IP not allowed'], Response::HTTP_FORBIDDEN);
        }

        $payload = $request->json()->all();

        Log::info("MifosWebhook received", [
            'event_type' => data_get($payload, 'eventType'),
            'loan_id' => data_get($payload, 'loanId'),
        ]);

        $eventType = data_get($payload, 'eventType');
        $externalLoanId = data_get($payload, 'loanId');

        if (! $externalLoanId) {
            Log::error("MifosWebhook: Missing loanId in payload");
            return response()->json(['error' => 'Missing loanId'], Response::HTTP_BAD_REQUEST);
        }

        $loan = Loan::where('external_loan_id', $externalLoanId)->first();

        if (! $loan) {
            Log::warning("MifosWebhook: Loan not found for external_loan_id {$externalLoanId}");
            return response()->json(['error' => 'Loan not found'], Response::HTTP_NOT_FOUND);
        }

        // Handle different event types
        return match ($eventType) {
            'LOAN_APPROVED', 'LOAN_APPROVED_POST_DISBURSEMENT' => $this->handleApproved($loan, $payload),
            'LOAN_REJECTED' => $this->handleRejected($loan, $payload),
            'LOAN_DISBURSED' => $this->handleDisbursed($loan, $payload),
            'LOAN_REPAID', 'TRANSACTION_POSTED' => $this->handleRepaid($loan, $payload),
            'LOAN_OVERDUE' => $this->handleOverdue($loan, $payload),
            'LOAN_CLOSED', 'LOAN_WRITTEN_OFF' => $this->handleClosed($loan, $payload),
            default => $this->handleGeneric($loan, $payload),
        };
    }

    protected function handleApproved(Loan $loan, array $payload): Response
    {
        $currentStatus = data_get($payload, 'loanStatus.status.value');

        Log::info("MifosWebhook: Loan {$loan->id} approved externally", [
            'external_status' => $currentStatus,
        ]);

        $loan->update([
            'status' => 'funded',
            'sync_status' => 'synced',
            'last_synced_at' => now(),
        ]);

        ExternalLoanStatusUpdated::dispatch($loan->id, $currentStatus, []);

        return response()->json(['success' => true, 'message' => 'Loan approved']);
    }

    protected function handleRejected(Loan $loan, array $payload): Response
    {
        $reason = data_get($payload, 'rejectionReason') ?? 'Rejected by external provider';

        Log::info("MifosWebhook: Loan {$loan->id} rejected externally", [
            'reason' => $reason,
        ]);

        $loan->update([
            'status' => 'cancelled',
            'rejection_reason' => $reason,
            'sync_status' => 'synced',
            'last_synced_at' => now(),
        ]);

        ExternalLoanStatusUpdated::dispatch($loan->id, 'rejected', ['reason' => $reason]);

        return response()->json(['success' => true, 'message' => 'Loan rejected']);
    }

    protected function handleDisbursed(Loan $loan, array $payload): Response
    {
        $disbursementDate = data_get($payload, 'disbursementDate');
        $amount = data_get($payload, 'amount');

        Log::info("MifosWebhook: Loan {$loan->id} disbursed externally", [
            'amount' => $amount,
            'date' => $disbursementDate,
        ]);

        $loan->update([
            'status' => 'active',
            'disbursed_at' => $disbursementDate ? now()->parse($disbursementDate) : now(),
            'sync_status' => 'synced',
            'last_synced_at' => now(),
        ]);

        ExternalLoanStatusUpdated::dispatch($loan->id, 'disbursed', []);

        return response()->json(['success' => true, 'message' => 'Loan disbursed']);
    }

    protected function handleRepaid(Loan $loan, array $payload): Response
    {
        $amount = data_get($payload, 'amount');
        $transactionId = data_get($payload, 'transactionId');

        Log::info("MifosWebhook: Repayment received for loan {$loan->id}", [
            'amount' => $amount,
            'transaction_id' => $transactionId,
        ]);

        // Note: Repayment processing is handled by Repayment module
        // This webhook just triggers a status sync
        ExternalLoanStatusUpdated::dispatch($loan->id, 'repayment_received', [
            'amount' => $amount,
            'transaction_id' => $transactionId,
        ]);

        return response()->json(['success' => true, 'message' => 'Repayment acknowledged']);
    }

    protected function handleOverdue(Loan $loan, array $payload): Response
    {
        $daysOverdue = data_get($payload, 'daysOverdue', 0);

        Log::info("MifosWebhook: Loan {$loan->id} overdue externally", [
            'days_overdue' => $daysOverdue,
        ]);

        $loan->update([
            'status' => 'overdue',
            'sync_status' => 'synced',
            'last_synced_at' => now(),
        ]);

        ExternalLoanStatusUpdated::dispatch($loan->id, 'overdue', compact('daysOverdue'));

        return response()->json(['success' => true, 'message' => 'Loan marked overdue']);
    }

    protected function handleClosed(Loan $loan, array $payload): Response
    {
        Log::info("MifosWebhook: Loan {$loan->id} closed externally");

        $loan->update([
            'status' => 'completed',
            'completed_at' => now(),
            'sync_status' => 'synced',
            'last_synced_at' => now(),
        ]);

        ExternalLoanStatusUpdated::dispatch($loan->id, 'closed', []);

        return response()->json(['success' => true, 'message' => 'Loan closed']);
    }

    protected function handleGeneric(Loan $loan, array $payload): Response
    {
        $eventType = data_get($payload, 'eventType');

        Log::info("MifosWebhook: Generic event for loan {$loan->id}", [
            'event_type' => $eventType,
        ]);

        // Trigger a status sync for unknown events
        SyncExternalLoanStatusJob::dispatch($loan->id);

        return response()->json(['success' => true, 'message' => 'Event acknowledged']);
    }
}
