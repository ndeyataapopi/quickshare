<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\ReferralService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    use ApiResponse;

    public function __construct(protected ReferralService $referralService)
    {
    }

    public function myCode(Request $request): JsonResponse
    {
        $referralCode = $this->referralService->getUserReferralCode($request->user());

        return $this->success([
            'referral_code' => $referralCode->code,
            'is_active' => $referralCode->is_active,
            'usage_count' => $referralCode->usage_count,
            'max_uses' => $referralCode->max_uses,
        ]);
    }

    public function myReferrals(Request $request): JsonResponse
    {
        $data = $this->referralService->getUserReferrals($request->user());

        return $this->success($data);
    }
}
