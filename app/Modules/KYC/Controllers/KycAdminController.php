<?php

namespace App\Modules\KYC\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\KYC\Requests\ReviewKycRequest;
use App\Modules\KYC\Services\KycService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycAdminController extends Controller
{
    use ApiResponse;

    public function __construct(protected KycService $kycService)
    {
    }

    public function pending(Request $request): JsonResponse
    {
        $submissions = $this->kycService->getPendingSubmissions();

        return $this->paginated($submissions, 'Pending KYC submissions retrieved.');
    }

    public function show(KycSubmission $submission): JsonResponse
    {
        $submission->load([
            'user:id,first_name,last_name,email,phone,national_id',
            'documents',
            'reviewer:id,first_name,last_name',
        ]);

        return $this->success([
            'submission' => $submission,
        ]);
    }

    public function review(ReviewKycRequest $request, KycSubmission $submission): JsonResponse
    {
        $reviewer = $request->user();

        if ($request->action === 'approve') {
            $submission = $this->kycService->approve(
                $submission,
                $reviewer,
                $request->admin_notes,
            );

            return $this->success([
                'submission' => $submission,
            ], 'KYC submission approved.');
        }

        $submission = $this->kycService->reject(
            $submission,
            $reviewer,
            $request->reason,
            $request->document_rejections ?? [],
            $request->boolean('allow_resubmission', true),
        );

        return $this->success([
            'submission' => $submission,
        ], 'KYC submission rejected.');
    }
}
