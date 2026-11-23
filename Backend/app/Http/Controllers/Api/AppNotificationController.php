<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (AppNotification $notification): array => $this->present($notification));

        $unread = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'unread_count' => $unread,
            'data' => $notifications,
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);
        $notification->markRead();

        return response()->json([
            'success' => true,
            'data' => $this->present($notification->fresh() ?? $notification),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AppNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'data' => $notification->data ?? [],
            'read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->toDateTimeString(),
            'created_at' => $notification->created_at?->toDateTimeString(),
        ];
    }
}
