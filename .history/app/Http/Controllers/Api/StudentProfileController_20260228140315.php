// app/Http/Controllers/Api/StudentProfileController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use Illuminate\Http\Request;

class StudentProfileController extends Controller
{
    // ─────────────────────────────────────────────────────
    // VIEW OWN PROFILE (Digital CV)  →  GET /api/student/profile
    // ─────────────────────────────────────────────────────
    public function show(Request $request)
    {
        $profile = $request->user()->studentProfile;

        if (! $profile) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }

        return response()->json(['profile' => $profile]);
    }

    // ─────────────────────────────────────────────────────
    // UPDATE OWN PROFILE (Digital CV)  →  PUT /api/student/profile
    // ─────────────────────────────────────────────────────
    public function update(Request $request)
    {
        $request->validate([
            // Personal
            'full_name'      => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'wilaya'         => ['nullable', 'string', 'max:100'],

            // Academic
            'university'     => ['nullable', 'string', 'max:255'],
            'field_of_study' => ['nullable', 'string', 'max:255'],
            'academic_level' => ['nullable', 'string', 'max:100'], // free text ✅

            // Skills & Portfolio
            'skills'         => ['nullable', 'array'],
            'skills.*'       => ['string', 'max:50'],
            'portfolio_link' => ['nullable', 'url', 'max:255'],     // single field ✅
        ]);

        $profile = $request->user()->studentProfile;

        if (! $profile) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }

        $profile->update($request->only([
            'full_name',
            'email',
            'phone',
            'wilaya',
            'university',
            'field_of_study',
            'academic_level',
            'skills',
            'portfolio_link',
        ]));

        return response()->json([
            'message' => 'Profile updated successfully.',
            'profile' => $profile->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────────────
    // VIEW ANY STUDENT CV (for companies)  →  GET /api/students/{id}/cv
    // ─────────────────────────────────────────────────────
    public function showPublic($id)
    {
        $profile = StudentProfile::with('user:id,name')
            ->where('user_id', $id)
            ->first();

        if (! $profile) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        return response()->json(['profile' => $profile]);
    }
}