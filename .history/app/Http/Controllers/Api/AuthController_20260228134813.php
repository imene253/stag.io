<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ─────────────────────────────────────────────────────
    // REGISTER  →  POST /api/register
    // ─────────────────────────────────────────────────────
    public function register(Request $request)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role'     => ['required', 'in:student,company'], // ✅ admin blocked
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password,
            'role'     => $request->role,
        ]);

        // Auto-create blank profile based on role
        if ($user->isStudent()) {
            $user->studentProfile()->create([
                'user_id'  => $user->id,
                'full_name'=> $request->name,
                'email'    => $request->email,
            ]);
        } else {
            $user->companyProfile()->create([
                'user_id'      => $user->id,
                'company_name' => $request->name,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $relation = $user->isStudent() ? 'studentProfile' : 'companyProfile';

        return response()->json([
            'message' => 'Registration successful',
            'token'   => $token,
            'role'    => $user->role,
            'user'    => $user->load($relation),
        ], 201);
    }

    // ─────────────────────────────────────────────────────
    // LOGIN  →  POST /api/login
    // ─────────────────────────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Your account is disabled. Contact administration.'
            ], 403);
        }

        // Single session: revoke old tokens
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        $relation = match($user->role) {
            'student' => 'studentProfile',
            'company' => 'companyProfile',
            default   => null,
        };

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
            'role'    => $user->role,
            'user'    => $relation ? $user->load($relation) : $user,
        ]);
    }

    // ─────────────────────────────────────────────────────
    // LOGOUT  →  POST /api/logout
    // ─────────────────────────────────────────────────────
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    // ─────────────────────────────────────────────────────
    // GET AUTH USER  →  GET /api/me
    // ─────────────────────────────────────────────────────
    public function me(Request $request)
    {
        $user = $request->user();

        $relation = match($user->role) {
            'student' => 'studentProfile',
            'company' => 'companyProfile',
            default   => null,
        };

        return response()->json([
            'user' => $relation ? $user->load($relation) : $user,
            'role' => $user->role,
        ]);
    }
}