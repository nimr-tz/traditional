<?php

namespace Tests\Feature;

use App\Mail\AbstractReviewRequested;
use App\Mail\AbstractSubmitted;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AbstractSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_unverified_payment_can_still_submit_an_abstract(): void
    {
        Mail::fake();

        $subtheme = Subtheme::create(['title' => 'Conservation of Medicinal Plants', 'active' => true, 'sort_order' => 1]);
        $user = User::factory()->create(['payment_status' => 'pending']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($user)->post('/abstracts', [
            'title' => 'A Study of Local Herbs',
            'subtheme_id' => $subtheme->id,
            'presentation_type' => 'oral',
            'authors' => [
                ['name' => 'A. Presenter', 'institution' => 'NIMR', 'is_presenter' => true],
            ],
            'abstract_text' => 'Background. Objective. Methods. Results. Conclusion.',
        ]);

        $response->assertRedirect(route('abstracts.index'));
        $this->assertDatabaseHas('abstract_submissions', ['title' => 'A Study of Local Herbs', 'user_id' => $user->id]);
        Mail::assertQueued(AbstractSubmitted::class);
        Mail::assertQueued(AbstractReviewRequested::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_author_can_revise_and_resubmit_after_a_revision_request(): void
    {
        Mail::fake();

        $subtheme = Subtheme::create(['title' => 'Policy', 'active' => true, 'sort_order' => 1]);
        $author = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $submission = $author->abstractSubmissions()->create([
            'subtheme_id' => $subtheme->id,
            'title' => 'Original title',
            'authors' => [['name' => 'A. Author', 'institution' => 'NIMR', 'is_presenter' => true]],
            'abstract_text' => 'Original abstract.',
            'presentation_type' => 'poster',
            'status' => 'revision_requested',
            'decision_notes' => 'Clarify the conclusion.',
            'reviewer_id' => $admin->id,
            'revision_requested_at' => now(),
        ]);

        $this->actingAs($author)->put(route('abstracts.update', $submission), [
            'title' => 'Revised title',
            'subtheme_id' => $subtheme->id,
            'presentation_type' => 'poster',
            'authors' => [['name' => 'A. Author', 'institution' => 'NIMR', 'is_presenter' => true]],
            'abstract_text' => 'Revised abstract with a clearer conclusion.',
        ])->assertRedirect(route('abstracts.index'));

        $submission->refresh();
        $this->assertSame('submitted', $submission->status);
        $this->assertNull($submission->decision_notes);
        $this->assertNotNull($submission->resubmitted_at);
        $this->assertDatabaseHas('abstract_review_histories', [
            'abstract_submission_id' => $submission->id,
            'action' => 'resubmitted',
        ]);
        Mail::assertQueued(AbstractSubmitted::class, fn ($mail) => $mail->isRevision && $mail->hasTo($author->email));
        Mail::assertQueued(AbstractReviewRequested::class, fn ($mail) => $mail->isRevision && $mail->hasTo($admin->email));
    }

    public function test_abstract_text_over_300_words_is_rejected(): void
    {
        $subtheme = Subtheme::create(['title' => 'Policy and Governance', 'active' => true, 'sort_order' => 1]);
        $user = User::factory()->create();

        $longText = implode(' ', array_fill(0, 301, 'word'));

        $response = $this->actingAs($user)->post('/abstracts', [
            'title' => 'Too Long',
            'subtheme_id' => $subtheme->id,
            'presentation_type' => 'poster',
            'authors' => [
                ['name' => 'A. Presenter', 'institution' => 'NIMR', 'is_presenter' => true],
            ],
            'abstract_text' => $longText,
        ]);

        $response->assertSessionHasErrors('abstract_text');
    }
}
