import { DashboardCard, IconTile } from '@/components/dashboard-card';
import { StatusPill, type PillTone } from '@/components/status-pill';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CalendarDays, FileText, FileUp, Plus, Presentation } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Abstracts', href: '/abstracts' }];

type PresentationStatus = 'pending' | 'uploaded' | 'approved';

interface Submission {
    id: number;
    title: string;
    status: 'submitted' | 'revision_requested' | 'accepted' | 'rejected';
    presentation_type: string;
    presentation_status: PresentationStatus;
    subtheme: { title: string } | null;
    created_at: string;
}

const presentationLabel: Record<PresentationStatus, string> = {
    pending: 'Upload presentation',
    uploaded: 'Presentation submitted',
    approved: 'Presentation approved',
};

interface AbstractIndexProps {
    submissions: Submission[];
}

const statusTone: Record<Submission['status'], PillTone> = {
    submitted: 'neutral',
    revision_requested: 'attention',
    accepted: 'positive',
    rejected: 'negative',
};

const statusLabel: Record<Submission['status'], string> = {
    submitted: 'Submitted',
    revision_requested: 'Revision requested',
    accepted: 'Accepted',
    rejected: 'Not accepted',
};

function formatDate(value: string) {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          }).format(date);
}

export default function AbstractIndex({ submissions }: AbstractIndexProps) {
    const submittedCount = submissions.filter((submission) => submission.status === 'submitted').length;
    const acceptedCount = submissions.filter((submission) => submission.status === 'accepted').length;
    const revisionCount = submissions.filter((submission) => submission.status === 'revision_requested').length;
    const rejectedCount = submissions.filter((submission) => submission.status === 'rejected').length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Abstracts" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 pb-10 md:p-6 md:pb-12">
                <section className="dark:from-card rounded-[24px] border border-[#135eeb]/10 bg-[linear-gradient(135deg,#ffffff_0%,#ffffff_62%,#f1f6ff_100%)] p-6 shadow-[0_8px_30px_rgba(19,94,235,0.07)] md:p-8 dark:to-[#15233b]">
                    <div className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-4">
                            <IconTile tone="green">
                                <FileText className="size-5" />
                            </IconTile>
                            <div>
                                <h1 className="font-serif text-2xl font-semibold md:text-3xl">My abstracts</h1>
                                <div className="mt-3 flex flex-wrap gap-2 text-xs font-semibold tabular-nums">
                                    <span className="rounded-full bg-[#eaf1ff] px-3 py-1.5 text-[#135eeb] dark:bg-[#135eeb]/20 dark:text-[#8db4ff]">
                                        {submissions.length} {submissions.length === 1 ? 'abstract' : 'abstracts'}
                                    </span>
                                    {submittedCount > 0 && (
                                        <span className="rounded-full bg-slate-100 px-3 py-1.5 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                            {submittedCount} submitted
                                        </span>
                                    )}
                                    {acceptedCount > 0 && (
                                        <span className="rounded-full bg-[#eef7e6] px-3 py-1.5 text-[#4c8a1f] dark:bg-[#67b52f]/15 dark:text-[#8fd45a]">
                                            {acceptedCount} accepted
                                        </span>
                                    )}
                                    {revisionCount > 0 && (
                                        <span className="rounded-full bg-blue-50 px-3 py-1.5 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                            {revisionCount} revision {revisionCount === 1 ? 'requested' : 'requests'}
                                        </span>
                                    )}
                                    {rejectedCount > 0 && (
                                        <span className="bg-destructive/10 text-destructive rounded-full px-3 py-1.5">
                                            {rejectedCount} not accepted
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>

                        <Button asChild className="h-11 bg-[#4c8a1f] px-5 font-bold hover:bg-[#3f751a]">
                            <Link href={route('abstracts.create')}>
                                <Plus className="size-4" />
                                Submit new abstract
                            </Link>
                        </Button>
                    </div>
                </section>

                {submissions.length === 0 ? (
                    <DashboardCard className="flex min-h-72 flex-col items-center justify-center text-center">
                        <IconTile tone="green">
                            <FileText className="size-5" />
                        </IconTile>
                        <h2 className="mt-4 text-lg font-semibold">No abstracts submitted</h2>
                        <Button asChild className="mt-5 bg-[#4c8a1f] font-bold hover:bg-[#3f751a]">
                            <Link href={route('abstracts.create')}>Submit your first abstract</Link>
                        </Button>
                    </DashboardCard>
                ) : (
                    <section className="space-y-4" aria-label="Abstract submissions">
                        {submissions.map((submission) => (
                            <DashboardCard key={submission.id} className="overflow-hidden p-0">
                                <div className="p-6 md:p-7">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <h2 className="text-lg leading-7 font-semibold md:text-xl">{submission.title}</h2>
                                            {submission.subtheme && (
                                                <p className="text-muted-foreground mt-2 text-sm leading-6">{submission.subtheme.title}</p>
                                            )}
                                        </div>
                                        <StatusPill tone={statusTone[submission.status]}>{statusLabel[submission.status]}</StatusPill>
                                    </div>

                                    <div className="text-muted-foreground mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-slate-100 pt-4 text-sm dark:border-slate-800">
                                        <span className="flex items-center gap-2 capitalize">
                                            <Presentation className="size-4 text-[#67b52f]" />
                                            {submission.presentation_type} presentation
                                        </span>
                                        <span className="flex items-center gap-2">
                                            <CalendarDays className="size-4 text-[#135eeb]" />
                                            Submitted {formatDate(submission.created_at)}
                                        </span>
                                    </div>
                                </div>

                                <div className="flex flex-wrap justify-end gap-3 border-t border-slate-100 bg-slate-50/70 px-6 py-4 dark:border-slate-800 dark:bg-slate-900/40">
                                    {submission.status === 'accepted' && (
                                        <Link
                                            href={route('abstracts.presentation.show', submission.id)}
                                            className="group inline-flex min-h-10 items-center gap-3 rounded-xl border border-[#4c8a1f]/25 bg-white px-4 py-2 text-sm font-bold text-[#4c8a1f] shadow-sm transition hover:-translate-y-0.5 hover:bg-[#eef7e6] dark:bg-transparent"
                                        >
                                            <FileUp className="size-4" />
                                            {presentationLabel[submission.presentation_status]}
                                        </Link>
                                    )}
                                    <Link
                                        href={route('abstracts.edit', submission.id)}
                                        className="group inline-flex min-h-10 items-center gap-3 rounded-xl bg-[#135eeb] px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-[#0d4fca] hover:shadow-md focus-visible:ring-2 focus-visible:ring-[#135eeb] focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        {submission.status === 'revision_requested'
                                            ? 'Revise abstract'
                                            : submission.status === 'submitted'
                                              ? 'Edit abstract'
                                              : 'View abstract'}
                                        <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                                    </Link>
                                </div>
                            </DashboardCard>
                        ))}
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
