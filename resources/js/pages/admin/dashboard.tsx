import { IconTile } from '@/components/dashboard-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, FileCheck2, FileClock, FilePenLine, GraduationCap, UsersRound, WalletCards } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin', href: '/admin' }];

interface QueueAbstract {
    id: number;
    title: string;
    presentation_type: 'oral' | 'poster';
    created_at: string;
    resubmitted_at: string | null;
    user: { name: string; email: string; institution: string | null };
    subtheme: { title: string } | null;
}

interface QueueStudent {
    id: number;
    name: string;
    email: string;
    institution: string | null;
    fee_category: string;
    created_at: string;
}

interface AdminDashboardProps {
    stats: {
        total_registrants: number;
        paid: number;
        pending_payment: number;
        checked_in: number;
        abstracts_total: number;
        abstracts_submitted: number;
        abstracts_revision_requested: number;
        abstracts_accepted: number;
        abstracts_rejected: number;
        students_pending: number;
    };
    reviewQueue: QueueAbstract[];
    studentQueue: QueueStudent[];
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value));
}

export default function AdminDashboard({ stats, reviewQueue, studentQueue }: AdminDashboardProps) {
    const pendingActions = stats.abstracts_submitted + stats.students_pending;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 pb-12 md:p-6">
                <header className="relative overflow-hidden rounded-3xl bg-[#0d3fa8] px-6 py-8 text-white md:px-9 md:py-10">
                    <div className="absolute -top-24 -right-16 size-64 rounded-full border-[44px] border-white/5" />
                    <div className="absolute -bottom-20 -left-10 size-56 rounded-full bg-[#8fd45a]/10 blur-2xl" />
                    <div className="relative flex flex-col gap-7 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p className="text-xs font-black tracking-[0.22em] text-[#8fd45a] uppercase">TMSC administration</p>
                            <h1 className="mt-3 max-w-2xl font-serif text-3xl font-semibold text-balance md:text-4xl">Admin workspace</h1>
                            <p className="mt-3 max-w-xl text-sm leading-6 text-white/75">
                                Registrations, payments, abstract review, and student verification for the conference.
                            </p>
                        </div>
                        <div className="rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur-sm">
                            <div className="text-3xl font-bold tabular-nums">{pendingActions}</div>
                            <div className="mt-1 text-xs font-bold tracking-wide text-white/70 uppercase">Actions waiting</div>
                        </div>
                    </div>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Conference totals">
                    {[
                        {
                            label: 'Registrants',
                            value: stats.total_registrants,
                            icon: UsersRound,
                            tone: 'blue' as const,
                            href: route('admin.registrations.index'),
                            classes: 'border-blue-200 bg-blue-50 dark:border-blue-900/40 dark:bg-blue-950/30',
                            valueClasses: 'text-blue-700 dark:text-blue-300',
                        },
                        {
                            label: 'Payments confirmed',
                            value: stats.paid,
                            icon: WalletCards,
                            tone: 'green' as const,
                            href: route('admin.registrations.index', { payment_status: 'verified' }),
                            classes: 'border-[#4c8a1f]/30 bg-[#f3f9ee] dark:border-[#4c8a1f]/30 dark:bg-[#4c8a1f]/10',
                            valueClasses: 'text-[#4c8a1f] dark:text-[#8fd45a]',
                        },
                        {
                            label: 'Abstracts awaiting review',
                            value: stats.abstracts_submitted,
                            icon: FileClock,
                            tone: 'amber' as const,
                            href: route('admin.abstracts.index', { status: 'submitted' }),
                            classes: 'border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/30',
                            valueClasses: 'text-amber-700 dark:text-amber-300',
                        },
                        {
                            label: 'Students awaiting verification',
                            value: stats.students_pending,
                            icon: GraduationCap,
                            tone: 'indigo' as const,
                            href: route('admin.students.index', { status: 'pending' }),
                            classes: 'border-indigo-200 bg-indigo-50 dark:border-indigo-900/40 dark:bg-indigo-950/30',
                            valueClasses: 'text-indigo-700 dark:text-indigo-300',
                        },
                    ].map((item) => {
                        const Icon = item.icon;
                        return (
                            <Link
                                key={item.label}
                                href={item.href}
                                className={`flex min-h-28 items-center justify-between rounded-2xl border p-5 transition hover:shadow-sm ${item.classes}`}
                            >
                                <div>
                                    <div className={`text-3xl font-bold tabular-nums ${item.valueClasses}`}>{item.value}</div>
                                    <div className="text-muted-foreground mt-2 text-xs font-semibold">{item.label}</div>
                                </div>
                                <IconTile tone={item.tone}>
                                    <Icon className="size-5" />
                                </IconTile>
                            </Link>
                        );
                    })}
                </section>

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(340px,0.75fr)]">
                    <section className="bg-card overflow-hidden rounded-2xl border">
                        <div className="flex items-center justify-between gap-4 border-b p-5">
                            <div className="flex items-start gap-3">
                                <IconTile tone="amber">
                                    <FileClock className="size-5" />
                                </IconTile>
                                <div>
                                    <h2 className="text-lg font-semibold">Abstracts awaiting review</h2>
                                    <p className="text-muted-foreground mt-1 text-sm">Read each submission fully before deciding.</p>
                                </div>
                            </div>
                            <Button asChild variant="outline" size="sm">
                                <Link href={route('admin.abstracts.index', { status: 'submitted' })}>View queue</Link>
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
                                                        Revised
                                                    </span>
                                                )}
                                                <span className="text-muted-foreground text-xs capitalize">
                                                    {submission.presentation_type} presentation
                                                </span>
                                            </div>
                                            <h3 className="mt-2 leading-6 font-semibold text-balance">{submission.title}</h3>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {submission.user.name} · {submission.subtheme?.title || submission.user.institution}
                                            </p>
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
                                    <p className="mt-3 font-semibold">Abstract queue is clear</p>
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="bg-card overflow-hidden rounded-2xl border">
                        <div className="flex items-center justify-between gap-4 border-b p-5">
                            <div className="flex items-start gap-3">
                                <IconTile tone="indigo">
                                    <GraduationCap className="size-5" />
                                </IconTile>
                                <div>
                                    <h2 className="text-lg font-semibold">Student verification</h2>
                                    <p className="text-muted-foreground mt-1 text-sm">Confirm student-rate eligibility.</p>
                                </div>
                            </div>
                            <Button asChild variant="outline" size="sm">
                                <Link href={route('admin.students.index', { status: 'pending' })}>Open</Link>
                            </Button>
                        </div>
                        <div className="divide-y">
                            {studentQueue.length ? (
                                studentQueue.map((student) => (
                                    <Link
                                        key={student.id}
                                        href={route('admin.students.index', { status: 'pending', search: student.email })}
                                        className="group hover:bg-muted/40 flex items-center justify-between gap-4 p-5"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold">{student.name}</p>
                                            <p className="text-muted-foreground mt-1 truncate text-xs">{student.institution || student.email}</p>
                                        </div>
                                        <ArrowRight className="text-muted-foreground size-4 transition-transform group-hover:translate-x-0.5" />
                                    </Link>
                                ))
                            ) : (
                                <div className="p-10 text-center">
                                    <CheckCircle2 className="mx-auto size-7 text-[#4c8a1f]" />
                                    <p className="mt-3 font-semibold">Student queue is clear</p>
                                </div>
                            )}
                        </div>
                    </section>
                </div>

                <section className="grid gap-3 border-t pt-5 sm:grid-cols-2 lg:grid-cols-4" aria-label="Additional totals">
                    {[
                        { label: 'Awaiting GePG payment', value: stats.pending_payment, icon: WalletCards, tone: 'blue' as const },
                        { label: 'Revision with authors', value: stats.abstracts_revision_requested, icon: FilePenLine, tone: 'amber' as const },
                        { label: 'Abstracts accepted', value: stats.abstracts_accepted, icon: FileCheck2, tone: 'green' as const },
                        { label: 'Checked in', value: stats.checked_in, icon: CheckCircle2, tone: 'indigo' as const },
                    ].map((item) => {
                        const Icon = item.icon;
                        return (
                            <div key={item.label} className="bg-card flex items-center gap-3 rounded-xl border px-4 py-3">
                                <IconTile tone={item.tone}>
                                    <Icon className="size-4" />
                                </IconTile>
                                <div className="min-w-0">
                                    <strong className="block text-lg leading-tight tabular-nums">{item.value}</strong>
                                    <span className="text-muted-foreground truncate text-xs">{item.label}</span>
                                </div>
                            </div>
                        );
                    })}
                </section>
            </div>
        </AppLayout>
    );
}
