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

    public function test_rejected_student_can_submit_a_replacement_document(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'fee_category' => 'student_east_africa',
            'student_document_path' => 'student-verification/old-id.pdf',
            'student_verification_status' => 'pending',
        ]);
        Storage::disk('local')->put($student->student_document_path, 'old student id');

        $this->actingAs($admin)->post(route('admin.students.reject', $student), [
            'notes' => 'The document is not readable.',
        ])->assertRedirect();

        $student->refresh();
        $this->assertSame('rejected', $student->student_verification_status);
        Mail::assertQueued(StudentVerificationRejected::class, fn ($mail) => $mail->hasTo($student->email));

        $oldPath = $student->student_document_path;
        $this->actingAs($student)->post(route('student-verification.document'), [
            'student_document' => UploadedFile::fake()->image('replacement.png'),
        ])->assertRedirect();

        $student->refresh();

        $this->assertSame('pending', $student->student_verification_status);
        $this->assertNull($student->student_verification_notes);
        $this->assertNotSame($oldPath, $student->student_document_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($student->student_document_path);
        Mail::assertQueued(StudentVerificationSubmitted::class, fn ($mail) => $mail->isReplacement && $mail->hasTo($admin->email));
    }
}
