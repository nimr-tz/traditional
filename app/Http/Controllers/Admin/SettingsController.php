<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdministratorAccessChange;
use App\Models\ConferenceSetting;
use App\Models\FeeCategory;
use App\Models\Institution;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/settings/index', [
            'feeCategories' => FeeCategory::orderBy('sort_order')->get(),
            'subthemes' => Subtheme::orderBy('sort_order')->get(),
            'institutions' => Institution::orderBy('sort_order')->get(),
            'conferenceSettings' => ConferenceSetting::allSettings(),
            'administrators' => User::query()
                ->where('is_admin', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'administratorAccessChanges' => AdministratorAccessChange::query()
                ->latest()
                ->limit(10)
                ->get([
                    'id',
                    'target_name',
                    'target_email',
                    'changed_by_name',
                    'action',
                    'created_at',
                ]),
        ]);
    }

    public function updateFeeCategory(Request $request, FeeCategory $feeCategory)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:8',
            'active' => 'boolean',
        ]);

        $feeCategory->update($data);

        return back()->with('success', 'Fee category updated.');
    }

    public function storeSubtheme(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Subtheme::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'active' => true,
            'sort_order' => Subtheme::max('sort_order') + 1,
        ]);

        return back()->with('success', 'Sub-theme added.');
    }

    public function updateSubtheme(Request $request, Subtheme $subtheme)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active' => 'boolean',
        ]);

        $subtheme->update($data);

        return back()->with('success', 'Sub-theme updated.');
    }

    public function storeInstitution(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:institutions,name',
        ]);

        Institution::create([
            'name' => $data['name'],
            'active' => true,
            'sort_order' => Institution::max('sort_order') + 1,
        ]);

        return back()->with('success', 'Institution added.');
    }

    public function updateInstitution(Request $request, Institution $institution)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:institutions,name,'.$institution->id,
            'active' => 'boolean',
        ]);

        $institution->update($data);

        return back()->with('success', 'Institution updated.');
    }

    public function updateConferenceSettings(Request $request)
    {
        $data = $request->validate([
            'conference_name' => 'nullable|string|max:255',
            'edition_number' => 'nullable|string|max:20',
            'conference_year' => 'nullable|string|max:20',
            'theme' => 'nullable|string|max:500',
            'venue' => 'nullable|string|max:255',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'submission_deadline' => 'nullable|date_format:Y-m-d',
            'abstract_notification_date' => 'nullable|date_format:Y-m-d',
            'tm_week_dates' => 'nullable|string|max:100',
            'gepg_payee_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'tagline' => 'nullable|string|max:255',
        ]);

        foreach ($data as $key => $value) {
            ConferenceSetting::set($key, $value);
        }

        return back()->with('success', 'Conference settings updated.');
    }
}
