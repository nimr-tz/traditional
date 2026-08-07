<?php

namespace Tests\Feature;

use App\Models\AbstractSubmission;
use App\Models\Attendance;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagementDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function registrant(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'country' => 'Tanzania',
            'is_east_africa' => true,
            'institution' => 'NIMR',
            'fee_category' => 'participant_east_africa',
            'payment_status' => 'pending',
        ], $overrides));
    }

    private function abstractFor(User $user, array $overrides = []): AbstractSubmission
    {
        $subtheme = Subtheme::firstOrCreate(['title' => 'Innovations'], ['active' => true, 'sort_order' => 1]);

        return AbstractSubmission::create(array_merge([
            'user_id' => $user->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'An abstract',
            'authors' => [['name' => 'A. Presenter', 'institution' => 'NIMR', 'is_presenter' => true]],
            'background' => 'Background.',
            'objective' => 'Objective.',
            'methods' => 'Methods.',
            'results' => 'Results.',
            'conclusion' => 'Conclusion.',
            'presentation_type' => 'oral',
            'status' => 'submitted',
        ], $overrides));
    }

    public function test_headline_totals_cover_registration_payment_and_abstracts(): void
    {
        $paidAuthor = $this->registrant(['payment_status' => 'verified']);
        $this->abstractFor($paidAuthor);

        $waived = $this->registrant(['payment_status' => 'waived']);
        $unpaidAuthor = $this->registrant(['payment_status' => 'submitted']);
        $this->abstractFor($unpaidAuthor, ['presentation_type' => 'poster']);

        $this->registrant();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.management.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/management/index')
                ->where('totals.registered', 4)
                // "Paid" folds in waived — the fee is settled either way.
                ->where('totals.paid', 2)
                ->where('totals.unpaid', 2)
                ->where('totals.awaiting_payment', 1)
                ->where('totals.with_abstracts', 2)
                ->where('totals.paid_with_abstracts', 1)
                ->where('abstracts.total', 2)
                ->where('abstracts.oral', 1)
                ->where('abstracts.poster', 1)
            );

        $this->assertNotNull($waived);
    }

    /** Staff, reviewers, and admins share the users table; counting them would inflate every headline. */
    public function test_only_participant_accounts_are_counted(): void
    {
        $this->registrant();
        User::factory()->reviewer()->create();
        User::factory()->superAdmin()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.management.index'))
            ->assertInertia(fn (Assert $page) => $page->where('totals.registered', 1));
    }

    public function test_students_are_split_from_normal_registrants(): void
    {
        $this->registrant(['fee_category' => 'student_east_africa']);
        $this->registrant(['fee_category' => 'student_non_east_africa']);
        $this->registrant(['fee_category' => 'participant_east_africa']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.management.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('totals.students', 2)
                ->where('totals.non_students', 1)
            );
    }

    public function test_registrations_are_broken_down_by_country_with_paid_split(): void
    {
        $this->registrant(['country' => 'Tanzania', 'payment_status' => 'verified']);
        $this->registrant(['country' => 'Tanzania']);
        $this->registrant(['country' => 'Kenya', 'payment_status' => 'verified']);
        $this->registrant(['country' => 'Germany', 'is_east_africa' => false]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.management.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('byCountry', 3)
                // Ordered by headcount, so the biggest market leads.
                ->where('byCountry.0.label', 'Tanzania')
                ->where('byCountry.0.total', 2)
                ->where('byCountry.0.paid', 1)
                ->where('byCountry.0.region', 'east_africa')
            );
    }

    /**
     * Institutions group on the denormalised name, so registrants who typed one
     * in via "Other" (no institution_id) still appear.
     */
    public function test_registrations_are_broken_down_by_institution_including_custom_entries(): void
    {
        $this->registrant(['institution' => 'NIMR']);
        $this->registrant(['institution' => 'NIMR', 'payment_status' => 'verified']);
        $this->registrant(['institution' => 'Village Health Clinic', 'institution_id' => null]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.management.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('byInstitution', 2)
                ->where('byInstitution.0.label', 'NIMR')
                ->where('byInstitution.0.total', 2)
                ->where('byInstitution.0.paid', 1)
                ->where('byInstitution.1.label', 'Village Health Clinic')
                ->where('byInstitution.1.is_custom', true)
            );
    }

    public function test_abstracts_are_broken_down_by_author_country(): void
    {
        $tz = $this->registrant(['country' => 'Tanzania']);
        $this->abstractFor($tz, ['presentation_type' => 'oral']);
        $this->abstractFor($tz, ['presentation_type' => 'poster', 'status' => 'accepted']);

        $ke = $this->registrant(['country' => 'Kenya']);
        $this->abstractFor($ke, ['presentation_type' => 'oral']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.management.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('abstractsByCountry', 2)
                ->where('abstractsByCountry.0.label', 'Tanzania')
                ->where('abstractsByCountry.0.total', 2)
                ->where('abstractsByCountry.0.oral', 1)
                ->where('abstractsByCountry.0.poster', 1)
                ->where('abstractsByCountry.0.accepted', 1)
            );
    }

    public function test_checked_in_registrants_are_counted(): void
    {
        $user = $this->registrant();
        Attendance::create(['user_id' => $user->id, 'checked_in_at' => now()]);
        $this->registrant();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.management.index'))
            ->assertInertia(fn (Assert $page) => $page->where('totals.checked_in', 1));
    }

    public function test_the_report_renders_with_no_data_at_all(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.management.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('totals.registered', 0)
                ->where('abstracts.total', 0)
                ->has('byCountry', 0)
                ->has('byInstitution', 0)
                ->has('abstractsByCountry', 0)
            );
    }

    public function test_reviewers_and_registrants_cannot_reach_the_report(): void
    {
        $this->actingAs(User::factory()->reviewer()->create())
            ->get(route('admin.management.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.management.index'))
            ->assertForbidden();
    }
}
