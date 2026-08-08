<?php

namespace Tests\Feature;

use App\Mail\StudentVerificationApproved;
use App\Mail\StudentVerificationRejected;
use App\Mail\StudentVerificationSubmitted;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_registration_requires_and_privately_stores_a_verification_document(): void
    {
        Storage::fake('local');
        Mail::fake();
        Notification::fake();
        $admin = User::factory()->admin()->create();

        FeeCategory::create([
            'key' => 'student_east_africa',
            'label' => 'East African Students',
            'amount' => 50000,
            'currency' => 'TZS',
            'active' => true,
        ]);

        $registration = [
            'salutation' => 'Ms.',
            'first_name' => 'Amina',
            'last_name' => 'Student',
            'email' => 'amina.student@example.com',
            'institution_id' => 'other',
            'institution_other' => 'Test University',
            'phone' => '+255700000000',
            'participant_type' => 'student',
            'country' => 'Tanzania',
            'fee_category' => 'student_east_africa',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->post('/register', $registration)->assertSessionHasErrors('student_document');

        $response = $this->post('/register', $registration + [
            'student_document' => UploadedFile::fake()->create('student-id.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'amina.student@example.com')->firstOrFail();

        $this->assertSame('pending', $user->student_verification_status);
        $this->assertSame('student', $user->participant_type);
        Storage::disk('local')->assertExists($user->student_document_path);
        Mail::assertQueued(StudentVerificationSubmitted::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_student_cannot_request_a_control_number_before_verification(): void
    {
        $student = User::factory()->create([
            'fee_category' => 'student_east_africa',
            'fee_amount' => 50000,
            'currency' => 'TZS',
            'student_document_path' => 'student-verification/id.pdf',
            'student_verification_status' => 'pending',
        ]);

        $response = $this->actingAs($student)->post(route('payment.control-number'));

        $response->assertSessionHas('error', 'Your student status must be verified before a control number can be issued.');
        $this->assertSame('pending', $student->fresh()->payment_status);
        $this->assertNull($student->fresh()->billing_request_id);
    }

    public function test_admin_can_review_private_document_and_verify_a_student(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'fee_category' => 'student_non_east_africa',
            'student_document_path' => 'student-verification/id.pdf',
            'student_verification_status' => 'pending',
        ]);
        Storage::disk('local')->put($student->student_document_path, 'student id');

        $this->actingAs($admin)
            ->get(route('admin.students.document', $student))
            ->assertOk();

        $this->actingAs($student)
            ->get(route('admin.students.document', $student))
            ->assertForbidden();

        $this->actingAs($admin)->post(route('admin.students.verify', $student), [
            'notes' => 'Document reviewed.',
        ])->assertRedirect();

        $student->refresh();

        $this->assertSame('verified', $student->student_verification_status);
        $this->assertSame($admin->id, $student->student_verified_by);
        $this->assertNotNull($student->student_verified_at);
        Mail::assertQueued(StudentVerificationApproved::class, fn ($mail) => $mail->hasTo($student->email));
    }

    public function test_admin_can_reopen_a_mistaken_verification_and_reject_it_properly(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'fee_category' => 'student_east_africa',
            'student_document_path' => 'student-verification/id.pdf',
            'student_verification_status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.students.verify', $student))->assertRedirect();
        $this->assertSame('verified', $student->fresh()->student_verification_status);

        $this->actingAs($admin)->post(route('admin.students.reopen', $student))->assertRedirect();

        $student->refresh();

        $this->assertSame('pending', $student->student_verification_status);
        $this->assertNull($student->student_verified_at);
        $this->assertNull($student->student_verified_by);
        $this->assertStringContainsString('previously verified', $student->student_verification_notes);

        // Reopening is silent; the follow-up decision is what the student hears about.
        Mail::assertNotQueued(StudentVerificationRejected::class);

        $this->seedParticipantCategory();

        $this->actingAs($admin)->post(route('admin.students.reject', $student), [
            'notes' => 'The student ID had expired.',
            'participant_type' => 'researcher',
        ])->assertRedirect();

        $student->refresh();

        $this->assertSame('rejected', $student->student_verification_status);
        $this->assertSame('The student ID had expired.', $student->student_verification_notes);
        Mail::assertQueued(StudentVerificationRejected::class, fn ($mail) => $mail->hasTo($student->email));
    }

    private function seedParticipantCategory(string $key = 'participant_east_africa', int $amount = 150000, string $currency = 'TZS'): FeeCategory
    {
        return FeeCategory::firstOrCreate(['key' => $key], [
            'label' => 'East African Participants',
            'amount' => $amount,
            'currency' => $currency,
            'active' => true,
        ]);
    }

    /**
     * The whole point of the change: the registrant is not left holding a rate
     * they were just refused, because nothing they can reach could fix it.
     */
    public function test_refusing_the_student_rate_moves_them_onto_the_participant_rate(): void
    {
        Mail::fake();
        $this->seedParticipantCategory();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'participant_type' => 'student',
            'fee_category' => 'student_east_africa',
            'fee_amount' => 50000,
            'currency' => 'TZS',
            'student_document_path' => 'student-verification/id.pdf',
            'student_verification_status' => 'pending',
            'payment_status' => 'submitted',
            'billing_request_id' => 'BILL-123',
            'control_number' => '995910073640',
        ]);

        $this->actingAs($admin)->post(route('admin.students.reject', $student), [
            'notes' => 'Doctoral candidates are not covered by the student rate.',
            'participant_type' => 'researcher',
        ])->assertRedirect();

        $student->refresh();

        $this->assertSame('participant_east_africa', $student->fee_category);
        $this->assertSame('researcher', $student->participant_type);
        $this->assertSame('rejected', $student->student_verification_status);

        // A control number already in their hands is honoured rather than
        // cancelled, and the fee stays equal to the bill they hold so no total
        // anywhere claims money that will never arrive.
        $this->assertSame('995910073640', $student->control_number);
        $this->assertSame('BILL-123', $student->billing_request_id);
        $this->assertSame('submitted', $student->payment_status);
        $this->assertSame('50000.00', $student->fee_amount);
        $this->assertSame('TZS', $student->currency);

        // They no longer need student verification, so payment is unblocked.
        $this->assertFalse($student->requiresStudentVerification());
        $this->assertTrue($student->hasVerifiedStudentStatus());

        Mail::assertQueued(StudentVerificationRejected::class, fn ($mail) => $mail->hasTo($student->email));
    }

    public function test_an_international_student_lands_on_the_international_participant_rate(): void
    {
        Mail::fake();
        $this->seedParticipantCategory('participant_non_east_africa', 150, 'USD');

        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'participant_type' => 'student',
            'fee_category' => 'student_non_east_africa',
            'student_document_path' => 'student-verification/id.pdf',
            'student_verification_status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.students.reject', $student), [
            'notes' => 'Not eligible.',
            'participant_type' => 'academic',
        ])->assertRedirect();

        $student->refresh();

        $this->assertSame('participant_non_east_africa', $student->fee_category);
        $this->assertSame('USD', $student->currency);
    }

    public function test_the_new_participant_type_cannot_be_student(): void
    {
        $this->seedParticipantCategory();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'fee_category' => 'student_east_africa',
            'student_document_path' => 'student-verification/id.pdf',
            'student_verification_status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.students.reject', $student), [
            'notes' => 'Not eligible.',
            'participant_type' => 'student',
        ])->assertSessionHasErrors('participant_type');

        $this->assertSame('pending', $student->fresh()->student_verification_status);
    }

    /**
     * Converting someone who has paid would leave them short with a valid badge
     * and overstate realised revenue by the difference.
     */
    public function test_a_paid_student_cannot_be_converted_without_finance_first(): void
    {
        Mail::fake();
        $this->seedParticipantCategory();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'fee_category' => 'student_east_africa',
            'fee_amount' => 50000,
            'currency' => 'TZS',
            'student_document_path' => 'student-verification/id.pdf',
            'student_verification_status' => 'pending',
            'payment_status' => 'verified',
        ]);

        $this->actingAs($admin)->post(route('admin.students.reject', $student), [
            'notes' => 'Not eligible.',
            'participant_type' => 'researcher',
        ])->assertStatus(422);

        $student->refresh();

        $this->assertSame('student_east_africa', $student->fee_category);
        $this->assertSame('pending', $student->student_verification_status);
        Mail::assertNothingQueued();
    }

    /** A converted registrant must stay auditable, not vanish from the queue. */
    public function test_a_converted_registrant_is_still_listed_under_student_verification(): void
    {
        Mail::fake();
        $this->seedParticipantCategory();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'name' => 'Converted Candidate',
            'fee_category' => 'student_east_africa',
            'student_document_path' => 'student-verification/id.pdf',
            'student_verification_status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.students.reject', $student), [
            'notes' => 'Not eligible.',
            'participant_type' => 'researcher',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.students.index', ['search' => 'Converted']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('students.data.0.id', $student->id)
                ->where('students.data.0.fee_category', 'participant_east_africa')
                ->where('students.data.0.student_verification_status', 'rejected')
                ->etc());
    }

    /**
     * A refusal moves them off the student category, so a guard keyed on the
     * current rate made refusals impossible to undo.
     */
    public function test_a_refusal_can_be_reopened_and_leaves_the_participant_rate_in_place(): void
    {
        Mail::fake();
        $this->seedParticipantCategory();
        $this->seedStudentCategory();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'participant_type' => 'student',
            'fee_category' => 'student_east_africa',
            'fee_amount' => 50000,
            'currency' => 'TZS',
            'student_document_path' => 'student-verification/id.pdf',
            'student_verification_status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.students.reject', $student), [
            'notes' => 'Not eligible.',
            'participant_type' => 'researcher',
        ])->assertRedirect();

        $this->assertSame('participant_east_africa', $student->fresh()->fee_category);

        $this->actingAs($admin)->post(route('admin.students.reopen', $student))->assertRedirect();

        $student->refresh();

        $this->assertSame('pending', $student->student_verification_status);
        // Reopening the review does not hand the student rate back — only
        // approval does that.
        $this->assertSame('participant_east_africa', $student->fee_category);
        $this->assertSame('150000.00', $student->fee_amount);
        $this->assertTrue($student->hasVerifiedStudentStatus());

        // And the decision can then be made either way.
        $this->actingAs($admin)->post(route('admin.students.verify', $student), [
            'notes' => 'Second look: valid ID.',
        ])->assertRedirect();

        $this->assertSame('student_east_africa', $student->fresh()->fee_category);
    }

    public function test_a_pending_student_review_cannot_be_reopened(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'fee_category' => 'student_east_africa',
            'student_document_path' => 'student-verification/id.pdf',
            'student_verification_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.students.reopen', $student))
            ->assertStatus(422);

        $this->assertSame('pending', $student->fresh()->student_verification_status);
    }

    public function test_reopening_blocks_a_new_student_rate_control_number(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'fee_category' => 'student_east_africa',
            'fee_amount' => 50000,
            'currency' => 'TZS',
            'student_document_path' => 'student-verification/id.pdf',
            'student_verification_status' => 'verified',
            'student_verified_at' => now(),
            'student_verified_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.students.reopen', $student))->assertRedirect();

        $this->actingAs($student->fresh())
            ->post(route('payment.control-number'))
            ->assertSessionHas('error', 'Your student status must be verified before a control number can be issued.');

        $this->assertNull($student->fresh()->billing_request_id);
    }

    private function seedStudentCategory(): FeeCategory
    {
        return FeeCategory::firstOrCreate(['key' => 'student_east_africa'], [
            'label' => 'East African Students',
            'amount' => 50000,
            'currency' => 'TZS',
            'active' => true,
        ]);
    }

    /**
     * The full cycle. The rule being pinned is that the student rate is granted
     * by approval and never by asking: a refused registrant re-applies while
     * staying on the participant rate they are free to pay, and only an
     * approving reviewer moves them back down.
     */
    public function test_a_refused_registrant_re_applies_while_staying_on_the_participant_rate(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->seedParticipantCategory();
        $this->seedStudentCategory();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'country' => 'Tanzania',
            'participant_type' => 'student',
            'fee_category' => 'student_east_africa',
            'fee_amount' => 50000,
            'currency' => 'TZS',
            'student_document_path' => 'student-verification/old-id.pdf',
            'student_verification_status' => 'pending',
        ]);
        Storage::disk('local')->put($student->student_document_path, 'old student id');

        $this->actingAs($admin)->post(route('admin.students.reject', $student), [
            'notes' => 'Doctoral candidates are not covered by the student rate.',
            'participant_type' => 'researcher',
        ])->assertRedirect();

        $student->refresh();
        $this->assertSame('participant_east_africa', $student->fee_category);
        $this->assertSame('150000.00', $student->fee_amount);
        Mail::assertQueued(StudentVerificationRejected::class, fn ($mail) => $mail->hasTo($student->email));

        // They cannot grant themselves the rate back by reselecting it.
        $this->actingAs($student)
            ->patch(route('registration.update'), [
                'participant_type' => 'student',
                'fee_category' => 'student_east_africa',
            ])
            ->assertSessionHas('error');

        $this->assertSame('participant_east_africa', $student->fresh()->fee_category);

        // Uploading a new document puts them back in the queue but must not
        // touch the rate they are currently able to pay.
        $oldPath = $student->student_document_path;
        $this->actingAs($student)->post(route('student-verification.document'), [
            'student_document' => UploadedFile::fake()->image('new-id.png'),
        ])->assertRedirect();

        $student->refresh();

        $this->assertSame('pending', $student->student_verification_status);
        $this->assertSame('participant_east_africa', $student->fee_category);
        $this->assertSame('150000.00', $student->fee_amount);
        $this->assertNotSame($oldPath, $student->student_document_path);
        Mail::assertQueued(StudentVerificationSubmitted::class, fn ($mail) => $mail->hasTo($admin->email));

        // And they are free to pay the participant rate while waiting.
        $this->assertTrue($student->hasVerifiedStudentStatus());

        // The reviewer can still open the new document even though the registrant
        // is no longer on a student category.
        $this->actingAs($admin)->get(route('admin.students.document', $student))->assertOk();

        // Approval is what finally grants the student rate.
        $this->actingAs($admin)->post(route('admin.students.verify', $student), [
            'notes' => 'Valid full-time student ID.',
        ])->assertRedirect();

        $student->refresh();

        $this->assertSame('verified', $student->student_verification_status);
        $this->assertSame('student_east_africa', $student->fee_category);
        $this->assertSame('50000.00', $student->fee_amount);
        $this->assertSame('student', $student->participant_type);
        Mail::assertQueued(StudentVerificationApproved::class, fn ($mail) => $mail->hasTo($student->email));
    }

    public function test_refusing_a_re_application_leaves_the_participant_rate_untouched(): void
    {
        Mail::fake();
        $this->seedParticipantCategory();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'participant_type' => 'researcher',
            'fee_category' => 'participant_east_africa',
            'fee_amount' => 150000,
            'currency' => 'TZS',
            'student_document_path' => 'student-verification/new-id.pdf',
            'student_verification_status' => 'pending',
            'payment_status' => 'submitted',
            'control_number' => '995910073999',
        ]);

        $this->actingAs($admin)->post(route('admin.students.reject', $student), [
            'notes' => 'Still not eligible.',
            'participant_type' => 'researcher',
        ])->assertRedirect();

        $student->refresh();

        $this->assertSame('rejected', $student->student_verification_status);
        $this->assertSame('participant_east_africa', $student->fee_category);
        // Their participant bill was correct all along, so it must survive.
        $this->assertSame('995910073999', $student->control_number);
        $this->assertSame('submitted', $student->payment_status);
    }

    /** Granting the student rate to someone who paid the participant rate is a refund, not a click. */
    public function test_a_paid_participant_cannot_be_granted_the_student_rate(): void
    {
        Mail::fake();
        $this->seedParticipantCategory();
        $this->seedStudentCategory();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'fee_category' => 'participant_east_africa',
            'fee_amount' => 150000,
            'currency' => 'TZS',
            'student_document_path' => 'student-verification/new-id.pdf',
            'student_verification_status' => 'pending',
            'payment_status' => 'verified',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.students.verify', $student), ['notes' => 'Looks fine.'])
            ->assertStatus(422);

        $student->refresh();

        $this->assertSame('participant_east_africa', $student->fee_category);
        $this->assertSame('pending', $student->student_verification_status);
        Mail::assertNothingQueued();
    }
}
