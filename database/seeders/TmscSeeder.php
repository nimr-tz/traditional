<?php

namespace Database\Seeders;

use App\Models\AbstractSubmission;
use App\Models\ConferenceSetting;
use App\Models\FeeCategory;
use App\Models\Institution;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Database\Seeder;

class TmscSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFeeCategories();
        $this->seedSubthemes();
        $this->seedInstitutions();
        $this->seedConferenceSettings();
        $this->seedUsers();
        $this->seedAbstracts();
    }

    /**
     * Official fee table from the 5th TMSC (2026, Mbeya) call for abstracts.
     * No separate practitioner tier — everyone pays the standard rate for
     * their region, except students. Non-East-Africa tiers are billed in
     * USD per the official table.
     */
    public function seedFeeCategories(): void
    {
        $categories = [
            'participant_east_africa' => ['label' => 'East African Participants', 'amount' => 150000, 'currency' => 'TZS', 'sort_order' => 1],
            'participant_non_east_africa' => ['label' => 'Non-East African Participants', 'amount' => 150, 'currency' => 'USD', 'sort_order' => 2],
            'student_east_africa' => ['label' => 'East African Students', 'amount' => 50000, 'currency' => 'TZS', 'sort_order' => 3],
            'student_non_east_africa' => ['label' => 'Non-East African Students', 'amount' => 50, 'currency' => 'USD', 'sort_order' => 4],
        ];

        foreach ($categories as $key => $category) {
            FeeCategory::updateOrCreate(['key' => $key], $category + ['active' => true]);
        }

        // No practitioner-specific tier in the current fee table.
        FeeCategory::where('key', 'practitioner')->delete();
    }

    /**
     * Sub-themes from the 5th TMSC (2026, Mbeya) call for abstracts.
     * Descriptions hold the official sub-bullet points under each theme.
     */
    public function seedSubthemes(): void
    {
        $subthemes = [
            [
                'title' => 'Integration of Traditional Medicine and Conventional Healthcare System',
                'description' => "Role of TM in Universal Health Coverage (UHC)\nCollaborations of traditional health practitioners and biomedical health care practitioners in delivering care\nRole of education in advancing safe and evidence based TCIM",
            ],
            [
                'title' => 'Policy, Regulation, and Governance of Traditional Medicine',
                'description' => 'Regulatory frameworks of traditional medicine research',
            ],
            [
                'title' => 'Traditional Medicine for Priority Health Conditions',
                'description' => 'TM for communicable diseases, NCD, Maternal and reproductive conditions, zoonosis, vector borne, Health system policy implications.',
            ],
            [
                'title' => 'Technology and Innovations in Traditional Medicine',
                'description' => "AI, machine learning, and bioinformatics for herbal drug discovery\nDigital documentation and TM informatics\nUse of ICTs for TM access, and monitoring\nCommercialization and Marketing TCIM",
            ],
            [
                'title' => 'Conservation and Sustainable Use of Medicinal Plants',
                'description' => "Biodiversity preservation and sustainable harvesting\nCommunity-led conservation practices\nCultivation of threatened medicinal plant species\nClimatic change and TCIM",
            ],
        ];

        foreach ($subthemes as $index => $subtheme) {
            Subtheme::updateOrCreate(
                ['title' => $subtheme['title']],
                ['description' => $subtheme['description'], 'active' => true, 'sort_order' => $index + 1]
            );
        }

        // Retire prior years' sub-themes that aren't part of the current call.
        Subtheme::whereNotIn('title', array_column($subthemes, 'title'))->update(['active' => false]);
    }

    /**
     * A starting list of institutions commonly represented at TMSC, so
     * registrants pick a consistent name instead of typing free text.
     * Scoped to health/medical-focused institutions (per organizer decision)
     * plus general research universities relevant to traditional medicine,
     * Tanzania-only. University names are the exact official names from the
     * TCU "University Institutions Approved to operate in Tanzania" list
     * (as of March 2026) — not invented. Not exhaustive — organizers add
     * more via Conference Settings as they see unlisted institutions come
     * in via "Other".
     */
    public function seedInstitutions(): void
    {
        $institutions = [
            // NIMR — a single institute, not its regional research centres.
            'National Institute for Medical Research (NIMR)',
            'Ministry of Health (Tanzania)',
            'Tanzania Medicines and Medical Devices Authority (TMDA)',
            'Traditional and Alternative Medicine Practitioners Council',

            // Health/medical-focused universities and colleges (TCU list).
            'Muhimbili University of Health and Allied Sciences (MUHAS)',
            'Catholic University of Health and Allied Sciences (CUHAS)',
            'KCMC University',
            'Aga Khan University (AKU)',
            'University of Medical Sciences and Technology (UMST)',
            'Mbeya College of Health and Allied Sciences (MCHAS)',
            'St. Francis University College of Health and Allied Sciences (SFUCHAS)',
            'St. Joseph University College of Health and Allied Sciences (SJCHAS)',

            // General research universities relevant to traditional medicine.
            'University of Dar es Salaam (UDSM)',
            'Sokoine University of Agriculture (SUA)',
            'University of Dodoma (UDOM)',
            'Nelson Mandela African Institution of Science and Technology (NM-AIST)',
        ];

        foreach ($institutions as $index => $name) {
            Institution::updateOrCreate(
                ['name' => $name],
                ['active' => true, 'sort_order' => $index + 1]
            );
        }
    }

    /**
     * From the official "Advert for Call for Abstract" — 5th TMSC, 2026, Mbeya.
     */
    public function seedConferenceSettings(): void
    {
        $defaults = [
            'conference_name' => 'Traditional Medicine Scientific Conference and Exhibitions',
            'edition_number' => '5th',
            'conference_year' => '2026',
            'theme' => 'Accelerating evidence informed Traditional, Complementary and Integrative Medicine in formal Healthcare System: the role of multisectoral collaboration',
            'venue' => 'Mbeya, Tanzania',
            'start_date' => '2026-08-26',
            'end_date' => '2026-08-26',
            'submission_deadline' => '2026-08-06',
            'abstract_notification_date' => '2026-08-08',
            'tm_week_dates' => '26–31 August 2026',
            'gepg_payee_name' => 'NATIONAL INSTITUTE FOR MEDICAL RESEARCH',
        ];

        foreach ($defaults as $key => $value) {
            ConferenceSetting::set($key, $value);
        }
    }

    /**
     * One account per role, plus a plain registrant, so the review/check-in/
     * role-management flows all have someone to sign in as straight away.
     * Every seeded account shares the password "password". Staff/reviewer/
     * admin accounts are marked paid since they're not going through
     * registration themselves; the participant is a real (unpaid) registrant
     * so the registration → payment → control-number flow can still be
     * exercised with it.
     */
    private function seedUsers(): void
    {
        $accounts = [
            ['email' => 'admin@tmsc.nimr.or.tz', 'name' => 'TMSC Administrator', 'role' => User::ROLE_SUPER_ADMIN],
            ['email' => 'admin2@tmsc.nimr.or.tz', 'name' => 'TMSC Admin', 'role' => User::ROLE_ADMIN],
            ['email' => 'reviewer1@tmsc.nimr.or.tz', 'name' => 'Amina Reviewer', 'role' => User::ROLE_REVIEWER],
            ['email' => 'reviewer2@tmsc.nimr.or.tz', 'name' => 'Baraka Reviewer', 'role' => User::ROLE_REVIEWER],
            ['email' => 'staff@tmsc.nimr.or.tz', 'name' => 'TMSC Check-in Staff', 'role' => User::ROLE_STAFF],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => 'password',
                    'role' => $account['role'],
                    'email_verified_at' => now(),
                    'payment_status' => 'verified',
                ]
            );
        }

        $participant = User::updateOrCreate(
            ['email' => 'participant@tmsc.nimr.or.tz'],
            [
                'name' => 'Test Participant',
                'role' => User::ROLE_USER,
                'password' => 'password',
                'email_verified_at' => now(),
                'salutation' => 'Dr.',
                'institution' => 'National Institute for Medical Research (NIMR)',
                'phone' => '+255 700 000 001',
                'country' => 'Tanzania',
                'is_east_africa' => true,
                'participant_type' => 'researcher',
                'payment_status' => 'pending',
            ]
        );
        $participant->assignFeeCategory('participant_east_africa');
        $participant->save();
    }

    /**
     * A few sample submissions under the seeded participant account, one per
     * review outcome, so the abstract review workflow has real data to
     * exercise (assign reviewers, submit recommendations, decide) right away.
     */
    private function seedAbstracts(): void
    {
        $author = User::where('email', 'participant@tmsc.nimr.or.tz')->first();
        $admin = User::where('email', 'admin@tmsc.nimr.or.tz')->first();

        if (! $author) {
            return;
        }

        $subthemeId = fn (string $title) => Subtheme::where('title', $title)->value('id');

        $abstracts = [
            [
                'title' => 'Community-Led Conservation of Medicinal Plants in the Southern Highlands',
                'subtheme_id' => $subthemeId('Conservation and Sustainable Use of Medicinal Plants'),
                'presentation_type' => 'poster',
                'background' => 'Overharvesting threatens several medicinal plant species used by traditional healers in the Southern Highlands.',
                'objective' => 'To document community-led conservation practices and assess their effect on medicinal plant availability.',
                'methods' => 'Semi-structured interviews with 40 traditional healers across three districts, combined with plot surveys of harvested species.',
                'results' => 'Villages practicing rotational harvesting showed higher plant density than open-access sites.',
                'conclusion' => 'Community-led rotational harvesting is a low-cost, scalable conservation strategy worth formal policy support.',
                'status' => 'submitted',
            ],
            [
                'title' => 'Integrating Traditional Birth Attendants into Maternal Health Referral Pathways',
                'subtheme_id' => $subthemeId('Traditional Medicine for Priority Health Conditions'),
                'presentation_type' => 'oral',
                'background' => 'Traditional birth attendants remain a first point of contact for maternal care in rural Tanzania.',
                'objective' => 'To evaluate a referral pathway linking traditional birth attendants to formal antenatal and obstetric services.',
                'methods' => 'A 12-month pilot across two districts tracking referral rates, timeliness, and maternal outcomes.',
                'results' => 'Referral pathway participation reduced late-stage complication presentations compared to the prior year.',
                'conclusion' => 'Structured referral partnerships can improve maternal outcomes without displacing traditional attendants.',
                'status' => 'accepted',
                'reviewer_id' => $admin?->id,
                'decided_at' => now()->subDays(2),
            ],
            [
                'title' => 'A Mobile App for Documenting Traditional Remedies',
                'subtheme_id' => $subthemeId('Technology and Innovations in Traditional Medicine'),
                'presentation_type' => 'oral',
                'background' => 'Traditional remedy knowledge is largely undocumented and at risk of being lost between generations.',
                'objective' => 'To design and pilot a mobile app for practitioners to record remedies, dosages, and preparation methods.',
                'methods' => 'Co-design workshops with 15 practitioners followed by a three-month field pilot of the resulting app.',
                'results' => 'Practitioners logged over 200 remedy records; most reported the app fit their existing workflow.',
                'conclusion' => 'Lightweight digital tools can support remedy documentation, though offline reliability needs further work.',
                'status' => 'revision_requested',
                'decision_notes' => 'Please add more detail on data privacy safeguards for practitioner-submitted remedies before resubmitting.',
                'revision_requested_at' => now()->subDay(),
            ],
        ];

        foreach ($abstracts as $abstract) {
            AbstractSubmission::updateOrCreate(
                ['user_id' => $author->id, 'title' => $abstract['title']],
                array_merge($abstract, [
                    'user_id' => $author->id,
                    'authors' => [
                        ['name' => $author->name, 'institution' => $author->institution ?? 'National Institute for Medical Research (NIMR)', 'is_presenter' => true],
                    ],
                ])
            );
        }
    }
}
