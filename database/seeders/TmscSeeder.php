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
        $this->seedReviewWorkflowTestData();
    }

    /**
     * `role` and `payment_status` aren't in User::$fillable (they're only meant
     * to be set via forceFill from trusted server-side code, never mass
     * assignment from a form), so a plain create()/updateOrCreate() call
     * silently drops them. Every place in this seeder that sets a role goes
     * through here so the `users.role` column and the `user_roles` pivot
     * (the actual source of truth for authorization) stay in sync.
     */
    private function setRole(User $user, string $role): void
    {
        $user->forceFill(['role' => $role])->save();
        $user->roleAssignments()->delete();
        $user->roleAssignments()->create(['role' => $role]);
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
            // Attend by role rather than by fee. Never offered on the public
            // form — the venue desk registers them. See FeeCategory.
            'complimentary_media' => ['label' => 'Media', 'amount' => 0, 'currency' => 'TZS', 'sort_order' => 90, 'is_complimentary' => true],
            'complimentary_secretariat' => ['label' => 'Secretariat', 'amount' => 0, 'currency' => 'TZS', 'sort_order' => 91, 'is_complimentary' => true],
            'complimentary_invited_guest' => ['label' => 'Invited Guest', 'amount' => 0, 'currency' => 'TZS', 'sort_order' => 92, 'is_complimentary' => true],
            'complimentary_exhibitor' => ['label' => 'Exhibitor', 'amount' => 0, 'currency' => 'TZS', 'sort_order' => 93, 'is_complimentary' => true],
            'participant_east_africa' => ['label' => 'East African Participants', 'amount' => 150000, 'currency' => 'TZS', 'sort_order' => 1],
            'participant_non_east_africa' => ['label' => 'Non-East African Participants', 'amount' => 150, 'currency' => 'USD', 'sort_order' => 2],
            'student_east_africa' => ['label' => 'East African Students', 'amount' => 50000, 'currency' => 'TZS', 'sort_order' => 3],
            'student_non_east_africa' => ['label' => 'Non-East African Students', 'amount' => 50, 'currency' => 'USD', 'sort_order' => 4],
        ];

        foreach ($categories as $key => $category) {
            // Left operand wins, so the complimentary entries above keep their
            // flag while every paid tier is explicitly pinned to false.
            FeeCategory::updateOrCreate(['key' => $key], $category + ['active' => true, 'is_complimentary' => false]);
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
            'start_date' => '2026-08-28',
            'end_date' => '2026-08-28',
            // Extended from 6 August; the displayed call-for-abstracts date.
            'submission_deadline' => '2026-08-13',
            // Registration closes a week before the conference so the venue has
            // a final headcount for badges and catering; `registration_closed`
            // is the manual override organizers can flip at any time.
            'registration_deadline' => '2026-08-21',
            'registration_closed' => '0',
            'abstract_notification_date' => '2026-08-13',
            // Presentations aren't reviewed — presenters may keep replacing
            // their file until this date, and whatever is on file then is what
            // gets presented.
            'presentation_deadline' => '2026-08-27',
            // Certificates unlock partway through the closing day, once having
            // attended actually means something.
            'certificate_release_at' => '2026-08-28 14:00',
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
            // Finance can verify and waive payments, and is the only role that
            // may settle one from the check-in app — without a seeded account
            // that whole path is unreachable on a fresh install.
            ['email' => 'finance@tmsc.nimr.or.tz', 'name' => 'TMSC Finance', 'role' => User::ROLE_FINANCE],
            ['email' => 'reviewer1@tmsc.nimr.or.tz', 'name' => 'Amina Reviewer', 'role' => User::ROLE_REVIEWER],
            ['email' => 'reviewer2@tmsc.nimr.or.tz', 'name' => 'Baraka Reviewer', 'role' => User::ROLE_REVIEWER],
            ['email' => 'staff@tmsc.nimr.or.tz', 'name' => 'TMSC Check-in Staff', 'role' => User::ROLE_STAFF],
            // A second door account, so "recorded by" on an attendance means
            // something when more than one person is scanning.
            ['email' => 'staff2@tmsc.nimr.or.tz', 'name' => 'Second Door Staff', 'role' => User::ROLE_STAFF],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => 'password',
                ]
            );
            $this->setRole($user, $account['role']);
            // `email_verified_at` is no more fillable than `role` is, so passing
            // it to updateOrCreate above dropped it without a word and left every
            // seeded account stuck behind the verification notice.
            $user->forceFill(['payment_status' => 'verified', 'email_verified_at' => now()])->save();
        }

        $participant = User::updateOrCreate(
            ['email' => 'participant@tmsc.nimr.or.tz'],
            [
                'name' => 'Test Participant',
                'password' => 'password',
                'salutation' => 'Dr.',
                'institution' => 'National Institute for Medical Research (NIMR)',
                'phone' => '+255 700 000 001',
                'country' => 'Tanzania',
                'is_east_africa' => true,
                'participant_type' => 'researcher',
            ]
        );
        $this->setRole($participant, User::ROLE_USER);
        $participant->assignFeeCategory('participant_east_africa');
        $participant->forceFill(['payment_status' => 'pending', 'email_verified_at' => now()])->save();
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

    /**
     * Extra reviewer + author accounts, plus 40 abstracts spread across
     * every review-workflow state (auto-accept on unanimous acceptance,
     * admin tie-break on disagreement, selective re-notification on
     * resubmission) so that logic has real, clickable data behind it —
     * not just the three hand-picked examples in seedAbstracts().
     */
    private function seedReviewWorkflowTestData(): void
    {
        $reviewers = $this->seedTestReviewers();
        $authors = $this->seedTestAuthors();
        $admin = User::where('email', 'admin2@tmsc.nimr.or.tz')->first();
        $subthemes = Subtheme::where('active', true)->orderBy('sort_order')->get();

        if ($subthemes->isEmpty()) {
            return;
        }

        $topics = [
            'integration of traditional birth attendants into antenatal referral pathways',
            'collaborative care models between traditional healers and primary health facilities',
            'traditional medicine curricula in university-based clinical education',
            'evidence-based triage protocols for traditional and biomedical co-management',
            'regulatory frameworks for traditional medicine practitioner licensing',
            'governance structures for herbal product quality standards',
            'community-based oversight of traditional medicine practice',
            'policy barriers to traditional medicine integration in district health plans',
            'traditional remedies for management of hypertension in rural clinics',
            'herbal adjuncts in tuberculosis treatment adherence programs',
            'traditional practices in maternal and postpartum care',
            'zoonotic disease awareness among traditional livestock healers',
            'traditional approaches to non-communicable disease self-management',
            'vector-borne disease prevention knowledge among traditional healers',
            'mobile applications for traditional remedy documentation',
            'machine learning screening of medicinal plant compounds',
            'digital informatics systems for traditional medicine records',
            'ICT-enabled access to traditional medicine services in remote areas',
            'commercialization pathways for traditional herbal products',
            'artificial intelligence-assisted identification of medicinal plant species',
            'sustainable harvesting practices for threatened medicinal plant species',
            'community-led conservation of medicinal plant biodiversity',
            'cultivation trials for at-risk traditional medicine plant species',
            'climate change adaptation in medicinal plant sourcing',
            'biodiversity monitoring in traditional medicine harvesting zones',
            'traditional bone-setting practice outcomes in rural districts',
            'antimicrobial screening of indigenous medicinal plant extracts',
            'traditional healer referral networks and early disease detection',
            'safety profiling of commonly used herbal preparations',
            'practitioner perspectives on integrating traditional and modern diagnostics',
            'traditional medicine knowledge transfer between generations of healers',
            'economic value chains for cultivated medicinal plants',
            'quality control practices among herbal product manufacturers',
            'traditional medicine documentation using participatory mapping',
            'patient experiences navigating traditional and formal care pathways',
            'traditional remedies for post-surgical wound care',
            'gender dynamics in traditional medicine practitioner networks',
            'traditional medicine practitioner training and certification pathways',
            'cross-border trade in medicinal plant raw materials',
            "traditional medicine's role in universal health coverage strategies",
        ];

        // Every state the review workflow can be in, weighted so the two new
        // behaviours (auto-accept on consensus, selective re-review after a
        // revision) each have several examples to test against.
        $stateCounts = [
            'unassigned' => 4,
            'assigned_no_decisions' => 5,
            'one_decided_accept_pending' => 5,
            'one_decided_revision_pending' => 3,
            'one_decided_reject_pending' => 2,
            'both_accepted_auto' => 6,
            'disagree_ready_for_admin' => 5,
            'both_reject_ready_for_admin' => 2,
            'revision_requested_mixed' => 4,
            'revision_requested_both' => 2,
            'accepted_manual_final' => 1,
            'rejected_final' => 1,
        ];

        $states = [];
        foreach ($stateCounts as $state => $count) {
            $states = array_merge($states, array_fill(0, $count, $state));
        }

        foreach ($states as $index => $state) {
            $this->seedScenarioAbstract(
                index: $index,
                state: $state,
                topic: $topics[$index % count($topics)],
                author: $authors[$index % count($authors)],
                subtheme: $subthemes[$index % $subthemes->count()],
                reviewerOne: $reviewers[$index % count($reviewers)],
                reviewerTwo: $reviewers[($index + 1) % count($reviewers)],
                admin: $admin,
            );
        }
    }

    /** @return list<User> */
    private function seedTestReviewers(): array
    {
        $reviewers = [
            'reviewer1@tmsc.nimr.or.tz' => 'Amina Reviewer',
            'reviewer2@tmsc.nimr.or.tz' => 'Baraka Reviewer',
            'reviewer3@tmsc.nimr.or.tz' => 'Consolata Mushi',
            'reviewer4@tmsc.nimr.or.tz' => 'Daudi Kessy',
            'reviewer5@tmsc.nimr.or.tz' => 'Esther Mwakalinga',
            'reviewer6@tmsc.nimr.or.tz' => 'Frank Sanga',
        ];

        return collect($reviewers)->map(function (string $name, string $email) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'email_verified_at' => now()]
            );
            $this->setRole($user, User::ROLE_REVIEWER);
            $user->forceFill(['payment_status' => 'verified'])->save();

            return $user;
        })->values()->all();
    }

    /** @return list<User> */
    private function seedTestAuthors(): array
    {
        $names = [
            'Grace Mwambene', 'Hamisi Juma', 'Irene Kileo', 'John Mgaya', 'Khadija Salum',
            'Lucas Mrema', 'Mary Chuma', 'Nasra Idi', 'Omary Kalulu', 'Pendo Massawe',
            'Qamar Athumani', 'Rehema Ngowi', 'Salum Bakari', 'Tumaini Shirima', 'Upendo Msigwa',
            'Victor Nyoni', 'Winnie Kimaro', 'Yusuf Rashidi', 'Zainab Hassan', 'Elias Mwanri',
        ];

        return collect($names)->map(function (string $name, int $i) {
            $email = 'author'.($i + 1).'@tmsc.nimr.or.tz';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'institution' => 'National Institute for Medical Research (NIMR)',
                    'phone' => '+255 700 000 '.str_pad((string) ($i + 100), 3, '0', STR_PAD_LEFT),
                    'country' => 'Tanzania',
                    'is_east_africa' => true,
                    'participant_type' => 'researcher',
                ]
            );
            $this->setRole($user, User::ROLE_USER);
            $user->assignFeeCategory('participant_east_africa');
            $user->forceFill(['payment_status' => $i % 4 === 0 ? 'pending' : 'verified'])->save();

            return $user;
        })->values()->all();
    }

    /** Short, generic-but-on-topic section text — well under the 300-word cap. */
    private function scenarioContent(string $topic): array
    {
        return [
            'background' => "Limited documented evidence exists on {$topic}, despite its relevance to community health practice in Tanzania.",
            'objective' => "To assess {$topic} and generate evidence to inform practice and policy at the 5th TMSC.",
            'methods' => 'A mixed-methods approach combining structured interviews, facility record review, and field observation across selected sites.',
            'results' => 'Preliminary findings show measurable practice-level effects, with variation linked to local context and resource availability.',
            'conclusion' => "Findings support further investment in {$topic}, with attention to local adaptation and ongoing monitoring.",
        ];
    }

    /**
     * Builds one seeded abstract and drives it through whichever
     * review-workflow state $state describes — assigning reviewers,
     * recording their recommendations, and (for the terminal states)
     * finalizing the decision — mirroring exactly what the controllers do,
     * so the seeded rows are indistinguishable from ones produced by
     * clicking through the real flow.
     */
    private function seedScenarioAbstract(
        int $index,
        string $state,
        string $topic,
        User $author,
        Subtheme $subtheme,
        User $reviewerOne,
        User $reviewerTwo,
        ?User $admin,
    ): void {
        $title = sprintf('TMSC Test #%02d — %s', $index + 1, ucfirst($topic));
        $content = $this->scenarioContent($topic);

        $abstract = AbstractSubmission::updateOrCreate(
            ['user_id' => $author->id, 'title' => $title],
            array_merge($content, [
                'subtheme_id' => $subtheme->id,
                'presentation_type' => $index % 2 === 0 ? 'oral' : 'poster',
                'authors' => [
                    ['name' => $author->name, 'institution' => $author->institution, 'is_presenter' => true],
                ],
                'status' => 'submitted',
                'reviewer_id' => null,
                'reviewer_one_id' => null,
                'reviewer_two_id' => null,
                'decision_notes' => null,
                'revision_requested_at' => null,
                'resubmitted_at' => null,
                'decided_at' => null,
            ])
        );

        // Reset review state so re-running the seeder is idempotent.
        $abstract->reviewerDecisions->each(fn ($decision) => $decision->comments()->delete());
        $abstract->reviewerDecisions()->delete();
        $abstract->reviewHistory()->delete();

        $abstract->reviewHistory()->create([
            'acted_by' => $author->id,
            'action' => 'submitted',
            'from_status' => null,
            'to_status' => 'submitted',
        ]);

        $assign = function () use ($abstract, $reviewerOne, $reviewerTwo) {
            $abstract->update(['reviewer_one_id' => $reviewerOne->id, 'reviewer_two_id' => $reviewerTwo->id]);
        };

        $decide = function (User $reviewer, string $recommendation, ?string $comment = null) use ($abstract) {
            $decision = $abstract->reviewerDecisions()->create([
                'reviewer_id' => $reviewer->id,
                'recommendation' => $recommendation,
                'decided_at' => now()->subHours(random_int(1, 72)),
            ]);

            if ($comment) {
                $decision->comments()->create(['section' => null, 'body' => $comment]);
            }
        };

        $finalize = function (string $status, ?int $deciderId, ?string $notes) use ($abstract) {
            $now = now();
            $abstract->update([
                'status' => $status,
                'reviewer_id' => $deciderId,
                'decision_notes' => $notes,
                'revision_requested_at' => $status === 'revision_requested' ? $now : null,
                'decided_at' => in_array($status, ['accepted', 'rejected'], true) ? $now : null,
            ]);
            $abstract->reviewHistory()->create([
                'acted_by' => $deciderId,
                'action' => $status,
                'from_status' => 'submitted',
                'to_status' => $status,
                'notes' => $notes,
            ]);
        };

        switch ($state) {
            case 'unassigned':
                break;

            case 'assigned_no_decisions':
                $assign();
                break;

            case 'one_decided_accept_pending':
                $assign();
                $decide($reviewerOne, 'accepted');
                break;

            case 'one_decided_revision_pending':
                $assign();
                $decide($reviewerOne, 'revision_requested', 'The methods section needs more detail on sample selection.');
                break;

            case 'one_decided_reject_pending':
                $assign();
                $decide($reviewerOne, 'rejected', 'Scope overlaps substantially with prior published work.');
                break;

            case 'both_accepted_auto':
                $assign();
                $decide($reviewerOne, 'accepted');
                $decide($reviewerTwo, 'accepted');
                $finalize('accepted', null, 'Automatically accepted — both assigned reviewers recommended acceptance.');
                break;

            case 'disagree_ready_for_admin':
                $assign();
                $decide($reviewerOne, 'accepted');
                $decide($reviewerTwo, 'revision_requested', 'Please clarify the ethical approval reference.');
                break;

            case 'both_reject_ready_for_admin':
                $assign();
                $decide($reviewerOne, 'rejected', 'Insufficient evidence for the stated conclusion.');
                $decide($reviewerTwo, 'rejected', 'Methodology is not clearly described.');
                break;

            case 'revision_requested_mixed':
                $assign();
                $decide($reviewerOne, 'accepted');
                $decide($reviewerTwo, 'revision_requested', 'Results section needs a clearer statement of the primary outcome.');
                $finalize('revision_requested', $admin?->id, 'One reviewer requested changes to the results — please address and resubmit.');
                break;

            case 'revision_requested_both':
                $assign();
                $decide($reviewerOne, 'revision_requested', 'Background needs more local context.');
                $decide($reviewerTwo, 'rejected', 'Conclusion is not supported by the results presented.');
                $finalize('revision_requested', $admin?->id, 'Please strengthen the background and align the conclusion with your results.');
                break;

            case 'accepted_manual_final':
                $assign();
                $decide($reviewerOne, 'accepted');
                $decide($reviewerTwo, 'rejected', 'Concerned about generalizability.');
                $finalize('accepted', $admin?->id, 'Accepted on balance — the concern raised is addressable at the presentation stage.');
                break;

            case 'rejected_final':
                $assign();
                $decide($reviewerOne, 'rejected', 'Out of scope for this conference.');
                $decide($reviewerTwo, 'rejected', 'Duplicate of prior submission.');
                $finalize('rejected', $admin?->id, 'Both reviewers recommended rejection; out of scope for TMSC 2026.');
                break;
        }
    }
}
