<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/admin/notifications
     *
     * List admin notifications with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::with('user:id,name,email,avatar')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by category (stored in data->category)
        if ($request->filled('category')) {
            $query->whereJsonContains('data->category', $request->input('category'));
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        // Filter by read status
        if ($request->filled('is_read')) {
            $query->where('is_read', filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by related user (from data->user_id)
        if ($request->filled('user_id')) {
            $query->whereJsonContains('data->user_id', (int) $request->input('user_id'));
        }

        // Search
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'LIKE', "%{$term}%")
                  ->orWhere('body', 'LIKE', "%{$term}%");
            });
        }

        // Date range
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        $notifications = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $notifications->items(),
            'meta'    => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
                'unread_count' => Notification::where('user_id', $request->user()->id)
                    ->where('is_read', false)
                    ->count(),
            ],
        ]);
    }

    /**
     * GET /api/admin/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data'    => ['count' => $count],
        ]);
    }

    /**
     * POST /api/admin/notifications/{notification}/read
     */
    public function markAsRead(Request $request, int $notification): JsonResponse
    {
        $notif = Notification::where('user_id', $request->user()->id)
            ->findOrFail($notification);

        $notif->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * POST /api/admin/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * DELETE /api/admin/notifications/{notification}
     */
    public function destroy(Request $request, int $notification): JsonResponse
    {
        $notif = Notification::where('user_id', $request->user()->id)
            ->findOrFail($notification);

        $notif->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.',
        ]);
    }
}
