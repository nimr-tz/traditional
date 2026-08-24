<?php

namespace Tests\Feature;

use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPresentationsTest extends TestCase
{
    use RefreshDatabase;

    private function acceptedSubmission(array $overrides = []): AbstractSubmission
    {
        $subtheme = Subtheme::firstOrCreate(
            ['title' => $overrides['subtheme_title'] ?? 'Innovations'],
            ['active' => true, 'sort_order' => 1],
        );

        // presentation_status isn't fillable (only the upload flow sets it —
        // see PresentationController), so it has to be forced in after create.
        $presentationStatus = $overrides['presentation_status'] ?? null;

        $submission = AbstractSubmission::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'Herbal Innovations',
            'authors' => [['name' => 'A. Presenter', 'institution' => 'NIMR', 'is_presenter' => true]],
            'background' => 'Background.',
            'objective' => 'Objective.',
            'methods' => 'Methods.',
            'results' => 'Results.',
            'conclusion' => 'Conclusion.',
            'presentation_type' => 'oral',
            'status' => 'accepted',
        ], array_diff_key($overrides, ['subtheme_title' => null, 'presentation_status' => null])));

        if ($presentationStatus !== null) {
            $submission->forceFill(['presentation_status' => $presentationStatus])->save();
        }

        return $submission;
    }

    public function test_admin_sees_presentation_status_across_accepted_abstracts(): void
    {
        $admin = User::factory()->admin()->create();
        $this->acceptedSubmission(['title' => 'Uploaded One', 'presentation_status' => 'uploaded']);
        $this->acceptedSubmission(['title' => 'Pending One', 'presentation_status' => 'pending']);
        // Not accepted — must not appear in the count or the list.
        $this->acceptedSubmission(['title' => 'Still Under Review', 'status' => 'submitted']);

        $response = $this->actingAs($admin)->get(route('admin.presentations.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/presentations/index')
            ->where('counts.total', 2)
            ->where('counts.uploaded', 1)
            ->where('counts.pending', 1));
    }

    public function test_filtering_by_status_narrows_the_list(): void
    {
        $admin = User::factory()->admin()->create();
        $this->acceptedSubmission(['title' => 'Uploaded One', 'presentation_status' => 'uploaded']);
        $this->acceptedSubmission(['title' => 'Pending One', 'presentation_status' => 'pending']);

        $response = $this->actingAs($admin)->get(route('admin.presentations.index', ['status' => 'pending']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.title', 'Pending One'));
    }

    public function test_reviewer_cannot_view_the_presentations_console(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $this->acceptedSubmission();

        $response = $this->actingAs($reviewer)->get(route('admin.presentations.index'));

        $response->assertForbidden();
    }
}
