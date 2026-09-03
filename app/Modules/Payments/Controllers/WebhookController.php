<?php

namespace App\Modules\Payments\Controllers;

use App\Modules\Payments\Providers\PaymentProviderManager;
use App\Modules\Payments\Services\PaymentExecutionOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController
{
    public function __construct(
        protected PaymentProviderManager $manager,
        protected PaymentExecutionOrchestrator $orchestrator,
    ) {
    }

    public function handle(Request $request, string $provider): JsonResponse
    {
        try {
            $providerInstance = $this->manager->resolve($provider);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => 'Unknown provider.'], 404);
        }

        if (! $providerInstance->verifyWebhookSignature($request)) {
            Log::warning('Payment webhook signature verification failed', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $result = $providerInstance->handleWebhook($request->all());
        $processResult = $this->orchestrator->processWebhook($result, $provider, $request);

        if ($processResult->duplicate) {
            return response()->json([
                'message' => $processResult->message ?? 'Duplicate webhook event.',
                'event_id' => $processResult->event?->id,
            ], 200);
        }

        if (! $processResult->success) {
            $code = $processResult->status === 'invalid' ? 422 : 400;

            return response()->json([
                'message' => $processResult->message ?? 'Webhook could not be processed.',
            ], $code);
        }

        return response()->json([
            'message' => $processResult->message ?? 'Webhook processed.',
            'event_id' => $processResult->event?->id,
        ], 200);
    }
}
