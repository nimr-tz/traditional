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

    public function test_admin_can_export_abstracts_to_word(): void
    {
        $admin = User::factory()->admin()->create();
        $this->submission();

        $response = $this->actingAs($admin)->get(route('admin.abstracts.export'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
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
        $xml = $this->extractDocumentXml($response->streamedContent());
        $this->assertStringNotContainsString('Rejected One', $xml);
        $this->assertStringContainsString('Herbal Innovations', $xml);
    }

    public function test_reviewer_cannot_export_abstracts(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $this->submission();

        $response = $this->actingAs($reviewer)->get(route('admin.abstracts.export'));

        $response->assertForbidden();
    }

    /** Pull the readable text out of the generated .docx (a zip of OOXML parts) so assertions can check content. */
    private function extractDocumentXml(string $docxContents): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmpFile, $docxContents);

        $zip = new \ZipArchive;
        $zip->open($tmpFile);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($tmpFile);

        return $xml ?: '';
    }
}
