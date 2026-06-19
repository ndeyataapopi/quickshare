<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Modules\Notifications\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    // ─── GET /api/v1/notifications ──────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($notifications, NotificationResource::class);
    }

    // ─── GET /api/v1/notifications/unread ───────────────────────────

    public function unread(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->unreadNotifications()
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($notifications, NotificationResource::class);
    }

    // ─── GET /api/v1/notifications/count ────────────────────────────

    public function count(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'total' => $user->notifications()->count(),
            'unread' => $user->unreadNotifications()->count(),
        ], 'Notification count retrieved.');
    }

    // ─── POST /api/v1/notifications/{id}/read ───────────────────────

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return $this->success(
            new NotificationResource($notification->fresh()),
            'Notification marked as read.'
        );
    }

    // ─── POST /api/v1/notifications/read-all ────────────────────────

    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(['marked_read' => $count], 'All notifications marked as read.');
    }

    // ─── DELETE /api/v1/notifications/{id} ──────────────────────────

    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()
            ->notifications()
            ->findOrFail($id)
            ->delete();

        return $this->success(null, 'Notification deleted.');
    }

    // ─── DELETE /api/v1/notifications ───────────────────────────────

    public function destroyAll(Request $request): JsonResponse
    {
        $count = $request->user()->notifications()->count();
        $request->user()->notifications()->delete();

        return $this->success(['deleted' => $count], 'All notifications deleted.');
    }
}
