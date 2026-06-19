<?php

namespace App\Modules\TrustScore\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\TrustScore\Services\TrustScoreService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrustScoreController extends Controller
{
    use ApiResponse;

    public function __construct(protected TrustScoreService $trustScoreService)
    {
    }

    public function myScore(Request $request): JsonResponse
    {
        $summary = $this->trustScoreService->getScoreSummary($request->user());

        return $this->success($summary);
    }

    public function myHistory(Request $request): JsonResponse
    {
        $history = $this->trustScoreService->getHistory(
            $request->user(),
            $request->integer('limit', 20),
        );

        return $this->success(['history' => $history]);
    }

    public function show(User $user, Request $request): JsonResponse
    {
        $summary = $this->trustScoreService->getScoreSummary($user);

        return $this->success($summary);
    }

    public function userHistory(User $user): JsonResponse
    {
        $history = $this->trustScoreService->getHistory($user);

        return $this->success(['history' => $history]);
    }
}
