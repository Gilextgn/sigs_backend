<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

        RateLimiter::clear($emailKey);
        RateLimiter::clear($ipKey);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role?->code,
                'permissions' => $user->permissions(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
