<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class StaffAttendanceExportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<int, mixed>> */
    private function rowsOf(TestResponse $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'attendance').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        try {
            return IOFactory::load($path)->getActiveSheet()->toArray();
        } finally {
            @unlink($path);
        }
    }

    /** @return array{0: User, 1: User, 2: User} */
    private function scenario(): array
    {
        $staff = User::factory()->staff()->create(['name' => 'Zed Staffer']);

        $today = User::factory()->create([
            'name' => 'Aaa Today',
            'phone' => '0712345678',
            'payment_status' => 'verified',
            'registration_code' => 'TMSC-TODAY00001',
        ]);
        Attendance::create(['user_id' => $today->id, 'checked_in_at' => now(), 'checked_in_by' => $staff->id]);

        $earlier = User::factory()->create(['name' => 'Bbb Earlier', 'payment_status' => 'verified']);
        Attendance::create(['user_id' => $earlier->id, 'checked_in_at' => now()->subDays(2), 'checked_in_by' => $staff->id]);

        return [$staff, $today, $earlier];
    }

    public function test_exports_todays_attendance_by_default(): void
    {
        [$staff] = $this->scenario();

        $response = $this->actingAs($staff)->get(route('staff.attendance.export'));

        $response->assertOk();
        $response->assertDownload('tmsc-attendance-'.today()->toDateString().'.xlsx');

        $rows = $this->rowsOf($response);

        $this->assertSame('Name', $rows[0][3]);
        $this->assertCount(2, $rows);
        $this->assertSame('Aaa Today', $rows[1][3]);
        $this->assertSame('TMSC-TODAY00001', $rows[1][4]);
        // Kept as text, so the leading zero survives.
        $this->assertSame('0712345678', $rows[1][7]);
        $this->assertSame('Zed Staffer', $rows[1][11]);
    }

    public function test_exports_a_chosen_day_or_every_day(): void
    {
        [$staff] = $this->scenario();

        $earlierDay = now()->subDays(2)->toDateString();
        $dayRows = $this->rowsOf($this->actingAs($staff)->get(route('staff.attendance.export', ['date' => $earlierDay])));

        $this->assertCount(2, $dayRows);
        $this->assertSame('Bbb Earlier', $dayRows[1][3]);

        $allRows = $this->rowsOf($this->actingAs($staff)->get(route('staff.attendance.export', ['date' => 'all'])));

        $this->assertCount(3, $allRows);
        $this->assertSame(['Bbb Earlier', 'Aaa Today'], [$allRows[1][3], $allRows[2][3]]);
    }

    public function test_rejects_a_malformed_date(): void
    {
        [$staff] = $this->scenario();

        $this->actingAs($staff)
            ->get(route('staff.attendance.export', ['date' => 'yesterday']))
            ->assertSessionHasErrors('date');
    }

    public function test_registrants_cannot_export(): void
    {
        [, $today] = $this->scenario();

        $this->actingAs($today)->get(route('staff.attendance.export'))->assertForbidden();
    }

    public function test_register_page_lists_the_days_with_scans(): void
    {
        [$staff] = $this->scenario();

        $this->actingAs($staff)
            ->get(route('staff.registrants'))
            ->assertInertia(fn ($page) => $page
                ->where('attendanceDays.0', ['date' => today()->toDateString(), 'total' => 1])
                ->where('attendanceDays.1', ['date' => now()->subDays(2)->toDateString(), 'total' => 1]));
    }
}
