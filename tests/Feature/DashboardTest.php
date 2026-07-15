<?php

namespace Tests\Feature;

use App\Models\ConferenceSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        ConferenceSetting::set('conference_name', 'Traditional Medicine Conference');
        ConferenceSetting::set('venue', 'Mbeya, Tanzania');
        ConferenceSetting::set('start_date', '2026-08-26');
        ConferenceSetting::set('submission_deadline', '2026-08-06');

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('conferenceName', 'Traditional Medicine Conference')
                ->where('venue', 'Mbeya, Tanzania')
                ->where('conferenceStartDate', '2026-08-26')
                ->where('submissionDeadline', '2026-08-06')
            );
    }
}
