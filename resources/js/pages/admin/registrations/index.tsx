import { IconTile } from '@/components/dashboard-card';
import { StatusPill, type PillTone } from '@/components/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Download, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Registrations', href: '/admin/registrations' },
];

type PaymentStatus = 'pending' | 'submitted' | 'verified' | 'rejected' | 'waived';

interface Registration {
    id: number;
    name: string;
    email: string;
    institution: string | null;
    participant_type: string | null;
    fee_category: string | null;
    fee_amount: string | null;
    currency: string;
    payment_status: PaymentStatus;
    control_number: string | null;
    billing_request_id: string | null;
    paid_at: string | null;
    student_verification_status: 'pending' | 'verified' | 'rejected' | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
}

interface RegistrationsIndexProps {
    registrations: Paginated<Registration>;
    filters: { payment_status?: string; search?: string };
    counts: { total: number; pending: number; submitted: number; verified: number };
}

const paymentConfig: Record<PaymentStatus, { label: string; tone: PillTone }> = {
    pending: { label: 'Not started', tone: 'neutral' },
    submitted: { label: 'Awaiting payment', tone: 'attention' },
    verified: { label: 'Paid', tone: 'positive' },
    rejected: { label: 'Payment issue', tone: 'negative' },
    waived: { label: 'Waived', tone: 'neutral' },
};

const statTiles: { key: keyof RegistrationsIndexProps['counts']; label: string }[] = [
    { key: 'total', label: 'Total registrants' },
    { key: 'pending', label: 'Not started' },
    { key: 'submitted', label: 'Awaiting payment' },
    { key: 'verified', label: 'Paid' },
];

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}

function formatDate(value: string | null) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

export default function RegistrationsIndex({ registrations, filters, counts }: RegistrationsIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(route('admin.registrations.index'), { ...filters, search: search || undefined }, { preserveState: true, replace: true });
    };

    const setStatusFilter = (value: string) => {
        router.get(
            route('admin.registrations.index'),
            { ...filters, payment_status: value === 'all' ? undefined : value },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Registrations" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex flex-col gap-4 border-b pb-5 md:flex-row md:items-end md:justify-between">
                    <div className="flex items-start gap-4">
                        <IconTile tone="blue">
                            <ClipboardList className="size-5" />
                        </IconTile>
                        <div>
                            <p className="text-xs font-bold tracking-[0.18em] text-[#4c8a1f] uppercase">Conference operations</p>
                            <h1 className="mt-2 font-serif text-3xl font-semibold">Registrations</h1>
                            <p className="text-muted-foreground mt-2 text-sm">
                                Payment status is updated automatically from GePG. This page is read-only.
                            </p>
                        </div>
                    </div>
                    <Button asChild variant="outline">
                        <a href={route('admin.registrations.export')}>
                            <Download className="size-4" />
                            Export to Excel
                        </a>
                    </Button>
                </header>

                <section className="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Registration totals">
                    {statTiles.map(({ key, label }) => {
                        const selected = filters.payment_status === key || (key === 'total' && !filters.payment_status);

                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setStatusFilter(key === 'total' ? 'all' : key)}
                                className={cn(
                                    'rounded-2xl border p-4 text-left transition',
                                    selected ? 'border-[#4c8a1f] bg-[#f3f9ee] shadow-sm dark:bg-[#4c8a1f]/10' : 'bg-card hover:border-[#4c8a1f]/40',
                                )}
                            >
                                <div className="text-2xl font-bold tabular-nums">{counts[key]}</div>
                                <div className="text-muted-foreground mt-1 text-xs font-semibold">{label}</div>
                            </button>
                        );
                    })}
                </section>

                <section className="bg-card overflow-hidden rounded-2xl border">
                    <div className="grid gap-3 border-b p-4 md:grid-cols-[minmax(0,1fr)_220px]">
                        <form onSubmit={applyFilters} className="flex gap-2">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search name, email, or institution"
                                    className="pl-9"
                                />
                            </div>
                            <Button type="submit" variant="secondary">
                                Search
                            </Button>
                        </form>
                        <Select value={filters.payment_status ?? 'all'} onValueChange={setStatusFilter}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All payment statuses</SelectItem>
                                <SelectItem value="pending">Not started</SelectItem>
                                <SelectItem value="submitted">Awaiting payment</SelectItem>
                                <SelectItem value="verified">Paid</SelectItem>
                                <SelectItem value="rejected">Payment issue</SelectItem>
                                <SelectItem value="waived">Waived</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[980px] text-sm">
                            <thead className="bg-muted/40 text-left text-xs tracking-wide uppercase">
                                <tr>
                                    <th className="p-4">Registrant</th>
                                    <th className="p-4">Category</th>
                                    <th className="p-4">Amount</th>
                                    <th className="p-4">Control number</th>
                                    <th className="p-4">Payment</th>
                                    <th className="p-4">Paid at</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {registrations.data.length ? (
                                    registrations.data.map((registration) => {
                                        const payment = paymentConfig[registration.payment_status];
                                        const isStudent = registration.fee_category?.startsWith('student_');

                                        return (
                                            <tr key={registration.id} className="hover:bg-muted/30">
                                                <td className="p-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#eaf1ff] font-serif text-xs font-bold text-[#135eeb] dark:bg-[#135eeb]/15">
                                                            {initials(registration.name)}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <div className="font-semibold">{registration.name}</div>
                                                            <div className="text-muted-foreground mt-0.5 truncate text-xs">{registration.email}</div>
                                                            <div className="text-muted-foreground mt-0.5 max-w-56 truncate text-xs">
                                                                {registration.institution}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="p-4">
                                                    <div className="capitalize">{registration.fee_category?.replaceAll('_', ' ') || '—'}</div>
                                                    {isStudent && (
                                                        <div className="text-muted-foreground mt-1 text-xs capitalize">
                                                            Student: {registration.student_verification_status || 'document not submitted'}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="p-4 font-semibold tabular-nums">
                                                    {registration.fee_amount
                                                        ? `${registration.currency} ${Number(registration.fee_amount).toLocaleString()}`
                                                        : '—'}
                                                </td>
                                                <td className="p-4 font-mono text-xs">{registration.control_number || 'Not issued'}</td>
                                                <td className="p-4">
                                                    <StatusPill tone={payment.tone}>{payment.label}</StatusPill>
                                                </td>
                                                <td className="text-muted-foreground p-4 text-xs">{formatDate(registration.paid_at)}</td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="p-12 text-center">
                                            <Search className="text-muted-foreground mx-auto size-7" />
                                            <p className="mt-3 font-semibold">No registrations match these filters</p>
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
