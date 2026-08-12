<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo registrants covering every state the venue desk has to deal with.
 *
 * Deliberately **not** called from TmscSeeder::run() — this is fixture data for
 * exercising the check-in desk, and seeding invented attendees into a real
 * conference database would corrupt every headcount and revenue total. Run it
 * explicitly:
 *
 *     php artisan db:seed --class=VenueDeskDemoSeeder
 *
 * Idempotent: re-running updates the same people rather than piling up
 * duplicates, so it is safe to use repeatedly while working on the desk.
 */
class VenueDeskDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureFeeCategories();

        $staff = User::where('email', 'staff@tmsc.nimr.or.tz')->first()
            ?? User::withRole(User::ROLE_STAFF)->first();

        foreach ($this->people() as $person) {
            $this->seedPerson($person, $staff);
        }

        $this->command?->info('Venue desk demo data seeded.');
    }

    /**
     * @param  array<string, mixed>  $person
     */
    private function seedPerson(array $person, ?User $staff): void
    {
        // Matched on name because some of these deliberately have no email —
        // a walk-in taken at the desk on phone alone is one of the states the
        // page has to render.
        $user = User::where('name', $person['name'])->first() ?? new User;

        $user->fill([
            'name' => $person['name'],
            'email' => $person['email'] ?? null,
            'phone' => $person['phone'],
            'institution' => $person['institution'],
            'country' => $person['country'],
            'participant_type' => $person['participant_type'],
            'is_east_africa' => $person['country'] === 'Tanzania' || $person['country'] === 'Kenya',
        ]);

        if (! $user->exists) {
            $user->password = Hash::make('password');
        }

        $user->email_verified_at = now();
        $user->save();

        $user->assignFeeCategory($person['fee_category']);

        $user->forceFill([
            'payment_status' => $person['payment_status'],
            'control_number' => $person['control_number'] ?? null,
            'billing_request_id' => isset($person['control_number']) ? 'DEMO-'.$user->id : null,
            'paid_at' => in_array($person['payment_status'], ['verified', 'waived'], true) ? now()->subHours(6) : null,
            'payment_notes' => $person['payment_notes'] ?? null,
            'student_verification_status' => $person['student_status'] ?? null,
            'student_document_path' => isset($person['student_status']) ? 'student-verification/demo.pdf' : null,
        ]);

        // A badge only exists once the money is settled — generateRegistrationCode
        // enforces that, so this branch mirrors the real rule rather than
        // sidestepping it.
        if ($user->isPaid() && ! $user->registration_code) {
            $user->generateRegistrationCode();
        }

        $user->save();

        $user->attendance()->delete();

        if (! $user->isPaid()) {
            return;
        }

        if (isset($person['arrived_minutes_ago'])) {
            Attendance::create([
                'user_id' => $user->id,
                'checked_in_at' => now()->subMinutes($person['arrived_minutes_ago']),
                'checked_in_by' => $staff?->id,
            ]);
        }

        // Earlier days, so the desk shows returning attendees — the case that
        // was impossible when attendance was one record for the whole event.
        foreach ($person['also_attended_days_ago'] ?? [] as $daysAgo) {
            Attendance::create([
                'user_id' => $user->id,
                'checked_in_at' => now()->subDays($daysAgo)->setTime(9, random_int(0, 55)),
                'checked_in_by' => $staff?->id,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function people(): array
    {
        return [
            // --- Already inside -------------------------------------------------
            [
                'name' => 'Asha Nyerere', 'email' => 'asha.nyerere@example.com', 'phone' => '+255 712 100 001',
                'institution' => 'Muhimbili University of Health and Allied Sciences', 'country' => 'Tanzania',
                'participant_type' => 'researcher', 'fee_category' => 'participant_east_africa',
                'payment_status' => 'verified', 'control_number' => '995910070001', 'arrived_minutes_ago' => 95,
                'also_attended_days_ago' => [1, 2],
            ],
            [
                'name' => 'Baraka Mwakasege', 'email' => 'baraka.m@example.com', 'phone' => '+255 712 100 002',
                'institution' => 'Sokoine University of Agriculture', 'country' => 'Tanzania',
                'participant_type' => 'academic', 'fee_category' => 'participant_east_africa',
                'payment_status' => 'verified', 'control_number' => '995910070002', 'arrived_minutes_ago' => 38,
            ],
            [
                'name' => 'Sarah Whitfield', 'email' => 's.whitfield@example.org', 'phone' => '+44 7700 900123',
                'institution' => 'London School of Hygiene and Tropical Medicine', 'country' => 'United Kingdom',
                'participant_type' => 'researcher', 'fee_category' => 'participant_non_east_africa',
                'payment_status' => 'verified', 'control_number' => '995910070003', 'arrived_minutes_ago' => 12,
            ],
            [
                'name' => 'Fatuma Ally', 'email' => 'fatuma.ally@example.com', 'phone' => '+255 712 100 004',
                'institution' => 'Ministry of Health', 'country' => 'Tanzania',
                'participant_type' => 'policy_maker', 'fee_category' => 'participant_east_africa',
                'payment_status' => 'waived', 'payment_notes' => 'Invited keynote speaker — fee waived.',
                'arrived_minutes_ago' => 4,
            ],
            [
                'name' => 'Daniel Kimaro', 'email' => 'daniel.kimaro@example.com', 'phone' => '+255 712 100 005',
                'institution' => 'University of Dodoma', 'country' => 'Tanzania',
                'participant_type' => 'student', 'fee_category' => 'student_east_africa',
                'payment_status' => 'verified', 'control_number' => '995910070005',
                'student_status' => 'verified', 'arrived_minutes_ago' => 61,
                'also_attended_days_ago' => [1],
            ],

            // --- Paid, badge in hand, not yet through the door -------------------
            [
                'name' => 'Neema Kileo', 'email' => 'neema.kileo@example.com', 'phone' => '+255 712 100 006',
                'institution' => 'National Institute for Medical Research (NIMR)', 'country' => 'Tanzania',
                'participant_type' => 'researcher', 'fee_category' => 'participant_east_africa',
                'payment_status' => 'verified', 'control_number' => '995910070006',
                // Came the last two days but not yet today: "Returning — not yet today".
                'also_attended_days_ago' => [1, 2],
            ],
            [
                'name' => 'Joseph Mkapa', 'email' => 'joseph.mkapa@example.com', 'phone' => '+255 712 100 007',
                'institution' => 'Kilimanjaro Christian Medical Centre', 'country' => 'Tanzania',
                'participant_type' => 'practitioner', 'fee_category' => 'participant_east_africa',
                'payment_status' => 'verified', 'control_number' => '995910070007',
            ],
            [
                'name' => 'Emmanuel Shirima', 'email' => 'e.shirima@example.com', 'phone' => '+255 712 100 008',
                'institution' => 'Traditional Healers Association', 'country' => 'Tanzania',
                'participant_type' => 'practitioner', 'fee_category' => 'participant_east_africa',
                'payment_status' => 'waived', 'payment_notes' => 'Community practitioner sponsorship.',
            ],

            // --- Owe money: control number out, waiting on payment ---------------
            [
                'name' => 'Grace Lyimo', 'email' => 'grace.lyimo@example.com', 'phone' => '+255 712 100 009',
                'institution' => 'Muhimbili National Hospital', 'country' => 'Tanzania',
                'participant_type' => 'practitioner', 'fee_category' => 'participant_east_africa',
                'payment_status' => 'submitted', 'control_number' => '995910070009',
            ],
            [
                'name' => 'Anders Holm', 'email' => 'anders.holm@example.org', 'phone' => '+47 900 12 345',
                'institution' => 'University of Oslo', 'country' => 'Norway',
                'participant_type' => 'academic', 'fee_category' => 'participant_non_east_africa',
                'payment_status' => 'submitted', 'control_number' => '995910070010',
            ],
            [
                'name' => 'Upendo Massawe', 'email' => 'upendo.massawe@example.com', 'phone' => '+255 712 100 011',
                'institution' => 'Government Chemist Laboratory Authority', 'country' => 'Tanzania',
                'participant_type' => 'researcher', 'fee_category' => 'participant_east_africa',
                'payment_status' => 'submitted', 'control_number' => '995910070011',
                'student_status' => 'rejected',
                'payment_notes' => 'Student rate refused; moved to the standard participant rate.',
            ],

            // --- Nothing started yet ---------------------------------------------
            [
                'name' => 'Rehema Mtei', 'email' => 'rehema.mtei@example.com', 'phone' => '+255 712 100 012',
                'institution' => 'Mbeya Zonal Referral Hospital', 'country' => 'Tanzania',
                'participant_type' => 'practitioner', 'fee_category' => 'participant_east_africa',
                'payment_status' => 'pending',
            ],
            [
                'name' => 'Zawadi Mushi', 'email' => 'zawadi.mushi@example.com', 'phone' => '+255 712 100 013',
                'institution' => 'University of Dar es Salaam', 'country' => 'Tanzania',
                'participant_type' => 'student', 'fee_category' => 'student_east_africa',
                'payment_status' => 'pending', 'student_status' => 'pending',
            ],

            // --- Attending free by role -------------------------------------------
            [
                'name' => 'Juma Kanyabwoya', 'email' => 'juma.k@example.com', 'phone' => '+255 712 100 016',
                'institution' => 'The Citizen', 'country' => 'Tanzania',
                'participant_type' => 'media', 'fee_category' => 'complimentary_media',
                'payment_status' => 'waived', 'payment_notes' => 'Complimentary registration: Media. No fee due.',
                'arrived_minutes_ago' => 130,
            ],
            [
                'name' => 'Editha Mwakibolwa', 'email' => 'editha.m@example.com', 'phone' => '+255 712 100 017',
                'institution' => 'NIMR Conference Secretariat', 'country' => 'Tanzania',
                'participant_type' => 'decision_maker', 'fee_category' => 'complimentary_secretariat',
                'payment_status' => 'waived', 'payment_notes' => 'Complimentary registration: Secretariat. No fee due.',
                'arrived_minutes_ago' => 210, 'also_attended_days_ago' => [1, 2],
            ],

            // --- Taken at the desk on a phone number alone ------------------------
            [
                'name' => 'Mzee Salehe Ramadhani', 'email' => null, 'phone' => '+255 712 100 014',
                'institution' => 'Bagamoyo Traditional Medicine Group', 'country' => 'Tanzania',
                'participant_type' => 'practitioner', 'fee_category' => 'participant_east_africa',
                'payment_status' => 'submitted', 'control_number' => '995910070014',
                'payment_notes' => 'Registered at the venue desk.',
            ],
            [
                'name' => 'Bibi Halima Mwinyi', 'email' => null, 'phone' => '+255 712 100 015',
                'institution' => 'Zanzibar Herbalists Cooperative', 'country' => 'Tanzania',
                'participant_type' => 'practitioner', 'fee_category' => 'participant_east_africa',
                'payment_status' => 'verified', 'control_number' => '995910070015',
                'payment_notes' => 'Registered and settled at the venue desk.', 'arrived_minutes_ago' => 22,
            ],
        ];
    }

    private function ensureFeeCategories(): void
    {
        $defaults = [
            'participant_east_africa' => ['East African Participants', 150000, 'TZS', 1, false],
            'participant_non_east_africa' => ['Non-East African Participants', 150, 'USD', 2, false],
            'student_east_africa' => ['East African Students', 50000, 'TZS', 3, false],
            'student_non_east_africa' => ['Non-East African Students', 50, 'USD', 4, false],
            'complimentary_media' => ['Media', 0, 'TZS', 90, true],
            'complimentary_secretariat' => ['Secretariat', 0, 'TZS', 91, true],
        ];

        foreach ($defaults as $key => [$label, $amount, $currency, $sort, $complimentary]) {
            FeeCategory::firstOrCreate(['key' => $key], [
                'label' => $label,
                'amount' => $amount,
                'currency' => $currency,
                'active' => true,
                'sort_order' => $sort,
                'is_complimentary' => $complimentary,
            ]);
        }
    }
}
