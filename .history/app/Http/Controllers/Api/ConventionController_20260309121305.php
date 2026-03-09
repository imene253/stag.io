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
    /**
     * Generate (or return existing) Convention de Stage PDF for a validated application.
     */
    public function show(Request $request, int $id)
    {
        $application = Application::with([
                'student.studentProfile',
                'offer.company.companyProfile',
                'convention',
            ])
            ->where('id', $id)
            ->where('status', 'validated')
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found or not in validated status.',
            ], 404);
        }

        $studentProfile = $application->student->studentProfile;
        $companyProfile = optional(optional($application->offer)->company)->companyProfile;

        if (! $studentProfile || ! $companyProfile) {
            return response()->json([
                'message' => 'Missing student or company profile data for convention generation.',
            ], 422);
        }

        $convention = $application->convention;

        if (! $convention) {
            $nextNumber = str_pad((string) (Convention::max('id') + 1), 4, '0', STR_PAD_LEFT);
            $conventionNumber = 'C-' . Carbon::now()->format('Y') . '-' . $nextNumber;

            $convention = Convention::create([
                'application_id'    => $application->id,
                'file_path'         => '',
                'convention_number' => $conventionNumber,
                'generated_at'      => Carbon::now()->toDateString(),
            ]);
        }

        $universityName    = $studentProfile->university ?? 'Université';
        $department        = $studentProfile->field_of_study ?? 'Département';
        $departmentHead    = 'Responsable pédagogique';
        $universityAddress = 'Adresse de l\'université';

        $pdf = Pdf::loadView('pdf.convention', [
            'university_name'    => $universityName,
            'department'         => $department,
            'department_head'    => $departmentHead,
            'university_address' => $universityAddress,
            'convention_number'  => $convention->convention_number,
            'student'            => $studentProfile,
            'company'            => $companyProfile,
            'offer'              => $application->offer,
        ])->setPaper('a4', 'portrait');

        $fileName = 'conventions/' . $convention->id . '-' . $convention->convention_number . '.pdf';

        Storage::disk('public')->put($fileName, $pdf->output());

        if ($convention->file_path !== $fileName) {
            $convention->update(['file_path' => $fileName]);
        }

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->output();
            },
            'Convention-' . $convention->convention_number . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
