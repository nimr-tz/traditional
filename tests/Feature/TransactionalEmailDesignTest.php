<?php

namespace Tests\Feature;

use App\Mail\AbstractDecision;
use App\Mail\AbstractSubmitted;
use App\Mail\PaymentConfirmed;
use App\Mail\RegistrationConfirmed;
use App\Models\AbstractSubmission;
use App\Models\FeeCategory;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionalEmailDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_payment_emails_use_the_shared_conference_design(): void
    {
        FeeCategory::create([
            'key' => 'participant_east_africa',
            'label' => 'East African Participants',
            'amount' => 250000,
            'currency' => 'TZS',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Test Participant',
            'salutation' => 'Dr.',
            'participant_type' => 'practitioner',
            'fee_category' => 'participant_east_africa',
            'currency' => 'TZS',
            'fee_amount' => 250000,
        ]);

        $registration = new RegistrationConfirmed($user);
        $payment = new PaymentConfirmed($user);

        foreach ([$registration, $payment] as $mail) {
            $html = (string) $mail->render();

            $this->assertStringContainsString('alt="NIMR"', $html);
            $this->assertStringContainsString('Dear Dr. Test Participant', $html);
            $this->assertStringContainsString('#135eeb', $html);
        }

        $this->assertStringContainsString('Registration category', (string) $registration->render());
        $this->assertStringContainsString('East African Participants', (string) $registration->render());
        $this->assertStringContainsString('TZS 250,000.00', (string) $payment->render());
    }

    public function test_abstract_emails_use_the_shared_conference_design(): void
    {
        $user = User::factory()->create([
            'name' => 'Abstract Author',
            'salutation' => 'Prof.',
        ]);
        $subtheme = Subtheme::create([
            'title' => 'Innovations',
            'active' => true,
            'sort_order' => 1,
        ]);
        $submission = AbstractSubmission::create([
            'user_id' => $user->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'Herbal Innovations',
            'authors' => [['name' => 'Abstract Author', 'is_presenter' => true]],
            'abstract_text' => 'A short abstract.',
            'presentation_type' => 'oral',
            'status' => 'accepted',
            'decision_notes' => 'Great work.',
        ]);

        foreach ([new AbstractSubmitted($submission), new AbstractDecision($submission)] as $mail) {
            $html = (string) $mail->render();

            $this->assertStringContainsString('alt="NIMR"', $html);
            $this->assertStringContainsString('Herbal Innovations', $html);
            $this->assertStringContainsString('Dear Prof. Abstract Author', $html);
        }
    }
}
