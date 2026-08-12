import InputError from '@/components/input-error';
import { StatusPill } from '@/components/status-pill';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Clock3,
    Download,
    EyeOff,
    FilePenLine,
    FileUp,
    Gavel,
    History,
    MessageSquare,
    Plus,
    Presentation,
    UserRound,
    X,
} from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type AbstractStatus = 'submitted' | 'revision_requested' | 'accepted' | 'rejected';
type ReviewAction = 'accepted' | 'revision_requested' | 'rejected';
/** Presentations aren't reviewed — a file is either on record or it isn't. */
type PresentationStatus = 'pending' | 'uploaded';

interface Author {
    name: string;
    institution: string;
    is_presenter?: boolean;
}

interface History {
    id: number;
    action: string;
    from_status: string | null;
    to_status: string;
    notes: string | null;
    created_at: string;
    actor: { name: string } | null;
}

interface ReviewerRef {
    id: number;
    name: string;
    email: string;
}

interface ReviewerComment {
    id: number;
    section: string | null;
    body: string;
}

interface ReviewerDecision {
    id: number;
    reviewer_id: number;
    // null when this is another reviewer's decision, redacted server-side for blind review.
    recommendation: ReviewAction | null;
    comments: ReviewerComment[];
    decided_at: string;
    reviewer: ReviewerRef;
}

const recommendationLabel: Record<ReviewAction, string> = {
    accepted: 'Accept',
    revision_requested: 'Request revision',
    rejected: 'Reject',
};

const ABSTRACT_SECTIONS = [
    { key: 'background', label: 'Background' },
    { key: 'objective', label: 'Objective' },
    { key: 'methods', label: 'Methods' },
    { key: 'results', label: 'Results' },
    { key: 'conclusion', label: 'Conclusion' },
] as const;

const sectionLabel: Record<string, string> = {
    background: 'Background',
    objective: 'Objective',
    methods: 'Methods',
    results: 'Results',
    conclusion: 'Conclusion',
};

function labelForSection(section: string | null): string {
    return section ? (sectionLabel[section] ?? section) : 'Overall';
}

interface Submission {
    id: number;
    title: string;
    // Nullable: abstracts submitted before the five-section split carry their
    // whole text in `background` and null everywhere else (see the
    // split_abstract_text_into_sections migration).
    background: string | null;
    objective: string | null;
    methods: string | null;
    results: string | null;
    conclusion: string | null;
    authors: Author[];
    presentation_type: 'oral' | 'poster';
    presentation_status: PresentationStatus;
    presentation_original_name: string | null;
    presentation_uploaded_at: string | null;
    presentation_notes: string | null;
    status: AbstractStatus;
    decision_notes: string | null;
    created_at: string;
    resubmitted_at: string | null;
    decided_at: string | null;
    // Absent for blind reviewers — the server never sends it (see
    // App\Support\BlindReview), so the type must force a check at every use.
    user?: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        institution: string | null;
        country: string | null;
    } | null;
    is_blinded?: boolean;
    subtheme: { title: string } | null;
    reviewer: { name: string } | null;
    reviewer_one: ReviewerRef | null;
    reviewer_two: ReviewerRef | null;
    reviewer_decisions: ReviewerDecision[];
    review_history: History[];
    /**
     * Earlier review rounds. A reviewer only ever receives their own; admins
     * receive both reviewers'. Empty on a first-round abstract.
     */
    prior_rounds: PriorRound[];
    review_round: number;
    completed_rounds: number;
    is_re_review: boolean;
}

interface PriorRound {
    id: number;
    round: number;
    reviewer_id: number;
    reviewer_name: string | null;
    recommendation: ReviewAction;
    decided_at: string | null;
    comments: ReviewerComment[];
}

const statusConfig: Record<AbstractStatus, { label: string; className: string }> = {
    submitted: { label: 'Awaiting review', className: 'bg-amber-50 text-amber-800 ring-amber-200' },
    revision_requested: { label: 'Waiting for revision', className: 'bg-blue-50 text-blue-800 ring-blue-200' },
    accepted: { label: 'Accepted', className: 'bg-green-50 text-green-800 ring-green-200' },
    rejected: { label: 'Rejected', className: 'bg-red-50 text-red-800 ring-red-200' },
};

const actionLabel: Record<string, string> = {
    submitted: 'Submitted by author',
    resubmitted: 'Revision resubmitted',
    revision_requested: 'Revision requested',
    accepted: 'Accepted',
    rejected: 'Permanently rejected',
};

function formatDate(value: string) {
    return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

/**
 * Where the abstract stands, and what — if anything — is owed next.
 *
 * The outcome used to live only in a grey sidebar box at the very bottom titled
 * "Final decision", so the single most important fact about a decided abstract
 * was the last thing you reached, and an accepted one never showed *when* it was
 * accepted at all.
 */
function outcomeFor(submission: Submission, viewer: { isAdmin: boolean; bothAssigned: boolean; bothDecided: boolean }) {
    const decidedOn = submission.decided_at ? formatDate(submission.decided_at) : null;

    if (submission.status === 'accepted') {
        return {
            tone: 'accepted' as const,
            headline: 'Accepted for the conference',
            detail: decidedOn ? `Decided ${decidedOn}.` : 'Decision recorded.',
            next: null,
        };
    }

    if (submission.status === 'rejected') {
        return {
            tone: 'rejected' as const,
            headline: 'Not accepted',
            detail: decidedOn ? `Decided ${decidedOn}. This is final.` : 'This is final.',
            next: null,
        };
    }

    if (submission.status === 'revision_requested') {
        return {
            tone: 'revision' as const,
            headline: 'Sent back to the author',
            detail: decidedOn ? `Revision requested ${decidedOn}. Waiting for them to resubmit.` : 'Waiting for the author to resubmit.',
            next: null,
        };
    }

    // Still under review — say precisely who is holding it up.
    if (!viewer.bothAssigned) {
        return {
            tone: 'waiting' as const,
            headline: 'Not yet under review',
            detail: 'Two reviewers must be assigned before review can begin.',
            next: viewer.isAdmin ? 'Assign reviewers' : null,
        };
    }

    if (viewer.bothDecided) {
        return {
            tone: 'ready' as const,
            headline: 'Both reviewers have reported',
            detail: 'Their recommendations did not resolve automatically, so the decision is yours.',
            next: viewer.isAdmin ? 'Record the final decision' : null,
        };
    }

    const pending = [submission.reviewer_one, submission.reviewer_two]
        .filter((reviewer): reviewer is ReviewerRef => Boolean(reviewer))
        .filter((reviewer) => !submission.reviewer_decisions.some((decision) => decision.reviewer_id === reviewer.id));

    return {
        tone: 'waiting' as const,
        headline: submission.is_re_review ? `Under re-review · round ${submission.review_round}` : 'Under review',
        detail:
            viewer.isAdmin && pending.length
                ? `Waiting on ${pending.map((reviewer) => reviewer.name).join(' and ')}.`
                : 'Waiting for reviewer recommendations.',
        next: null,
    };
}

const outcomeTone: Record<'accepted' | 'rejected' | 'revision' | 'waiting' | 'ready', string> = {
    accepted: 'border-[#4c8a1f]/30 bg-[#f3f9ee] dark:bg-[#4c8a1f]/10',
    rejected: 'border-red-200 bg-red-50 dark:border-red-900/40 dark:bg-red-950/20',
    revision: 'border-[#135eeb]/25 bg-[#135eeb]/5',
    waiting: 'border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/20',
    ready: 'border-[#4c8a1f]/40 bg-[#f3f9ee] dark:bg-[#4c8a1f]/10',
};

const outcomeIcon: Record<'accepted' | 'rejected' | 'revision' | 'waiting' | 'ready', typeof Check> = {
    accepted: Check,
    rejected: X,
    revision: FilePenLine,
    waiting: Clock3,
    ready: Gavel,
};

/**
 * Whether the two reviewers agreed, stated in one line.
 *
 * With only the per-reviewer boxes, working out "did they agree" meant reading
 * both and comparing — the first thing an admin actually wants to know.
 */
function consensusFor(submission: Submission): { label: string; tone: 'positive' | 'attention' | 'negative' | 'neutral' } | null {
    const recommendations = submission.reviewer_decisions
        .map((decision) => decision.recommendation)
        .filter((recommendation): recommendation is ReviewAction => Boolean(recommendation));

    if (recommendations.length < 2) {
        return null;
    }

    const [first, second] = recommendations;

    if (first === second) {
        return first === 'accepted'
            ? { label: 'Both recommend acceptance', tone: 'positive' }
            : first === 'rejected'
              ? { label: 'Both recommend rejection', tone: 'negative' }
              : { label: 'Both ask for revision', tone: 'attention' };
    }

    return {
        label: `Split — ${recommendationLabel[first].toLowerCase()} vs ${recommendationLabel[second].toLowerCase()}`,
        tone: 'attention',
    };
}

interface AbstractReviewShowProps {
    submission: Submission;
    eligibleReviewers: ReviewerRef[];
}

export default function AbstractReviewShow({ submission, eligibleReviewers }: AbstractReviewShowProps) {
    const { auth } = usePage<SharedData>().props;
    const isAdmin = auth.user.roles.includes('admin') || auth.user.roles.includes('super_admin');
    const isAssignedReviewer = auth.user.id === submission.reviewer_one?.id || auth.user.id === submission.reviewer_two?.id;
    const myDecision = submission.reviewer_decisions.find((d) => d.reviewer_id === auth.user.id) ?? null;
    const otherDecision = submission.reviewer_decisions.find((d) => d.reviewer_id !== auth.user.id) ?? null;
    const isReReview = Boolean(submission.is_re_review);
    const myPriorRounds = submission.prior_rounds ?? [];
    const bothReviewersAssigned = Boolean(submission.reviewer_one && submission.reviewer_two);
    const bothReviewersDecided = bothReviewersAssigned && submission.reviewer_decisions.length === 2;

    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const status = statusConfig[submission.status];
    const wordCount = ABSTRACT_SECTIONS.reduce((total, { key }) => {
        const value = submission[key]?.trim() ?? '';
        return total + (value ? value.split(/\s+/).length : 0);
    }, 0);
    // A pre-split submission: everything in Background, nothing else filled.
    // Worth calling out, otherwise four empty headings read as a bug.
    const isLegacyFormat =
        Boolean(submission.background?.trim()) &&
        !submission.objective?.trim() &&
        !submission.methods?.trim() &&
        !submission.results?.trim() &&
        !submission.conclusion?.trim();
    const canDecide = isAdmin && submission.status === 'submitted' && bothReviewersDecided;
    const outcome = outcomeFor(submission, { isAdmin, bothAssigned: bothReviewersAssigned, bothDecided: bothReviewersDecided });
    const OutcomeIcon = outcomeIcon[outcome.tone];
    const consensus = isAdmin ? consensusFor(submission) : null;
    // Every comment the viewer is allowed to see, this round and earlier.
    const commentCount =
        submission.reviewer_decisions.reduce((total, decision) => total + decision.comments.length, 0) +
        myPriorRounds.reduce((total, prior) => total + prior.comments.length, 0);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin' },
        { title: 'Abstract Review', href: '/admin/abstracts' },
        { title: submission.title, href: route('admin.abstracts.show', submission.id) },
    ];

    const [reviewerOneId, setReviewerOneId] = useState(submission.reviewer_one ? String(submission.reviewer_one.id) : '');
    const [reviewerTwoId, setReviewerTwoId] = useState(submission.reviewer_two ? String(submission.reviewer_two.id) : '');
    const [assigning, setAssigning] = useState(false);
    const [assignError, setAssignError] = useState<string | null>(null);

    const assignReviewers: FormEventHandler = (event) => {
        event.preventDefault();
        setAssignError(null);
        setAssigning(true);

        router.post(
            route('admin.abstracts.reviewers.assign', submission.id),
            { reviewer_one_id: reviewerOneId, reviewer_two_id: reviewerTwoId },
            {
                preserveScroll: true,
                onError: (validationErrors) => setAssignError(String(Object.values(validationErrors)[0] ?? 'Reviewers could not be assigned.')),
                onFinish: () => setAssigning(false),
            },
        );
    };

    const [myRecommendation, setMyRecommendation] = useState<ReviewAction>(myDecision?.recommendation ?? 'accepted');
    const [myComments, setMyComments] = useState<{ section: string | null; body: string }[]>(
        myDecision?.comments.map((c) => ({ section: c.section, body: c.body })) ?? [],
    );
    const [draftSection, setDraftSection] = useState<string>('overall');
    const [draftBody, setDraftBody] = useState('');
    const [recommending, setRecommending] = useState(false);
    const [recommendError, setRecommendError] = useState<string | null>(null);

    const addComment = () => {
        if (!draftBody.trim()) return;
        setMyComments((prev) => [...prev, { section: draftSection === 'overall' ? null : draftSection, body: draftBody.trim() }]);
        setDraftBody('');
    };

    const removeComment = (index: number) => {
        setMyComments((prev) => prev.filter((_, i) => i !== index));
    };

    const submitRecommendation: FormEventHandler = (event) => {
        event.preventDefault();

        if (myRecommendation !== 'accepted' && myComments.length === 0) {
            setRecommendError('At least one comment is required for this recommendation.');
            return;
        }

        setRecommendError(null);
        setRecommending(true);

        router.post(
            route('admin.abstracts.reviewer-decision', submission.id),
            { recommendation: myRecommendation, comments: myComments },
            {
                preserveScroll: true,
                onError: (validationErrors) =>
                    setRecommendError(String(Object.values(validationErrors)[0] ?? 'Your recommendation could not be recorded.')),
                onFinish: () => setRecommending(false),
            },
        );
    };

    const decide = (action: ReviewAction) => {
        if (action !== 'accepted' && !notes.trim()) {
            setErrors({ decision_notes: 'A reviewer comment is required for this action.' });
            return;
        }

        if (
            (action === 'accepted' || action === 'rejected') &&
            !window.confirm(action === 'accepted' ? 'Accept this abstract as a final decision?' : 'Permanently reject this abstract?')
        ) {
            return;
        }

        router.post(
            route('admin.abstracts.decide', submission.id),
            { action, decision_notes: notes },
            {
                preserveScroll: true,
                onStart: () => {
                    setProcessing(true);
                    setErrors({});
                },
                onError: (validationErrors) => setErrors(validationErrors),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Review: ${submission.title}`} />

            <div className="mx-auto w-full max-w-7xl p-4 pb-12 md:p-6">
                <Link
                    href={route('admin.abstracts.index')}
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-2 text-sm font-semibold"
                >
                    <ArrowLeft className="size-4" /> Back to abstract queue
                </Link>

                <div className="mt-5 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <main className="space-y-5">
                        {/* The outcome, first. What happened, when, why, and what is
                            owed next — before the abstract text an admin has usually
                            already read. */}
                        <section className={cn('rounded-2xl border p-5', outcomeTone[outcome.tone])}>
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="flex items-start gap-3">
                                    <OutcomeIcon className="mt-0.5 size-5 shrink-0" />
                                    <div>
                                        <h2 className="text-lg leading-6 font-semibold">{outcome.headline}</h2>
                                        <p className="text-muted-foreground mt-1 text-sm leading-6">{outcome.detail}</p>
                                    </div>
                                </div>
                                {consensus && <StatusPill tone={consensus.tone}>{consensus.label}</StatusPill>}
                            </div>

                            {/* The reason the author was given, quoted rather than
                                tucked into a grey sidebar box. */}
                            {submission.decision_notes && submission.status !== 'submitted' && (
                                <blockquote className="mt-4 border-l-2 border-current/25 pl-4 text-sm leading-6 italic">
                                    {submission.decision_notes}
                                </blockquote>
                            )}

                            {(commentCount > 0 || outcome.next) && (
                                <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs font-semibold">
                                    {commentCount > 0 && (
                                        <span className="flex items-center gap-1.5">
                                            <MessageSquare className="size-3.5" />
                                            {commentCount} reviewer comment{commentCount === 1 ? '' : 's'}
                                            {submission.completed_rounds > 0 &&
                                                ` across ${submission.review_round} round${submission.review_round === 1 ? '' : 's'}`}
                                        </span>
                                    )}
                                    {outcome.next && <span className="text-[#33620f] dark:text-[#8fd45a]">Next: {outcome.next}</span>}
                                </div>
                            )}
                        </section>

                        <article className="bg-card rounded-2xl border">
                            <header className="border-b p-6 md:p-8">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className={`inline-flex rounded-full px-3 py-1 text-xs font-bold ring-1 ring-inset ${status.className}`}>
                                        {status.label}
                                    </span>
                                    {submission.resubmitted_at && <span className="text-xs font-bold text-[#135eeb]">Revised submission</span>}
                                </div>
                                <h1 className="mt-4 font-serif text-3xl leading-tight font-semibold text-balance md:text-4xl">{submission.title}</h1>
                                <div className="mt-5 flex flex-wrap items-center gap-2">
                                    <StatusPill tone="positive" className="capitalize">
                                        <Presentation className="mr-1.5 size-3.5" />
                                        {submission.presentation_type} presentation
                                    </StatusPill>
                                    {submission.subtheme && <StatusPill tone="attention">{submission.subtheme.title}</StatusPill>}
                                    <StatusPill tone="neutral">
                                        {submission.resubmitted_at ? 'Resubmitted' : 'Submitted'}{' '}
                                        {formatDate(submission.resubmitted_at ?? submission.created_at)}
                                    </StatusPill>
                                </div>
                            </header>

                            <section className="p-6 md:p-8">
                                <div className="mb-5 flex items-end justify-between border-b pb-3">
                                    <h2 className="text-sm font-bold tracking-[0.14em] uppercase">Abstract</h2>
                                    <span className="text-muted-foreground text-xs tabular-nums">{wordCount} words combined</span>
                                </div>
                                {isLegacyFormat && (
                                    <p className="mb-5 rounded-lg border border-[#b5651d]/25 bg-[#b5651d]/5 px-4 py-3 text-sm leading-relaxed text-[#8a4d16]">
                                        This abstract was submitted before the structured five-section format, so its full text sits under Background.
                                        The remaining sections were never filled in.
                                    </p>
                                )}
                                <div className="space-y-6">
                                    {ABSTRACT_SECTIONS.map(({ key, label }) => {
                                        const body = submission[key]?.trim() ?? '';

                                        return (
                                            <div key={key}>
                                                <h3 className="text-xs font-bold tracking-[0.1em] text-[#4c8a1f] uppercase">{label}</h3>
                                                <div className="mt-2 text-[15px] leading-8 whitespace-pre-wrap text-slate-800 dark:text-slate-200">
                                                    {body || <span className="text-muted-foreground text-sm italic">Not provided.</span>}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </section>
                        </article>

                        {submission.status === 'accepted' && (
                            <section className="bg-card rounded-2xl border p-6 md:p-8">
                                <div className="flex items-start gap-4">
                                    <span className="flex size-10 items-center justify-center rounded-xl bg-[#eaf1ff] text-[#135eeb] dark:bg-[#135eeb]/15">
                                        <FileUp className="size-5" />
                                    </span>
                                    <div>
                                        <h2 className="text-lg font-semibold">Presentation file</h2>
                                        {/* Presentations aren't reviewed — this panel is read-only. */}
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            {submission.presentation_status === 'uploaded'
                                                ? 'Submitted by the presenter. They may replace it until the upload deadline.'
                                                : 'Not uploaded yet.'}
                                        </p>
                                    </div>
                                </div>

                                {submission.presentation_original_name && (
                                    <div
                                        className={cn(
                                            'mt-5 flex items-center justify-between gap-4 rounded-xl border p-4',
                                            // Mirrors the author's view so both sides read the same at a glance.
                                            submission.presentation_status === 'uploaded'
                                                ? 'border-[#4c8a1f]/30 bg-[#eef7e6] dark:border-[#67b52f]/25 dark:bg-[#67b52f]/10'
                                                : 'dark:bg-muted/40 border-transparent bg-slate-50',
                                        )}
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold">{submission.presentation_original_name}</p>
                                            {submission.presentation_uploaded_at && (
                                                <p className="text-muted-foreground mt-0.5 text-xs">
                                                    Uploaded {formatDate(submission.presentation_uploaded_at)}
                                                </p>
                                            )}
                                            {submission.presentation_notes && (
                                                <p className="text-muted-foreground mt-2 text-sm leading-6">
                                                    Presenter notes: {submission.presentation_notes}
                                                </p>
                                            )}
                                        </div>
                                        {/* Uploaded files are often named after their author, so the
                                            download is admin-only — enforced server-side too. */}
                                        {isAdmin && (
                                            <Button asChild variant="outline" size="sm" className="shrink-0">
                                                <a href={route('admin.abstracts.presentation.download', submission.id)}>
                                                    <Download className="size-4" />
                                                    Download
                                                </a>
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </section>
                        )}

                        <section className="bg-card rounded-2xl border p-6 md:p-8">
                            <h2 className="text-lg font-semibold">Authors</h2>
                            {submission.is_blinded && (
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Names and affiliations are withheld for blind review — only the number of authors and who presents is shown.
                                </p>
                            )}
                            <div className="mt-4 divide-y">
                                {submission.authors.map((author, index) => (
                                    <div key={`${author.name}-${index}`} className="flex items-start justify-between gap-4 py-4 first:pt-0 last:pb-0">
                                        <div>
                                            <p className={submission.is_blinded ? 'text-muted-foreground font-medium italic' : 'font-semibold'}>
                                                {author.name}
                                            </p>
                                            {author.institution && <p className="text-muted-foreground mt-1 text-sm">{author.institution}</p>}
                                        </div>
                                        {author.is_presenter && (
                                            <span className="rounded-full bg-[#eef7e6] px-2.5 py-1 text-xs font-bold text-[#4c8a1f]">Presenter</span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="bg-card rounded-2xl border p-6 md:p-8">
                            <h2 className="text-lg font-semibold">Review history</h2>
                            <div className="mt-5 space-y-5 border-l border-slate-200 pl-5 dark:border-slate-700">
                                {submission.review_history.length ? (
                                    submission.review_history.map((item) => (
                                        <div key={item.id} className="relative">
                                            <span className="ring-background absolute top-1 -left-[25px] size-2 rounded-full bg-[#4c8a1f] ring-4" />
                                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                                <p className="font-semibold">{actionLabel[item.action] ?? item.action.replaceAll('_', ' ')}</p>
                                                <time className="text-muted-foreground text-xs">{formatDate(item.created_at)}</time>
                                            </div>
                                            <p className="text-muted-foreground mt-1 text-sm">{item.actor?.name ?? 'System'}</p>
                                            {item.notes && (
                                                <p className="mt-3 rounded-xl bg-slate-50 p-4 text-sm leading-6 dark:bg-slate-900">{item.notes}</p>
                                            )}
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-muted-foreground text-sm">No review action has been recorded yet.</p>
                                )}
                            </div>
                        </section>
                    </main>

                    <aside className="space-y-5 lg:sticky lg:top-6">
                        {submission.user ? (
                            <section className="dark:bg-card rounded-2xl border border-[#135eeb]/10 bg-white p-5 shadow-[0_1px_2px_rgba(19,94,235,0.06)]">
                                <div className="flex items-center gap-3">
                                    <span className="flex size-10 items-center justify-center rounded-xl bg-[#eef7e6] text-[#4c8a1f]">
                                        <UserRound className="size-5" />
                                    </span>
                                    <div>
                                        <h2 className="font-semibold">Submitting author</h2>
                                        <p className="text-muted-foreground text-xs">Visible to admins only</p>
                                    </div>
                                </div>
                                <dl className="mt-5 space-y-4 text-sm">
                                    <div>
                                        <dt className="text-muted-foreground text-xs">Name</dt>
                                        <dd className="mt-1 font-semibold">{submission.user.name}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground text-xs">Email</dt>
                                        <dd className="mt-1 break-all">{submission.user.email}</dd>
                                    </div>
                                    {submission.user.institution && (
                                        <div>
                                            <dt className="text-muted-foreground text-xs">Institution</dt>
                                            <dd className="mt-1">{submission.user.institution}</dd>
                                        </div>
                                    )}
                                    {submission.user.phone && (
                                        <div>
                                            <dt className="text-muted-foreground text-xs">Phone</dt>
                                            <dd className="mt-1">{submission.user.phone}</dd>
                                        </div>
                                    )}
                                </dl>
                            </section>
                        ) : (
                            <section className="rounded-2xl border border-[#b5651d]/25 bg-[#b5651d]/5 p-5">
                                <div className="flex items-center gap-3">
                                    <span className="flex size-10 items-center justify-center rounded-xl bg-[#b5651d]/10 text-[#b5651d]">
                                        <EyeOff className="size-5" />
                                    </span>
                                    <div>
                                        <h2 className="font-semibold text-[#8a4d16]">Blind review</h2>
                                        <p className="text-xs text-[#8a4d16]/80">Author identity withheld</p>
                                    </div>
                                </div>
                                <p className="mt-4 text-sm leading-6 text-[#8a4d16]">
                                    Judge this abstract on its content alone. The author, their institution, and their affiliations are hidden from
                                    reviewers, and they will never see which named reviewer wrote which comment.
                                </p>
                            </section>
                        )}

                        {isAdmin && submission.status === 'submitted' && (
                            <section className="bg-card rounded-2xl border p-5">
                                <h2 className="text-lg font-semibold">Assign reviewers</h2>
                                <p className="text-muted-foreground mt-1 text-xs leading-5">
                                    Two reviewers must each submit a recommendation before a final decision can be made.
                                </p>
                                <form onSubmit={assignReviewers} className="mt-4 space-y-3">
                                    <div className="grid gap-1">
                                        <label className="text-xs font-semibold">Reviewer A</label>
                                        <Select value={reviewerOneId} onValueChange={setReviewerOneId}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a reviewer" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {eligibleReviewers.map((reviewer) => (
                                                    <SelectItem key={reviewer.id} value={String(reviewer.id)}>
                                                        {reviewer.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-1">
                                        <label className="text-xs font-semibold">Reviewer B</label>
                                        <Select value={reviewerTwoId} onValueChange={setReviewerTwoId}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a reviewer" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {eligibleReviewers.map((reviewer) => (
                                                    <SelectItem key={reviewer.id} value={String(reviewer.id)}>
                                                        {reviewer.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    {assignError && <p className="text-sm text-red-700">{assignError}</p>}
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                        disabled={assigning || !reviewerOneId || !reviewerTwoId || reviewerOneId === reviewerTwoId}
                                    >
                                        {assigning ? 'Saving…' : 'Save assignment'}
                                    </Button>
                                </form>
                            </section>
                        )}

                        {isAdmin && (submission.reviewer_one || submission.reviewer_two) && (
                            <section className="bg-card rounded-2xl border p-5">
                                <h2 className="text-lg font-semibold">Reviewer recommendations</h2>
                                <p className="text-muted-foreground mt-1 text-xs leading-5">
                                    Only visible to admins — assigned reviewers can't see each other's recommendation or comments.
                                </p>
                                <div className="mt-4 space-y-3">
                                    {[submission.reviewer_one, submission.reviewer_two].map((reviewer, index) => {
                                        if (!reviewer) return null;
                                        const decision = submission.reviewer_decisions.find((d) => d.reviewer_id === reviewer.id);

                                        return (
                                            <div
                                                key={reviewer.id}
                                                className={cn(
                                                    'rounded-xl border-l-2 bg-slate-50 p-4 dark:bg-slate-900',
                                                    decision?.recommendation === 'accepted'
                                                        ? 'border-l-[#4c8a1f]'
                                                        : decision?.recommendation === 'rejected'
                                                          ? 'border-l-red-400'
                                                          : decision?.recommendation
                                                            ? 'border-l-[#135eeb]'
                                                            : 'border-l-slate-300 dark:border-l-slate-700',
                                                )}
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="text-sm font-semibold">
                                                        Reviewer {index === 0 ? 'A' : 'B'} · {reviewer.name}
                                                    </p>
                                                    {decision?.recommendation ? (
                                                        <span
                                                            className={cn(
                                                                'text-xs font-bold',
                                                                decision.recommendation === 'accepted'
                                                                    ? 'text-[#4c8a1f]'
                                                                    : decision.recommendation === 'rejected'
                                                                      ? 'text-red-700 dark:text-red-400'
                                                                      : 'text-[#135eeb]',
                                                            )}
                                                        >
                                                            {recommendationLabel[decision.recommendation]}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground text-xs">Pending</span>
                                                    )}
                                                </div>
                                                {/* Recommendations without comments are legitimate — only an
                                                    acceptance may be silent — so say so rather than leaving a gap. */}
                                                {decision && decision.comments.length === 0 && (
                                                    <p className="text-muted-foreground mt-2 text-xs">No comments left.</p>
                                                )}
                                                {decision && decision.comments.length > 0 && (
                                                    <div className="mt-3 space-y-2">
                                                        {decision.comments.map((comment) => (
                                                            <div key={comment.id} className="text-sm leading-6">
                                                                <span className="mr-1.5 inline-flex rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-[#4c8a1f] dark:bg-black/20">
                                                                    {labelForSection(comment.section)}
                                                                </span>
                                                                <span className="text-muted-foreground">{comment.body}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </section>
                        )}

                        {/* A revision is a re-review, not a fresh one: show what this
                            reviewer asked for last time so they can judge whether the
                            author answered it. Only their own rounds are ever sent. */}
                        {myPriorRounds.length > 0 && (
                            <section className="rounded-2xl border border-[#135eeb]/25 bg-[#135eeb]/5 p-5">
                                <div className="flex items-center gap-2">
                                    <History className="size-4 text-[#135eeb]" />
                                    <h2 className="text-lg font-semibold text-[#0f2350] dark:text-[#a9c6ff]">
                                        {isAdmin ? 'Previous review rounds' : 'What you asked for last time'}
                                    </h2>
                                </div>

                                <div className="mt-4 space-y-4">
                                    {myPriorRounds.map((prior) => (
                                        <div key={prior.id} className="rounded-xl bg-white/70 p-4 dark:bg-black/20">
                                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                                <p className="text-sm font-semibold">
                                                    Round {prior.round}
                                                    {isAdmin && prior.reviewer_name ? ` · ${prior.reviewer_name}` : ''}
                                                </p>
                                                <span className="text-xs font-bold text-[#135eeb]">
                                                    {recommendationLabel[prior.recommendation] ?? prior.recommendation}
                                                </span>
                                            </div>

                                            {prior.comments.length > 0 ? (
                                                <div className="mt-3 space-y-2">
                                                    {prior.comments.map((comment) => (
                                                        <div key={comment.id} className="text-sm leading-6">
                                                            <span className="mr-1.5 inline-flex rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-[#135eeb] dark:bg-black/30">
                                                                {labelForSection(comment.section)}
                                                            </span>
                                                            <span className="text-muted-foreground">{comment.body}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-muted-foreground mt-2 text-sm">No comments were left in that round.</p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </section>
                        )}

                        {isAssignedReviewer && submission.status === 'submitted' && (
                            <section className="bg-card rounded-2xl border p-5">
                                <h2 className="text-lg font-semibold">{isReReview ? 'Your re-review' : 'Your recommendation'}</h2>
                                <p className="text-muted-foreground mt-1 text-xs leading-5">
                                    {isReReview
                                        ? `This is round ${submission.review_round} — the author has revised the abstract ${submission.completed_rounds === 1 ? 'once' : `${submission.completed_rounds} times`}. Judge the revision against the comments above.`
                                        : 'If both reviewers recommend acceptance, the abstract is accepted automatically. Any other combination is left for an admin to decide.'}
                                </p>
                                <form onSubmit={submitRecommendation} className="mt-4 space-y-3">
                                    <div className="grid gap-2">
                                        {(Object.keys(recommendationLabel) as ReviewAction[]).map((action) => (
                                            <label key={action} className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="radio"
                                                    name="recommendation"
                                                    value={action}
                                                    checked={myRecommendation === action}
                                                    onChange={() => setMyRecommendation(action)}
                                                />
                                                {recommendationLabel[action]}
                                            </label>
                                        ))}
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <label className="text-xs font-semibold">
                                            Comments {myRecommendation !== 'accepted' && '(at least one required)'}
                                        </label>
                                        {myComments.length > 0 && (
                                            <div className="flex flex-col gap-2">
                                                {myComments.map((comment, index) => (
                                                    <div
                                                        key={index}
                                                        className="flex items-start justify-between gap-2 rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-900"
                                                    >
                                                        <div>
                                                            <span className="mr-1.5 inline-flex rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-[#4c8a1f] dark:bg-black/20">
                                                                {labelForSection(comment.section)}
                                                            </span>
                                                            <span className="text-muted-foreground">{comment.body}</span>
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() => removeComment(index)}
                                                            className="text-muted-foreground shrink-0 hover:text-red-600"
                                                            aria-label="Remove comment"
                                                        >
                                                            <X className="size-3.5" />
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                        <div className="flex flex-col gap-2 rounded-lg border p-3">
                                            <Select value={draftSection} onValueChange={setDraftSection}>
                                                <SelectTrigger className="h-8 text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="overall">Overall</SelectItem>
                                                    {ABSTRACT_SECTIONS.map(({ key, label }) => (
                                                        <SelectItem key={key} value={key}>
                                                            {label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <Textarea
                                                value={draftBody}
                                                onChange={(event) => setDraftBody(event.target.value)}
                                                rows={3}
                                                placeholder="Write clear, actionable feedback for the author."
                                                className="text-sm"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={addComment}
                                                disabled={!draftBody.trim()}
                                                className="w-fit"
                                            >
                                                <Plus className="size-3.5" />
                                                Add comment
                                            </Button>
                                        </div>
                                    </div>
                                    {recommendError && <p className="text-sm text-red-700">{recommendError}</p>}
                                    <Button type="submit" size="sm" disabled={recommending} className="bg-[#4c8a1f] hover:bg-[#3f751a]">
                                        {myDecision ? 'Update recommendation' : 'Submit recommendation'}
                                    </Button>
                                    <p className="text-muted-foreground text-xs">
                                        {otherDecision
                                            ? 'The other assigned reviewer has submitted their recommendation.'
                                            : 'The other assigned reviewer has not decided yet.'}
                                    </p>
                                </form>
                            </section>
                        )}

                        {/* Only shown when there is something to do. Once decided, the
                            outcome and its reasoning lead the page instead — this panel
                            used to render a second, greyer copy of it under a heading
                            that promised an action it no longer offered. */}
                        {isAdmin && submission.status === 'submitted' && (
                            <section className="bg-card rounded-2xl border p-5">
                                <h2 className="text-lg font-semibold">{canDecide ? 'Final decision' : 'Decision'}</h2>
                                {canDecide ? (
                                    <>
                                        <label htmlFor="decision-notes" className="mt-4 block text-sm font-semibold">
                                            Decision notes
                                        </label>
                                        <p className="text-muted-foreground mt-1 text-xs leading-5">
                                            Required when requesting revisions or permanently rejecting. Included in the author’s email.
                                        </p>
                                        <Textarea
                                            id="decision-notes"
                                            value={notes}
                                            onChange={(event) => setNotes(event.target.value)}
                                            rows={6}
                                            className="mt-3"
                                            placeholder="Write clear, actionable feedback for the author."
                                            disabled={processing}
                                        />
                                        <InputError message={errors.decision_notes} className="mt-2" />

                                        <div className="mt-5 grid gap-2">
                                            <Button
                                                onClick={() => decide('accepted')}
                                                disabled={processing}
                                                className="h-11 bg-[#4c8a1f] font-bold hover:bg-[#3f751a]"
                                            >
                                                <Check className="size-4" />
                                                Accept abstract
                                            </Button>
                                            <Button
                                                onClick={() => decide('revision_requested')}
                                                disabled={processing || !notes.trim()}
                                                variant="outline"
                                                className="h-11 border-[#135eeb]/35 font-bold text-[#135eeb] hover:bg-[#135eeb]/5"
                                            >
                                                <FilePenLine className="size-4" />
                                                Request revision
                                            </Button>
                                            <Button
                                                onClick={() => decide('rejected')}
                                                disabled={processing || !notes.trim()}
                                                variant="outline"
                                                className="h-11 border-red-200 font-bold text-red-700 hover:bg-red-50 hover:text-red-800"
                                            >
                                                <X className="size-4" />
                                                Reject permanently
                                            </Button>
                                        </div>
                                        <p className="text-muted-foreground mt-4 text-center text-xs">Accept and permanent rejection are final.</p>
                                    </>
                                ) : (
                                    <div className="mt-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                                        <div className="flex items-center gap-2 font-semibold">
                                            <Clock3 className="size-4 text-[#4c8a1f]" />
                                            Waiting on reviewers
                                        </div>
                                        <p className="text-muted-foreground mt-2 text-sm leading-6">
                                            {!bothReviewersAssigned
                                                ? 'Assign two reviewers above to start the review.'
                                                : 'Both assigned reviewers must submit a recommendation. If they both recommend acceptance, the abstract is accepted automatically — otherwise you’ll be asked to decide here.'}
                                        </p>
                                    </div>
                                )}
                            </section>
                        )}
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
