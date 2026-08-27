import { DashboardCard } from '@/components/dashboard-card';
import { RegistrantBadge, type BadgeContent } from '@/components/registrant-badge';
import { PrintBadgeButton, StandingBadge, formatAmount, formatDateTime, formatTime } from '@/components/registrant-standing';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, CreditCard, Pencil, ScanLine } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface PersonDetail {
    id: number;
    name: string;
    salutation: string | null;
    position_title: string | null;
    email: string | null;
    phone: string | null;
    institution: string | null;
    participant_type: string | null;
    country: string | null;
    is_east_africa: boolean;
    fee_category: string | null;
    fee_category_label: string | null;
    is_complimentary: boolean;
    fee_amount: string | null;
    currency: string | null;
    payment_status: string | null;
    is_paid: boolean;
    control_number: string | null;
    paid_at: string | null;
    payment_notes: string | null;
    payment_verified_by: string | null;
    registration_code: string | null;
    registered_at: string;
    requires_student_verification: boolean;
    student_verification_status: 'pending' | 'verified' | 'rejected' | null;
    student_verified_at: string | null;
    student_verified_by: string | null;
    student_verification_notes: string | null;
    can_print_badge: boolean;
    checked_in_at: string | null;
    last_seen_at: string | null;
    days_attended: number;
    badges_printed: number;
}

interface AttendanceRecord {
    id: number;
    date: string;
    checked_in_at: string;
    recorded_by: string | null;
}

interface BadgePrint {
    id: number;
    print_number: number;
    printed_at: string;
    printed_by: string | null;
}

interface RegistrantPageProps {
    canManageFinance: boolean;
    person: PersonDetail;
    /** Null until their payment is verified or waived — there is no badge before that. */
    badge: BadgeContent | null;
    attendance: AttendanceRecord[];
    badgePrints: BadgePrint[];
    salutations: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Check-in', href: '/staff' },
    { title: 'Registrant', href: '#' },
];

function participantTypeLabel(value: string | null): string {
    if (!value) return '—';
    return value
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

export default function RegistrantPage({ canManageFinance, person, badge, attendance, badgePrints, salutations }: RegistrantPageProps) {
    const { flash } = usePage<SharedData & { flash: { success?: string; error?: string; info?: string } }>().props;
    const [settling, setSettling] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ notes: '' });

    const act = (routeName: string) => {
        post(route(routeName, person.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setSettling(false);
            },
        });
    };

    // Correcting what the desk mistyped — name, title, institution — without
    // registering the person again. Works after the badge is printed; the desk
    // just reprints.
    const [editing, setEditing] = useState(false);
    const details = useForm({
        salutation: person.salutation ?? '',
        name: person.name,
        position_title: person.position_title ?? '',
        phone: person.phone ?? '',
        institution: person.institution ?? '',
    });

    const saveDetails: FormEventHandler = (event) => {
        event.preventDefault();
        details.patch(route('staff.registrant.update', person.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const cancelEdit = () => {
        details.clearErrors();
        details.setData({
            salutation: person.salutation ?? '',
            name: person.name,
            position_title: person.position_title ?? '',
            phone: person.phone ?? '',
            institution: person.institution ?? '',
        });
        setEditing(false);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={person.name} />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4 pb-12 md:p-6">
                <Link
                    href={route('staff.dashboard')}
                    className="text-muted-foreground hover:text-foreground flex items-center gap-1.5 text-sm font-semibold"
                >
                    <ArrowLeft className="size-4" />
                    Back to the desk
                </Link>

                <section className="relative overflow-hidden rounded-[24px] bg-[#0d3fa8] px-6 py-8 text-white md:px-8">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-xs font-black tracking-[0.24em] text-[#8fd45a] uppercase">Registrant</p>
                            <h1 className="mt-2 font-serif text-2xl font-semibold md:text-3xl">
                                {person.salutation ? `${person.salutation} ` : ''}
                                {person.name}
                            </h1>
                            <p className="mt-1.5 text-sm text-white/60">
                                {person.email ?? person.phone ?? 'No contact on file'}
                                {person.institution ? ` · ${person.institution}` : ''}
                            </p>
                        </div>
                        <StandingBadge person={person} className="text-white [&_p]:text-white/70" />
                    </div>
                </section>

                {flash?.success && (
                    <div className="rounded-xl border border-[#4c8a1f]/30 bg-[#f3f9ee] p-4 text-sm font-semibold text-[#33620f] dark:bg-[#4c8a1f]/10">
                        {flash.success}
                    </div>
                )}
                {flash?.info && (
                    <div className="rounded-xl border border-amber-300/40 bg-amber-50 p-4 text-sm font-semibold text-amber-800 dark:bg-amber-950/30">
                        {flash.info}
                    </div>
                )}
                {flash?.error && (
                    <div className="border-destructive/30 bg-destructive/5 text-destructive rounded-xl border p-4 text-sm font-semibold">
                        {flash.error}
                    </div>
                )}

                {/* Actions live together, next to the details that justify each one — not
                    scattered across a search row. */}
                <DashboardCard>
                    <h2 className="text-sm font-bold tracking-wide uppercase">Actions</h2>
                    <div className="mt-4 flex flex-wrap gap-2">
                        {!person.is_paid && (
                            <Button size="sm" variant="outline" onClick={() => act('staff.control-number')} disabled={processing}>
                                <CreditCard className="size-4" />
                                {person.control_number ? 'Re-issue control number' : 'Issue control number'}
                            </Button>
                        )}

                        {!person.is_paid && canManageFinance && !settling && (
                            <Button size="sm" onClick={() => setSettling(true)} className="bg-[#4c8a1f] font-bold hover:bg-[#3f751a]">
                                <CheckCircle2 className="size-4" />
                                Settle payment
                            </Button>
                        )}

                        {person.can_print_badge && <PrintBadgeButton person={person} printRoute={route('staff.badge', person.id)} />}
                    </div>

                    {!person.is_paid && !canManageFinance && (
                        <p className="text-muted-foreground mt-3 text-xs">Only finance can settle a payment — send them to the finance desk.</p>
                    )}

                    {settling && (
                        <div className="mt-3 flex flex-col gap-2.5 rounded-xl border bg-slate-50 p-4 dark:bg-slate-900">
                            <Textarea
                                value={data.notes}
                                onChange={(event) => setData('notes', event.target.value)}
                                placeholder="Notes — required to waive (e.g. receipt number, or why the fee is waived)"
                                rows={2}
                                disabled={processing}
                                className="bg-background text-sm"
                            />
                            {errors.notes && <p className="text-destructive text-xs">{errors.notes}</p>}
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    onClick={() => act('staff.confirm-payment')}
                                    disabled={processing}
                                    className="bg-[#4c8a1f] font-bold hover:bg-[#3f751a]"
                                >
                                    Confirm payment received
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => act('staff.waive')}
                                    disabled={processing}
                                    className="border-amber-300 font-bold text-amber-800 hover:bg-amber-50"
                                >
                                    Waive the fee
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => setSettling(false)} disabled={processing}>
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    )}
                </DashboardCard>

                <div className="grid gap-5 md:grid-cols-2">
                    <DashboardCard>
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-sm font-bold tracking-wide uppercase">Contact &amp; identity</h2>
                            {!editing && (
                                <Button size="sm" variant="ghost" className="h-7 gap-1.5 px-2 text-xs" onClick={() => setEditing(true)}>
                                    <Pencil className="size-3.5" />
                                    Edit
                                </Button>
                            )}
                        </div>

                        {editing ? (
                            <form onSubmit={saveDetails} className="mt-4 flex flex-col gap-3 text-sm">
                                <EditField label="Salutation" error={details.errors.salutation}>
                                    <select
                                        value={details.data.salutation}
                                        onChange={(event) => details.setData('salutation', event.target.value)}
                                        className="border-input bg-background text-foreground h-9 rounded-md border px-3 text-sm"
                                    >
                                        <option value="">—</option>
                                        {salutations.map((s) => (
                                            <option key={s} value={s}>
                                                {s}
                                            </option>
                                        ))}
                                    </select>
                                </EditField>
                                <EditField label="Full name" error={details.errors.name}>
                                    <Input
                                        value={details.data.name}
                                        onChange={(event) => details.setData('name', event.target.value)}
                                        autoComplete="off"
                                    />
                                </EditField>
                                <EditField label="Position / role" error={details.errors.position_title}>
                                    <Input
                                        value={details.data.position_title}
                                        onChange={(event) => details.setData('position_title', event.target.value)}
                                        autoComplete="off"
                                        placeholder="Optional — e.g. Director General"
                                    />
                                </EditField>
                                <EditField label="Institution" error={details.errors.institution}>
                                    <Input
                                        value={details.data.institution}
                                        onChange={(event) => details.setData('institution', event.target.value)}
                                        autoComplete="off"
                                    />
                                </EditField>
                                <EditField label="Phone" error={details.errors.phone}>
                                    <Input
                                        value={details.data.phone}
                                        onChange={(event) => details.setData('phone', event.target.value)}
                                        inputMode="tel"
                                        autoComplete="off"
                                        placeholder="Optional"
                                    />
                                </EditField>
                                <p className="text-muted-foreground text-xs">
                                    Email is changed by an administrator. Category, country and payment are settled separately.
                                </p>
                                <div className="flex gap-2 pt-1">
                                    <Button size="sm" type="submit" disabled={details.processing}>
                                        Save changes
                                    </Button>
                                    <Button size="sm" type="button" variant="ghost" onClick={cancelEdit} disabled={details.processing}>
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            <dl className="mt-4 flex flex-col gap-3 text-sm">
                                <Row label="Email" value={person.email ?? '—'} />
                                <Row label="Phone" value={person.phone ?? '—'} />
                                {person.position_title && <Row label="Position / role" value={person.position_title} />}
                                <Row label="Institution" value={person.institution ?? '—'} />
                                <Row label="Participant type" value={participantTypeLabel(person.participant_type)} />
                                <Row
                                    label="Country"
                                    value={person.country ? `${person.country} (${person.is_east_africa ? 'East Africa' : 'International'})` : '—'}
                                />
                                <Row label="Registered" value={formatDateTime(person.registered_at)} />
                            </dl>
                        )}
                    </DashboardCard>

                    <DashboardCard>
                        <h2 className="text-sm font-bold tracking-wide uppercase">Registration & payment</h2>
                        <dl className="mt-4 flex flex-col gap-3 text-sm">
                            <Row label="Category" value={person.fee_category_label ?? '—'} />
                            <Row label="Fee" value={person.is_complimentary ? 'No fee' : (formatAmount(person.fee_amount, person.currency) ?? '—')} />
                            <Row label="Status" value={person.payment_status ?? '—'} />
                            <Row label="Control number" value={person.control_number ?? '—'} mono />
                            <Row label="Badge code" value={person.registration_code ?? '—'} mono />
                            {person.paid_at && <Row label="Settled" value={formatDateTime(person.paid_at)} />}
                            {person.payment_verified_by && <Row label="Settled by" value={person.payment_verified_by} />}
                            {person.payment_notes && <Row label="Notes" value={person.payment_notes} />}
                        </dl>
                    </DashboardCard>

                    {person.requires_student_verification && (
                        <DashboardCard>
                            <h2 className="text-sm font-bold tracking-wide uppercase">Student verification</h2>
                            <dl className="mt-4 flex flex-col gap-3 text-sm">
                                <Row label="Status" value={person.student_verification_status ?? 'not started'} />
                                {person.student_verified_at && <Row label="Decided" value={formatDateTime(person.student_verified_at)} />}
                                {person.student_verified_by && <Row label="Decided by" value={person.student_verified_by} />}
                                {person.student_verification_notes && <Row label="Notes" value={person.student_verification_notes} />}
                            </dl>
                        </DashboardCard>
                    )}

                    <DashboardCard>
                        <h2 className="flex items-center gap-2 text-sm font-bold tracking-wide uppercase">
                            <ScanLine className="size-4" />
                            Attendance ({person.days_attended} day{person.days_attended === 1 ? '' : 's'})
                        </h2>
                        {attendance.length === 0 ? (
                            <p className="text-muted-foreground mt-4 text-sm">Not scanned in yet.</p>
                        ) : (
                            <ul className="mt-4 flex flex-col gap-2.5 text-sm">
                                {attendance.map((record) => (
                                    <li key={record.id} className="flex items-center justify-between gap-3">
                                        <span className="font-medium">{formatDateTime(record.checked_in_at)}</span>
                                        {record.recorded_by && <span className="text-muted-foreground text-xs">by {record.recorded_by}</span>}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </DashboardCard>

                    <DashboardCard>
                        <h2 className="text-sm font-bold tracking-wide uppercase">Badge</h2>
                        {badge ? (
                            <>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Scannable from the screen — no need to print one just to read the code.
                                </p>
                                <div className="mt-4 flex justify-center overflow-x-auto rounded-xl border bg-white p-4">
                                    <RegistrantBadge badge={badge} className="shrink-0 shadow-sm" />
                                </div>
                            </>
                        ) : (
                            <p className="text-muted-foreground mt-4 text-sm">No badge yet — one exists once their payment is verified or waived.</p>
                        )}

                        <h2 className="mt-6 text-sm font-bold tracking-wide uppercase">Badge prints ({person.badges_printed})</h2>
                        {badgePrints.length === 0 ? (
                            <p className="text-muted-foreground mt-4 text-sm">No badge printed yet.</p>
                        ) : (
                            <ul className="mt-4 flex flex-col gap-2.5 text-sm">
                                {badgePrints.map((print) => (
                                    <li key={print.id} className="flex items-center justify-between gap-3">
                                        <span className="font-medium">
                                            #{print.print_number} · {formatTime(print.printed_at)}
                                        </span>
                                        {print.printed_by && <span className="text-muted-foreground text-xs">by {print.printed_by}</span>}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </DashboardCard>
                </div>
            </div>
        </AppLayout>
    );
}

function Row({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
    return (
        <div className="flex items-start justify-between gap-4">
            <dt className="text-muted-foreground shrink-0">{label}</dt>
            <dd className={mono ? 'text-right font-mono text-xs' : 'text-right font-medium'}>{value}</dd>
        </div>
    );
}

function EditField({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <label className="grid gap-1">
            <span className="text-muted-foreground text-xs font-semibold">{label}</span>
            {children}
            {error && <span className="text-destructive text-xs">{error}</span>}
        </label>
    );
}
