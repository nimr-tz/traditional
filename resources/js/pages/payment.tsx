import { StatusPill, type PillTone } from '@/components/status-pill';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Check, CheckCircle2, Clock3, Copy, FileCheck2, LoaderCircle, ReceiptText, ShieldCheck, Wallet } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Payment', href: '/payment' }];

interface PaymentUser {
    fee_amount: string | null;
    currency: string;
    control_number: string | null;
    payment_status: 'pending' | 'submitted' | 'verified' | 'rejected';
    paid_at: string | null;
    registration_code: string | null;
    student_verification_status: 'pending' | 'verified' | 'rejected' | null;
    student_verification_notes: string | null;
}

interface PaymentProps {
    user: PaymentUser;
    registrationCategory: string | null;
    gepgPayeeName: string;
    requiresStudentVerification: boolean;
}

const statusLabel: Record<PaymentUser['payment_status'], string> = {
    pending: 'Awaiting payment',
    submitted: 'Payment pending',
    verified: 'Paid',
    rejected: 'Action required',
};

const statusTone: Record<PaymentUser['payment_status'], PillTone> = {
    pending: 'neutral',
    submitted: 'neutral',
    verified: 'positive',
    rejected: 'negative',
};

function SummaryRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="dark:border-border flex items-start justify-between gap-5 border-b border-slate-100 py-4 last:border-0">
            <dt className="text-muted-foreground text-sm">{label}</dt>
            <dd className="text-foreground max-w-[60%] text-right text-sm font-semibold">{value}</dd>
        </div>
    );
}

function PaymentStep({ number, title, description }: { number: number; title: string; description: string }) {
    return (
        <li className="flex gap-4">
            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#eaf1ff] text-sm font-bold text-[#135eeb] dark:bg-[#135eeb]/15">
                {number}
            </span>
            <div className="pt-0.5">
                <p className="text-foreground text-sm font-semibold">{title}</p>
                <p className="text-muted-foreground mt-1 text-sm leading-6">{description}</p>
            </div>
        </li>
    );
}

export default function Payment({ user, registrationCategory, gepgPayeeName, requiresStudentVerification }: PaymentProps) {
    const { props } = usePage<{ flash: { success?: string; error?: string; info?: string } }>();
    const [status, setStatus] = useState(user);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        setStatus(user);
    }, [user]);

    useEffect(() => {
        if (status.payment_status !== 'submitted') {
            return;
        }

        const interval = setInterval(() => {
            fetch(route('payment.status'), { headers: { Accept: 'application/json' } })
                .then((res) => res.json())
                .then((data) => setStatus((previous) => ({ ...previous, ...data })));
        }, 3000);

        return () => clearInterval(interval);
    }, [status.payment_status]);

    const { post: postControlNumber, processing: requestingControlNumber } = useForm({});
    const studentDocumentForm = useForm<{ student_document: File | null }>({ student_document: null });

    const studentVerificationComplete = !requiresStudentVerification || status.student_verification_status === 'verified';
    const formattedAmount = status.fee_amount
        ? `${status.currency} ${Number(status.fee_amount).toLocaleString(undefined, { maximumFractionDigits: 2 })}`
        : 'Pending';

    const requestControlNumber = () => {
        postControlNumber(route('payment.control-number'), { preserveScroll: true });
    };

    const copyControlNumber = async () => {
        if (!status.control_number) return;

        await navigator.clipboard.writeText(status.control_number);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    const submitStudentDocument = () => {
        studentDocumentForm.post(route('student-verification.document'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => studentDocumentForm.reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment" />

            <div className="mx-auto flex w-full max-w-[1180px] flex-col gap-5 p-4 pb-10 md:p-6 md:pb-12">
                <header className="relative overflow-hidden rounded-[24px] bg-[#0d3fa8] px-6 py-8 text-white md:px-10 md:py-10">
                    <div className="absolute -top-28 -right-20 h-72 w-72 rounded-full bg-white/5" />
                    <div className="absolute -right-8 -bottom-32 h-64 w-64 rounded-full border-[40px] border-white/5" />

                    <div className="relative grid items-end gap-8 md:grid-cols-[minmax(0,1fr)_auto]">
                        <div>
                            <p className="mb-3 text-[11px] font-black tracking-[0.32em] text-white/60 uppercase">Registration payment</p>
                            <h1 className="font-serif text-3xl font-semibold md:text-[40px]">Complete your registration</h1>
                            <p className="mt-3 max-w-2xl text-sm leading-6 text-white/75 md:text-base">
                                Request your control number, make the payment through your preferred channel, and follow the confirmation here.
                            </p>
                        </div>

                        <div className="min-w-[220px] rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur-sm">
                            <div className="mb-2 flex items-center justify-between gap-4">
                                <p className="text-xs font-semibold tracking-wide text-white/65 uppercase">Amount due</p>
                                <StatusPill tone={statusTone[status.payment_status]}>{statusLabel[status.payment_status]}</StatusPill>
                            </div>
                            <p className="text-2xl font-bold tracking-tight tabular-nums">{formattedAmount}</p>
                        </div>
                    </div>
                </header>

                {props.flash?.success && (
                    <Alert>
                        <CheckCircle2 className="h-4 w-4" />
                        <AlertTitle>Done</AlertTitle>
                        <AlertDescription>{props.flash.success}</AlertDescription>
                    </Alert>
                )}
                {props.flash?.error && (
                    <Alert variant="destructive">
                        <AlertTitle>We could not complete that request</AlertTitle>
                        <AlertDescription>{props.flash.error}</AlertDescription>
                    </Alert>
                )}
                {props.flash?.info && (
                    <Alert>
                        <AlertTitle>Payment update</AlertTitle>
                        <AlertDescription>{props.flash.info}</AlertDescription>
                    </Alert>
                )}

                {requiresStudentVerification && status.student_verification_status === 'pending' && (
                    <Alert>
                        <Clock3 className="h-4 w-4" />
                        <AlertTitle>Your student document is being reviewed</AlertTitle>
                        <AlertDescription>You will be able to request a control number as soon as your student status is approved.</AlertDescription>
                    </Alert>
                )}

                {requiresStudentVerification && status.student_verification_status === 'verified' && (
                    <Alert>
                        <ShieldCheck className="h-4 w-4" />
                        <AlertTitle>Student status approved</AlertTitle>
                        <AlertDescription>Your student registration rate is confirmed and ready for payment.</AlertDescription>
                    </Alert>
                )}

                {requiresStudentVerification && status.student_verification_status === 'rejected' && (
                    <Alert variant="destructive">
                        <FileCheck2 className="h-4 w-4" />
                        <AlertTitle>Please provide another student document</AlertTitle>
                        <AlertDescription>
                            {status.student_verification_notes && <p>{status.student_verification_notes}</p>}
                            <div className="mt-4 grid max-w-xl gap-2">
                                <Input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    onChange={(event) => studentDocumentForm.setData('student_document', event.target.files?.[0] ?? null)}
                                />
                                {studentDocumentForm.errors.student_document && (
                                    <p className="text-xs">{studentDocumentForm.errors.student_document}</p>
                                )}
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="mt-1 w-fit"
                                    disabled={!studentDocumentForm.data.student_document || studentDocumentForm.processing}
                                    onClick={submitStudentDocument}
                                >
                                    {studentDocumentForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    Submit replacement document
                                </Button>
                            </div>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1.65fr)_minmax(290px,0.75fr)]">
                    <main className="dark:bg-card rounded-2xl border border-[#135eeb]/10 bg-white p-6 shadow-[0_1px_2px_rgba(19,94,235,0.06)] md:p-8">
                        <div className="dark:border-border flex items-start gap-4 border-b border-slate-100 pb-6">
                            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#eaf1ff] text-[#135eeb] dark:bg-[#135eeb]/15">
                                <Wallet className="h-5 w-5" />
                            </span>
                            <div>
                                <h2 className="text-xl font-semibold">Payment details</h2>
                                <p className="text-muted-foreground mt-1 text-sm leading-6">Use one control number for the full registration fee.</p>
                            </div>
                        </div>

                        {status.payment_status === 'verified' ? (
                            <div className="py-8">
                                <div className="rounded-2xl border border-[#67b52f]/20 bg-[#f3f9ee] p-6 dark:bg-[#67b52f]/10">
                                    <div className="flex gap-4">
                                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#67b52f] text-white">
                                            <Check className="h-5 w-5" />
                                        </span>
                                        <div>
                                            <h3 className="text-foreground text-lg font-semibold">Payment confirmed</h3>
                                            <p className="text-muted-foreground mt-1 text-sm leading-6">
                                                Your registration fee has been received
                                                {status.paid_at ? ` on ${new Date(status.paid_at).toLocaleDateString()}` : ''}.
                                            </p>
                                            {status.registration_code && (
                                                <p className="mt-3 text-sm">
                                                    Registration code: <strong className="font-mono">{status.registration_code}</strong>
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="grid gap-8 py-7 md:grid-cols-[minmax(0,0.9fr)_minmax(280px,1.1fr)]">
                                <div>
                                    <h3 className="text-foreground text-sm font-bold tracking-wide uppercase">How to pay</h3>
                                    <ol className="mt-5 space-y-5">
                                        <PaymentStep
                                            number={1}
                                            title="Get your control number"
                                            description="Request a number for your registration fee below."
                                        />
                                        <PaymentStep
                                            number={2}
                                            title="Make the payment"
                                            description="Use the control number through a bank, mobile banking, or another supported payment channel."
                                        />
                                        <PaymentStep
                                            number={3}
                                            title="Wait for confirmation"
                                            description="This page updates automatically after your payment is received."
                                        />
                                    </ol>
                                </div>

                                <div className="dark:bg-muted/40 rounded-2xl bg-slate-50 p-5 md:p-6">
                                    {status.control_number ? (
                                        <>
                                            <div className="flex items-center gap-2 text-[#135eeb]">
                                                <ReceiptText className="h-4 w-4" />
                                                <p className="text-xs font-bold tracking-wide uppercase">Your control number</p>
                                            </div>
                                            <p className="text-foreground mt-4 font-mono text-2xl font-bold tracking-wide break-all tabular-nums">
                                                {status.control_number}
                                            </p>
                                            <Button type="button" variant="outline" size="sm" className="mt-4" onClick={copyControlNumber}>
                                                {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                                                {copied ? 'Copied' : 'Copy number'}
                                            </Button>
                                            <div className="text-muted-foreground dark:border-border mt-6 flex items-start gap-2 border-t border-slate-200 pt-5 text-sm">
                                                <LoaderCircle className="mt-0.5 h-4 w-4 shrink-0 animate-spin text-[#135eeb]" />
                                                <p>Waiting for payment confirmation. You may leave this page and return later.</p>
                                            </div>
                                        </>
                                    ) : status.payment_status === 'submitted' ? (
                                        <div className="flex min-h-48 flex-col items-center justify-center text-center">
                                            <LoaderCircle className="h-7 w-7 animate-spin text-[#135eeb]" />
                                            <h3 className="mt-4 font-semibold">Preparing your control number</h3>
                                            <p className="text-muted-foreground mt-2 max-w-xs text-sm leading-6">
                                                Keep this page open. Your number will appear here automatically.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="flex min-h-48 flex-col justify-between">
                                            <div>
                                                <p className="text-foreground text-sm font-semibold">Ready to continue?</p>
                                                <p className="text-muted-foreground mt-2 text-sm leading-6">
                                                    We will create a control number for the amount shown above.
                                                </p>
                                            </div>
                                            <Button
                                                onClick={requestControlNumber}
                                                disabled={requestingControlNumber || !studentVerificationComplete || !status.fee_amount}
                                                className="mt-6 w-full bg-[#135eeb] text-white hover:bg-[#135eeb]/90"
                                            >
                                                {requestingControlNumber ? (
                                                    <LoaderCircle className="h-4 w-4 animate-spin" />
                                                ) : (
                                                    <ArrowRight className="h-4 w-4" />
                                                )}
                                                Request control number
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                    </main>

                    <aside className="dark:bg-card rounded-2xl border border-[#135eeb]/10 bg-white p-6 shadow-[0_1px_2px_rgba(19,94,235,0.06)]">
                        <div className="dark:border-border flex items-center gap-3 border-b border-slate-100 pb-5">
                            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#eef7e6] text-[#67b52f] dark:bg-[#67b52f]/15">
                                <ReceiptText className="h-5 w-5" />
                            </span>
                            <div>
                                <h2 className="font-semibold">Registration summary</h2>
                                <p className="text-muted-foreground mt-0.5 text-xs">Review before making payment</p>
                            </div>
                        </div>

                        <dl className="mt-1">
                            <SummaryRow label="Category" value={registrationCategory ?? 'To be confirmed'} />
                            <SummaryRow label="Registration fee" value={<span className="tabular-nums">{formattedAmount}</span>} />
                            <SummaryRow label="Payment method" value="Control number" />
                            <SummaryRow label="Payable to" value={gepgPayeeName} />
                            {requiresStudentVerification && (
                                <SummaryRow
                                    label="Student status"
                                    value={
                                        status.student_verification_status === 'verified'
                                            ? 'Approved'
                                            : status.student_verification_status === 'rejected'
                                              ? 'Document required'
                                              : 'Under review'
                                    }
                                />
                            )}
                        </dl>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
