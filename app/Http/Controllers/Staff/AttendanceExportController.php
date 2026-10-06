<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Who walked in, as a spreadsheet to keep.
 *
 * One row per scan, so a single day's export is that day's register and the
 * "all" export is every day stacked — the same grain the check-in app records.
 * Defaults to today, because the question at the end of a conference day is
 * "who was here", not "pick a date".
 */
class AttendanceExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $request->validate([
            'date' => ['nullable', 'regex:/^(all|\d{4}-\d{2}-\d{2})$/'],
        ]);

        $date = $request->input('date', today()->toDateString());
        $allDays = $date === 'all';

        $records = Attendance::query()
            ->with([
                'user:id,name,salutation,email,phone,institution,country,participant_type,fee_category,registration_code',
                'staff:id,name,salutation',
            ])
            ->whereHas('user', fn (Builder $query) => $query->withRole(User::ROLE_USER))
            ->when(! $allDays, fn (Builder $query) => $query->on($date))
            ->orderBy('attendance_date')
            ->orderBy('checked_in_at')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($allDays ? 'All days' : $date);

        $headers = ['#', 'Date', 'Checked In At', 'Name', 'Badge Code', 'Institution', 'Email', 'Phone', 'Country', 'Participant Type', 'Fee Category', 'Recorded By'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $rows = $records->values()->map(fn (Attendance $attendance, int $index) => [
            $index + 1,
            $attendance->attendance_date?->toDateString(),
            $attendance->checked_in_at?->format('H:i'),
            $attendance->user?->full_name,
            $attendance->user?->registration_code,
            $attendance->user?->institution,
            $attendance->user?->email,
            $attendance->user?->phone,
            $attendance->user?->country,
            $attendance->user?->participant_type,
            $attendance->user?->fee_category,
            $attendance->staff?->full_name,
        ])->all();

        // Written as text, not inferred: phone numbers and badge codes are
        // identifiers, and Excel would otherwise strip a leading zero or show
        // 255... in scientific notation.
        foreach ($rows as $offset => $row) {
            foreach ($row as $column => $value) {
                $sheet->setCellValueExplicit(
                    [$column + 1, $offset + 2],
                    $value ?? '',
                    $column === 0 ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING,
                );
            }
        }

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'tmsc-attendance-'.($allDays ? 'all-days' : $date).'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename);
    }
}
