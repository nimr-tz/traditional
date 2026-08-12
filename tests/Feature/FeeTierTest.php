<?php

namespace Tests\Feature;

use App\Models\FeeCategory;
use App\Support\FeeTier;
use Database\Seeders\ProductionReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FeeTierTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `guard()` deliberately skips the region check for a key whose shape it
     * doesn't recognise, rather than rejecting a legitimate selection. That
     * safety valve is only acceptable while every *paying* key does resolve —
     * this is the test that keeps it honest.
     */
    public function test_every_seeded_paying_fee_category_resolves_to_a_region(): void
    {
        $this->seed(ProductionReferenceDataSeeder::class);

        $keys = FeeCategory::where('is_complimentary', false)->pluck('key');

        $this->assertNotEmpty($keys);

        foreach ($keys as $key) {
            $this->assertNotNull(
                FeeTier::regionOf($key),
                "Fee category '{$key}' does not resolve to a regional tier, so the region check would silently be skipped for it."
            );
        }
    }

    /**
     * Complimentary categories have no region on purpose — media and
     * secretariat attend free wherever they are from, so there is no cheaper
     * local rate for anyone to slip onto. WalkInRegistrar skips `guard()`
     * outright for them rather than relying on the null-region safety valve,
     * and this pins that they are genuinely outside the tier system.
     */
    public function test_complimentary_categories_sit_outside_the_regional_tiers(): void
    {
        $this->seed(ProductionReferenceDataSeeder::class);

        $keys = FeeCategory::where('is_complimentary', true)->pluck('key');

        $this->assertNotEmpty($keys, 'The complimentary roles should be seeded for production.');

        foreach ($keys as $key) {
            $this->assertNull(FeeTier::regionOf($key));
            $this->assertFalse(FeeTier::isStudentCategory($key));
        }
    }

    public function test_region_is_resolved_from_the_key_suffix(): void
    {
        $this->assertSame(FeeTier::EAST_AFRICA, FeeTier::regionOf('participant_east_africa'));
        $this->assertSame(FeeTier::EAST_AFRICA, FeeTier::regionOf('student_east_africa'));
        $this->assertSame(FeeTier::INTERNATIONAL, FeeTier::regionOf('participant_non_east_africa'));
        $this->assertSame(FeeTier::INTERNATIONAL, FeeTier::regionOf('student_non_east_africa'));
        $this->assertNull(FeeTier::regionOf('something_else'));
    }

    public function test_east_african_country_matching_is_exact(): void
    {
        $this->assertTrue(FeeTier::isEastAfricaCountry('Tanzania'));
        $this->assertFalse(FeeTier::isEastAfricaCountry('tanzania'));
        $this->assertFalse(FeeTier::isEastAfricaCountry('Germany'));
        $this->assertFalse(FeeTier::isEastAfricaCountry(null));
    }

    /** Every East African country must be selectable, or locals get billed in USD. */
    public function test_every_east_african_country_appears_in_the_selectable_country_list(): void
    {
        foreach (config('tmsc.east_africa_countries') as $country) {
            $this->assertContains($country, config('tmsc.countries'), "'{$country}' is not selectable on the register form.");
        }
    }

    public function test_guard_rejects_an_international_registrant_choosing_the_east_african_rate(): void
    {
        $this->expectException(ValidationException::class);

        FeeTier::guard('participant_east_africa', 'researcher', 'Germany');
    }

    public function test_guard_rejects_an_east_african_registrant_choosing_the_international_rate(): void
    {
        $this->expectException(ValidationException::class);

        FeeTier::guard('participant_non_east_africa', 'researcher', 'Kenya');
    }

    public function test_guard_rejects_a_non_student_choosing_a_student_rate(): void
    {
        $this->expectException(ValidationException::class);

        FeeTier::guard('student_east_africa', 'researcher', 'Tanzania');
    }

    public function test_guard_accepts_matching_selections(): void
    {
        FeeTier::guard('participant_east_africa', 'researcher', 'Tanzania');
        FeeTier::guard('student_east_africa', 'student', 'Uganda');
        FeeTier::guard('participant_non_east_africa', 'practitioner', 'Germany');
        FeeTier::guard('student_non_east_africa', 'student', 'Japan');

        $this->expectNotToPerformAssertions();
    }
}
