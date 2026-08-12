<?php

namespace Tests\Feature;

use App\Mail\CriticalErrorAlert;
use App\Models\FeeCategory;
use App\Models\User;
use App\Services\CriticalErrorAlertService;
use Database\Seeders\ProductionReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class OperationsReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_reference_seeder_does_not_create_demo_users(): void
    {
        $this->seed(ProductionReferenceDataSeeder::class);

        // Four paying tiers, plus the complimentary roles that attend free —
        // media, secretariat, invited guests and exhibitors. Asserted as a split
        // rather than a total so a miscount says which half went wrong.
        $this->assertSame(4, FeeCategory::where('is_complimentary', false)->count());
        $this->assertSame(4, FeeCategory::where('is_complimentary', true)->count());

        // Complimentary rates are granted at the venue desk, so they must never
        // reach the public registration form.
        $this->assertSame(4, FeeCategory::selectableByPublic()->count());

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('abstract_submissions', 0);
    }

    public function test_super_admin_bootstrap_uses_secret_environment_password(): void
    {
        putenv('TMSC_BOOTSTRAP_PASSWORD=strong-temporary-password');

        try {
            $this->artisan('tmsc:bootstrap-super-admin', [
                'email' => 'admin@example.com',
                '--name' => 'TMSC Administrator',
            ])->assertSuccessful();
        } finally {
            putenv('TMSC_BOOTSTRAP_PASSWORD');
        }

        $user = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_critical_runtime_exception_is_deduplicated_and_emailed(): void
    {
        Mail::fake();
        config([
            'mail.error_alerts.enabled' => true,
            'mail.error_alerts.to' => ['ops@example.com'],
            'mail.error_alerts.mailer' => 'array',
        ]);

        $request = Request::create('/admin/dashboard', 'GET');
        $exception = new RuntimeException('Dashboard failed');
        $service = app(CriticalErrorAlertService::class);

        $service->handle($exception, $request);
        $service->handle($exception, $request);

        Mail::assertSent(CriticalErrorAlert::class, 1);
    }
}
