<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SchoolAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Security\Models\AuditLog;

class AuthController extends Controller
{
    /**
     * Authentification par session (SPA Sanctum). Le frontend doit d'abord
     * appeler GET /sanctum/csrf-cookie avant ce endpoint.
     * Reprend les règles existantes : compte verrouillé/inactif refusé,
     * throttle par email pour limiter le bruteforce.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();
        $emailKey = 'login:email:'.mb_strtolower($credentials['email']).'|'.$request->ip();
        $ipKey = 'login:ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($emailKey, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            $seconds = max(RateLimiter::availableIn($emailKey), RateLimiter::availableIn($ipKey));
            throw ValidationException::withMessages([
                'email' => "Trop de tentatives. Réessayez dans {$seconds}s.",
            ]);
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Auth::attempt($credentials, true)) {
            RateLimiter::hit($emailKey, 60);
            RateLimiter::hit($ipKey, 60);
            AuditLog::record('auth.login_failed', 'User', null, ['email' => $credentials['email']]);
            throw ValidationException::withMessages([
                'email' => 'Identifiants invalides.',
            ]);
        }

        if ($user->status !== 'active') {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Ce compte est '.($user->status === 'locked' ? 'verrouillé' : 'désactivé').'.',
            ]);
        }

        // Une école suspendue ne peut plus ouvrir de session : on le dit
        // clairement plutôt que de laisser croire à un mauvais mot de passe.
        $school = SchoolAccess::resolve($user);

        if ($user->school_id !== null && (! $school || $school->isSuspended())) {
            Auth::logout();

            return response()->json(SchoolAccess::suspensionPayload($school), 403);
        }

        RateLimiter::clear($emailKey);
        RateLimiter::clear($ipKey);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['user' => SchoolAccess::userPayload($user)]);
    }

    /**
     * Changement de mot de passe par l'utilisateur lui-même (tout compte :
     * école ou plateforme). L'ancien mot de passe est exigé ; les autres
     * sessions ouvertes sont fermées, celle-ci reste active.
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed', 'different:current_password'],
        ], [
            'password.different' => "Le nouveau mot de passe doit être différent de l'actuel.",
            'password.confirmed' => 'La confirmation ne correspond pas.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Mot de passe actuel incorrect.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        if (config('session.driver') === 'database' && Schema::hasTable('sessions') && $request->hasSession()) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        // Le journal d'audit appartient à une école : le propriétaire n'y figure pas.
        if ($user->school_id !== null) {
            AuditLog::record('auth.password_changed', 'User', (string) $user->id);
        }

        return response()->json(['user' => SchoolAccess::userPayload($user->fresh())]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
