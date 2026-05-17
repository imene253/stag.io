<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Convention;
use App\Models\InternshipOffer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentConventionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_all_validated_conventions_not_just_one(): void
    {
        Carbon::setTestNow('2026-06-15');

        $company = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $firstOffer = $this->createOffer($company, 'First internship', '2026-06-01');
        $secondOffer = $this->createOffer($company, 'Second internship', '2026-10-01');

        $firstApplication = $this->createValidatedApplication($student, $firstOffer);
        $secondApplication = $this->createValidatedApplication($student, $secondOffer);

        Convention::create([
            'application_id' => $firstApplication->id,
            'file_path' => 'conventions/CONV-2026-0001.pdf',
            'convention_number' => 'CONV-2026-0001',
            'generated_at' => Carbon::today(),
        ]);

        Convention::create([
            'application_id' => $secondApplication->id,
            'file_path' => 'conventions/CONV-2026-0002.pdf',
            'convention_number' => 'CONV-2026-0002',
            'generated_at' => Carbon::today(),
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/student/conventions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 2);

        $numbers = collect($this->getJson('/api/student/conventions')->json('data'))
            ->pluck('convention_number')
            ->all();

        $this->assertEqualsCanonicalizing(
            ['CONV-2026-0001', 'CONV-2026-0002'],
            $numbers
        );

        $this->getJson('/api/student/convention')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        Carbon::setTestNow();
    }

    public function test_pending_or_selected_applications_without_convention_are_excluded(): void
    {
        $company = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $offer = $this->createOffer($company, 'Pending only', '2026-09-01');

        Application::create([
            'student_id' => $student->id,
            'offer_id' => $offer->id,
            'status' => 'selected',
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/student/conventions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function createOffer(User $company, string $title, string $startsAt): InternshipOffer
    {
        return InternshipOffer::create([
            'user_id' => $company->id,
            'title' => $title,
            'description' => 'Description',
            'domain' => 'IT',
            'location' => 'Algiers',
            'type' => 'présentiel',
            'duration_unit' => 'months',
            'duration_value' => 3,
            'required_skills' => [],
            'status' => 'open',
            'deadline' => '2026-12-31',
            'internship_starts_at' => $startsAt,
        ]);
    }

    private function createValidatedApplication(User $student, InternshipOffer $offer): Application
    {
        return Application::create([
            'student_id' => $student->id,
            'offer_id' => $offer->id,
            'status' => 'validated',
            'internship_starts_at' => $offer->internship_starts_at,
            'internship_ends_at' => Carbon::parse($offer->internship_starts_at)->addMonths(3)->toDateString(),
        ]);
    }
}
