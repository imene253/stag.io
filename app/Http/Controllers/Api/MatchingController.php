<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Services\GeminiService;
use App\Models\StudentProfile;
use App\Models\InternshipOffer;

class MatchingController extends Controller
{
    protected $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    // Keep this for Thunder Client testing
    public function match(Request $request)
    {
        $request->validate([
            'cv' => 'required|string',
            'offer' => 'required|string',
        ]);

        $result = $this->gemini->matchProfile(
            $request->cv,
            $request->offer
        );

        return response()->json($result);
    }

    // Real app matching: logged-in student + selected offer
    public function matchReal($offerId)
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Only students can use AI matching.',
            ], 403);
        }

        $profile = StudentProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Student profile not found.',
            ], 404);
        }

        $offer = InternshipOffer::find($offerId);

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'Internship offer not found.',
            ], 404);
        }

        $studentSkills = $this->formatSkills($profile->skills);
        $offerSkills = $this->formatSkills($offer->required_skills);

        $cvText = "
Student profile:
- University: {$profile->university}
- Field of study: {$profile->field_of_study}
- Academic level: {$profile->academic_level}
- Skills: {$studentSkills}
- Portfolio: {$profile->portfolio_link}
- Previous internship experiences: {$profile->previous_internship_experiences}
";

        $offerText = "
Internship offer:
- Title: {$offer->title}
- Domain: {$offer->domain}
- Description: {$offer->description}
- Required skills: {$offerSkills}
- Type: {$offer->type}
- Location: {$offer->location}
- Duration: {$offer->duration_value} {$offer->duration_unit}
";

        $studentUpdatedAt = optional($profile->updated_at)->timestamp ?? 0;
        $offerUpdatedAt = optional($offer->updated_at)->timestamp ?? 0;

        $cacheKey = "ai_match_student_{$user->id}_offer_{$offer->id}_student_{$studentUpdatedAt}_offer_{$offerUpdatedAt}";

        $result = Cache::remember($cacheKey, now()->addHours(6), function () use ($cvText, $offerText) {
            return $this->gemini->matchProfile($cvText, $offerText);
        });

        return response()->json($result);
    }

    private function formatSkills($skills): string
    {
        if (is_array($skills)) {
            return implode(', ', $skills);
        }

        if (is_string($skills)) {
            $decoded = json_decode($skills, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return implode(', ', $decoded);
            }

            return $skills;
        }

        return 'No skills provided';
    }
}