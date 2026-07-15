<?php

namespace Database\Seeders;

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
        $this->seedAdmin();
    }

    /**
     * Official fee table from the 5th TMSC (2026, Mbeya) call for abstracts.
     * No separate practitioner tier — everyone pays the standard rate for
     * their region, except students. Non-East-Africa tiers are billed in
     * USD per the official table.
     */
    private function seedFeeCategories(): void
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
    private function seedSubthemes(): void
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
    private function seedInstitutions(): void
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
    private function seedConferenceSettings(): void
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

    private function seedAdmin(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@tmsc.nimr.or.tz'],
            [
                'name' => 'TMSC Administrator',
                'password' => 'password',
                'is_admin' => true,
                'email_verified_at' => now(),
                'payment_status' => 'verified',
            ]
        );
    }
}
