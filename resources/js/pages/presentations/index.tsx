import { DashboardCard, IconTile } from '@/components/dashboard-card';
import { StatusPill, type PillTone } from '@/components/status-pill';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CircleCheck, Clock3, FileUp, Lock, Presentation as PresentationIcon, ShieldAlert } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Presentations', href: '/presentations' }];

type SubmissionStatus = 'submitted' | 'revision_requested' | 'accepted' | 'rejected';
type PresentationStatus = 'pending' | 'uploaded';

interface Submission {
    id: number;
    title: string;
    status: SubmissionStatus;
    subtheme: { title: string } | null;
    presentation_type: 'oral' | 'poster';
    presentation_status: PresentationStatus;
    presentation_original_name: string | null;
    can_upload: boolean;
}

interface PresentationWindow {
    deadline: string | null;
    is_open: boolean;
    closed_message: string | null;
}

interface PresentationsIndexProps {
    submissions: Submission[];
    window: PresentationWindow;
}

const statusExplanation: Record<Exclude<SubmissionStatus, 'accepted'>, string> = {
    submitted: 'Presentation uploads open once this abstract is accepted.',
    revision_requested: 'Presentation uploads open once your revision is accepted.',
    rejected: 'This abstract was not accepted, so it has no presentation slot.',
};

function formatDeadline(value: string) {
    const [y, m, d] = value.split('-').map(Number);
    return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(
        new Date(Date.UTC(y, m - 1, d)),
    );
}

export default function PresentationsIndex({ submissions, window: presentationWindow }: PresentationsIndexProps) {
    const eligible = submissions.filter((s) => s.status === 'accepted');
    const uploadedCount = eligible.filter((s) => s.presentation_status === 'uploaded').length;
    const outstandingCount = eligible.length - uploadedCount;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Presentations" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 pb-10 md:p-6 md:pb-12">
                <section className="dark:from-card relative overflow-hidden rounded-[24px] border border-[#4c8a1f]/10 bg-[linear-gradient(135deg,#ffffff_0%,#ffffff_62%,#f3f9ee_100%)] p-6 shadow-[0_8px_30px_rgba(76,138,31,0.08)] md:p-8 dark:to-[#132312]">
                    <div className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-4">
                            <IconTile tone="green">
                                <PresentationIcon className="size-5" />
                            </IconTile>
                            <div>
                                <h1 className="font-serif text-2xl font-semibold md:text-3xl">Presentations</h1>
                                <p className="text-muted-foreground mt-2 max-w-xl text-sm leading-6">
                                    Your slide or poster file for every accepted abstract, in one place.
                                </p>
                                {eligible.length > 0 && (
                                    <div className="mt-4 flex flex-wrap gap-2 text-xs font-semibold tabular-nums">
                                        <span className="rounded-full bg-[#eef7e6] px-3 py-1.5 text-[#4c8a1f] dark:bg-[#67b52f]/15 dark:text-[#8fd45a]">
                                            {uploadedCount} submitted
                                        </span>
                                        {outstandingCount > 0 && (
                                            <span className="rounded-full bg-blue-50 px-3 py-1.5 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                                {outstandingCount} still to upload
                                            </span>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </section>

                <DashboardCard className="bg-slate-50/70 dark:bg-slate-900/40">
                    <h2 className="text-sm font-bold tracking-wide uppercase">How this works</h2>
                    <ul className="mt-3 space-y-2.5 text-sm leading-6">
                        <li className="flex items-start gap-2.5">
                            <CircleCheck className="mt-0.5 size-4 shrink-0 text-[#4c8a1f]" />
                            <span>
                                Presentations are not reviewed. Whatever file is on record when the deadline passes is what you'll present — there's
                                no approval step.
                            </span>
                        </li>
                        <li className="flex items-start gap-2.5">
                            <CircleCheck className="mt-0.5 size-4 shrink-0 text-[#4c8a1f]" />
                            <span>Upload opens once an abstract is accepted — you can't submit a file before then.</span>
                        </li>
                        <li className="flex items-start gap-2.5">
                            <CircleCheck className="mt-0.5 size-4 shrink-0 text-[#4c8a1f]" />
                            <span>You can replace your file as many times as you need to, right up until the deadline.</span>
                        </li>
                        <li className="flex items-start gap-2.5">
                            <CircleCheck className="mt-0.5 size-4 shrink-0 text-[#4c8a1f]" />
                            <span>Your assigned reviewers and the conference organizers can view whatever file you've uploaded.</span>
                        </li>
                    </ul>
                </DashboardCard>

                {!presentationWindow.is_open && (
                    <div className="flex items-start gap-3 rounded-2xl border border-amber-300/40 bg-[linear-gradient(135deg,#fef6e6_0%,#ffffff_75%)] p-5 text-sm leading-6 dark:border-amber-400/25 dark:bg-[linear-gradient(135deg,rgba(217,158,10,0.16)_0%,rgba(217,158,10,0.03)_100%)]">
                        <ShieldAlert className="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <p>{presentationWindow.closed_message ?? 'The upload deadline has passed. Presentation files can no longer be changed.'}</p>
                    </div>
                )}

                {submissions.length === 0 ? (
                    <DashboardCard className="flex min-h-72 flex-col items-center justify-center text-center">
                        <IconTile tone="green">
                            <PresentationIcon className="size-5" />
                        </IconTile>
                        <h2 className="mt-4 text-lg font-semibold">No abstracts yet</h2>
                        <p className="text-muted-foreground mt-2 max-w-sm text-sm leading-6">
                            Submit an abstract first — once it's accepted, you'll upload your presentation file here.
                        </p>
                        <Link
                            href={route('abstracts.index')}
                            className="mt-5 inline-flex h-11 items-center gap-2 rounded-xl bg-[#4c8a1f] px-5 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-[#3f751a] hover:shadow-md"
                        >
                            Go to my abstracts
                            <ArrowRight className="size-4" />
                        </Link>
                    </DashboardCard>
                ) : (
                    <section className="space-y-4" aria-label="Presentations">
                        {submissions.map((submission) => {
                            const isAccepted = submission.status === 'accepted';
                            const isUploaded = submission.presentation_status === 'uploaded';
                            const tone: PillTone = !isAccepted ? 'neutral' : isUploaded ? 'positive' : 'attention';
                            const pillLabel = !isAccepted
                                ? 'Locked'
                                : isUploaded
                                  ? 'Submitted'
                                  : presentationWindow.is_open
                                    ? 'Not uploaded'
                                    : 'Missed deadline';

                            return (
                                <DashboardCard key={submission.id} className="overflow-hidden p-0">
                                    <div className="p-6 md:p-7">
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="min-w-0">
                                                <h2 className="text-lg leading-7 font-semibold md:text-xl">{submission.title}</h2>
                                                {submission.subtheme && (
                                                    <p className="text-muted-foreground mt-2 text-sm leading-6">{submission.subtheme.title}</p>
                                                )}
                                            </div>
                                            <StatusPill tone={tone}>{pillLabel}</StatusPill>
                                        </div>

                                        <div className="text-muted-foreground mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-slate-100 pt-4 text-sm dark:border-slate-800">
                                            <span className="flex items-center gap-2 capitalize">
                                                <PresentationIcon className="size-4 text-[#67b52f]" />
                                                {submission.presentation_type} presentation
                                            </span>
                                            {isAccepted && submission.presentation_original_name && (
                                                <span className="flex min-w-0 items-center gap-2">
                                                    <FileUp className="size-4 text-[#135eeb]" />
                                                    <span className="truncate">{submission.presentation_original_name}</span>
                                                </span>
                                            )}
                                            {isAccepted && !isUploaded && presentationWindow.is_open && presentationWindow.deadline && (
                                                <span className="flex items-center gap-2">
                                                    <Clock3 className="size-4 text-[#135eeb]" />
                                                    Upload by {formatDeadline(presentationWindow.deadline)}
                                                </span>
                                            )}
                                            {!isAccepted && (
                                                <span className="flex items-center gap-2">
                                                    <Lock className="size-4" />
                                                    {statusExplanation[submission.status as Exclude<SubmissionStatus, 'accepted'>]}
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    {isAccepted && (
                                        <div className="flex flex-wrap justify-end gap-3 border-t border-slate-100 bg-slate-50/70 px-6 py-4 dark:border-slate-800 dark:bg-slate-900/40">
                                            <Link
                                                href={route('abstracts.presentation.show', submission.id)}
                                                className={cn(
                                                    'group inline-flex min-h-10 items-center gap-3 rounded-xl px-4 py-2 text-sm font-bold shadow-sm transition hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
                                                    isUploaded
                                                        ? 'bg-[#4c8a1f] text-white hover:bg-[#3f751a] hover:shadow-md focus-visible:ring-[#4c8a1f]'
                                                        : 'border border-[#4c8a1f]/25 bg-white text-[#4c8a1f] hover:bg-[#eef7e6] focus-visible:ring-[#4c8a1f] dark:bg-transparent',
                                                )}
                                            >
                                                <FileUp className="size-4" />
                                                {isUploaded ? 'View or replace' : 'Upload presentation'}
                                            </Link>
                                        </div>
                                    )}
                                </DashboardCard>
                            );
                        })}
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
