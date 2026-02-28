// app/Http/Controllers/Api/CompanyProfileController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use Illuminate\Http\Request;

class CompanyProfileController extends Controller
{
    // ─────────────────────────────────────────────────────
    // VIEW OWN PROFILE  →  GET /api/company/profile
    // ─────────────────────────────────────────────────────
    public function show(Request $request)
    {
        $profile = $request->user()->companyProfile;

        if (! $profile) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }

        return response()->json(['profile' => $profile]);
    }

    // ─────────────────────────────────────────────────────
    // UPDATE OWN PROFILE  →  PUT /api/company/profile
    // ─────────────────────────────────────────────────────
    public function update(Request $request)
    {
        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'industry'     => ['nullable', 'string', 'max:255'],
            'location'     => ['nullable', 'string', 'max:255'],
            'website_url'  => ['nullable', 'url', 'max:255'],
            'company_size' => ['nullable', 'in:1-10,11-50,51-200,201-500,500+'],
            'description'  => ['nullable', 'string', 'max:2000'],
        ]);

        $profile = $request->user()->companyProfile;

        if (! $profile) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }

        $profile->update($request->only([
            'company_name',
            'industry',
            'location',
            'website_url',
            'company_size',
            'description',
        ]));

        return response()->json([
            'message' => 'Company profile updated successfully.',
            'profile' => $profile->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────────────
    // VIEW ANY COMPANY (public)  →  GET /api/companies/{id}
    // ─────────────────────────────────────────────────────
    public function showPublic($id)
    {
        $profile = CompanyProfile::with('user:id,name')
            ->where('user_id', $id)
            ->first();

        if (! $profile) {
            return response()->json(['message' => 'Company not found.'], 404);
        }

        return response()->json(['profile' => $profile]);
    }
}