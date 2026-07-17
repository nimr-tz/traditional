import { DashboardCard, IconTile } from '@/components/dashboard-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Banknote, CalendarClock, ClipboardList, Download, ListChecks, Wallet } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Finance', href: '/admin/finance' },
];

interface RevenueBucket {
    realized: number;
    outstanding: number;
    waived: number;
    projected: number;
}

interface PendingPayment {
    id: number;
    name: string;
    email: string;
    fee_category: string | null;
    updated_at: string;
}

interface FinanceDashboardProps {
    stats: {
        verified_count: number;
        submitted_count: number;
        waived_count: number;
        rejected_count: number;
        not_started_count: number;
    };
    revenue: Record<string, RevenueBucket>;
    todayVerifiedCount: number;
    todayRevenue: Record<string, number>;
    pendingPayments: PendingPayment[];
    categoryStats: Record<string, number>;
}

function money(amount: number, currency: string): string {
    return `${currency} ${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
}

export default function FinanceDashboard({
    stats,
    revenue,
    todayVerifiedCount,
    todayRevenue,
    pendingPayments,
    categoryStats,
}: FinanceDashboardProps) {
    const currencies = Object.keys(revenue);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Finance" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex flex-col gap-4 border-b pb-5 md:flex-row md:items-end md:justify-between">
                    <div className="flex items-start gap-4">
                        <IconTile tone="green">
                            <Wallet className="size-5" />
                        </IconTile>
                        <div>
                            <p className="text-xs font-bold tracking-[0.18em] text-[#4c8a1f] uppercase">Conference operations</p>
                            <h1 className="mt-2 font-serif text-3xl font-semibold">Finance</h1>
                            <p className="text-muted-foreground mt-2 text-sm">
                                Monitor settlement performance, outstanding balances, waivers, and reporting for registrant payments.
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <a href={route('admin.finance.export', { type: 'summary' })}>
                                <Download className="size-4" />
                                Summary CSV
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <a href={route('admin.finance.export', { type: 'individuals' })}>
                                <Download className="size-4" />
                                Participant CSV
                            </a>
                        </Button>
                    </div>
                </header>

                <section className="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Payment totals">
                    <Link
                        href={route('admin.finance.payments', { status: 'verified' })}
                        className="rounded-2xl border border-[#4c8a1f]/30 bg-[#f3f9ee] p-4 transition hover:shadow-sm dark:bg-[#4c8a1f]/10"
                    >
                        <div className="text-2xl font-bold text-[#4c8a1f] tabular-nums">{stats.verified_count}</div>
                        <div className="text-muted-foreground mt-1 text-xs font-semibold">Settled</div>
                    </Link>
                    <Link
                        href={route('admin.finance.payments', { status: 'submitted' })}
                        className="rounded-2xl border border-blue-200 bg-blue-50 p-4 transition hover:shadow-sm dark:border-blue-900/40 dark:bg-blue-950/30"
                    >
                        <div className="text-2xl font-bold text-blue-700 tabular-nums dark:text-blue-300">{stats.submitted_count}</div>
                        <div className="text-muted-foreground mt-1 text-xs font-semibold">Pending settlement</div>
                    </Link>
                    <Link
                        href={route('admin.finance.payments', { status: 'pending' })}
                        className="bg-card rounded-2xl border p-4 transition hover:shadow-sm"
                    >
                        <div className="text-2xl font-bold tabular-nums">{stats.not_started_count}</div>
                        <div className="text-muted-foreground mt-1 text-xs font-semibold">Not started</div>
                    </Link>
                    <Link
                        href={route('admin.finance.payments', { status: 'waived' })}
                        className="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 transition hover:shadow-sm dark:border-indigo-900/40 dark:bg-indigo-950/30"
                    >
                        <div className="text-2xl font-bold text-indigo-700 tabular-nums dark:text-indigo-300">{stats.waived_count}</div>
                        <div className="text-muted-foreground mt-1 text-xs font-semibold">Waived</div>
                    </Link>
                </section>

                <div className="grid gap-6 xl:grid-cols-[1.25fr_0.9fr]">
                    <div className="space-y-6">
                        <section className="grid gap-6 md:grid-cols-2">
                            {currencies.map((currency) => {
                                const bucket = revenue[currency];
                                const rate = bucket.projected > 0 ? Math.round((bucket.realized / bucket.projected) * 100) : 0;

                                return (
                                    <DashboardCard key={currency}>
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-muted-foreground text-[11px] font-black tracking-[0.2em] uppercase">
                                                    {currency} revenue
                                                </p>
                                                <h2 className="mt-2 text-xl font-black">Collection</h2>
                                            </div>
                                            <div className="rounded-xl bg-[#eef7e6] px-3 py-2 text-sm font-black text-[#4c8a1f] dark:bg-[#4c8a1f]/15">
                                                {rate}%
                                            </div>
                                        </div>

                                        <div className="mt-4 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                            <div className="h-full rounded-full bg-[#4c8a1f]" style={{ width: `${rate}%` }} />
                                        </div>

                                        <div className="mt-5 grid grid-cols-2 gap-3">
                                            <div className="rounded-xl bg-[#f3f9ee] px-4 py-3 dark:bg-[#4c8a1f]/10">
                                                <p className="text-[10px] font-black tracking-wide text-[#4c8a1f] uppercase">Realized</p>
                                                <p className="mt-1 text-lg font-black">{money(bucket.realized, currency)}</p>
                                            </div>
                                            <div className="rounded-xl bg-blue-50 px-4 py-3 dark:bg-blue-950/30">
                                                <p className="text-[10px] font-black tracking-wide text-blue-700 uppercase dark:text-blue-300">
                                                    Outstanding
                                                </p>
                                                <p className="mt-1 text-lg font-black">{money(bucket.outstanding, currency)}</p>
                                            </div>
                                            <div className="rounded-xl bg-slate-100 px-4 py-3 dark:bg-slate-800/70">
                                                <p className="text-muted-foreground text-[10px] font-black tracking-wide uppercase">Waived</p>
                                                <p className="mt-1 text-lg font-black">{money(bucket.waived, currency)}</p>
                                            </div>
                                            <div className="rounded-xl bg-indigo-50 px-4 py-3 dark:bg-indigo-950/30">
                                                <p className="text-[10px] font-black tracking-wide text-indigo-700 uppercase dark:text-indigo-300">
                                                    Projected
                                                </p>
                                                <p className="mt-1 text-lg font-black">{money(bucket.projected, currency)}</p>
                                            </div>
                                        </div>
                                    </DashboardCard>
                                );
                            })}
                        </section>

                        <DashboardCard>
                            <div className="flex items-center gap-3">
                                <IconTile tone="blue">
                                    <ListChecks className="size-5" />
                                </IconTile>
                                <div>
                                    <p className="text-muted-foreground text-[11px] font-black tracking-[0.2em] uppercase">Registrant mix</p>
                                    <h2 className="mt-1 text-xl font-black">Category breakdown</h2>
                                </div>
                            </div>
                            <div className="mt-5 space-y-2.5">
                                {Object.keys(categoryStats).length ? (
                                    Object.entries(categoryStats).map(([category, total]) => (
                                        <div
                                            key={category}
                                            className="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-900/50"
                                        >
                                            <span className="text-sm font-semibold capitalize">{category.replaceAll('_', ' ')}</span>
                                            <span className="text-lg font-black tabular-nums">{total}</span>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-muted-foreground text-sm">No category data available.</p>
                                )}
                            </div>
                        </DashboardCard>
                    </div>

                    <aside className="space-y-6">
                        <DashboardCard>
                            <div className="flex items-center gap-3">
                                <IconTile tone="green">
                                    <CalendarClock className="size-5" />
                                </IconTile>
                                <div>
                                    <p className="text-muted-foreground text-[11px] font-black tracking-[0.2em] uppercase">Today</p>
                                    <h2 className="mt-1 text-xl font-black">Daily pulse</h2>
                                </div>
                            </div>
                            <div className="mt-5 space-y-3">
                                <div className="rounded-xl bg-[#0f1b2d] px-4 py-4 text-white">
                                    <p className="text-[10px] font-black tracking-wide text-white/60 uppercase">Verified today</p>
                                    <p className="mt-2 text-3xl font-black">{todayVerifiedCount}</p>
                                </div>
                                <div className="space-y-1.5 rounded-xl bg-slate-50 px-4 py-4 text-sm font-semibold dark:bg-slate-900/50">
                                    {Object.keys(todayRevenue).length ? (
                                        Object.entries(todayRevenue).map(([currency, amount]) => (
                                            <div key={currency} className="flex items-center justify-between">
                                                <span>{currency}</span>
                                                <span className="tabular-nums">{amount.toLocaleString(undefined, { maximumFractionDigits: 2 })}</span>
                                            </div>
                                        ))
                                    ) : (
                                        <p className="text-muted-foreground font-normal">No revenue recorded yet today.</p>
                                    )}
                                </div>
                            </div>
                        </DashboardCard>

                        <DashboardCard>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <IconTile tone="blue">
                                        <ClipboardList className="size-5" />
                                    </IconTile>
                                    <div>
                                        <p className="text-muted-foreground text-[11px] font-black tracking-[0.2em] uppercase">Action queue</p>
                                        <h2 className="mt-1 text-xl font-black">Pending reviews</h2>
                                    </div>
                                </div>
                            </div>
                            <div className="mt-4 space-y-2.5">
                                {pendingPayments.length ? (
                                    pendingPayments.map((registration) => (
                                        <Link
                                            key={registration.id}
                                            href={route('admin.finance.payments.show', registration.id)}
                                            className="block rounded-xl border p-3.5 transition hover:border-[#4c8a1f]/40 hover:shadow-sm"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-bold">{registration.name}</p>
                                                    <p className="text-muted-foreground mt-0.5 truncate text-xs">{registration.email}</p>
                                                    <p className="text-muted-foreground mt-1 text-[11px] font-bold uppercase">
                                                        {registration.fee_category?.replaceAll('_', ' ') || 'Category not set'}
                                                    </p>
                                                </div>
                                                <span className="shrink-0 rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-black tracking-wide text-blue-700 uppercase dark:bg-blue-950/30 dark:text-blue-300">
                                                    Submitted
                                                </span>
                                            </div>
                                        </Link>
                                    ))
                                ) : (
                                    <div className="rounded-xl border border-dashed p-6 text-center text-sm">
                                        <Banknote className="text-muted-foreground mx-auto size-6" />
                                        <p className="text-muted-foreground mt-2">No pending payment submissions.</p>
                                    </div>
                                )}
                            </div>
                        </DashboardCard>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
