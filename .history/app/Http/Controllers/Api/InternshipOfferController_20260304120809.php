// app/Http/Controllers/Api/InternshipOfferController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternshipOffer;
use Illuminate\Http\Request;

class InternshipOfferController extends Controller
{
    // ─────────────────────────────────────────────────────
    // LIST ALL OPEN OFFERS (with filters)
    // GET /api/offers
    // Available filters: wilaya, domain, type, skill
    // ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = InternshipOffer::with('company')
            ->open(); // only open offers

        // Filter by wilaya/location
        if ($request->filled('wilaya')) {
            $query->byWilaya($request->wilaya);
        }

        // Filter by domain
        if ($request->filled('domain')) {
            $query->byDomain($request->domain);
        }

        // Filter by internship type
        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        // Filter by required skill
        if ($request->filled('skill')) {
            $query->bySkill($request->skill);
        }

        $offers = $query->latest()->paginate(10);

        return response()->json($offers);
    }

    // ─────────────────────────────────────────────────────
    // VIEW SINGLE OFFER
    // GET /api/offers/{id}
    // ─────────────────────────────────────────────────────
    public function show($id)
    {
        $offer = InternshipOffer::with('company')->find($id);

        if (! $offer) {
            return response()->json([
                'message' => 'Offer not found.'
            ], 404);
        }

        return response()->json(['offer' => $offer]);
    }

    // ─────────────────────────────────────────────────────
    // CREATE OFFER (company only)
    // POST /api/company/offers
    // ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['required', 'string'],
            'domain'          => ['required', 'string', 'max:255'],
            'location'        => ['required', 'string', 'max:255'],
            'type'            => ['required', 'in:présentiel,télétravail,hybride'],
            'duration_unit'   => ['required', 'in:weeks,months'],
            'duration_value'  => ['required', 'integer', 'min:1'],
            'required_skills' => ['nullable', 'array'],
            'required_skills.*'=> ['string', 'max:50'],
            'deadline'        => ['nullable', 'date', 'after:today'],
        ]);

        $offer = InternshipOffer::create([
            'user_id'         => $request->user()->id,
            'title'           => $request->title,
            'description'     => $request->description,
            'domain'          => $request->domain,
            'location'        => $request->location,
            'type'            => $request->type,
            'duration_unit'   => $request->duration_unit,
            'duration_value'  => $request->duration_value,
            'required_skills' => $request->required_skills ?? [],
            'deadline'        => $request->deadline,
            'status'          => 'open',
        ]);

        return response()->json([
            'message' => 'Offer created successfully.',
            'offer'   => $offer,
        ], 201);
    }

    // ─────────────────────────────────────────────────────
    // UPDATE OFFER (company only, must own the offer)
    // PUT /api/company/offers/{id}
    // ─────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $offer = InternshipOffer::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $offer) {
            return response()->json([
                'message' => 'Offer not found or you do not own it.'
            ], 404);
        }

        $request->validate([
            'title'           => ['sometimes', 'string', 'max:255'],
            'description'     => ['sometimes', 'string'],
            'domain'          => ['sometimes', 'string', 'max:255'],
            'location'        => ['sometimes', 'string', 'max:255'],
            'type'            => ['sometimes', 'in:présentiel,télétravail,hybride'],
            'duration_unit'   => ['sometimes', 'in:weeks,months'],
            'duration_value'  => ['sometimes', 'integer', 'min:1'],
            'required_skills' => ['nullable', 'array'],
            'required_skills.*'=> ['string', 'max:50'],
            'status'          => ['sometimes', 'in:open,closed'],
            'deadline'        => ['nullable', 'date'],
        ]);

        $offer->update($request->only([
            'title',
            'description',
            'domain',
            'location',
            'type',
            'duration_unit',
            'duration_value',
            'required_skills',
            'status',
            'deadline',
        ]));

        return response()->json([
            'message' => 'Offer updated successfully.',
            'offer'   => $offer->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────────────
    // DELETE OFFER (company only, must own the offer)
    // DELETE /api/company/offers/{id}
    // ─────────────────────────────────────────────────────
    public function destroy(Request $request, $id)
    {
        $offer = InternshipOffer::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $offer) {
            return response()->json([
                'message' => 'Offer not found or you do not own it.'
            ], 404);
        }

        $offer->delete();

        return response()->json([
            'message' => 'Offer deleted successfully.'
        ]);
    }

    // ─────────────────────────────────────────────────────
    // LIST COMPANY'S OWN OFFERS
    // GET /api/company/offers
    // ─────────────────────────────────────────────────────
    public function myOffers(Request $request)
    {
        $offers = InternshipOffer::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json($offers);
    }
}