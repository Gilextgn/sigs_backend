<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Security\Models\AdminNotification;

/** Chaque utilisateur ne voit que les notifications qui lui sont adressées. */
class AdminNotificationController extends Controller
{
    public function index(Request $request)
    {
        $mine = AdminNotification::where('user_id', $request->user()->id);

        return response()->json([
            'unread_count' => (clone $mine)->whereNull('read_at')->count(),
            'data' => $mine->orderByDesc('id')->limit($request->integer('limit', 30))->get(),
        ]);
    }

    public function markRead(Request $request, AdminNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->noContent();
    }

    public function markAllRead(Request $request)
    {
        AdminNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }
}
