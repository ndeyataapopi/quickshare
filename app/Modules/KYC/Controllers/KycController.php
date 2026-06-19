<?php

namespace App\Modules\KYC\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\KYC\Models\KycDocument;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\KYC\Requests\ResubmitKycRequest;
use App\Modules\KYC\Requests\SubmitKycRequest;
use App\Modules\KYC\Services\KycService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycController extends Controller
{
    use ApiResponse;

    public function __construct(protected KycService $kycService)
    {
    }

    public function submit(SubmitKycRequest $request): JsonResponse
    {
        $documents = [
            'national_id_front' => $request->file('national_id_front'),
            'national_id_back' => $request->file('national_id_back'),
            'payslip' => $request->file('payslip'),
            'bank_statement' => $request->file('bank_statement'),
        ];

        $submission = $this->kycService->submit($request->user(), $documents);

        return $this->created([
            'submission' => $submission,
        ], 'KYC documents submitted successfully. Your documents are being reviewed.');
    }

    public function resubmit(ResubmitKycRequest $request, KycSubmission $submission): JsonResponse
    {
        $documents = [];
        foreach (['national_id_front', 'national_id_back', 'payslip', 'bank_statement'] as $type) {
            if ($request->hasFile($type)) {
                $documents[$type] = $request->file($type);
            }
        }

        if (empty($documents)) {
            return $this->error('At least one document must be uploaded.', 422);
        }

        $submission = $this->kycService->resubmit($request->user(), $submission, $documents);

        return $this->success([
            'submission' => $submission,
        ], 'Documents resubmitted successfully.');
    }

    public function status(Request $request): JsonResponse
    {
        $submissions = $this->kycService->getUserSubmissions($request->user());

        return $this->success([
            'submissions' => $submissions,
        ]);
    }

    public function downloadDocument(Request $request, KycDocument $document): \Symfony\Component\HttpFoundation\Response
    {
        // Users can only download their own documents
        if ($document->user_id !== $request->user()->id && ! $request->user()->hasPermissionTo('approve_kyc')) {
            return $this->error('Unauthorized.', 403);
        }

        $content = $this->kycService->getDecryptedDocument($document);

        return response($content, 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => "inline; filename=\"{$document->original_filename}\"",
            'Content-Length' => strlen($content),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
