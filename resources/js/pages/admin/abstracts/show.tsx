import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, Clock3, Download, FilePenLine, FileUp, Plus, Presentation, UserRound, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type AbstractStatus = 'submitted' | 'revision_requested' | 'accepted' | 'rejected';
type ReviewAction = 'accepted' | 'revision_requested' | 'rejected';
type PresentationStatus = 'pending' | 'uploaded' | 'approved';

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
    recommendation: ReviewAction;
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
    background: string;
    objective: string;
    methods: string;
    results: string;
    conclusion: string;
    authors: Author[];
    presentation_type: 'oral' | 'poster';
    presentation_status: PresentationStatus;
    presentation_original_name: string | null;
    presentation_uploaded_at: string | null;
    presentation_notes: string | null;
    presentation_review_notes: string | null;
    status: AbstractStatus;
    decision_notes: string | null;
    created_at: string;
    resubmitted_at: string | null;
    decided_at: string | null;
    user: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        institution: string | null;
        country: string | null;
    };
    subtheme: { title: string } | null;
    reviewer: { name: string } | null;
    reviewer_one: ReviewerRef | null;
    reviewer_two: ReviewerRef | null;
    reviewer_decisions: ReviewerDecision[];
    review_history: History[];
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

interface AbstractReviewShowProps {
    submission: Submission;
    eligibleReviewers: ReviewerRef[];
}

export default function AbstractReviewShow({ submission, eligibleReviewers }: AbstractReviewShowProps) {
    const { auth } = usePage<SharedData>().props;
    const isAdmin = auth.user.role === 'admin' || auth.user.role === 'super_admin';
    const isAssignedReviewer = auth.user.id === submission.reviewer_one?.id || auth.user.id === submission.reviewer_two?.id;
    const myDecision = submission.reviewer_decisions.find((d) => d.reviewer_id === auth.user.id) ?? null;
    const otherDecision = submission.reviewer_decisions.find((d) => d.reviewer_id !== auth.user.id) ?? null;
    const bothReviewersAssigned = Boolean(submission.reviewer_one && submission.reviewer_two);
    const bothReviewersDecided = bothReviewersAssigned && submission.reviewer_decisions.length === 2;

    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [presentationNotes, setPresentationNotes] = useState('');
    const [presentationProcessing, setPresentationProcessing] = useState(false);
    const status = statusConfig[submission.status];
    const wordCount = ABSTRACT_SECTIONS.reduce((total, { key }) => {
        const value = submission[key];
        return total + (value.trim() ? value.trim().split(/\s+/).length : 0);
    }, 0);
    const canDecide = isAdmin && submission.status === 'submitted' && bothReviewersDecided;
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

    const approvePresentation = () => {
        if (!window.confirm('Approve this presentation file?')) return;

        router.post(
            route('admin.abstracts.presentation.approve', submission.id),
            {},
            { preserveScroll: true, onStart: () => setPresentationProcessing(true), onFinish: () => setPresentationProcessing(false) },
        );
    };

    const rejectPresentation = () => {
        if (!presentationNotes.trim()) return;

        router.post(
            route('admin.abstracts.presentation.reject', submission.id),
            { notes: presentationNotes },
            {
                preserveScroll: true,
                onStart: () => setPresentationProcessing(true),
                onSuccess: () => setPresentationNotes(''),
                onFinish: () => setPresentationProcessing(false),
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
                        <article className="bg-card rounded-2xl border">
                            <header className="border-b p-6 md:p-8">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className={`inline-flex rounded-full px-3 py-1 text-xs font-bold ring-1 ring-inset ${status.className}`}>
                                        {status.label}
                                    </span>
                                    {submission.resubmitted_at && <span className="text-xs font-bold text-[#135eeb]">Revised submission</span>}
                                </div>
                                <h1 className="mt-4 font-serif text-3xl leading-tight font-semibold text-balance md:text-4xl">{submission.title}</h1>
                                <div className="text-muted-foreground mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                                    <span className="inline-flex items-center gap-2 capitalize">
                                        <Presentation className="size-4 text-[#4c8a1f]" />
                                        {submission.presentation_type} presentation
                                    </span>
                                    {submission.subtheme && <span>{submission.subtheme.title}</span>}
                                    <span>
                                        {submission.resubmitted_at ? 'Resubmitted' : 'Submitted'}{' '}
                                        {formatDate(submission.resubmitted_at ?? submission.created_at)}
                                    </span>
                                </div>
                            </header>

                            <section className="p-6 md:p-8">
                                <div className="mb-5 flex items-end justify-between border-b pb-3">
                                    <h2 className="text-sm font-bold tracking-[0.14em] uppercase">Abstract</h2>
                                    <span className="text-muted-foreground text-xs tabular-nums">{wordCount} words combined</span>
                                </div>
                                <div className="space-y-6">
                                    {ABSTRACT_SECTIONS.map(({ key, label }) => (
                                        <div key={key}>
                                            <h3 className="text-xs font-bold tracking-[0.1em] text-[#4c8a1f] uppercase">{label}</h3>
                                            <div className="mt-2 text-[15px] leading-8 whitespace-pre-wrap text-slate-800 dark:text-slate-200">
                                                {submission[key]}
                                            </div>
                                        </div>
                                    ))}
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
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            {submission.presentation_status === 'pending' && !submission.presentation_original_name
                                                ? 'Not uploaded yet.'
                                                : submission.presentation_status === 'pending'
                                                  ? 'Rejected — waiting for a replacement.'
                                                  : submission.presentation_status === 'uploaded'
                                                    ? 'Awaiting review.'
                                                    : 'Approved.'}
                                        </p>
                                    </div>
                                </div>

                                {submission.presentation_original_name && (
                                    <div className="dark:bg-muted/40 mt-5 flex items-center justify-between gap-4 rounded-xl bg-slate-50 p-4">
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
                                        <Button asChild variant="outline" size="sm" className="shrink-0">
                                            <a href={route('admin.abstracts.presentation.download', submission.id)}>
                                                <Download className="size-4" />
                                                Download
                                            </a>
                                        </Button>
                                    </div>
                                )}

                                {submission.presentation_status === 'uploaded' && (
                                    <div className="mt-5 space-y-3">
                                        <Button
                                            onClick={approvePresentation}
                                            disabled={presentationProcessing}
                                            className="bg-[#4c8a1f] font-bold hover:bg-[#3f751a]"
                                        >
                                            <Check className="size-4" />
                                            Approve presentation
                                        </Button>

                                        <div className="grid gap-2">
                                            <Textarea
                                                value={presentationNotes}
                                                onChange={(e) => setPresentationNotes(e.target.value)}
                                                rows={3}
                                                placeholder="Explain what needs to change before this can be approved."
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={rejectPresentation}
                                                disabled={presentationProcessing || !presentationNotes.trim()}
                                                className="w-fit border-red-200 font-bold text-red-700 hover:bg-red-50 hover:text-red-800"
                                            >
                                                <X className="size-4" />
                                                Reject presentation
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </section>
                        )}

                        <section className="bg-card rounded-2xl border p-6 md:p-8">
                            <h2 className="text-lg font-semibold">Authors</h2>
                            <div className="mt-4 divide-y">
                                {submission.authors.map((author, index) => (
                                    <div key={`${author.name}-${index}`} className="flex items-start justify-between gap-4 py-4 first:pt-0 last:pb-0">
                                        <div>
                                            <p className="font-semibold">{author.name}</p>
                                            <p className="text-muted-foreground mt-1 text-sm">{author.institution}</p>
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
                        <section className="bg-card rounded-2xl border p-5">
                            <div className="flex items-center gap-3">
                                <span className="flex size-10 items-center justify-center rounded-xl bg-[#eef7e6] text-[#4c8a1f]">
                                    <UserRound className="size-5" />
                                </span>
                                <div>
                                    <h2 className="font-semibold">Submitting author</h2>
                                    <p className="text-muted-foreground text-xs">Participant record</p>
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

                        {(submission.reviewer_one || submission.reviewer_two) && (
                            <section className="bg-card rounded-2xl border p-5">
                                <h2 className="text-lg font-semibold">Reviewer recommendations</h2>
                                <div className="mt-4 space-y-3">
                                    {[submission.reviewer_one, submission.reviewer_two].map((reviewer, index) => {
                                        if (!reviewer) return null;
                                        const decision = submission.reviewer_decisions.find((d) => d.reviewer_id === reviewer.id);

                                        return (
                                            <div key={reviewer.id} className="rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="text-sm font-semibold">
                                                        Reviewer {index === 0 ? 'A' : 'B'} · {reviewer.name}
                                                    </p>
                                                    {decision ? (
                                                        <span className="text-xs font-bold text-[#4c8a1f]">
                                                            {recommendationLabel[decision.recommendation]}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground text-xs">Pending</span>
                                                    )}
                                                </div>
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

                        {isAssignedReviewer && submission.status === 'submitted' && (
                            <section className="bg-card rounded-2xl border p-5">
                                <h2 className="text-lg font-semibold">Your recommendation</h2>
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

                        {isAdmin && (
                            <section className="bg-card rounded-2xl border p-5">
                                <h2 className="text-lg font-semibold">Final decision</h2>
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
                                ) : submission.status === 'submitted' ? (
                                    <div className="mt-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                                        <div className="flex items-center gap-2 font-semibold">
                                            <Clock3 className="size-4 text-[#4c8a1f]" />
                                            Waiting on reviewers
                                        </div>
                                        <p className="text-muted-foreground mt-2 text-sm leading-6">
                                            {!bothReviewersAssigned
                                                ? 'Assign two reviewers above to start the review.'
                                                : 'Both assigned reviewers must submit a recommendation before you can decide.'}
                                        </p>
                                    </div>
                                ) : (
                                    <div className="mt-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                                        <div className="flex items-center gap-2 font-semibold">
                                            <Clock3 className="size-4 text-[#4c8a1f]" />
                                            {status.label}
                                        </div>
                                        {submission.decision_notes && (
                                            <p className="text-muted-foreground mt-3 text-sm leading-6">{submission.decision_notes}</p>
                                        )}
                                        {submission.reviewer && (
                                            <p className="text-muted-foreground mt-3 text-xs">Reviewed by {submission.reviewer.name}</p>
                                        )}
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
