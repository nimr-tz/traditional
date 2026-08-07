<?php

namespace Tests\Feature;

use App\Mail\PresentationUploaded;
use App\Models\AbstractSubmission;
use App\Models\ConferenceSetting;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
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

        $admin = User::factory()->admin()->create();
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
        // Nobody reviews a presentation, so no organizer is notified.
        Mail::assertNotQueued(PresentationUploaded::class, fn ($mail) => $mail->hasTo($admin->email));
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

        $admin = User::factory()->admin()->create();
        $stranger = User::factory()->create();
        $submission = $this->acceptedSubmission();

        $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('slides.pdf', 100),
        ]);

        $this->actingAs($stranger)->get(route('abstracts.presentation.download', $submission))->assertForbidden();
        $this->actingAs($submission->user)->get(route('abstracts.presentation.download', $submission))->assertOk();
        $this->actingAs($admin)->get(route('abstracts.presentation.download', $submission))->assertOk();
    }

    /**
     * Presentations aren't reviewed. Whatever the presenter has on file when
     * the window closes is what gets presented, so the only thing that ends
     * their ability to change it is the deadline.
     */
    public function test_a_presenter_can_keep_replacing_their_file_while_the_window_is_open(): void
    {
        Storage::fake('local');
        Mail::fake();

        ConferenceSetting::set('presentation_deadline', now()->addWeek()->toDateString());
        $submission = $this->acceptedSubmission();

        foreach (['first.pdf', 'second.pdf', 'third.pdf'] as $name) {
            $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
                'presentation_file' => UploadedFile::fake()->create($name, 100),
            ])->assertRedirect();
        }

        $submission->refresh();
        $this->assertSame('uploaded', $submission->presentation_status);
        $this->assertSame('third.pdf', $submission->presentation_original_name);
        $this->assertTrue($submission->canUploadPresentation());
    }

    public function test_uploads_close_once_the_deadline_has_passed(): void
    {
        Storage::fake('local');
        Mail::fake();

        ConferenceSetting::set('presentation_deadline', now()->addWeek()->toDateString());
        $submission = $this->acceptedSubmission();

        $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('slides.pdf', 100),
        ])->assertRedirect();

        ConferenceSetting::set('presentation_deadline', now()->subDay()->toDateString());

        $this->assertFalse($submission->refresh()->canUploadPresentation());

        $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('late.pdf', 100),
        ])->assertStatus(422);

        $this->actingAs($submission->user)
            ->delete(route('abstracts.presentation.destroy', $submission))
            ->assertStatus(422);

        // The file uploaded before the deadline stands.
        $this->assertSame('slides.pdf', $submission->refresh()->presentation_original_name);
    }

    /** The presenter is told; nobody is asked to review it. */
    public function test_uploading_notifies_only_the_presenter(): void
    {
        Storage::fake('local');
        Mail::fake();

        User::factory()->reviewer()->create();
        User::factory()->admin()->create();
        $submission = $this->acceptedSubmission();

        $this->actingAs($submission->user)->post(route('abstracts.presentation.store', $submission), [
            'presentation_file' => UploadedFile::fake()->create('slides.pdf', 100),
        ])->assertRedirect();

        Mail::assertQueued(PresentationUploaded::class, 1);
        Mail::assertQueued(PresentationUploaded::class, fn ($mail) => $mail->hasTo($submission->user->email));
    }

    /** The approve/reject endpoints are gone entirely, not merely hidden. */
    public function test_there_is_no_presentation_review_endpoint(): void
    {
        $this->assertFalse(Route::has('admin.abstracts.presentation.approve'));
        $this->assertFalse(Route::has('admin.abstracts.presentation.reject'));
    }

    public function test_an_open_deadline_still_requires_the_abstract_to_be_accepted(): void
    {
        ConferenceSetting::set('presentation_deadline', now()->addWeek()->toDateString());
        $submission = $this->acceptedSubmission();
        $submission->forceFill(['status' => 'submitted'])->save();

        $this->assertFalse($submission->refresh()->canUploadPresentation());
    }
}
