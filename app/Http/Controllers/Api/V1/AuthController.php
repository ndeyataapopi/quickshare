<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Modules\Auth\Events\UserLoggedIn;
use App\Modules\Auth\Events\UserLoggedOut;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    // ─── POST /api/v1/auth/login ────────────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        if ($user->status !== 'active') {
            Auth::logout();
            return $this->error('Your account has been suspended. Please contact support.', 403);
        }

        // Revoke existing tokens for this device (optional: pass device name)
        $deviceName = $request->input('device_name', 'API');
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName)->plainTextToken;

        UserLoggedIn::dispatch($user);

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful.');
    }

    // ─── POST /api/v1/auth/logout ───────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke the current token
        $request->user()->currentAccessToken()->delete();

        UserLoggedOut::dispatch($user);

        return $this->success(null, 'Logged out successfully.');
    }

    // ─── POST /api/v1/auth/logout-all ──────────────────────────────

    public function logoutAll(Request $request): JsonResponse
    {
        // Revoke all tokens
        $request->user()->tokens()->delete();

        return $this->success(null, 'Logged out from all devices.');
    }

    // ─── GET /api/v1/auth/me ────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        return $this->success(
            new UserResource($request->user()),
            'User profile retrieved.'
        );
    }

    // ─── PUT /api/v1/auth/me ────────────────────────────────────────

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|string|max:20',
            'date_of_birth' => 'sometimes|date|before:today',
        ]);

        $request->user()->update($validated);

        return $this->success(
            new UserResource($request->user()->fresh()),
            'Profile updated successfully.'
        );
    }

    // ─── POST /api/v1/auth/change-password ─────────────────────────

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $request->user()->update([
            'password' => bcrypt($validated['password']),
        ]);

        // Revoke all other tokens for security
        $request->user()->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return $this->success(null, 'Password changed successfully.');
    }

    // ─── GET /api/v1/auth/tokens ────────────────────────────────────

    public function tokens(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->select(['id', 'name', 'last_used_at', 'created_at'])->get();

        return $this->success($tokens, 'Tokens retrieved.');
    }

    // ─── DELETE /api/v1/auth/tokens/{tokenId} ──────────────────────

    public function revokeToken(Request $request, int $tokenId): JsonResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return $this->success(null, 'Token revoked.');
    }
}
