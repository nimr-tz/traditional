import AbstractAuthorsField, { Author } from '@/components/abstract-authors-field';
import InputError from '@/components/input-error';
import { StatusBanner } from '@/components/status-banner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Clock3, FilePenLine, LoaderCircle, XCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Subtheme {
    id: number;
    title: string;
}

const ABSTRACT_SECTIONS = [
    { key: 'background', label: 'Background' },
    { key: 'objective', label: 'Objective' },
    { key: 'methods', label: 'Methods' },
    { key: 'results', label: 'Results' },
    { key: 'conclusion', label: 'Conclusion' },
] as const;

type SectionKey = (typeof ABSTRACT_SECTIONS)[number]['key'];

/**
 * Sections are nullable: abstracts submitted before the five-section split
 * carry their whole text in `background` and null everywhere else (see the
 * split_abstract_text_into_sections migration), so this must never assume a
 * string is present.
 */
function countWords(value: string | null | undefined): number {
    const trimmed = value?.trim() ?? '';
    return trimmed ? trimmed.split(/\s+/).length : 0;
}

interface ReviewerComment {
    id: number;
    section: SectionKey | null;
    body: string;
    addressed: boolean;
    reviewer_label: string;
    /** Which review round left this — earlier rounds are kept, not deleted. */
    round: number;
    is_current: boolean;
    created_at: string;
}

interface Submission {
    id: number;
    title: string;
    subtheme_id: number;
    presentation_type: string;
    authors: Author[];
    // Nullable for pre-split submissions — see countWords above.
    background: string | null;
    objective: string | null;
    methods: string | null;
    results: string | null;
    conclusion: string | null;
    status: 'submitted' | 'revision_requested' | 'accepted' | 'rejected';
    decision_notes: string | null;
}

interface EditAbstractProps {
    submission: Submission;
    subthemes: Subtheme[];
    presentationTypes: Record<string, string>;
    reviewerComments: ReviewerComment[];
}

interface AbstractFormData {
    title: string;
    subtheme_id: string;
    presentation_type: string;
    authors: Author[];
    background: string;
    objective: string;
    methods: string;
    results: string;
    conclusion: string;
    [key: string]: string | Author[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Abstracts', href: '/abstracts' },
    { title: 'Edit Abstract', href: '#' },
];

function formatDate(value: string) {
    return new Intl.DateTimeFormat('en', { dateStyle: 'medium' }).format(new Date(value));
}

export default function EditAbstract({ submission, subthemes, presentationTypes, reviewerComments }: EditAbstractProps) {
    const isRevision = submission.status === 'revision_requested';
    const editable = submission.status === 'submitted' || isRevision;

    const { data, setData, put, processing, errors } = useForm<AbstractFormData>({
        title: submission.title,
        subtheme_id: String(submission.subtheme_id),
        presentation_type: submission.presentation_type,
        authors: submission.authors,
        // A pre-split abstract has nulls here; a textarea needs a string, and
        // sending null back would fail the required-section validation anyway.
        background: submission.background ?? '',
        objective: submission.objective ?? '',
        methods: submission.methods ?? '',
        results: submission.results ?? '',
        conclusion: submission.conclusion ?? '',
    });

    const wordCount = ABSTRACT_SECTIONS.reduce((total, { key }) => total + countWords(data[key as SectionKey]), 0);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('abstracts.update', submission.id));
    };

    const toggleAddressed = (commentId: number) => {
        router.post(route('abstracts.comments.toggle-addressed', [submission.id, commentId]), {}, { preserveScroll: true, preserveState: true });
    };

    const commentCountFor = (section: SectionKey) => reviewerComments.filter((c) => c.section === section).length;
    const addressedCount = reviewerComments.filter((c) => c.addressed).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Abstract" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                {isRevision && (
                    <StatusBanner
                        tone="warning"
                        icon={FilePenLine}
                        title="Revision requested"
                        description="Two reviewers have read your abstract. Their comments are shown alongside the form — update the relevant sections, mark each comment as addressed, and resubmit for another review."
                        note={submission.decision_notes}
                        noteLabel="Editorial decision · TMSC Scientific Committee"
                    />
                )}

                {submission.status === 'submitted' && (
                    <StatusBanner
                        tone="info"
                        icon={Clock3}
                        title="Awaiting review"
                        description="Your abstract has been submitted and is waiting for the assigned reviewers to weigh in. You can still edit it until a decision is made."
                    />
                )}

                {submission.status === 'accepted' && (
                    <StatusBanner
                        tone="success"
                        icon={CheckCircle2}
                        title="Abstract accepted"
                        description="Congratulations — this abstract has been accepted. It's now read-only. Head to the presentation page to upload your slides."
                        note={submission.decision_notes}
                        noteLabel="Reviewer notes"
                    />
                )}

                {submission.status === 'rejected' && (
                    <StatusBanner
                        tone="negative"
                        icon={XCircle}
                        title="Not accepted"
                        description="This abstract was not accepted for the conference. It's now read-only."
                        note={submission.decision_notes}
                        noteLabel="Reviewer notes"
                    />
                )}

                <div className={cn(reviewerComments.length > 0 && 'grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]')}>
                    <form onSubmit={submit} className="flex flex-col gap-6">
                        <fieldset disabled={!editable} className="contents">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Abstract title *</Label>
                                <Input
                                    id="title"
                                    className="font-semibold uppercase"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                />
                                <p className="text-muted-foreground text-xs">The title should be in bold capital letters.</p>
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-6 sm:grid-cols-[1fr_auto]">
                                <div className="grid gap-2">
                                    <Label htmlFor="subtheme_id">Sub-theme *</Label>
                                    <Select value={data.subtheme_id} onValueChange={(value) => setData('subtheme_id', value)} disabled={!editable}>
                                        <SelectTrigger id="subtheme_id" className="h-11">
                                            <SelectValue placeholder="Select a sub-theme" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {subthemes.map((s) => (
                                                <SelectItem key={s.id} value={String(s.id)}>
                                                    {s.title}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.subtheme_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Presentation type *</Label>
                                    <div className="flex gap-2">
                                        {Object.entries(presentationTypes).map(([key, label]) => {
                                            const selected = data.presentation_type === key;
                                            return (
                                                <button
                                                    key={key}
                                                    type="button"
                                                    disabled={!editable}
                                                    onClick={() => setData('presentation_type', key)}
                                                    className={`h-11 flex-1 rounded-md border px-5 text-sm font-semibold whitespace-nowrap transition-colors disabled:cursor-not-allowed disabled:opacity-70 ${
                                                        selected
                                                            ? 'border-[#4c8a1f] bg-[#eef7e6] text-[#4c8a1f]'
                                                            : 'border-input bg-background text-foreground'
                                                    }`}
                                                >
                                                    {label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                    <InputError message={errors.presentation_type} />
                                </div>
                            </div>

                            <AbstractAuthorsField authors={data.authors} onChange={(authors) => setData('authors', authors)} errors={errors} />

                            <div className="grid gap-2">
                                <div className="flex items-center justify-between">
                                    <Label>Abstract</Label>
                                    <p className={`text-xs ${wordCount > 300 ? 'text-destructive' : 'text-muted-foreground'}`}>
                                        {wordCount} / 300 words combined
                                    </p>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    Fill in each section separately. The 300-word limit applies to all five sections combined.
                                </p>
                            </div>

                            {ABSTRACT_SECTIONS.map(({ key, label }) => {
                                const count = commentCountFor(key);

                                return (
                                    <div key={key} className="grid gap-2">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Label htmlFor={key} className="text-[#4c8a1f]">
                                                    {label}
                                                </Label>
                                                {count > 0 && (
                                                    <span className="inline-flex items-center gap-1 rounded-full border border-amber-300/50 bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                                                        {count} {count === 1 ? 'comment' : 'comments'}
                                                    </span>
                                                )}
                                            </div>
                                            <span className="text-muted-foreground text-[11px] tabular-nums">{countWords(data[key])} words</span>
                                        </div>
                                        <Textarea
                                            id={key}
                                            rows={4}
                                            value={data[key]}
                                            onChange={(e) => setData(key, e.target.value)}
                                            disabled={!editable}
                                        />
                                        <InputError message={errors[key]} />
                                    </div>
                                );
                            })}
                        </fieldset>

                        {editable && (
                            <Button type="submit" disabled={processing} className="w-fit bg-[#4c8a1f] font-bold hover:bg-[#3f751a]">
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                {isRevision ? 'Resubmit revised abstract' : 'Save changes'}
                            </Button>
                        )}
                    </form>

                    {reviewerComments.length > 0 && (
                        <aside className="flex flex-col gap-3.5 lg:sticky lg:top-6">
                            <div className="flex items-baseline justify-between gap-2">
                                <h2 className="font-serif text-lg font-semibold">Reviewer comments</h2>
                                <span className="text-xs font-semibold text-[#4c8a1f] tabular-nums">
                                    {addressedCount} of {reviewerComments.length} addressed
                                </span>
                            </div>
                            <p className="text-muted-foreground text-xs leading-5">
                                Comments are anonymised and shown in full. Tick each one off as you work through it.
                            </p>
                            <div className="flex flex-col gap-3">
                                {reviewerComments.map((comment) => (
                                    <div
                                        key={comment.id}
                                        className={cn(
                                            'rounded-xl border p-4 shadow-sm transition',
                                            comment.addressed ? 'border-[#67b52f]/45 opacity-70' : 'border-slate-200 dark:border-slate-700',
                                        )}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span
                                                className={cn(
                                                    'inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold tracking-wide uppercase',
                                                    comment.section ? 'bg-[#eef7e6] text-[#4c8a1f]' : 'bg-[#eaf1ff] text-[#135eeb]',
                                                )}
                                            >
                                                {comment.section
                                                    ? (ABSTRACT_SECTIONS.find((s) => s.key === comment.section)?.label ?? comment.section)
                                                    : 'Overall'}
                                            </span>
                                            <span className="text-muted-foreground text-xs">{comment.reviewer_label}</span>
                                            {/* Feedback from an earlier round is kept for reference — flag it
                                                so it isn't mistaken for something still outstanding. */}
                                            {!comment.is_current && (
                                                <span className="text-muted-foreground rounded-full border px-2 py-0.5 text-[11px]">
                                                    Round {comment.round} · earlier
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-2.5 text-sm leading-6">{comment.body}</p>
                                        <p className="text-muted-foreground mt-1.5 text-[11px]">{formatDate(comment.created_at)}</p>
                                        {editable && (
                                            <label className="mt-2.5 flex w-fit cursor-pointer items-center gap-2 text-xs font-semibold">
                                                <input
                                                    type="checkbox"
                                                    checked={comment.addressed}
                                                    onChange={() => toggleAddressed(comment.id)}
                                                    className="accent-[#4c8a1f]"
                                                />
                                                <span className={comment.addressed ? 'text-[#4c8a1f]' : 'text-muted-foreground'}>
                                                    {comment.addressed ? 'Addressed' : 'Mark as addressed'}
                                                </span>
                                            </label>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </aside>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
