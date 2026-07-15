<?php

namespace App\Http\Controllers;

use App\Models\ConferenceSetting;
use App\Models\FeeCategory;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        $abstracts = $user->abstractSubmissions()
            ->with('subtheme:id,title')
            ->latest()
            ->get(['id', 'subtheme_id', 'title', 'status', 'presentation_type', 'created_at']);

        return Inertia::render('dashboard', [
            'userName' => $user->name,
            'registration' => [
                'payment_status' => $user->payment_status,
                'fee_category' => $user->fee_category,
                'fee_category_label' => FeeCategory::query()->where('key', $user->fee_category)->value('label'),
                'fee_amount' => $user->fee_amount,
                'currency' => $user->currency,
                'is_paid' => $user->isPaid(),
                'is_checked_in' => $user->isCheckedIn(),
            ],
            'abstracts' => $abstracts,
            'abstractsCount' => $abstracts->count(),
            'conferenceName' => ConferenceSetting::get('conference_name'),
            'conferenceYear' => ConferenceSetting::get('conference_year'),
            'venue' => ConferenceSetting::get('venue'),
            'conferenceStartDate' => ConferenceSetting::get('start_date'),
            'submissionDeadline' => ConferenceSetting::get('submission_deadline'),
        ]);
    }
}
