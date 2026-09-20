<?php

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\AcademicYears\Models\SchoolSetting;
use Modules\Platform\Models\School;
use Modules\Platform\Models\SchoolStatusEvent;
use Modules\Users\Models\Role;

/**
 * Console du propriétaire : créer une école et son administrateur, la
 * suspendre, la réactiver, ajuster son échéance.
 *
 * Volontairement AUCUNE lecture des données d'une école : ni élèves, ni
 * paiements, ni classes, ni même leur nombre. On ne voit que le statut
 * d'abonnement, les comptes administrateurs et la dernière connexion.
 */
class PlatformSchoolController extends Controller
{
    public function index()
    {
        $schools = School::orderBy('name')->get();

        $usersBySchool = User::whereNotNull('school_id')
            ->selectRaw('school_id, COUNT(*) as users_count, MAX(last_login_at) as last_login_at')
            ->groupBy('school_id')
            ->get()
            ->keyBy('school_id');

        $adminsBySchool = $this->admins()->groupBy('school_id');

        $rows = $schools->map(fn (School $school) => $this->row(
            $school,
            $usersBySchool->get($school->id),
            $adminsBySchool->get($school->id)?->first(),
        ));

        return response()->json([
            'summary' => [
                'total' => $rows->count(),
                'active' => $rows->where('status', 'active')->count(),
                'overdue' => $rows->where('status', 'overdue')->count(),
                'suspended' => $rows->where('status', 'suspended')->count(),
            ],
            'schools' => $rows->values(),
        ]);
    }

    public function show(School $school)
    {
        $usage = User::where('school_id', $school->id)
            ->selectRaw('COUNT(*) as users_count, MAX(last_login_at) as last_login_at')
            ->first();

        $admins = $this->admins($school->id);

        return response()->json([
            ...$this->row($school, $usage, $admins->first()),
            'admins' => $admins->map(fn (User $admin) => [
                'id' => $admin->id,
                'full_name' => $admin->full_name,
                'email' => $admin->email,
                'status' => $admin->status,
                'last_login_at' => $admin->last_login_at,
            ])->values(),
            'events' => $school->events()->with('actor:id,full_name')->limit(30)->get()->map(fn (SchoolStatusEvent $event) => [
                'id' => $event->id,
                'action' => $event->action,
                'reason' => $event->reason,
                'actor' => $event->actor?->full_name,
                'created_at' => $event->created_at,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'admin_name' => ['required', 'string', 'max:180'],
            'admin_email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'admin_password' => ['nullable', 'string', 'min:8', 'max:100'],
            'subscription_due_at' => ['nullable', 'date'],
            'auto_suspend' => ['sometimes', 'boolean'],
            'grace_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
        ]);

        $generated = empty($data['admin_password']) ? $this->temporaryPassword() : null;
        $password = $data['admin_password'] ?? $generated;

        $school = DB::transaction(function () use ($data, $password, $request) {
            $school = School::create([
                'name' => $data['name'],
                'subscription_due_at' => $data['subscription_due_at'] ?? null,
                'auto_suspend' => $data['auto_suspend'] ?? false,
                'grace_days' => $data['grace_days'] ?? 0,
            ]);

            foreach ([
                'school_name' => $data['name'],
                'matricule_prefix' => config('school.matricule_prefix'),
                'currency' => config('school.currency'),
            ] as $key => $value) {
                SchoolSetting::create(['school_id' => $school->id, 'setting_key' => $key, 'setting_value' => $value]);
            }

            // Une année en cours dès le premier jour : sans elle, ni inscription
            // ni Rentrée ne fonctionnent, et l'administrateur ne saurait pas pourquoi.
            AcademicYear::create($this->currentYear($school->id));

            User::create([
                'school_id' => $school->id,
                'role_id' => Role::where('code', 'admin')->value('id'),
                'full_name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($password),
                'status' => 'active',
            ]);

            $this->record($school, 'created', null, $request);

            return $school;
        });

        return response()->json([
            ...$this->show($school)->getData(true),
            'temporary_password' => $generated,
        ], 201);
    }

    public function update(Request $request, School $school)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'subscription_due_at' => ['sometimes', 'nullable', 'date'],
            'auto_suspend' => ['sometimes', 'boolean'],
            'grace_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
        ]);

        $school->update($data);

        if (array_key_exists('subscription_due_at', $data)) {
            $this->record($school, 'due_date_changed', $data['subscription_due_at'] ?? 'aucune échéance', $request);
        }

        return $this->show($school->fresh());
    }

    public function suspend(Request $request, School $school)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $school->update(['suspended_at' => now(), 'suspension_reason' => $data['reason'] ?? null]);
        $this->record($school, 'suspended', $data['reason'] ?? null, $request);

        return $this->show($school->fresh());
    }

    public function reactivate(Request $request, School $school)
    {
        $data = $request->validate(['subscription_due_at' => ['nullable', 'date', 'after_or_equal:today']]);

        $school->suspended_at = null;
        $school->suspension_reason = null;

        if (! empty($data['subscription_due_at'])) {
            $school->subscription_due_at = $data['subscription_due_at'];
        }

        // Une école bloquée pour impayé le resterait aussitôt : on ne fait pas
        // croire à une réactivation qui n'en est pas une. Vérifié avant d'enregistrer.
        abort_if(
            $school->isSuspended(),
            422,
            "L'échéance est dépassée et la suspension automatique est active : prolongez l'échéance pour réactiver.",
        );

        $school->save();
        $this->record($school, 'reactivated', null, $request);

        return $this->show($school->fresh());
    }

    /** Nouveau mot de passe temporaire pour un administrateur qui a perdu l'accès. */
    public function resetAdminPassword(Request $request, School $school)
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer']]);

        $admin = $this->admins($school->id)->when(
            $data['user_id'] ?? null,
            fn ($admins, $id) => $admins->where('id', $id),
        )->first();

        abort_unless($admin, 404, "Aucun administrateur pour cette école.");

        $password = $this->temporaryPassword();
        $admin->forceFill(['password' => Hash::make($password), 'status' => 'active'])->save();

        // Les sessions ouvertes avec l'ancien mot de passe sont coupées.
        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $admin->id)->delete();
        }

        $this->record($school, 'password_reset', $admin->email, $request);

        return response()->json(['user_id' => $admin->id, 'email' => $admin->email, 'temporary_password' => $password]);
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function admins(?int $schoolId = null)
    {
        return User::whereNotNull('school_id')
            ->when($schoolId, fn ($query, $id) => $query->where('school_id', $id))
            ->whereHas('role', fn ($query) => $query->where('code', 'admin'))
            ->orderBy('id')
            ->get(['id', 'school_id', 'full_name', 'email', 'status', 'last_login_at']);
    }

    private function row(School $school, $usage, ?User $admin): array
    {
        return [
            'id' => $school->id,
            'name' => $school->name,
            'status' => $school->status(),
            'suspension_kind' => $school->suspensionKind(),
            'suspension_reason' => $school->suspension_reason,
            'suspended_at' => $school->suspended_at,
            'subscription_due_at' => $school->subscription_due_at?->toDateString(),
            'blocked_from' => $school->blockedFrom()?->toDateString(),
            'auto_suspend' => $school->auto_suspend,
            'grace_days' => $school->grace_days,
            'users_count' => (int) ($usage->users_count ?? 0),
            'last_login_at' => $usage->last_login_at ?? null,
            'admin' => $admin ? ['id' => $admin->id, 'full_name' => $admin->full_name, 'email' => $admin->email] : null,
            'created_at' => $school->created_at,
        ];
    }

    private function record(School $school, string $action, ?string $reason, Request $request): void
    {
        SchoolStatusEvent::create([
            'school_id' => $school->id,
            'action' => $action,
            'reason' => $reason,
            'actor_user_id' => $request->user()->id,
            'created_at' => now(),
        ]);
    }

    /** Année scolaire en cours d'après la date : septembre 2026 → 2026-2027. */
    private function currentYear(int $schoolId): array
    {
        $startYear = now()->month >= 8 ? now()->year : now()->year - 1;
        $code = $startYear.'-'.($startYear + 1);

        return [
            'school_id' => $schoolId,
            'code' => $code,
            'label' => 'Année '.$code,
            'is_active' => true,
            'date_start' => $startYear.'-09-01',
            'date_end' => ($startYear + 1).'-07-31',
        ];
    }

    /** Mot de passe lisible à dicter au téléphone : pas de 0/O, 1/l/I. */
    private function temporaryPassword(int $length = 10): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
