<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\InternshipOffer;
use App\Models\User;
use App\Notifications\AdminRejectedApplicationNotification;
use App\Notifications\AdminValidatedApplicationNotification;
use App\Notifications\CompanyApplicationClosedByStudentChoiceNotification;
use App\Notifications\CompanyAcceptedApplicationNotification;
use App\Notifications\CompanyAcceptedNeedsAdminValidationNotification;
use App\Notifications\CompanyRefusedApplicationNotification;
use App\Notifications\StudentFinalChoiceConfirmedNotification;
use App\Notifications\StudentFinalChoiceNeedsAdminValidationNotification;
use App\Services\ConventionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    public function __construct(
        protected ConventionService $conventionService
    ) {
    }

    public function apply(Request $request, $offerId)
    {
        InternshipOffer::closeExpired();

        $offer = InternshipOffer::find($offerId);

        if (! $offer || $offer->status !== 'open') {
            return response()->json([
                'message' => 'Offer not found or is no longer open.',
            ], 404);
        }

        if ($offer->isPastDeadline()) {
            return response()->json([
                'message' => 'The application deadline for this offer has passed.',
            ], 403);
        }

        $activePlacement = Application::activePlacementForStudent($request->user()->id);

        if ($activePlacement) {
            if (! $offer->internship_starts_at) {
                return response()->json([
                    'message' => 'This offer has no internship start date. You cannot apply while you have an active internship.',
                ], 422);
            }

            if (! $activePlacement->allowsApplicationToOffer($offer)) {
                return response()->json([
                    'message' => 'You can only apply to offers that start after your current internship ends.',
                    'active_application_id' => $activePlacement->id,
                    'internship_ends_at' => $activePlacement->internship_ends_at->toDateString(),
                    'offer_starts_at' => Carbon::parse($offer->internship_starts_at)->toDateString(),
                ], 403);
            }
        }

        $alreadyApplied = Application::where('student_id', $request->user()->id)
            ->where('offer_id', $offerId)
            ->exists();

        if ($alreadyApplied) {
            return response()->json([
                'message' => 'You have already applied to this offer.',
            ], 409);
        }

        $request->validate([
            'cover_letter' => ['nullable', 'string', 'max:2000'],
        ]);

        $application = Application::create([
            'student_id'   => $request->user()->id,
            'offer_id'     => $offerId,
            'status'       => 'pending',
            'cover_letter' => $request->cover_letter,
        ]);

        return response()->json([
            'message'     => 'Application submitted successfully.',
            'application' => $application->load('offer'),
        ], 201);
    }

    public function myApplications(Request $request)
    {
        $applications = Application::where('student_id', $request->user()->id)
            ->with('offer.company')
            ->latest()
            ->paginate(10);

        return response()->json($applications);
    }

    public function cancel(Request $request, $id)
    {
        $application = Application::where('id', $id)
            ->where('student_id', $request->user()->id)
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found.'
            ], 404);
        }

        if (! $application->isPending()) {
            return response()->json([
                'message' => 'You can only cancel a pending application.'
            ], 403);
        }

        $application->delete();

        return response()->json([
            'message' => 'Application cancelled successfully.'
        ]);
    }

    public function finalizeChoice(Request $request, $id)
    {
        $application = Application::query()
            ->where('id', $id)
            ->where('student_id', $request->user()->id)
            ->whereIn('status', ['accepted', 'validated'])
            ->with('offer')
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found or not eligible for final selection.',
            ], 404);
        }

        $activePlacement = Application::query()
            ->where('student_id', $request->user()->id)
            ->whereIn('status', ['selected', 'validated'])
            ->whereDate('internship_ends_at', '>=', Carbon::today())
            ->where('id', '!=', $application->id)
            ->first();

        if ($activePlacement && ! $activePlacement->allowsApplicationToOffer($application->offer)) {
            return response()->json([
                'message' => 'You can only select an internship that starts after your current one ends.',
                'active_application_id' => $activePlacement->id,
                'internship_ends_at' => $activePlacement->internship_ends_at->toDateString(),
                'offer_starts_at' => optional($application->offer->internship_starts_at)
                    ? Carbon::parse($application->offer->internship_starts_at)->toDateString()
                    : null,
            ], 403);
        }

        $startDate = $application->offer->internship_starts_at
            ? Carbon::parse((string) $application->offer->internship_starts_at)->startOfDay()
            : null;

        if (! $startDate) {
            return response()->json([
                'message' => 'Company must set internship start date on the offer before final selection.',
            ], 422);
        }

        $endDate = $application->offer->duration_unit === 'weeks'
            ? $startDate->copy()->addWeeks((int) $application->offer->duration_value)
            : $startDate->copy()->addMonths((int) $application->offer->duration_value);

        /*
         * Only accepted applications are rejected here.
         * Meaning:
         * - refused  = company refused the student
         * - rejected = student did not choose it after being accepted, or admin rejected it
         */
        $closedApplications = Application::query()
            ->where('student_id', $application->student_id)
            ->where('id', '!=', $application->id)
            ->where('status', 'accepted')
            ->with('offer.company')
            ->get();

        DB::transaction(function () use ($application, $startDate, $endDate, $closedApplications): void {
            $application->update([
                'status' => 'selected',
                'selected_at' => now(),
                'internship_starts_at' => $startDate->toDateString(),
                'internship_ends_at' => $endDate->toDateString(),
            ]);

            if ($closedApplications->isNotEmpty()) {
                Application::query()
                    ->whereIn('id', $closedApplications->pluck('id')->all())
                    ->update(['status' => 'rejected']);
            }
        });

        $selectedApplication = $application->fresh()->load('offer.company', 'student.studentProfile');
        $closedCount = $closedApplications->count();

        $selectedApplication->student->notify(
            new StudentFinalChoiceConfirmedNotification($selectedApplication, $closedCount)
        );

        User::where('role', 'admin')->get()->each(function (User $admin) use ($selectedApplication, $closedCount): void {
            $admin->notify(
                new StudentFinalChoiceNeedsAdminValidationNotification($selectedApplication, $closedCount)
            );
        });

        foreach ($closedApplications as $closedApplication) {
            $company = $closedApplication->offer?->company;

            if ($company) {
                $company->notify(
                    new CompanyApplicationClosedByStudentChoiceNotification($closedApplication, $selectedApplication)
                );
            }
        }

        return response()->json([
            'message' => 'Final choice saved. Other accepted applications were rejected.',
            'application' => $selectedApplication,
        ]);
    }

    public function offerApplicants(Request $request, $offerId)
    {
        $offer = InternshipOffer::where('id', $offerId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $offer) {
            return response()->json([
                'message' => 'Offer not found or you do not own it.'
            ], 404);
        }

        $applications = Application::where('offer_id', $offerId)
            ->with('student.studentProfile')
            ->latest()
            ->paginate(10);

        return response()->json($applications);
    }

    public function accept(Request $request, $id)
    {
        $application = Application::whereHas('offer', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->where('id', $id)
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found or you do not own the offer.'
            ], 404);
        }

        if (! $application->isPending()) {
            return response()->json([
                'message' => 'Only pending applications can be accepted.'
            ], 403);
        }

        $application->update(['status' => 'accepted']);

        $application->student->notify(
            new CompanyAcceptedApplicationNotification($application->fresh()->load('offer.company'))
        );

        User::where('role', 'admin')->get()->each(function (User $admin) use ($application): void {
            $admin->notify(
                new CompanyAcceptedNeedsAdminValidationNotification($application->fresh()->load('student', 'offer.company'))
            );
        });

        return response()->json([
            'message'     => 'Candidate accepted. Admin has been notified for validation.',
            'application' => $application->fresh()->load('student.studentProfile', 'offer'),
        ]);
    }

    public function refuse(Request $request, $id)
    {
        $application = Application::whereHas('offer', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->where('id', $id)
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found or you do not own the offer.'
            ], 404);
        }

        if (! $application->isPending()) {
            return response()->json([
                'message' => 'Only pending applications can be refused.'
            ], 403);
        }

        $application->update(['status' => 'refused']);

        $application->student->notify(
            new CompanyRefusedApplicationNotification($application->fresh()->load('offer.company'))
        );

        return response()->json([
            'message'     => 'Candidate refused.',
            'application' => $application->fresh(),
        ]);
    }

    public function pendingValidation()
    {
        $applications = Application::where('status', 'selected')
            ->with([
                'student.studentProfile',
                'offer.company.companyProfile',
            ])
            ->latest()
            ->paginate(10);

        return response()->json($applications);
    }

    public function validate(Request $request, $id)
    {
        $application = Application::where('id', $id)
            ->where('status', 'selected')
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found or student has not made final selection yet.'
            ], 404);
        }

        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $application->update([
            'status'     => 'validated',
            'admin_note' => $request->admin_note,
        ]);

        $application = $application->fresh()->load('student', 'offer.company');
        $application->student->notify(new AdminValidatedApplicationNotification($application));
        $application->offer->company->notify(new AdminValidatedApplicationNotification($application));

        if (! $application->convention) {
            $this->conventionService->generate($application);
        }

        return response()->json([
            'message'     => 'Application validated and Convention de Stage generated.',
            'application' => $application->fresh()->load([
                'student.studentProfile',
                'offer.company.companyProfile',
                'convention',
            ]),
        ]);
    }

    public function reject(Request $request, $id)
    {
        $application = Application::where('id', $id)
            ->where('status', 'selected')
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found or student has not made final selection yet.'
            ], 404);
        }

        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $application->update([
            'status'     => 'rejected',
            'admin_note' => $request->admin_note,
        ]);

        $application = $application->fresh()->load('student', 'offer.company');
        $application->student->notify(new AdminRejectedApplicationNotification($application));
        $application->offer->company->notify(new AdminRejectedApplicationNotification($application));

        return response()->json([
            'message'     => 'Application rejected by administration.',
            'application' => $application->fresh(),
        ]);
    }

    public function adminIndex(Request $request)
    {
        $query = Application::with([
            'student.studentProfile',
            'offer.company.companyProfile',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $applications = $query->latest()->paginate(15);

        return response()->json($applications);
    }

    public function stats()
    {
        return response()->json([
            'total_applications' => Application::count(),
            'pending'            => Application::where('status', 'pending')->count(),
            'accepted'           => Application::where('status', 'accepted')->count(),
            'refused'            => Application::where('status', 'refused')->count(),
            'validated'          => Application::where('status', 'validated')->count(),
            'rejected'           => Application::where('status', 'rejected')->count(),
            'selected'           => Application::where('status', 'selected')->count(),
            'total_offers'       => InternshipOffer::count(),
            'open_offers'        => InternshipOffer::where('status', 'open')->count(),
            'students_placed'    => Application::whereIn('status', ['validated', 'selected'])
                ->distinct('student_id')
                ->count(),
        ]);
    }
}