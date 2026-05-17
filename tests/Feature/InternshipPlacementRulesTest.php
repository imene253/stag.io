<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\InternshipOffer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InternshipPlacementRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_expired_offers_are_auto_closed_when_listed(): void
    {
        $company = $this->createCompany();

        $expiredOffer = $this->createOffer($company, [
            'deadline' => '2026-06-01',
        ]);

        $this->getJson('/api/offers')
            ->assertOk()
            ->assertJsonMissing(['id' => $expiredOffer->id]);

        $this->assertSame('closed', $expiredOffer->fresh()->status);
    }

    public function test_close_expired_command_closes_past_deadline_offers(): void
    {
        $company = $this->createCompany();

        $offer = $this->createOffer($company, [
            'deadline' => '2026-06-01',
        ]);

        Artisan::call('offers:close-expired');

        $this->assertSame('closed', $offer->fresh()->status);
    }

    public function test_student_cannot_apply_to_offer_with_past_deadline(): void
    {
        $company = $this->createCompany();
        $student = $this->createStudent();

        $offer = $this->createOffer($company, [
            'deadline' => '2026-06-01',
        ]);

        Sanctum::actingAs($student);

        $this->postJson("/api/student/offers/{$offer->id}/apply")
            ->assertNotFound();
    }

    public function test_student_with_active_placement_cannot_apply_to_overlapping_offer(): void
    {
        $company = $this->createCompany();
        $student = $this->createStudent();

        $currentOffer = $this->createOffer($company, [
            'internship_starts_at' => '2026-06-01',
        ]);

        $this->createActivePlacement($student, $currentOffer);

        $overlappingOffer = $this->createOffer($company, [
            'title' => 'Overlapping internship',
            'internship_starts_at' => '2026-07-01',
        ]);

        Sanctum::actingAs($student);

        $this->postJson("/api/student/offers/{$overlappingOffer->id}/apply")
            ->assertForbidden()
            ->assertJson([
                'message' => 'You can only apply to offers that start after your current internship ends.',
                'internship_ends_at' => '2026-09-01',
                'offer_starts_at' => '2026-07-01',
            ]);
    }

    public function test_student_with_validated_placement_cannot_apply_to_overlapping_offer(): void
    {
        $company = $this->createCompany();
        $student = $this->createStudent();

        $currentOffer = $this->createOffer($company, [
            'internship_starts_at' => '2026-06-01',
        ]);

        $this->createActivePlacement($student, $currentOffer, 'validated');

        $overlappingOffer = $this->createOffer($company, [
            'internship_starts_at' => '2026-08-15',
        ]);

        Sanctum::actingAs($student);

        $this->postJson("/api/student/offers/{$overlappingOffer->id}/apply")
            ->assertForbidden();
    }

    public function test_student_with_active_placement_can_apply_when_new_offer_starts_after_current_ends(): void
    {
        $company = $this->createCompany();
        $student = $this->createStudent();

        $currentOffer = $this->createOffer($company, [
            'internship_starts_at' => '2026-06-01',
        ]);

        $this->createActivePlacement($student, $currentOffer);

        $futureOffer = $this->createOffer($company, [
            'title' => 'Future internship',
            'internship_starts_at' => '2026-10-01',
        ]);

        Sanctum::actingAs($student);

        $this->postJson("/api/student/offers/{$futureOffer->id}/apply", [
            'cover_letter' => 'Interested in this future role.',
        ])
            ->assertCreated()
            ->assertJsonPath('application.status', 'pending');

        $this->assertDatabaseHas('applications', [
            'student_id' => $student->id,
            'offer_id' => $futureOffer->id,
            'status' => 'pending',
        ]);
    }

    public function test_finalize_choice_is_blocked_when_it_overlaps_active_placement(): void
    {
        $company = $this->createCompany();
        $student = $this->createStudent();

        $currentOffer = $this->createOffer($company, [
            'internship_starts_at' => '2026-06-01',
        ]);

        $this->createActivePlacement($student, $currentOffer);

        $secondOffer = $this->createOffer($company, [
            'internship_starts_at' => '2026-07-15',
        ]);

        $acceptedApplication = Application::create([
            'student_id' => $student->id,
            'offer_id' => $secondOffer->id,
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($student);

        $this->putJson("/api/student/applications/{$acceptedApplication->id}/finalize-choice")
            ->assertForbidden()
            ->assertJsonFragment([
                'message' => 'You cannot select this internship because it overlaps with your current internship.',
            ]);
    }

    public function test_finalize_choice_succeeds_when_new_offer_starts_after_current_placement_ends(): void
    {
        $company = $this->createCompany();
        $student = $this->createStudent();

        $currentOffer = $this->createOffer($company, [
            'internship_starts_at' => '2026-06-01',
        ]);

        $this->createActivePlacement($student, $currentOffer);

        $futureOffer = $this->createOffer($company, [
            'internship_starts_at' => '2026-10-01',
            'duration_value' => 2,
        ]);

        $acceptedApplication = Application::create([
            'student_id' => $student->id,
            'offer_id' => $futureOffer->id,
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($student);

        $response = $this->putJson("/api/student/applications/{$acceptedApplication->id}/finalize-choice")
            ->assertOk()
            ->assertJsonPath('application.status', 'selected');

        $this->assertStringStartsWith(
            '2026-10-01',
            (string) $response->json('application.internship_starts_at')
        );
        $this->assertStringStartsWith(
            '2026-12-01',
            (string) $response->json('application.internship_ends_at')
        );
    }

    private function createStudent(): User
    {
        return User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
    }

    private function createCompany(): User
    {
        return User::factory()->create([
            'role' => 'company',
            'is_active' => true,
        ]);
    }

    private function createOffer(User $company, array $overrides = []): InternshipOffer
    {
        return InternshipOffer::create(array_merge([
            'user_id' => $company->id,
            'title' => 'Test Offer',
            'description' => 'Test description',
            'domain' => 'IT',
            'location' => 'Algiers',
            'type' => 'présentiel',
            'duration_unit' => 'months',
            'duration_value' => 3,
            'required_skills' => [],
            'status' => 'open',
            'deadline' => '2026-12-31',
            'internship_starts_at' => '2026-09-01',
        ], $overrides));
    }

    private function createActivePlacement(
        User $student,
        InternshipOffer $offer,
        string $status = 'selected'
    ): Application {
        return Application::create([
            'student_id' => $student->id,
            'offer_id' => $offer->id,
            'status' => $status,
            'selected_at' => now(),
            'internship_starts_at' => '2026-06-01',
            'internship_ends_at' => '2026-09-01',
        ]);
    }
}
