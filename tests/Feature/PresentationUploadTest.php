<?php

namespace Tests\Feature;

use App\Mail\PresentationApproved;
use App\Mail\PresentationRejected;
use App\Mail\PresentationSubmittedForReview;
use App\Mail\PresentationUploaded;
use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PresentationUploadTest extends TestCase
{
    use RefreshDatabase;

    private function acceptedSubmission(array $overrides = []): AbstractSubmission
    {
        $subtheme = Subtheme::create(['title' => 'Innovations', 'active' => true, 'sort_order' => 1]);
        $author = User::factory()->create();

        return AbstractSubmission::create(array_merge([
            'user_id' => $author->id,
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
        ], $overrides));
    }

    public function test_presentation_page_is_blocked_until_the_abstract_is_accepted(): void
    {
        $submission = $this->acceptedSubmission(['status' => 'submitted']);

        $this->actingAs($submission->user)
            ->get(route('abstracts.presentation.show', $submission))
            ->assertForbidden();
    }

    public function test_only_the_owning_presenter_can_view_or_upload(): void
    {
        $submission = $this->acceptedSubmission();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('abstracts.presentation.show', $submission))->assertForbidden();

        $this->actingAs($stranger)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('slides.pdf', 100, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_presenter_can_upload_a_presentation_matching_their_type(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $submission = $this->acceptedSubmission();

        $response = $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('slides.pptx', 200),
            'presentation_notes' => 'Draft v1.',
        ]);

        $response->assertRedirect();
        $submission->refresh();

        $this->assertSame('uploaded', $submission->presentation_status);
        $this->assertSame('slides.pptx', $submission->presentation_original_name);
        $this->assertNotNull($submission->presentation_uploaded_at);
        Storage::disk('local')->assertExists($submission->presentation_file);
        Mail::assertQueued(PresentationUploaded::class, fn ($mail) => $mail->hasTo($submission->user->email) && ! $mail->isReplacement);
        Mail::assertQueued(PresentationSubmittedForReview::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_wrong_file_type_for_the_presentation_type_is_rejected(): void
    {
        $submission = $this->acceptedSubmission(['presentation_type' => 'oral']);

        $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('slides.mp4', 200),
        ])->assertSessionHasErrors('presentation_file');
    }

    public function test_reuploading_replaces_the_old_file_and_flags_the_notification_as_a_replacement(): void
    {
        Storage::fake('local');
        Mail::fake();

        $submission = $this->acceptedSubmission();

        $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('first.pdf', 100),
        ]);

        $oldPath = $submission->fresh()->presentation_file;

        $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('second.pdf', 100),
        ]);

        $submission->refresh();
        $this->assertSame('second.pdf', $submission->presentation_original_name);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($submission->presentation_file);
        Mail::assertQueued(PresentationUploaded::class, fn ($mail) => $mail->isReplacement);
    }

    public function test_presenter_can_remove_their_uploaded_file(): void
    {
        Storage::fake('local');
        Mail::fake();

        $submission = $this->acceptedSubmission();

        $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('slides.pdf', 100),
        ]);

        $path = $submission->fresh()->presentation_file;

        $this->actingAs($submission->user)->delete(route('abstracts.presentation.destroy', $submission))->assertRedirect();

        $submission->refresh();
        $this->assertNull($submission->presentation_file);
        $this->assertSame('pending', $submission->presentation_status);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_download_is_limited_to_the_owner_and_admins(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['is_admin' => true]);
        $stranger = User::factory()->create();
        $submission = $this->acceptedSubmission();

        $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('slides.pdf', 100),
        ]);

        $this->actingAs($stranger)->get(route('abstracts.presentation.download', $submission))->assertForbidden();
        $this->actingAs($submission->user)->get(route('abstracts.presentation.download', $submission))->assertOk();
        $this->actingAs($admin)->get(route('abstracts.presentation.download', $submission))->assertOk();
    }

    public function test_admin_can_approve_an_uploaded_presentation_and_it_locks_further_uploads(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $submission = $this->acceptedSubmission();

        $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('slides.pdf', 100),
        ]);

        $this->actingAs($admin)->post(route('admin.abstracts.presentation.approve', $submission))->assertRedirect();

        $submission->refresh();
        $this->assertSame('approved', $submission->presentation_status);
        $this->assertFalse($submission->canUploadPresentation());
        Mail::assertQueued(PresentationApproved::class, fn ($mail) => $mail->hasTo($submission->user->email));

        $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('another.pdf', 100),
        ])->assertStatus(422);
    }

    public function test_admin_rejection_requires_notes_and_reopens_upload(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $submission = $this->acceptedSubmission();

        $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('slides.pdf', 100),
        ]);

        $this->actingAs($admin)->post(route('admin.abstracts.presentation.reject', $submission), [
            'notes' => '',
        ])->assertSessionHasErrors('notes');

        $this->actingAs($admin)->post(route('admin.abstracts.presentation.reject', $submission), [
            'notes' => 'Slides are missing the required disclosure slide.',
        ])->assertRedirect();

        $submission->refresh();
        $this->assertSame('pending', $submission->presentation_status);
        $this->assertSame('Slides are missing the required disclosure slide.', $submission->presentation_review_notes);
        $this->assertTrue($submission->canUploadPresentation());
        Mail::assertQueued(PresentationRejected::class, fn ($mail) => $mail->hasTo($submission->user->email));
    }
}
