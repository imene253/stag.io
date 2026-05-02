<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiService
{
    protected $apiKey;
    protected $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model');
    }

    public function matchProfile($cv, $offer)
    {
        $prompt = "
Compare this student CV with this internship offer.

CV:
$cv

Offer:
$offer

Return ONLY valid JSON.
Do not include markdown.
Do not include ```json.
Do not include explanations outside JSON.

Use exactly this structure:
{
  \"percentage\": 0,
  \"matched_skills\": [],
  \"missing_skills\": [],
  \"explanation\": \"\"
}

Rules:
- percentage must be a number between 0 and 100.
- NEVER return 0% unless there is absolutely no relevance at all.
- If the student has general programming or software skills, minimum score should be 20%.
- NEVER return above 90% unless ALL key required skills are directly present.
- matched_skills must include ONLY exact technical skill matches.
- missing_skills must include ONLY important missing technical skills.
- DO NOT include soft skills in missing_skills.
- explanation must speak directly to the student using you and your.
- explanation must not be empty.
- explanation must be concise and helpful.
- avoid saying the student.
";

       $response = Http::post(
    "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
    [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.2,
        ],
    ]
);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => 'Gemini API request failed',
                'percentage' => null,
                'matched_skills' => [],
                'missing_skills' => [],
                'explanation' => '',
                'error' => $response->json(),
            ];
        }

        $rawText = $response->json('candidates.0.content.parts.0.text');

        if (!$rawText) {
            return [
                'success' => false,
                'message' => 'No response text received from Gemini',
                'percentage' => null,
                'matched_skills' => [],
                'missing_skills' => [],
                'explanation' => '',
                'raw_response' => $response->json(),
            ];
        }

        $cleanJson = trim($rawText);

        $cleanJson = preg_replace('/^```json\s*/i', '', $cleanJson);
        $cleanJson = preg_replace('/^```\s*/', '', $cleanJson);
        $cleanJson = preg_replace('/\s*```$/', '', $cleanJson);

        if (preg_match('/\{.*\}/s', $cleanJson, $matches)) {
            $cleanJson = $matches[0];
        }

        $data = json_decode($cleanJson, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [
                'success' => false,
                'message' => 'AI response could not be parsed as JSON',
                'percentage' => null,
                'matched_skills' => [],
                'missing_skills' => [],
                'explanation' => '',
                'raw_response' => $rawText,
            ];
        }

        $percentage = $data['percentage'] ?? null;
        $matchedSkills = $data['matched_skills'] ?? [];
        $missingSkills = $data['missing_skills'] ?? [];
        $explanation = trim($data['explanation'] ?? '');

        if (!is_numeric($percentage) || $explanation === '') {
            return [
                'success' => false,
                'message' => 'AI returned an incomplete match result. Please try again.',
                'percentage' => null,
                'matched_skills' => [],
                'missing_skills' => [],
                'explanation' => '',
                'raw_response' => $rawText,
            ];
        }

        return [
            'success' => true,
            'percentage' => max(0, min(100, (int) round($percentage))),
            'matched_skills' => is_array($matchedSkills) ? $matchedSkills : [],
            'missing_skills' => is_array($missingSkills) ? $missingSkills : [],
            'explanation' => $explanation,
        ];
    }
}