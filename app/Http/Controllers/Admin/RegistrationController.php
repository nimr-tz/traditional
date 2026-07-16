<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::where('role', User::ROLE_USER)
            ->when($request->payment_status, fn ($q, $status) => $q->where('payment_status', $status))
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('institution', 'like', "%{$search}%");
            }));

        return Inertia::render('admin/registrations/index', [
            'registrations' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only(['payment_status', 'search']),
            'counts' => [
                'total' => User::where('role', User::ROLE_USER)->count(),
                'pending' => User::where('role', User::ROLE_USER)->where('payment_status', 'pending')->count(),
                'submitted' => User::where('role', User::ROLE_USER)->where('payment_status', 'submitted')->count(),
                'verified' => User::where('role', User::ROLE_USER)->where('payment_status', 'verified')->count(),
            ],
        ]);
    }

    public function export(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Name', 'Email', 'Institution', 'Phone', 'Country', 'Participant Type', 'Fee Category', 'Amount', 'Currency', 'Payment Status', 'Paid At', 'Checked In'];
        $sheet->fromArray($headers, null, 'A1');

        $rows = User::where('role', User::ROLE_USER)->with('attendance')->get()->map(fn (User $user) => [
            $user->name,
            $user->email,
            $user->institution,
            $user->phone,
            $user->country,
            $user->participant_type,
            $user->fee_category,
            $user->fee_amount,
            $user->currency,
            $user->payment_status,
            $user->paid_at?->format('Y-m-d H:i'),
            $user->isCheckedIn() ? 'Yes' : 'No',
        ])->toArray();

        $sheet->fromArray($rows, null, 'A2');

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'tmsc-registrations-'.now()->format('Y-m-d').'.xlsx');
    }
}
