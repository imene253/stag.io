<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Convention;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ConventionService
{
    /**
     * Generate a convention PDF and record for an application.
     */
    public function generate(Application $application): Convention
    {
        $application->loadMissing([
            'student.studentProfile',
            'offer.company.companyProfile',
        ]);

        $studentProfile = $application->student->studentProfile;
        $companyProfile = $application->offer->company->companyProfile;
        $offer          = $application->offer;

        $count  = Convention::count() + 1;
        $number = 'CONV-' . Carbon::now()->year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $pdf = Pdf::loadView('pdf.convention', [
            'university_name'    => config('university.name'),
            'department'         => config('university.department'),
            'department_head'    => config('university.department_head'),
            'university_address' => config('university.address'),
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

        $filename = 'conventions/' . $number . '.pdf';
        Storage::disk('local')->put($filename, $pdf->output());

        return Convention::create([
            'application_id'    => $application->id,
            'file_path'         => $filename,
            'convention_number' => $number,
            'generated_at'      => Carbon::today(),
        ]);
    }

    /**
     * Regenerate a convention for an application, replacing the old file/record.
     */
    public function regenerate(Application $application): Convention
    {
        $existing = $application->convention;

        if ($existing) {
            Storage::disk('local')->delete($existing->file_path);
            $existing->delete();
        }

        return $this->generate($application);
    }
}

