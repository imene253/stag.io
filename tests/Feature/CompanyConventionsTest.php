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

class CompanyConventionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_sees_all_conventions_for_its_offers(): void
    {
        $company = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $studentA = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $studentB = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $firstOffer = $this->createOffer($company, 'Offer A');
        $secondOffer = $this->createOffer($company, 'Offer B');

        $firstApplication = $this->createValidatedApplication($studentA, $firstOffer);
        $secondApplication = $this->createValidatedApplication($studentB, $secondOffer);

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

        Sanctum::actingAs($company);

        $this->getJson('/api/company/conventions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 2);

        $numbers = collect($this->getJson('/api/company/conventions')->json('data'))
            ->pluck('convention_number')
            ->all();

        $this->assertEqualsCanonicalizing(
            ['CONV-2026-0001', 'CONV-2026-0002'],
            $numbers
        );

        $this->assertTrue(
            collect($this->getJson('/api/company/conventions')->json('data'))
                ->every(fn (array $item) => isset($item['download_url']))
        );
    }

    public function test_company_does_not_see_conventions_from_other_companies(): void
    {
        $company = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $otherCompany = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $otherOffer = $this->createOffer($otherCompany, 'Other company offer');
        $otherApplication = $this->createValidatedApplication($student, $otherOffer);

        Convention::create([
            'application_id' => $otherApplication->id,
            'file_path' => 'conventions/CONV-2026-0099.pdf',
            'convention_number' => 'CONV-2026-0099',
            'generated_at' => Carbon::today(),
        ]);

        Sanctum::actingAs($company);

        $this->getJson('/api/company/conventions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function createOffer(User $company, string $title): InternshipOffer
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
            'internship_starts_at' => '2026-09-01',
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
