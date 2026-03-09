<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Convention;
use App\Services\ConventionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConventionController extends Controller
{
    public function __construct(
        protected ConventionService $conventionService
    ) {
    }

    // ADMIN — LIST ALL CONVENTIONS
    public function index()
    {
        $conventions = Convention::with([
            'application.student.studentProfile',
            'application.offer.company.companyProfile',
        ])
            ->latest()
            ->paginate(15);

        return response()->json($conventions);
    }

    // ADMIN — MANUALLY GENERATE / REGENERATE PDF
    public function generate($applicationId)
    {
        $application = Application::where('id', $applicationId)
            ->where('status', 'validated')
            ->with([
                'student.studentProfile',
                'offer.company.companyProfile',
                'convention',
            ])
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found or not validated yet.'
            ], 404);
        }

        if ($application->convention) {
            $convention = $this->conventionService->regenerate($application);
            $msg = 'Convention regenerated successfully.';
        } else {
            $convention = $this->conventionService->generate($application);
            $msg = 'Convention generated successfully.';
        }

        return response()->json([
            'message'      => $msg,
            'convention'   => $convention,
            'download_url' => url('api/conventions/' . $convention->id . '/download'),
        ], 201);
    }

    // DOWNLOAD PDF (student, company, admin)
    public function download(Request $request, $id)
    {
        $convention = Convention::with([
            'application.offer',
        ])->find($id);

        if (! $convention) {
            return response()->json(['message' => 'Convention not found.'], 404);
        }

        $user        = $request->user();
        $application = $convention->application;

        $isStudent = $application->student_id === $user->id;
        $isCompany = $application->offer->user_id === $user->id;
        $isAdmin   = method_exists($user, 'isAdmin') ? $user->isAdmin() : $user->role === 'admin';

        if (! $isStudent && ! $isCompany && ! $isAdmin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // If the physical file is missing (e.g. after a new deploy),
        // regenerate the convention PDF for this application.
        if (! Storage::disk('local')->exists($convention->file_path)) {
            $application = Application::where('id', $convention->application_id)
                ->where('status', 'validated')
                ->with([
                    'student.studentProfile',
                    'offer.company.companyProfile',
                ])
                ->first();

            if ($application) {
                $convention = $this->conventionService->regenerate($application);
            } else {
                return response()->json(['message' => 'File not found on server and application is not in a state to regenerate.'], 404);
            }
        }

        return response()->download(
            storage_path('app/' . $convention->file_path),
            $convention->convention_number . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    // STUDENT — VIEW MY CONVENTION
    public function myConvention(Request $request)
    {
        $convention = Convention::whereHas('application', function ($q) use ($request) {
                $q->where('student_id', $request->user()->id)
                  ->where('status', 'validated');
            })
            ->with('application.offer.company.companyProfile')
            ->latest()
            ->first();

        if (! $convention) {
            return response()->json([
                'message' => 'No convention found yet.'
            ], 404);
        }

        return response()->json([
            'convention'   => $convention,
            'download_url' => url('api/conventions/' . $convention->id . '/download'),
        ]);
    }
}
