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
        // Le directeur d'un groupe reçoit les notifications de tous ses sites.
        $mine = AdminNotification::withoutGlobalScope('school')->where('user_id', $request->user()->id);

        // Plusieurs sites : chaque notification dit de quel site elle vient.
        $sites = \App\Support\SchoolGroup::accessibleSites($request->user());
        $labels = $sites->count() > 1 ? $sites->mapWithKeys(fn ($site) => [$site->id => \App\Support\SchoolGroup::label($site)]) : collect();

        return response()->json([
            'unread_count' => (clone $mine)->whereNull('read_at')->count(),
            'data' => $mine->orderByDesc('id')->limit($request->integer('limit', 30))->get()
                ->each(fn ($n) => $n->setAttribute('site', $labels[$n->school_id] ?? null)),
        ]);
    }

    public function markRead(Request $request, int $notification)
    {
        $notification = AdminNotification::withoutGlobalScope('school')->where('user_id', $request->user()->id)->findOrFail($notification);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->noContent();
    }

    public function markAllRead(Request $request)
    {
        AdminNotification::withoutGlobalScope('school')->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }
}
