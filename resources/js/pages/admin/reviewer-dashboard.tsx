import { IconTile } from '@/components/dashboard-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, ClipboardCheck, FileClock, FilePenLine } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin', href: '/admin' }];

/**
 * No `user` field on purpose — review is blind, so the author never reaches this
 * payload. See App\Support\BlindReview.
 */
interface QueueAbstract {
    id: number;
    title: string;
    presentation_type: 'oral' | 'poster';
    created_at: string;
    resubmitted_at: string | null;
    review_round: number;
    subtheme: { title: string } | null;
}

interface ReviewerDashboardProps {
    stats: {
        assigned_total: number;
        awaiting_my_decision: number;
        reviewed_by_me: number;
        revisions_i_requested: number;
        acceptances_i_recommended: number;
    };
    reviewQueue: QueueAbstract[];
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value));
}

export default function ReviewerDashboard({ stats, reviewQueue }: ReviewerDashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reviewer Dashboard" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 pb-12 md:p-6">
                <header className="relative overflow-hidden rounded-3xl bg-[#0d3fa8] px-6 py-8 text-white md:px-9 md:py-10">
                    <div className="absolute -top-24 -right-16 size-64 rounded-full border-[44px] border-white/5" />
                    <div className="absolute -bottom-20 -left-10 size-56 rounded-full bg-[#8fd45a]/10 blur-2xl" />
                    <div className="relative flex flex-col gap-7 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p className="text-xs font-black tracking-[0.22em] text-[#8fd45a] uppercase">TMSC abstract review</p>
                            <h1 className="mt-3 max-w-2xl font-serif text-3xl font-semibold text-balance md:text-4xl">Reviewer workspace</h1>
                            <p className="mt-3 max-w-xl text-sm leading-6 text-white/75">
                                Only abstracts assigned to you appear here. Registrations, payments, and student verification are handled by
                                conference admins.
                            </p>
                        </div>
                        <div className="rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur-sm">
                            <div className="text-3xl font-bold tabular-nums">{stats.awaiting_my_decision}</div>
                            <div className="mt-1 text-xs font-bold tracking-wide text-white/70 uppercase">Awaiting your decision</div>
                        </div>
                    </div>
                </header>

                {/* The first three account for everything assigned: awaiting +
                    reviewed = assigned. The last two describe the recommendations
                    this reviewer has made, which is a different question. */}
                <section className="grid gap-3 sm:grid-cols-3" aria-label="Your review totals">
                    {[
                        {
                            label: 'Assigned to you',
                            value: stats.assigned_total,
                            hint: `${stats.awaiting_my_decision} awaiting · ${stats.reviewed_by_me} reviewed`,
                            icon: ClipboardCheck,
                            tone: 'blue' as const,
                            classes: 'border-blue-200 bg-blue-50 dark:border-blue-900/40 dark:bg-blue-950/30',
                            valueClasses: 'text-blue-700 dark:text-blue-300',
                        },
                        {
                            label: 'Awaiting your decision',
                            value: stats.awaiting_my_decision,
                            hint: 'Includes revised abstracts needing another look',
                            icon: FileClock,
                            tone: 'amber' as const,
                            classes: 'border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/30',
                            valueClasses: 'text-amber-700 dark:text-amber-300',
                        },
                        {
                            label: 'Revisions you requested',
                            value: stats.revisions_i_requested,
                            hint: `${stats.acceptances_i_recommended} acceptance${stats.acceptances_i_recommended === 1 ? '' : 's'} recommended`,
                            icon: FilePenLine,
                            tone: 'indigo' as const,
                            classes: 'border-indigo-200 bg-indigo-50 dark:border-indigo-900/40 dark:bg-indigo-950/30',
                            valueClasses: 'text-indigo-700 dark:text-indigo-300',
                        },
                    ].map((item) => {
                        const Icon = item.icon;
                        return (
                            <div key={item.label} className={`flex min-h-28 items-center justify-between rounded-2xl border p-5 ${item.classes}`}>
                                <div className="min-w-0">
                                    <div className={`text-3xl font-bold tabular-nums ${item.valueClasses}`}>{item.value}</div>
                                    <div className="text-muted-foreground mt-2 text-xs font-semibold">{item.label}</div>
                                    {item.hint && <div className="text-muted-foreground/80 mt-1 text-[11px] leading-4">{item.hint}</div>}
                                </div>
                                <IconTile tone={item.tone}>
                                    <Icon className="size-5" />
                                </IconTile>
                            </div>
                        );
                    })}
                </section>

                <section className="bg-card overflow-hidden rounded-2xl border">
                    <div className="flex items-center justify-between gap-4 border-b p-5">
                        <div className="flex items-start gap-3">
                            <IconTile tone="amber">
                                <FileClock className="size-5" />
                            </IconTile>
                            <div>
                                <h2 className="text-lg font-semibold">Your review queue</h2>
                                <p className="text-muted-foreground mt-1 text-sm">Abstracts assigned to you that still need your recommendation.</p>
                            </div>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link href={route('admin.abstracts.index')}>View all assigned</Link>
                        </Button>
                    </div>
                    <div className="divide-y">
                        {reviewQueue.length ? (
                            reviewQueue.map((submission) => (
                                <article
                                    key={submission.id}
                                    className="grid gap-4 border-l-4 border-l-amber-300 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center dark:border-l-amber-500/40"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            {submission.resubmitted_at && (
                                                <span className="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                                    Revised{submission.review_round > 1 ? ` · round ${submission.review_round}` : ''}
                                                </span>
                                            )}
                                            <span className="text-muted-foreground text-xs capitalize">
                                                {submission.presentation_type} presentation
                                            </span>
                                        </div>
                                        <h3 className="mt-2 leading-6 font-semibold text-balance">{submission.title}</h3>
                                        {/* Subtheme only. This used to fall back to the author's
                                            institution, which named them outright. */}
                                        {submission.subtheme?.title && (
                                            <p className="text-muted-foreground mt-1 text-xs">{submission.subtheme.title}</p>
                                        )}
                                        <p className="text-muted-foreground mt-2 text-xs">
                                            {submission.resubmitted_at ? 'Resubmitted' : 'Submitted'}{' '}
                                            {formatDate(submission.resubmitted_at ?? submission.created_at)}
                                        </p>
                                    </div>
                                    <Button asChild size="sm" className="bg-[#4c8a1f] font-bold hover:bg-[#3f751a]">
                                        <Link href={route('admin.abstracts.show', submission.id)}>
                                            Review <ArrowRight className="size-4" />
                                        </Link>
                                    </Button>
                                </article>
                            ))
                        ) : (
                            <div className="p-10 text-center">
                                <CheckCircle2 className="mx-auto size-7 text-[#4c8a1f]" />
                                <p className="mt-3 font-semibold">Nothing waiting on you right now</p>
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
