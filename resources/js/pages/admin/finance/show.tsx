import { DashboardCard, IconTile } from '@/components/dashboard-card';
import { StatusBanner } from '@/components/status-banner';
import { StatusPill, type PillTone } from '@/components/status-pill';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Download, RotateCcw, ShieldCheck, Wallet, XCircle } from 'lucide-react';

type PaymentStatus = 'pending' | 'submitted' | 'verified' | 'rejected' | 'waived';

interface Registration {
    id: number;
    name: string;
    full_name: string;
    email: string;
    fee_category: string | null;
    fee_amount: string | null;
    currency: string;
    control_number: string | null;
    billing_request_id: string | null;
    payment_method: 'gepg' | 'bank_transfer' | null;
    payment_proof_path: string | null;
    payment_status: PaymentStatus;
    payment_notes: string | null;
    created_at: string;
    updated_at: string;
}

interface FinanceShowProps {
    registration: Registration;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Finance', href: '/admin/finance' },
    { title: 'Payments', href: '/admin/finance/payments' },
];

const statusConfig: Record<PaymentStatus, { label: string; tone: PillTone }> = {
    pending: { label: 'Not started', tone: 'neutral' },
    submitted: { label: 'Pending settlement', tone: 'attention' },
    verified: { label: 'Settled', tone: 'positive' },
    rejected: { label: 'Rejected', tone: 'negative' },
    waived: { label: 'Waived', tone: 'neutral' },
};

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}

export default function FinanceShow({ registration }: FinanceShowProps) {
    const { props } = usePage<{ flash: { success?: string; error?: string } }>();
    const breadcrumbsWithName: BreadcrumbItem[] = [...breadcrumbs, { title: registration.full_name, href: '#' }];

    const verifyForm = useForm({ notes: '' });
    const rejectForm = useForm({ notes: '' });
    const waiveForm = useForm({ notes: '' });

    const verify = () => {
        verifyForm.post(route('admin.finance.payments.verify', registration.id), { preserveScroll: true, onSuccess: () => verifyForm.reset() });
    };

    const reject = () => {
        rejectForm.post(route('admin.finance.payments.reject', registration.id), { preserveScroll: true, onSuccess: () => rejectForm.reset() });
    };

    const waive = () => {
        waiveForm.post(route('admin.finance.payments.waive', registration.id), { preserveScroll: true, onSuccess: () => waiveForm.reset() });
    };

    const returnToPayment = () => {
        router.post(route('admin.finance.payments.return-to-payment', registration.id), {}, { preserveScroll: true });
    };

    const resetBilling = () => {
        router.post(route('admin.finance.payments.reset-billing', registration.id), {}, { preserveScroll: true });
    };

    const status = statusConfig[registration.payment_status];
    const canOverride = registration.payment_status !== 'verified' && registration.payment_status !== 'waived';
    const isBankTransfer = registration.payment_method === 'bank_transfer';

    return (
        <AppLayout breadcrumbs={breadcrumbsWithName}>
            <Head title={`Payment: ${registration.full_name}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex flex-col gap-4 border-b pb-5 md:flex-row md:items-end md:justify-between">
                    <div className="flex items-start gap-4">
                        <IconTile tone="green">
                            <Wallet className="size-5" />
                        </IconTile>
                        <div>
                            <p className="text-xs font-bold tracking-[0.18em] text-[#4c8a1f] uppercase">Finance</p>
                            <h1 className="mt-2 font-serif text-3xl font-semibold">{registration.full_name}</h1>
                            <p className="text-muted-foreground mt-1 text-sm">{registration.email}</p>
                        </div>
                    </div>
                    <StatusPill tone={status.tone}>{status.label}</StatusPill>
                </header>

                {props.flash?.success && <StatusBanner tone="success" icon={CheckCircle2} title="Done" description={props.flash.success} />}
                {props.flash?.error && (
                    <StatusBanner tone="negative" icon={XCircle} title="Could not complete that request" description={props.flash.error} />
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <DashboardCard>
                            <div className="flex items-start justify-between">
                                <div className="flex items-center gap-4">
                                    <div className="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-xl font-black text-slate-400 dark:bg-slate-800">
                                        {initials(registration.name)}
                                    </div>
                                    <div>
                                        <div className="rounded-lg border bg-slate-50 px-3 py-1 text-xs font-bold tracking-wide uppercase dark:bg-slate-900">
                                            {registration.fee_category?.replaceAll('_', ' ') || 'Standard'}
                                        </div>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <p className="text-muted-foreground text-xs font-bold tracking-wide uppercase">Fee due</p>
                                    <p className="mt-1 text-2xl font-black tabular-nums">
                                        {registration.fee_amount
                                            ? `${registration.currency} ${Number(registration.fee_amount).toLocaleString()}`
                                            : '—'}
                                    </p>
                                </div>
                            </div>
                        </DashboardCard>

                        {isBankTransfer ? (
                            <DashboardCard>
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-bold">Bank transfer</h3>
                                    <span className="rounded bg-blue-100 px-2 py-1 text-[10px] font-bold text-blue-700 uppercase dark:bg-blue-900/30 dark:text-blue-300">
                                        Manual review
                                    </span>
                                </div>

                                {registration.payment_proof_path ? (
                                    <Button asChild variant="outline" className="mt-4">
                                        <a href={`/storage/${registration.payment_proof_path}`} target="_blank" rel="noreferrer">
                                            View transfer receipt
                                        </a>
                                    </Button>
                                ) : (
                                    <p className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-bold text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-400">
                                        No proof uploaded yet.
                                    </p>
                                )}
                            </DashboardCard>
                        ) : (
                            <DashboardCard>
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-bold">GePG integration</h3>
                                    <span className="bg-muted rounded px-2 py-1 text-[10px] font-bold uppercase">Live sync</span>
                                </div>
                                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/50">
                                        <p className="text-muted-foreground text-[10px] font-bold tracking-wide uppercase">Control number</p>
                                        <p className="mt-1 font-mono text-lg font-black">{registration.control_number || 'Not generated'}</p>
                                    </div>
                                    <div className="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/50">
                                        <p className="text-muted-foreground text-[10px] font-bold tracking-wide uppercase">Billing ID</p>
                                        <p className="mt-1 font-mono text-sm font-bold">{registration.billing_request_id || '—'}</p>
                                    </div>
                                </div>

                                {registration.billing_request_id && (
                                    <div className="mt-4 flex gap-3">
                                        {registration.control_number && (
                                            <Button asChild variant="outline" size="sm" className="flex-1">
                                                <a href={route('admin.finance.payments.invoice', registration.id)} target="_blank" rel="noreferrer">
                                                    <Download className="size-4" />
                                                    Invoice
                                                </a>
                                            </Button>
                                        )}
                                        {(registration.payment_status === 'verified' || registration.payment_status === 'waived') && (
                                            <Button asChild variant="outline" size="sm" className="flex-1">
                                                <a href={route('admin.finance.payments.receipt', registration.id)} target="_blank" rel="noreferrer">
                                                    <Download className="size-4" />
                                                    Receipt
                                                </a>
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </DashboardCard>
                        )}

                        {registration.payment_notes && (
                            <DashboardCard>
                                <p className="text-muted-foreground text-xs font-bold tracking-wide uppercase">Latest note</p>
                                <p className="mt-2 text-sm leading-6 whitespace-pre-wrap">{registration.payment_notes}</p>
                            </DashboardCard>
                        )}
                    </div>

                    <aside className="space-y-6">
                        <DashboardCard>
                            <h3 className="text-muted-foreground text-xs font-bold tracking-wide uppercase">Overrides & exceptions</h3>

                            {canOverride ? (
                                <div className="mt-4 space-y-4">
                                    {isBankTransfer && registration.payment_proof_path ? (
                                        <div className="space-y-2">
                                            <Textarea
                                                value={verifyForm.data.notes}
                                                onChange={(event) => verifyForm.setData('notes', event.target.value)}
                                                placeholder="Verification note (optional)"
                                                rows={2}
                                            />
                                            <Button
                                                onClick={verify}
                                                disabled={verifyForm.processing}
                                                className="w-full bg-[#4c8a1f] hover:bg-[#3f751a]"
                                            >
                                                Verify bank transfer
                                            </Button>
                                            <Textarea
                                                value={rejectForm.data.notes}
                                                onChange={(event) => rejectForm.setData('notes', event.target.value)}
                                                placeholder="Reason for rejecting this proof (required)"
                                                rows={2}
                                            />
                                            {rejectForm.errors.notes && <p className="text-destructive text-xs">{rejectForm.errors.notes}</p>}
                                            <Button
                                                onClick={reject}
                                                disabled={rejectForm.processing}
                                                variant="outline"
                                                className="w-full border-red-200 text-red-700 hover:bg-red-50"
                                            >
                                                Reject proof
                                            </Button>
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            <Textarea
                                                value={verifyForm.data.notes}
                                                onChange={(event) => verifyForm.setData('notes', event.target.value)}
                                                placeholder="Only use this if GePG fails to sync (optional note)"
                                                rows={2}
                                            />
                                            <Button onClick={verify} disabled={verifyForm.processing} variant="outline" className="w-full">
                                                Manual settlement (override)
                                            </Button>

                                            {registration.billing_request_id && registration.payment_status === 'submitted' && (
                                                <Button
                                                    onClick={resetBilling}
                                                    variant="outline"
                                                    className="w-full border-amber-200 text-amber-700 hover:bg-amber-50"
                                                >
                                                    <RotateCcw className="size-4" />
                                                    Reset & re-request control number
                                                </Button>
                                            )}
                                        </div>
                                    )}

                                    <div className="border-t pt-4">
                                        <Textarea
                                            value={waiveForm.data.notes}
                                            onChange={(event) => waiveForm.setData('notes', event.target.value)}
                                            placeholder="Justification for waiving this fee (required)"
                                            rows={2}
                                        />
                                        {waiveForm.errors.notes && <p className="text-destructive text-xs">{waiveForm.errors.notes}</p>}
                                        <Button
                                            onClick={waive}
                                            disabled={waiveForm.processing}
                                            variant="outline"
                                            className="mt-2 w-full border-indigo-200 text-indigo-700 hover:bg-indigo-50"
                                        >
                                            <ShieldCheck className="size-4" />
                                            Grant official waiver
                                        </Button>
                                    </div>
                                </div>
                            ) : registration.payment_status === 'waived' ? (
                                <Button
                                    onClick={returnToPayment}
                                    variant="outline"
                                    className="mt-4 w-full border-amber-200 text-amber-700 hover:bg-amber-50"
                                >
                                    <RotateCcw className="size-4" />
                                    Return to payment flow
                                </Button>
                            ) : (
                                <p className="text-muted-foreground mt-4 text-sm">This payment is settled — no further action needed.</p>
                            )}

                            <p className="text-muted-foreground mt-4 text-center text-[11px] leading-relaxed">
                                Standard payments are cleared automatically by GePG. Use overrides only for exceptions or offline payments.
                            </p>
                        </DashboardCard>

                        <DashboardCard>
                            <h3 className="text-muted-foreground text-xs font-bold tracking-wide uppercase">System meta</h3>
                            <dl className="mt-4 space-y-3 text-sm">
                                <div>
                                    <dt className="text-muted-foreground text-xs">Registered</dt>
                                    <dd className="font-medium">{new Date(registration.created_at).toLocaleDateString()}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-xs">Last update</dt>
                                    <dd className="font-medium">{new Date(registration.updated_at).toLocaleString()}</dd>
                                </div>
                            </dl>
                        </DashboardCard>

                        <Button asChild variant="ghost" className="w-full">
                            <Link href={route('admin.finance.payments')}>Back to ledger</Link>
                        </Button>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
