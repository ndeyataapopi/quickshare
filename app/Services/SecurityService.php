<?php

namespace App\Services;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SecurityService
{
    public function logLoginAttempt(array $data): void
    {
        LoginAttempt::create([
            'user_id' => $data['user_id'] ?? null,
            'email' => $data['email'],
            'ip_address' => $data['ip_address'],
            'user_agent' => $data['user_agent'],
            'success' => $data['success'] ?? false,
            'failure_reason' => $data['failure_reason'] ?? null,
            'location_country' => $data['location_country'] ?? null,
            'location_city' => $data['location_city'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ]);
    }

    public function isLoginRateLimited(string $email, string $ipAddress): bool
    {
        $key = "login_attempt:{$email}:{$ipAddress}";
        $attempts = Cache::get($key, 0);

        if ($attempts >= 5) {
            return true;
        }

        Cache::put($key, $attempts + 1, now()->addMinutes(15));

        return false;
    }

    public function detectSuspiciousActivity(User $user, Request $request): array
    {
        $suspicious = [];

        // Check for login from unusual location
        $lastSession = $user->sessions()->latest()->first();
        if ($lastSession && $this->isUnusualLocation($request, $lastSession)) {
            $suspicious[] = 'unusual_location';
        }

        // Check for rapid login attempts from different IPs
        $recentAttempts = LoginAttempt::forEmail($user->email)
            ->recent(30)
            ->failed()
            ->groupBy('ip_address')
            ->get()
            ->count();

        if ($recentAttempts >= 3) {
            $suspicious[] = 'multiple_ips';
        }

        // Check for concurrent sessions from different locations
        $concurrentSessions = UserSession::where('user_id', $user->id)
            ->where('last_activity_at', '>=', now()->subMinutes(30))
            ->where('is_current', true)
            ->groupBy('ip_address')
            ->get()
            ->count();

        if ($concurrentSessions > 2) {
            $suspicious[] = 'concurrent_sessions';
        }

        return $suspicious;
    }

    protected function isUnusualLocation(Request $request, UserSession $lastSession): bool
    {
        $distance = $this->calculateDistance(
            $request->input('latitude'),
            $request->input('longitude'),
            $lastSession->latitude,
            $lastSession->longitude
        );

        // Flag if distance > 500km
        return $distance > 500;
    }

    protected function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        if (! $lat1 || ! $lon1 || ! $lat2 || ! $lon2) {
            return 0;
        }

        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function createUserSession(User $user, Request $request): UserSession
    {
        $session = UserSession::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_type' => $this->detectDeviceType($request),
            'device_name' => $this->detectDeviceName($request),
            'browser' => $this->detectBrowser($request),
            'platform' => $this->detectPlatform($request),
            'location_country' => $request->input('location_country'),
            'location_city' => $request->input('location_city'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'is_current' => true,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        // Mark other sessions as not current
        UserSession::where('user_id', $user->id)
            ->where('id', '!=', $session->id)
            ->update(['is_current' => false]);

        return $session;
    }

    public function detectDeviceType(Request $request): ?string
    {
        $userAgent = $request->userAgent();

        if (preg_match('/iPhone/i', $userAgent) || preg_match('/Android.*Mobile/i', $userAgent)) {
            return 'mobile';
        }

        if (preg_match('/iPad/i', $userAgent) || preg_match('/Android(?!.*Mobile)/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/Windows/i', $userAgent) || preg_match('/Macintosh/i', $userAgent) || preg_match('/Linux/i', $userAgent)) {
            return 'desktop';
        }

        return null;
    }

    public function detectDeviceName(Request $request): ?string
    {
        $userAgent = $request->userAgent();

        if (preg_match('/iPhone/i', $userAgent)) {
            return 'iPhone';
        }

        if (preg_match('/iPad/i', $userAgent)) {
            return 'iPad';
        }

        if (preg_match('/Android/i', $userAgent)) {
            return 'Android';
        }

        if (preg_match('/Macintosh/i', $userAgent)) {
            return 'Macintosh';
        }

        if (preg_match('/Windows/i', $userAgent)) {
            return 'Windows';
        }

        if (preg_match('/Linux/i', $userAgent)) {
            return 'Linux';
        }

        return null;
    }

    public function detectBrowser(Request $request): ?string
    {
        $userAgent = $request->userAgent();

        if (preg_match('/Chrome/i', $userAgent)) {
            return 'Chrome';
        }

        if (preg_match('/Firefox/i', $userAgent)) {
            return 'Firefox';
        }

        if (preg_match('/Safari/i', $userAgent) && ! preg_match('/Chrome/i', $userAgent)) {
            return 'Safari';
        }

        if (preg_match('/Edge/i', $userAgent)) {
            return 'Edge';
        }

        return null;
    }

    public function detectPlatform(Request $request): ?string
    {
        $userAgent = $request->userAgent();

        if (preg_match('/Windows NT 10/i', $userAgent)) {
            return 'Windows 10';
        }

        if (preg_match('/Windows NT 11/i', $userAgent)) {
            return 'Windows 11';
        }

        if (preg_match('/Mac OS X/i', $userAgent)) {
            return 'macOS';
        }

        if (preg_match('/Android/i', $userAgent)) {
            return 'Android';
        }

        if (preg_match('/iOS/i', $userAgent)) {
            return 'iOS';
        }

        if (preg_match('/Linux/i', $userAgent)) {
            return 'Linux';
        }

        return null;
    }

    public function cleanupExpiredSessions(): int
    {
        return UserSession::expired()->delete();
    }

    public function cleanupOldLoginAttempts(int $days = 90): int
    {
        return LoginAttempt::where('created_at', '<', now()->subDays($days))->delete();
    }
}
