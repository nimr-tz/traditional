import { DashboardCard, IconTile } from '@/components/dashboard-card';
import { StatusPill, type PillTone } from '@/components/status-pill';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Award, BadgeCheck, CalendarDays, FileText, MapPin, Sparkles, Wallet } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

type PaymentStatus = 'pending' | 'submitted' | 'verified' | 'rejected';
type AbstractStatus = 'submitted' | 'revision_requested' | 'accepted' | 'rejected';

interface Abstract {
    id: number;
    title: string;
    status: AbstractStatus;
    presentation_type: string;
    created_at: string;
    subtheme: { title: string } | null;
}

interface DashboardProps {
    userName: string;
    registration: {
        payment_status: PaymentStatus;
        fee_category: string | null;
        fee_category_label: string | null;
        fee_amount: string | null;
        currency: string;
        is_paid: boolean;
        is_checked_in: boolean;
    };
    abstracts: Abstract[];
    abstractsCount: number;
    conferenceName: string;
    conferenceYear: string | null;
    venue: string | null;
    conferenceStartDate: string | null;
    submissionDeadline: string | null;
}

const paymentStatusLabel: Record<PaymentStatus, string> = {
    pending: 'Not started',
    submitted: 'Awaiting payment',
    verified: 'Paid',
    rejected: 'Action required',
};

const paymentStatusTone: Record<PaymentStatus, PillTone> = {
    pending: 'neutral',
    submitted: 'neutral',
    revision_requested: 'attention',
    verified: 'positive',
    rejected: 'negative',
};

const abstractStatusTone: Record<AbstractStatus, PillTone> = {
    submitted: 'neutral',
    accepted: 'positive',
    rejected: 'negative',
};

const abstractStatusLabel: Record<AbstractStatus, string> = {
    submitted: 'Submitted',
    revision_requested: 'Revision requested',
    accepted: 'Accepted',
    rejected: 'Not accepted',
};

function formatDate(value: string | null) {
    if (!value) return null;

    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          }).format(date);
}

function formatSubmittedDate(value: string) {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          }).format(date);
}

function ActionRow({
    href,
    title,
    download = false,
    tone = 'blue',
    compact = false,
}: {
    href: string;
    title: string;
    download?: boolean;
    tone?: 'blue' | 'green';
    compact?: boolean;
}) {
    const content = (
        <>
            <span className="min-w-0">
                <span className="block text-sm font-bold text-white">{title}</span>
            </span>
            <span
                className={cn(
                    'flex shrink-0 items-center justify-center rounded-full bg-white/15 text-white transition-transform group-hover:translate-x-0.5',
                    compact ? 'size-8' : 'size-9',
                )}
            >
                <ArrowRight className="size-4" />
            </span>
        </>
    );

    const className = cn(
        'group mt-3 flex items-center rounded-xl border border-transparent text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
        compact ? 'mx-auto min-h-11 w-fit min-w-32 justify-center gap-3 px-5 py-2' : 'min-h-14 w-full justify-between gap-4 px-4 py-2.5',
        tone === 'blue'
            ? 'bg-[#135eeb] hover:bg-[#0d4fca] focus-visible:ring-[#135eeb]'
            : 'bg-[#4c8a1f] hover:bg-[#3f751a] focus-visible:ring-[#4c8a1f]',
    );

    if (download) {
        return (
            <a href={href} className={className}>
                {content}
            </a>
        );
    }

    return (
        <Link href={href} className={className}>
            {content}
        </Link>
    );
}

export default function Dashboard({
    userName,
    registration,
    abstracts,
    abstractsCount,
    conferenceName,
    conferenceYear,
    venue,
    conferenceStartDate,
    submissionDeadline,
}: DashboardProps) {
    const firstName = userName?.split(' ')[0] ?? '';
    const hasAbstract = abstractsCount > 0;
    const formattedConferenceDate = formatDate(conferenceStartDate);
    const formattedSubmissionDeadline = formatDate(submissionDeadline);
    const formattedFee = registration.fee_amount
        ? `${registration.currency} ${Number(registration.fee_amount).toLocaleString(undefined, { maximumFractionDigits: 2 })}`
        : 'To be confirmed';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="mx-auto flex w-full max-w-[1180px] flex-col gap-5 p-4 pb-10 md:p-6 md:pb-12">
                <section className="relative overflow-hidden rounded-[24px] bg-[#0d3fa8] px-6 py-8 text-white md:px-10 md:py-10">
                    <div className="absolute -top-28 -right-20 size-72 rounded-full bg-white/5" />
                    <div className="absolute -right-8 -bottom-32 size-64 rounded-full border-[40px] border-white/5" />

                    <div className="relative">
                        <div className="flex flex-col justify-between py-2 md:py-4">
                            <p className="text-sm font-black tracking-[0.24em] text-[#8fd45a] uppercase md:text-base">
                                {conferenceName}
                                {conferenceYear ? ` · ${conferenceYear}` : ''}
                            </p>

                            <div className="flex flex-1 items-center py-8 md:py-10 xl:py-6">
                                <h1 className="max-w-3xl font-serif text-3xl leading-tight font-semibold text-balance md:text-[44px]">
                                    Welcome back, {firstName}.
                                </h1>
                            </div>

                            {(venue || formattedConferenceDate) && (
                                <div className="flex flex-wrap gap-x-6 gap-y-3 border-t border-white/15 pt-5 text-sm text-white/75">
                                    {formattedConferenceDate && (
                                        <span className="flex items-center gap-2">
                                            <CalendarDays className="size-4 text-[#8fd45a]" />
                                            {formattedConferenceDate}
                                        </span>
                                    )}
                                    {venue && (
                                        <span className="flex items-center gap-2">
                                            <MapPin className="size-4 text-[#8fd45a]" />
                                            {venue}
                                        </span>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                <section>
                    <div className="mb-4">
                        <h2 className="font-serif text-2xl font-semibold">Manage your participation</h2>
                        <p className="text-muted-foreground mt-1 text-sm leading-6">
                            Open a section below to complete a task, review its status, or download the documents you need.
                        </p>
                    </div>

                    <div className="grid items-start gap-5 md:grid-cols-2">
                        <DashboardCard
                            className={cn(
                                'flex flex-col',
                                registration.is_paid &&
                                    'dark:from-card dark:via-card border-[#67b52f]/35 bg-gradient-to-br from-white via-white to-[#f1f9eb] shadow-[0_10px_30px_rgba(76,138,31,0.12)] dark:to-[#18291b]',
                            )}
                        >
                            <div className="flex items-start justify-between gap-4">
                                <IconTile tone={registration.is_paid ? 'green' : 'blue'}>
                                    {registration.is_paid ? <BadgeCheck className="size-6" /> : <Wallet className="size-5" />}
                                </IconTile>
                                <StatusPill
                                    tone={paymentStatusTone[registration.payment_status]}
                                    className={
                                        registration.is_paid
                                            ? 'bg-[#4c8a1f] px-3 text-white shadow-sm dark:bg-[#67b52f] dark:text-[#10220b]'
                                            : registration.payment_status === 'pending'
                                              ? 'bg-[#eaf1ff] text-[#135eeb] dark:bg-[#135eeb]/20 dark:text-[#8db4ff]'
                                              : undefined
                                    }
                                >
                                    {paymentStatusLabel[registration.payment_status]}
                                </StatusPill>
                            </div>

                            <h3 className="mt-5 text-xl font-semibold">{registration.is_paid ? 'Payment complete' : 'Registration & payment'}</h3>
                            <div
                                className={cn(
                                    'mt-6 rounded-2xl border p-5',
                                    registration.is_paid
                                        ? 'border-[#4c8a1f] bg-[#4c8a1f] text-white shadow-[0_8px_20px_rgba(76,138,31,0.2)]'
                                        : 'dark:border-border border-slate-100 bg-slate-50 dark:bg-slate-900/50',
                                )}
                            >
                                <p
                                    className={cn(
                                        'text-xs font-semibold tracking-[0.12em] uppercase',
                                        registration.is_paid ? 'text-white/70' : 'text-muted-foreground',
                                    )}
                                >
                                    {registration.is_paid ? 'Amount paid' : 'Amount to pay'}
                                </p>
                                <p className="mt-2 text-3xl font-bold tracking-tight tabular-nums">{formattedFee}</p>
                                {registration.is_paid && (
                                    <div className="mt-4 flex items-center gap-2 border-t border-white/20 pt-3 text-sm font-semibold text-white/90">
                                        <BadgeCheck className="size-4" />
                                        Payment confirmed
                                    </div>
                                )}
                            </div>

                            <div className="mt-auto pt-4">
                                <ActionRow
                                    href={route('payment.show')}
                                    compact
                                    title={
                                        registration.is_paid
                                            ? 'View payment details'
                                            : registration.payment_status === 'submitted'
                                              ? 'View payment status'
                                              : 'Pay'
                                    }
                                    tone={registration.is_paid ? 'green' : 'blue'}
                                />
                            </div>
                        </DashboardCard>

                        <DashboardCard className="flex flex-col">
                            <div className="flex items-start justify-between gap-4">
                                <IconTile tone="green">
                                    <FileText className="size-5" />
                                </IconTile>
                                <span className="inline-flex rounded-full bg-[#eef7e6] px-2.5 py-1 text-xs font-semibold text-[#4c8a1f] tabular-nums dark:bg-[#67b52f]/15 dark:text-[#8fd45a]">
                                    {hasAbstract ? `${abstractsCount} ${abstractsCount === 1 ? 'abstract' : 'abstracts'}` : 'Optional'}
                                </span>
                            </div>

                            <h3 className="mt-5 text-xl font-semibold">Research abstracts</h3>
                            {!hasAbstract && (
                                <p className="text-muted-foreground mt-2 text-sm leading-6">
                                    {formattedSubmissionDeadline
                                        ? `Share your research for consideration by ${formattedSubmissionDeadline}. You can submit regardless of payment status.`
                                        : 'Share your research for conference consideration at any payment stage. Abstract submission is optional.'}
                                </p>
                            )}

                            {hasAbstract ? (
                                <div className="mt-5 space-y-3">
                                    {abstracts.slice(0, 2).map((abstract) => (
                                        <div
                                            key={abstract.id}
                                            className="rounded-2xl border border-[#67b52f]/20 bg-[#67b52f]/5 p-4 dark:bg-[#67b52f]/10"
                                        >
                                            <div className="flex items-start justify-between gap-4">
                                                <div className="min-w-0">
                                                    <h4 className="text-sm leading-5 font-semibold">{abstract.title}</h4>
                                                    {abstract.subtheme && (
                                                        <p className="text-muted-foreground mt-1 text-xs leading-5">{abstract.subtheme.title}</p>
                                                    )}
                                                </div>
                                                <StatusPill tone={abstractStatusTone[abstract.status]}>
                                                    {abstractStatusLabel[abstract.status]}
                                                </StatusPill>
                                            </div>
                                            <div className="text-muted-foreground mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 border-t border-[#67b52f]/15 pt-3 text-xs">
                                                <span className="capitalize">{abstract.presentation_type} presentation</span>
                                                <span aria-hidden="true">·</span>
                                                <span>Submitted {formatSubmittedDate(abstract.created_at)}</span>
                                            </div>
                                        </div>
                                    ))}
                                    {abstractsCount > 2 && (
                                        <p className="text-muted-foreground text-center text-xs">
                                            +{abstractsCount - 2} more {abstractsCount - 2 === 1 ? 'abstract' : 'abstracts'}
                                        </p>
                                    )}
                                </div>
                            ) : (
                                <div className="dark:border-border mt-6 border-y border-slate-100 py-5">
                                    <p className="text-sm font-semibold">No abstract submitted yet</p>
                                    <p className="text-muted-foreground mt-1 text-xs leading-5">
                                        You can submit before or after payment, or continue without submitting one.
                                    </p>
                                </div>
                            )}

                            <div className="mt-auto pt-5">
                                {hasAbstract ? (
                                    <ActionRow href={route('abstracts.index')} compact title="Manage abstracts" tone="green" />
                                ) : (
                                    <ActionRow href={route('abstracts.create')} compact title="Submit your first abstract" tone="green" />
                                )}
                            </div>
                        </DashboardCard>

                        {registration.is_checked_in && (
                            <DashboardCard className="md:col-span-2">
                                <div className="grid items-center gap-6 md:grid-cols-[minmax(0,1fr)_minmax(280px,0.6fr)]">
                                    <div className="flex items-start gap-4">
                                        <IconTile tone="green">
                                            <Award className="size-5" />
                                        </IconTile>
                                        <div>
                                            <h3 className="flex items-center gap-2 text-xl font-semibold">
                                                Certificate of participation
                                                <Sparkles className="size-4 text-[#67b52f]" />
                                            </h3>
                                            <p className="text-muted-foreground mt-2 max-w-2xl text-sm leading-6">
                                                Your attendance has been confirmed. Your personalized conference certificate is ready to save or
                                                print.
                                            </p>
                                        </div>
                                    </div>
                                    <ActionRow href={route('certificate.download')} title="Download my certificate" download />
                                </div>
                            </DashboardCard>
                        )}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
