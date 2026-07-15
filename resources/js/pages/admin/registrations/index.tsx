import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Registrations', href: '/admin/registrations' },
];

type PaymentStatus = 'pending' | 'submitted' | 'verified' | 'rejected';

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

const paymentConfig: Record<PaymentStatus, { label: string; className: string }> = {
    pending: { label: 'Not started', className: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' },
    submitted: { label: 'Awaiting payment', className: 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' },
    verified: { label: 'Paid', className: 'bg-green-50 text-green-800 dark:bg-green-950/40 dark:text-green-300' },
    rejected: { label: 'Payment issue', className: 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' },
};

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
                    <div>
                        <p className="text-xs font-bold tracking-[0.18em] text-[#4c8a1f] uppercase">Conference operations</p>
                        <h1 className="mt-2 font-serif text-3xl font-semibold">Registrations</h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Payment status is updated automatically from GePG. This page is read-only.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <a href={route('admin.registrations.export')}>
                            <Download className="size-4" />
                            Export to Excel
                        </a>
                    </Button>
                </header>

                <section className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {[
                        ['Total registrants', counts.total],
                        ['Not started', counts.pending],
                        ['Awaiting payment', counts.submitted],
                        ['Paid', counts.verified],
                    ].map(([label, value]) => (
                        <div key={String(label)} className="bg-card rounded-2xl border p-4">
                            <div className="text-2xl font-bold tabular-nums">{value}</div>
                            <div className="text-muted-foreground mt-1 text-xs font-semibold">{label}</div>
                        </div>
                    ))}
                </section>

                <section className="bg-card rounded-2xl border">
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
                                {registrations.data.map((registration) => {
                                    const payment = paymentConfig[registration.payment_status];
                                    const isStudent = registration.fee_category?.startsWith('student_');

                                    return (
                                        <tr key={registration.id}>
                                            <td className="p-4">
                                                <div className="font-semibold">{registration.name}</div>
                                                <div className="text-muted-foreground mt-1 text-xs">{registration.email}</div>
                                                <div className="text-muted-foreground mt-1 max-w-64 truncate text-xs">{registration.institution}</div>
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
                                                <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${payment.className}`}>
                                                    {payment.label}
                                                </span>
                                            </td>
                                            <td className="text-muted-foreground p-4 text-xs">{formatDate(registration.paid_at)}</td>
                                        </tr>
                                    );
                                })}
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
                                className={`rounded-lg px-3 py-1.5 text-sm ${link.active ? 'bg-[#4c8a1f] text-white' : 'text-muted-foreground hover:bg-muted'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
