<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Security\Models\AuditLog;

class AuditLogController extends Controller
{
    /** Catégories du journal : préfixes des codes d'action qu'elles regroupent. */
    private const CATEGORIES = [
        'caisse' => ['payment.', 'cash.'],
        'tarifs' => ['tuitioninstallment.', 'feetype.', 'schoolclass.', 'feesubscription.', 'student.fee_subscriptions'],
        'eleves' => ['student.', 'guardian.'],
        'utilisateurs' => ['user.'],
        'connexions' => ['auth.'],
        'enseignants' => ['payrollentry.', 'teacher.', 'teacherassignment.', 'teacherattendance.', 'classschedule.', 'subject.'],
        'parametres' => ['schoolsetting.', 'academicyear.', 'academic_year.', 'reminders.'],
    ];

    public function index(Request $request)
    {
        $prefixes = self::CATEGORIES[$request->string('category')->toString()] ?? null;

        return AuditLog::with('actor:id,full_name')
            ->when($prefixes, fn ($q) => $q->where(fn ($w) => collect($prefixes)->each(fn ($p) => $w->orWhere('action_code', 'like', $p.'%'))))
            ->when($request->integer('actor_id'), fn ($q, $id) => $q->where('actor_user_id', $id))
            ->when($request->date('from'), fn ($q, $d) => $q->where('created_at', '>=', $d->startOfDay()))
            ->when($request->date('to'), fn ($q, $d) => $q->where('created_at', '<=', $d->endOfDay()))
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 50), 200));
    }
}
