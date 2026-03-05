<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\InternshipOffer;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
   
    public function apply(Request $request, $offerId)
    {
        $offer = InternshipOffer::find($offerId);

        // Check offer exists and is open
        if (! $offer || $offer->status !== 'open') {
            return response()->json([
                'message' => 'Offer not found or is no longer open.'
            ], 404);
        }

        // Check student hasn't already applied
        $alreadyApplied = Application::where('student_id', $request->user()->id)
            ->where('offer_id', $offerId)
            ->exists();

        if ($alreadyApplied) {
            return response()->json([
                'message' => 'You have already applied to this offer.'
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

    // ─────────────────────────────────────────────────────
    // CANCEL APPLICATION (only if still pending)
    // DELETE /api/student/applications/{id}
    // ─────────────────────────────────────────────────────
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

    // ═════════════════════════════════════════════════════
    //  COMPANY ACTIONS
    // ═════════════════════════════════════════════════════

    // ─────────────────────────────────────────────────────
    // VIEW ALL APPLICANTS FOR AN OFFER
    // GET /api/company/offers/{id}/applications
    // ─────────────────────────────────────────────────────
    public function offerApplicants(Request $request, $offerId)
    {
        // Make sure the offer belongs to this company
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

    // ─────────────────────────────────────────────────────
    // ACCEPT A CANDIDATE
    // PUT /api/company/applications/{id}/accept
    // ─────────────────────────────────────────────────────
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

        // ✅ At this point:
        // Admin gets notified to validate (next module)
        // You can fire an event here: event(new ApplicationAccepted($application));

        return response()->json([
            'message'     => 'Candidate accepted. Admin has been notified for validation.',
            'application' => $application->fresh()->load('student.studentProfile', 'offer'),
        ]);
    }

    // ─────────────────────────────────────────────────────
    // REFUSE A CANDIDATE
    // PUT /api/company/applications/{id}/refuse
    // ─────────────────────────────────────────────────────
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

        return response()->json([
            'message'     => 'Candidate refused.',
            'application' => $application->fresh(),
        ]);
    }

    // ═════════════════════════════════════════════════════
    //  ADMIN ACTIONS
    // ═════════════════════════════════════════════════════

    // ─────────────────────────────────────────────────────
    // VIEW ALL ACCEPTED APPLICATIONS (pending admin validation)
    // GET /api/admin/applications/pending
    // ─────────────────────────────────────────────────────
    public function pendingValidation()
    {
        $applications = Application::where('status', 'accepted')
            ->with([
                'student.studentProfile',
                'offer.company.companyProfile',
            ])
            ->latest()
            ->paginate(10);

        return response()->json($applications);
    }

    // ─────────────────────────────────────────────────────
    // VALIDATE AN APPLICATION (triggers PDF generation)
    // PUT /api/admin/applications/{id}/validate
    // ─────────────────────────────────────────────────────
    public function validate(Request $request, $id)
    {
        $application = Application::where('id', $id)
            ->where('status', 'accepted') // can only validate accepted ones
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found or not in accepted status.'
            ], 404);
        }

        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $application->update([
            'status'     => 'validated',
            'admin_note' => $request->admin_note,
        ]);

        // ✅ At this point:
        // PDF generation is triggered (next module)
        // event(new ApplicationValidated($application));

        return response()->json([
            'message'     => 'Application validated. Convention de Stage will be generated.',
            'application' => $application->fresh()->load([
                'student.studentProfile',
                'offer.company.companyProfile',
            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────
    // REJECT AN APPLICATION (admin rejects after company accepted)
    // PUT /api/admin/applications/{id}/reject
    // ─────────────────────────────────────────────────────
    public function reject(Request $request, $id)
    {
        $application = Application::where('id', $id)
            ->where('status', 'accepted')
            ->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found or not in accepted status.'
            ], 404);
        }

        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $application->update([
            'status'     => 'rejected',
            'admin_note' => $request->admin_note,
        ]);

        return response()->json([
            'message'     => 'Application rejected by administration.',
            'application' => $application->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────────────
    // ADMIN — VIEW ALL APPLICATIONS (with filters)
    // GET /api/admin/applications
    // ─────────────────────────────────────────────────────
    public function adminIndex(Request $request)
    {
        $query = Application::with([
            'student.studentProfile',
            'offer.company.companyProfile',
        ]);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $applications = $query->latest()->paginate(15);

        return response()->json($applications);
    }

    // ─────────────────────────────────────────────────────
    // ADMIN — STATISTICS
    // GET /api/admin/stats
    // ─────────────────────────────────────────────────────
    public function stats()
    {
        return response()->json([
            'total_applications' => Application::count(),
            'pending'            => Application::where('status', 'pending')->count(),
            'accepted'           => Application::where('status', 'accepted')->count(),
            'refused'            => Application::where('status', 'refused')->count(),
            'validated'          => Application::where('status', 'validated')->count(),
            'rejected'           => Application::where('status', 'rejected')->count(),
            'total_offers'       => InternshipOffer::count(),
            'open_offers'        => InternshipOffer::where('status', 'open')->count(),
            'students_placed'    => Application::where('status', 'validated')
                                        ->distinct('student_id')->count(),
        ]);
    }
}