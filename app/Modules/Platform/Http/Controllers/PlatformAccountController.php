<?php

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\SchoolAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Le compte du propriétaire lui-même : nom et e-mail de connexion.
 * Le mot de passe passe par PUT /api/auth/password, commun à tous les comptes.
 */
class PlatformAccountController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email,'.$user->id],
            'current_password' => ['nullable', 'string'],
        ]);

        // Changer l'identifiant de connexion est sensible : on redemande le mot de passe.
        if ($data['email'] !== $user->email && ! Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => "Mot de passe actuel requis pour changer l'e-mail de connexion.",
            ]);
        }

        $user->forceFill(['full_name' => $data['full_name'], 'email' => $data['email']])->save();

        return response()->json(['user' => SchoolAccess::userPayload($user->fresh())]);
    }
}
