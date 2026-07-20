<?php

namespace App\Modules\Notifications\Traits;

trait HasDatabaseChannel
{
    public function toDatabase($notifiable): array
    {
        $type = $this->notificationType();

        return [
            'type' => $type,
            'title' => $this->databaseTitle($type),
            'message' => $this->databaseMessage($type, $notifiable),
            'details' => $this->databaseDetails($type),
            'action_text' => $this->databaseAction($type)['text'] ?? null,
            'action_url' => $this->databaseAction($type)['url'] ?? null,
            'data' => $this->data ?? [],
        ];
    }

    protected function notificationType(): string
    {
        return strtolower(
            preg_replace('/Notification$/', '', class_basename(static::class))
        );
    }

    protected function databaseTitle(string $type): string
    {
        return match ($type) {
            'welcome' => 'Welcome to QuickShare',
            'password_reset' => 'Password Reset',
            'kyc_approved' => 'KYC Approved',
            'kyc_rejected' => 'KYC Rejected',
            'loan_submitted' => 'Loan Application Submitted',
            'loan_approved' => 'Loan Approved',
            'loan_rejected' => 'Loan Application Rejected',
            'loan_funded' => 'Loan Fully Funded',
            'loan_disbursed' => 'Loan Disbursed',
            'repayment_reminder' => 'Repayment Reminder',
            'repayment_overdue' => 'Repayment Overdue',
            'repayment_received' => 'Payment Received',
            'funding_payment_submitted' => 'Funding Payment Submitted',
            'funding_payment_approved' => 'Funding Payment Approved',
            'funding_payment_rejected' => 'Funding Payment Rejected',
            'funding_payment_info_requested' => 'More Information Required',
            default => 'Notification',
        };
    }

    protected function databaseMessage(string $type, $notifiable): string
    {
        $reference = $this->data['reference'] ?? 'N/A';
        $currency = config('loans.currency_symbol', 'N$');
        $amount = isset($this->data['amount'])
            ? $currency . ' ' . number_format((float) $this->data['amount'], 2)
            : null;

        return match ($type) {
            'welcome' => "Hi {$notifiable->first_name}, welcome to QuickShare! Complete your profile to get started.",
            'password_reset' => 'A password reset was requested for your account. If this was not you, please contact support.',
            'kyc_approved' => 'Your KYC verification has been approved. You can now apply for loans.',
            'kyc_rejected' => 'Your KYC verification was not approved. Please review the feedback and resubmit.',
            'loan_submitted' => "Your loan application for {$amount} has been submitted and is under review.",
            'loan_approved' => "Your loan application {$reference} has been approved and will be listed for funding.",
            'loan_rejected' => 'Your loan application was not approved at this time.',
            'loan_funded' => "Great news! Loan {$reference} is fully funded and will be disbursed soon.",
            'loan_disbursed' => "Funds for loan {$reference} have been disbursed to your bank account.",
            'repayment_reminder' => "Your repayment for loan {$reference} is due soon.",
            'repayment_overdue' => 'Your loan repayment is now overdue. Please make payment to avoid penalties.',
            'repayment_received' => "Thank you! We received your repayment of {$amount} for loan {$reference}.",
            'funding_payment_submitted' => "A funding payment of {$amount} has been submitted for loan {$reference}.",
            'funding_payment_approved' => "Your funding payment of {$amount} for loan {$reference} has been approved.",
            'funding_payment_rejected' => "Your funding payment of {$amount} for loan {$reference} was rejected.",
            'funding_payment_info_requested' => "We need more information for your funding payment of {$amount} on loan {$reference}.",
            default => 'You have a new notification.',
        };
    }

    protected function databaseDetails(string $type): array
    {
        $currency = config('loans.currency_symbol', 'N$');
        $details = [];

        if (! empty($this->data['reference'])) {
            $details['Loan Reference'] = $this->data['reference'];
        }

        if (isset($this->data['amount'])) {
            $details['Amount'] = $currency . ' ' . number_format((float) $this->data['amount'], 2);
        }

        if (! empty($this->data['due_date'])) {
            $details['Due Date'] = $this->data['due_date'];
        }

        if (isset($this->data['days_until_due'])) {
            $details['Days Until Due'] = (int) $this->data['days_until_due'];
        }

        if (isset($this->data['days_overdue'])) {
            $details['Days Overdue'] = (int) $this->data['days_overdue'];
        }

        if (isset($this->data['remaining_balance'])) {
            $details['Remaining Balance'] = $currency . ' ' . number_format((float) $this->data['remaining_balance'], 2);
        }

        if (! empty($this->data['disbursed_at'])) {
            $details['Disbursed On'] = $this->data['disbursed_at'];
        }

        if (! empty($this->data['reason'])) {
            $details['Reason'] = $this->data['reason'];
        }

        return $details;
    }

    protected function databaseAction(string $type): ?array
    {
        $loanId = $this->data['loan_id'] ?? null;

        return match ($type) {
            'welcome' => ['text' => 'Complete Profile', 'url' => url('/profile')],
            'password_reset' => ['text' => 'Reset Password', 'url' => $this->data['reset_url'] ?? url('/')],
            'kyc_approved' => ['text' => 'Apply for a Loan', 'url' => route('client.loans.create')],
            'kyc_rejected' => ['text' => 'Resubmit KYC', 'url' => url('/kyc/resubmit')],
            'loan_submitted', 'loan_approved', 'loan_funded', 'repayment_received' =>
                ['text' => 'View Loan', 'url' => $loanId ? route('client.loans.show', $loanId) : route('client.loans.index')],
            'loan_rejected' => ['text' => 'Apply Again', 'url' => route('client.loans.create')],
            'loan_disbursed', 'repayment_reminder', 'repayment_overdue' =>
                ['text' => 'Make Payment', 'url' => route('client.repayments.index')],
            'funding_payment_submitted' => [
                'text' => 'Review Funding',
                'url' => $this->data['transaction_id'] ?? null
                    ? route('admin.funding-payments.show', $this->data['transaction_id'])
                    : route('admin.funding-payments.index'),
            ],
            'funding_payment_approved', 'funding_payment_rejected', 'funding_payment_info_requested' =>
                ['text' => 'View Investment', 'url' => $loanId ? route('client.loans.show', $loanId) : route('client.investments.index')],
            default => null,
        };
    }
}
