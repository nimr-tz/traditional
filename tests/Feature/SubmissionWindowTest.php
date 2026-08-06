<?php

namespace Tests\Feature;

use App\Models\AbstractSubmission;
use App\Models\ConferenceSetting;
use App\Models\Subtheme;
use App\Models\User;
use App\Support\SubmissionWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubmissionWindowTest extends TestCase
{
    use RefreshDatabase;

    private function subtheme(): Subtheme
    {
        return Subtheme::create([
            'title' => 'Traditional medicine in primary care',
            'active' => true,
            'sort_order' => 1,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Subtheme $subtheme): array
    {
        return [
            'title' => 'A late abstract',
            'subtheme_id' => $subtheme->id,
            'presentation_type' => array_key_first(config('tmsc.presentation_types')),
            'authors' => [['name' => 'A. Author', 'institution' => 'NIMR', 'is_presenter' => true]],
            'background' => 'Background text.',
            'objective' => 'Objective text.',
            'methods' => 'Methods text.',
            'results' => 'Results text.',
            'conclusion' => 'Conclusion text.',
        ];
    }

    public function test_abstracts_can_be_submitted_up_to_the_end_of_the_deadline_day(): void
    {
        Mail::fake();
        ConferenceSetting::set('submission_deadline', '2026-08-13');
        $this->travelTo('2026-08-13 23:30:00');

        $subtheme = $this->subtheme();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('abstracts.store'), $this->payload($subtheme))
            ->assertRedirect(route('abstracts.index'));

        $this->assertSame(1, $user->abstractSubmissions()->count());
    }

    public function test_new_abstracts_are_blocked_once_the_deadline_has_passed(): void
    {
        ConferenceSetting::set('submission_deadline', '2026-08-13');
        $this->travelTo('2026-08-14 00:30:00');

        $subtheme = $this->subtheme();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('abstracts.create'))
            ->assertRedirect(route('abstracts.index'));

        $this->actingAs($user)
            ->post(route('abstracts.store'), $this->payload($subtheme))
            ->assertRedirect(route('abstracts.index'))
            ->assertSessionHas('error', 'The deadline for abstract submissions passed on 13 August 2026.');

        $this->assertSame(0, $user->abstractSubmissions()->count());
    }

    /**
     * The whole point of the carve-out: reviewers ask for revisions after the
     * call closes, so an author must still be able to answer them.
     */
    public function test_a_revision_can_still_be_resubmitted_after_the_deadline(): void
    {
        Mail::fake();
        ConferenceSetting::set('submission_deadline', '2026-08-13');
        $this->travelTo('2026-08-20 09:00:00');

        $subtheme = $this->subtheme();
        $user = User::factory()->create();

        $abstract = AbstractSubmission::create([
            'user_id' => $user->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'An abstract under review',
            'presentation_type' => array_key_first(config('tmsc.presentation_types')),
            'authors' => [['name' => 'A. Author', 'institution' => 'NIMR', 'is_presenter' => true]],
            'background' => 'Background text.',
            'objective' => 'Objective text.',
            'methods' => 'Methods text.',
            'results' => 'Results text.',
            'conclusion' => 'Conclusion text.',
            'status' => 'revision_requested',
        ]);

        $this->actingAs($user)->get(route('abstracts.edit', $abstract))->assertOk();

        $this->actingAs($user)
            ->put(route('abstracts.update', $abstract), array_merge($this->payload($subtheme), [
                'title' => 'A revised abstract',
            ]))
            ->assertRedirect(route('abstracts.index'));

        $abstract->refresh();

        $this->assertSame('A revised abstract', $abstract->title);
        $this->assertSame('submitted', $abstract->status);
    }

    public function test_an_unset_or_garbled_deadline_leaves_the_call_open(): void
    {
        ConferenceSetting::set('submission_deadline', null);
        $this->assertTrue(SubmissionWindow::isOpen());
        $this->assertNull(SubmissionWindow::deadline());

        ConferenceSetting::set('submission_deadline', 'not a date at all');
        $this->assertTrue(SubmissionWindow::isOpen());
    }

    public function test_a_human_entered_deadline_is_still_understood(): void
    {
        ConferenceSetting::set('submission_deadline', '13 August 2026');
        $this->travelTo('2026-08-14 00:30:00');

        $this->assertTrue(SubmissionWindow::hasClosed());
        $this->assertSame('2026-08-13', SubmissionWindow::deadline()?->toDateString());
    }
}
