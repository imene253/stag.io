<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Convention;
use App\Services\ConventionService;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'application.student.studentProfile',
            'application.offer.company.companyProfile',
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

        // Always render the PDF in memory from current DB data.
        $studentProfile = $application->student->studentProfile;
        $companyProfile = $application->offer->company->companyProfile;
        $offer          = $application->offer;

        $pdf = Pdf::loadView('pdf.convention', [
            'university_name'    => config('university.name'),
            'department'         => config('university.department'),
            'department_head'    => config('university.department_head'),
            'university_address' => config('university.address'),
            'student'            => $studentProfile,
            'company'            => $companyProfile,
            'offer'              => $offer,
            'convention_number'  => $convention->convention_number,
        ])->setPaper('a4', 'portrait')
          ->setOptions([
              'isHtml5ParserEnabled' => true,
              'isRemoteEnabled'      => false,
              'defaultFont'          => 'DejaVu Sans',
          ]);

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->output();
            },
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
