<?php

namespace Tests\Feature;

use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbstractExportTest extends TestCase
{
    use RefreshDatabase;

    private function submission(array $overrides = []): AbstractSubmission
    {
        $subtheme = Subtheme::firstOrCreate(
            ['title' => $overrides['subtheme_title'] ?? 'Innovations'],
            ['active' => true, 'sort_order' => 1],
        );

        return AbstractSubmission::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'Herbal Innovations',
            'authors' => [
                ['name' => 'A. Presenter', 'institution' => 'NIMR', 'is_presenter' => true],
                ['name' => 'B. Coauthor', 'institution' => 'MUHAS', 'is_presenter' => false],
            ],
            'background' => 'Background.',
            'objective' => 'Objective.',
            'methods' => 'Methods.',
            'results' => 'Results.',
            'conclusion' => 'Conclusion.',
            'presentation_type' => 'poster',
            'status' => 'accepted',
        ], array_diff_key($overrides, ['subtheme_title' => null])));
    }

    public function test_admin_can_export_abstracts_to_pdf(): void
    {
        $admin = User::factory()->admin()->create();
        $this->submission();

        $response = $this->actingAs($admin)->get(route('admin.abstracts.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_respects_status_and_subtheme_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $this->submission(['status' => 'accepted', 'subtheme_title' => 'Innovations']);
        $rejected = $this->submission(['status' => 'rejected', 'subtheme_title' => 'Traditions', 'title' => 'Rejected One']);

        $response = $this->actingAs($admin)->get(route('admin.abstracts.export', [
            'status' => 'accepted',
            'subtheme_id' => AbstractSubmission::where('title', 'Herbal Innovations')->first()->subtheme_id,
        ]));

        $response->assertOk();
        $text = $this->extractPdfText($response->getContent());
        $this->assertStringNotContainsString('Rejected One', $text);
        $this->assertStringContainsString('Herbal Innovations', $text);
    }

    public function test_reviewer_cannot_export_abstracts(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $this->submission();

        $response = $this->actingAs($reviewer)->get(route('admin.abstracts.export'));

        $response->assertForbidden();
    }

    /**
     * Pull the literal text out of a generated PDF so assertions can check
     * content. The view renders in Helvetica, a core PDF font, so text stays
     * as plain WinAnsi bytes in the content stream rather than being remapped
     * through an embedded font's glyph indices — only the stream itself is
     * Flate-compressed, hence the inflate step.
     */
    private function extractPdfText(string $pdfContents): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdfContents, $matches);

        $text = '';
        foreach ($matches[1] as $stream) {
            $inflated = @gzuncompress($stream) ?: @gzinflate($stream);
            if ($inflated !== false) {
                $text .= $inflated;
            }
        }

        return $text;
    }
}
