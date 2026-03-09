// app/Http/Controllers/Api/ConventionController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Convention;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConventionController extends Controller
{
    // ─────────────────────────────────────────────────────
    // GENERATE PDF (called after admin validates)
    // POST /api/admin/applications/{id}/generate-convention
    // ─────────────────────────────────────────────────────
    public function generate(Request $request, $applicationId)
    {
        $application = Application::where('id', $applicationId)
            ->where('status', 'validated')
            ->with([
                'student.studentProfile',
                'offer.company.companyProfile',
            ])
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found or not validated yet.'
            ], 404);
        }

        // Check if convention already exists
        if ($application->convention) {
            return response()->json([
                'message'     => 'Convention already generated.',
                'convention'  => $application->convention,
                'download_url'=> url('api/conventions/' . $application->convention->id . '/download'),
            ]);
        }

        // ─── Build data for the PDF ──────────────────────
        $studentProfile = $application->student->studentProfile;
        $companyProfile = $application->offer->company->companyProfile;
        $offer          = $application->offer;

        // Generate unique convention number: CONV-2026-0001
        $count  = Convention::count() + 1;
        $number = 'CONV-' . Carbon::now()->year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        // ─── Render PDF ──────────────────────────────────
        $pdf = Pdf::loadView('pdf.convention', [
            // University info (customize these)
            'university_name'    => config('university.name', 'Université IFA Blida'),
            'department'         => config('university.department', 'Département Informatique'),
            'department_head'    => config('university.department_head', 'Chef de Département'),
            'university_address' => config('university.address', 'Blida, Algérie'),

            // Data
            'student'            => $studentProfile,
            'company'            => $companyProfile,
            'offer'              => $offer,
            'convention_number'  => $number,
        ])
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'defaultFont'          => 'DejaVu Sans',
        ]);

        // ─── Save to storage ─────────────────────────────
        $filename = 'conventions/' . $number . '.pdf';
        Storage::disk('local')->put($filename, $pdf->output());

        // ─── Save record in DB ───────────────────────────
        $convention = Convention::create([
            'application_id'    => $application->id,
            'file_path'         => $filename,
            'convention_number' => $number,
            'generated_at'      => Carbon::today(),
        ]);

        return response()->json([
            'message'      => 'Convention de Stage generated successfully.',
            'convention'   => $convention,
            'download_url' => url('api/conventions/' . $convention->id . '/download'),
        ], 201);
    }

    // ─────────────────────────────────────────────────────
    // DOWNLOAD PDF
    // GET /api/conventions/{id}/download
    // ─────────────────────────────────────────────────────
    public function download(Request $request, $id)
    {
        $convention = Convention::with('application.student')->find($id);

        if (! $convention) {
            return response()->json(['message' => 'Convention not found.'], 404);
        }

        $user = $request->user();

        // Only the student, the company, or admin can download
        $application    = $convention->application;
        $isOwnerStudent = $application->student_id === $user->id;
        $isOwnerCompany = $application->offer->user_id === $user->id;
        $isAdmin        = $user->isAdmin();

        if (! $isOwnerStudent && ! $isOwnerCompany && ! $isAdmin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! Storage::disk('local')->exists($convention->file_path)) {
            return response()->json(['message' => 'File not found on server.'], 404);
        }

        return response()->download(
            storage_path('app/' . $convention->file_path),
            $convention->convention_number . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    // ─────────────────────────────────────────────────────
    // LIST ALL CONVENTIONS (admin)
    // GET /api/admin/conventions
    // ─────────────────────────────────────────────────────
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

    // ─────────────────────────────────────────────────────
    // STUDENT: VIEW MY CONVENTION
    // GET /api/student/convention
    // ─────────────────────────────────────────────────────
    public function myConvention(Request $request)
    {
        $convention = Convention::whereHas('application', function ($q) use ($request) {
                $q->where('student_id', $request->user()->id)
                  ->where('status', 'validated');
            })
            ->with('application.offer')
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