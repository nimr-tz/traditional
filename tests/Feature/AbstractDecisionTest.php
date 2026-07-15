<?php

namespace Tests\Feature;

use App\Mail\AbstractDecision;
use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AbstractDecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_accept_an_abstract_and_the_presenter_is_emailed(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $subtheme = Subtheme::create(['title' => 'Innovations', 'active' => true, 'sort_order' => 1]);
        $submission = AbstractSubmission::create([
            'user_id' => User::factory()->create()->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'Herbal Innovations',
            'authors' => [['name' => 'A. Presenter', 'institution' => 'NIMR', 'is_presenter' => true]],
            'abstract_text' => 'A short abstract.',
            'presentation_type' => 'poster',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($admin)->post("/admin/abstracts/{$submission->id}/decide", [
            'action' => 'accepted',
            'decision_notes' => 'Great work.',
        ]);

        $response->assertRedirect();
        $submission->refresh();

        $this->assertSame('accepted', $submission->status);
        $this->assertSame('poster', $submission->presentation_type);
        $this->assertSame($admin->id, $submission->reviewer_id);
        $this->assertNotNull($submission->decided_at);
        $this->assertDatabaseHas('abstract_review_histories', [
            'abstract_submission_id' => $submission->id,
            'acted_by' => $admin->id,
            'action' => 'accepted',
        ]);
        Mail::assertQueued(AbstractDecision::class);
    }

    public function test_revision_requires_a_comment_and_returns_the_abstract_to_the_author(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $author = User::factory()->create();
        $subtheme = Subtheme::create(['title' => 'Research', 'active' => true, 'sort_order' => 1]);
        $submission = AbstractSubmission::create([
            'user_id' => $author->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'Needs Revision',
            'authors' => [['name' => 'A. Author', 'institution' => 'NIMR', 'is_presenter' => true]],
            'abstract_text' => 'Original text.',
            'presentation_type' => 'oral',
            'status' => 'submitted',
        ]);

        $this->actingAs($admin)->post(route('admin.abstracts.decide', $submission), [
            'action' => 'revision_requested',
            'decision_notes' => '',
        ])->assertSessionHasErrors('decision_notes');

        $this->actingAs($admin)->post(route('admin.abstracts.decide', $submission), [
            'action' => 'revision_requested',
            'decision_notes' => 'Clarify the study methods.',
        ])->assertRedirect(route('admin.abstracts.show', $submission));

        $this->assertSame('revision_requested', $submission->fresh()->status);
        $this->assertNull($submission->fresh()->decided_at);
        Mail::assertQueued(AbstractDecision::class, fn ($mail) => $mail->hasTo($author->email));
    }

    public function test_accepted_and_rejected_decisions_are_final(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $subtheme = Subtheme::create(['title' => 'Research', 'active' => true, 'sort_order' => 1]);
        $submission = AbstractSubmission::create([
            'user_id' => User::factory()->create()->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'Final Decision',
            'authors' => [['name' => 'A. Author', 'institution' => 'NIMR', 'is_presenter' => true]],
            'abstract_text' => 'Text.',
            'presentation_type' => 'oral',
            'status' => 'accepted',
            'decided_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.abstracts.decide', $submission), [
            'action' => 'rejected',
            'decision_notes' => 'Changed mind.',
        ])->assertUnprocessable();

        $this->assertSame('accepted', $submission->fresh()->status);
    }

    public function test_non_admin_cannot_access_the_admin_abstracts_area(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/abstracts')->assertForbidden();
    }
}
