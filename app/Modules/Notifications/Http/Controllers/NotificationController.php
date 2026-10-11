<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Http\Resources\UserNotificationResource;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = UserNotification::query()
            ->forUser($request->user())
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->boolean('unread')) {
            $query->unread();
        }

        $page = $query->cursorPaginate((int) ($validated['per_page'] ?? 25));

        return ApiResponse::success(
            UserNotificationResource::collection($page),
            meta: [
                'pagination' => [
                    'per_page' => $page->perPage(),
                    'next_cursor' => $page->nextCursor()?->encode(),
                    'prev_cursor' => $page->previousCursor()?->encode(),
                ],
                'unread_count' => $this->notifications->unreadCount($request->user()),
            ],
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        $this->authorize('markRead', $notification);

        return ApiResponse::success(
            new UserNotificationResource($this->notifications->markRead($notification)),
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'updated' => $this->notifications->markAllRead($request->user()),
        ]);
    }
}
