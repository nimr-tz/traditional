import { IconTile } from '@/components/dashboard-card';
import { StatusPill, type PillTone } from '@/components/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Search, Wallet } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Finance', href: '/admin/finance' },
    { title: 'Payments', href: '/admin/finance/payments' },
];

type PaymentStatus = 'pending' | 'submitted' | 'verified' | 'rejected' | 'waived';

interface Registration {
    id: number;
    name: string;
    full_name: string;
    email: string;
    fee_category: string | null;
    fee_amount: string | null;
    currency: string;
    payment_status: PaymentStatus;
    payment_method: 'gepg' | 'bank_transfer' | null;
    control_number: string | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

interface FinancePaymentsProps {
    registrations: Paginated<Registration>;
    filters: { status?: string; search?: string; category?: string; method?: string };
    stats: { verified_count: number; submitted_count: number; waived_count: number; not_started_count: number };
}

const paymentConfig: Record<PaymentStatus, { label: string; tone: PillTone }> = {
    pending: { label: 'Not started', tone: 'neutral' },
    submitted: { label: 'Pending settlement', tone: 'attention' },
    verified: { label: 'Settled', tone: 'positive' },
    rejected: { label: 'Rejected', tone: 'negative' },
    waived: { label: 'Waived', tone: 'neutral' },
};

const statusTabs: { value: string; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'verified', label: 'Settled' },
    { value: 'submitted', label: 'Pending settlement' },
    { value: 'pending', label: 'Not started' },
    { value: 'waived', label: 'Waived' },
    { value: 'rejected', label: 'Rejected' },
];

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}

export default function FinancePayments({ registrations, filters, stats }: FinancePaymentsProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(route('admin.finance.payments'), { ...filters, search: search || undefined }, { preserveState: true, replace: true });
    };

    const setStatus = (status: string) => {
        router.get(
            route('admin.finance.payments'),
            { ...filters, status: status === 'all' ? undefined : status },
            { preserveState: true, replace: true },
        );
    };

    const setCategory = (category: string) => {
        router.get(
            route('admin.finance.payments'),
            { ...filters, category: category === 'all' ? undefined : category },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payments" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex flex-col gap-4 border-b pb-5 md:flex-row md:items-end md:justify-between">
                    <div className="flex items-start gap-4">
                        <IconTile tone="green">
                            <Wallet className="size-5" />
                        </IconTile>
                        <div>
                            <p className="text-xs font-bold tracking-[0.18em] text-[#4c8a1f] uppercase">Finance</p>
                            <h1 className="mt-2 font-serif text-3xl font-semibold">Payment ledger</h1>
                            <p className="text-muted-foreground mt-2 text-sm">Review, verify, reject, or waive individual registrant payments.</p>
                        </div>
                    </div>
                </header>

                <section className="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Payment totals">
                    <div className="rounded-2xl border border-[#4c8a1f]/30 bg-[#f3f9ee] p-4 dark:bg-[#4c8a1f]/10">
                        <div className="text-2xl font-bold text-[#4c8a1f] tabular-nums">{stats.verified_count}</div>
                        <div className="text-muted-foreground mt-1 text-xs font-semibold">Settled</div>
                    </div>
                    <div className="rounded-2xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/40 dark:bg-blue-950/30">
                        <div className="text-2xl font-bold text-blue-700 tabular-nums dark:text-blue-300">{stats.submitted_count}</div>
                        <div className="text-muted-foreground mt-1 text-xs font-semibold">Pending settlement</div>
                    </div>
                    <div className="bg-card rounded-2xl border p-4">
                        <div className="text-2xl font-bold tabular-nums">{stats.not_started_count}</div>
                        <div className="text-muted-foreground mt-1 text-xs font-semibold">Not started</div>
                    </div>
                    <div className="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900/40 dark:bg-indigo-950/30">
                        <div className="text-2xl font-bold text-indigo-700 tabular-nums dark:text-indigo-300">{stats.waived_count}</div>
                        <div className="text-muted-foreground mt-1 text-xs font-semibold">Waived</div>
                    </div>
                </section>

                <section className="bg-card overflow-hidden rounded-2xl border">
                    <div className="grid gap-3 border-b p-4 md:grid-cols-[minmax(0,1fr)_220px]">
                        <form onSubmit={applyFilters} className="flex gap-2">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search name, email, or control number"
                                    className="pl-9"
                                />
                            </div>
                            <Button type="submit" variant="secondary">
                                Search
                            </Button>
                        </form>
                        <Select value={filters.category ?? 'all'} onValueChange={setCategory}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All categories</SelectItem>
                                <SelectItem value="participant_east_africa">Participant (East Africa)</SelectItem>
                                <SelectItem value="participant_non_east_africa">Participant (International)</SelectItem>
                                <SelectItem value="student_east_africa">Student (East Africa)</SelectItem>
                                <SelectItem value="student_non_east_africa">Student (International)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="flex flex-wrap gap-1 border-b p-3">
                        {statusTabs.map((tab) => (
                            <button
                                key={tab.value}
                                type="button"
                                onClick={() => setStatus(tab.value)}
                                className={cn(
                                    'rounded-lg px-3 py-1.5 text-xs font-bold tracking-wide uppercase transition',
                                    (filters.status ?? 'all') === tab.value ? 'bg-[#4c8a1f] text-white' : 'text-muted-foreground hover:bg-muted',
                                )}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[860px] text-sm">
                            <thead className="bg-muted/40 text-left text-xs tracking-wide uppercase">
                                <tr>
                                    <th className="p-4">Participant</th>
                                    <th className="p-4">Control No. / Method</th>
                                    <th className="p-4">Status</th>
                                    <th className="p-4 text-right">Fee</th>
                                    <th className="p-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {registrations.data.length ? (
                                    registrations.data.map((registration) => {
                                        const payment = paymentConfig[registration.payment_status];

                                        return (
                                            <tr key={registration.id} className="hover:bg-muted/30">
                                                <td className="p-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#eaf1ff] font-serif text-xs font-bold text-[#135eeb] dark:bg-[#135eeb]/15">
                                                            {initials(registration.name)}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <div className="font-semibold">{registration.full_name}</div>
                                                            <div className="text-muted-foreground mt-0.5 truncate text-xs">{registration.email}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="p-4">
                                                    {registration.payment_method === 'bank_transfer' ? (
                                                        <span className="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-bold text-blue-700 dark:bg-blue-950/30 dark:text-blue-300">
                                                            Bank transfer
                                                        </span>
                                                    ) : registration.control_number ? (
                                                        <span className="font-mono text-xs font-bold">{registration.control_number}</span>
                                                    ) : (
                                                        <span className="text-muted-foreground text-xs italic">Not generated</span>
                                                    )}
                                                </td>
                                                <td className="p-4">
                                                    <StatusPill tone={payment.tone}>{payment.label}</StatusPill>
                                                </td>
                                                <td className="p-4 text-right font-semibold tabular-nums">
                                                    {registration.fee_amount
                                                        ? `${registration.currency} ${Number(registration.fee_amount).toLocaleString()}`
                                                        : '—'}
                                                </td>
                                                <td className="p-4 text-right">
                                                    <Button asChild size="sm" variant="outline">
                                                        <Link href={route('admin.finance.payments.show', registration.id)}>
                                                            View
                                                            <ArrowRight className="size-3.5" />
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan={5} className="p-12 text-center">
                                            <Search className="text-muted-foreground mx-auto size-7" />
                                            <p className="mt-3 font-semibold">No payments match these filters</p>
                                            <p className="text-muted-foreground mt-1 text-sm">Clear a filter or try a different search.</p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {registrations.links.length > 3 && (
                    <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                        {registrations.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveState
                                className={cn(
                                    'rounded-lg px-3 py-1.5 text-sm',
                                    link.active ? 'bg-[#4c8a1f] text-white' : 'text-muted-foreground hover:bg-muted',
                                    !link.url && 'pointer-events-none opacity-40',
                                )}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
