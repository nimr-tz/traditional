import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Clock3, FilePenLine, Presentation, UserRound, X } from 'lucide-react';
import { useState } from 'react';

type AbstractStatus = 'submitted' | 'revision_requested' | 'accepted' | 'rejected';
type ReviewAction = 'accepted' | 'revision_requested' | 'rejected';

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

interface Submission {
    id: number;
    title: string;
    abstract_text: string;
    authors: Author[];
    presentation_type: 'oral' | 'poster';
    status: AbstractStatus;
    decision_notes: string | null;
    created_at: string;
    resubmitted_at: string | null;
    decided_at: string | null;
    user: {
        name: string;
        email: string;
        phone: string | null;
        institution: string | null;
        country: string | null;
    };
    subtheme: { title: string } | null;
    reviewer: { name: string } | null;
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

export default function AbstractReviewShow({ submission }: { submission: Submission }) {
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const status = statusConfig[submission.status];
    const wordCount = submission.abstract_text.trim() ? submission.abstract_text.trim().split(/\s+/).length : 0;
    const canDecide = submission.status === 'submitted';
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin' },
        { title: 'Abstract Review', href: '/admin/abstracts' },
        { title: submission.title, href: route('admin.abstracts.show', submission.id) },
    ];

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
                                    <span className="text-muted-foreground text-xs tabular-nums">{wordCount} words</span>
                                </div>
                                <div className="text-[15px] leading-8 whitespace-pre-wrap text-slate-800 dark:text-slate-200">
                                    {submission.abstract_text}
                                </div>
                            </section>
                        </article>

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

                        <section className="bg-card rounded-2xl border p-5">
                            <h2 className="text-lg font-semibold">Reviewer decision</h2>
                            {canDecide ? (
                                <>
                                    <label htmlFor="decision-notes" className="mt-4 block text-sm font-semibold">
                                        Reviewer comment
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
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
