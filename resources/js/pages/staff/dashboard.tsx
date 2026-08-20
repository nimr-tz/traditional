import { DashboardCard } from '@/components/dashboard-card';
import { StandingBadge, formatAmount, formatDateTime, formatTime } from '@/components/registrant-standing';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronRight, CreditCard, Gift, ScanLine, Search, ShieldCheck, UserPlus, X } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Check-in', href: '/staff' }];

interface Person {
    id: number;
    name: string;
    /** Null for walk-ins registered at the desk without one. */
    email: string | null;
    phone: string | null;
    institution: string | null;
    is_paid: boolean;
    payment_status: string | null;
    fee_amount: string | null;
    currency: string | null;
    control_number: string | null;
    registration_code: string | null;
    /** Today's scan, if any — attendance is recorded once per person per day. */
    checked_in_at: string | null;
    days_attended: number;
    last_seen_at: string | null;
    can_print_badge: boolean;
    /** Badges already produced. Anything above zero makes the next one a reprint. */
    badges_printed: number;
}

interface FeeCategory {
    key: string;
    label: string;
    amount: string;
    currency: string;
    /** Media, invited guests — attend by role, owe nothing. */
    is_complimentary: boolean;
}

interface Institution {
    id: number;
    name: string;
}

interface Arrival {
    id: number;
    checked_in_at: string;
    attendance_date: string | null;
    is_today: boolean;
    name: string | null;
    institution: string | null;
    recorded_by: string | null;
}

interface StaffDashboardProps {
    staffName: string;
    canManageFinance: boolean;
    conferenceName: string | null;
    venue: string | null;
    filters: { search: string | null };
    results: Person[] | null;
    stats: { expected: number; here_today: number; not_arrived_today: number; attended_ever: number };
    deskOptions: {
        fee_categories: FeeCategory[];
        participant_types: Record<string, string>;
        countries: string[];
        east_africa_countries: string[];
        institutions: Institution[];
    };
    arrivals: Arrival[];
}

export default function StaffDashboard({
    staffName,
    canManageFinance,
    conferenceName,
    venue,
    filters,
    results,
    stats,
    deskOptions,
    arrivals,
}: StaffDashboardProps) {
    const { flash } = usePage<SharedData & { flash: { success?: string; error?: string; info?: string } }>().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [registering, setRegistering] = useState(false);

    const runSearch: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(route('staff.dashboard'), { search: search || undefined }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Check-in" />

            {/* Full width, two columns: the desk works on the left, arrivals stream on the right. */}
            <div className="flex w-full flex-col gap-6 p-4 pb-10 md:p-6 md:pb-12 xl:flex-row xl:items-start">
                <div className="flex min-w-0 flex-1 flex-col gap-5">
                    <section className="relative overflow-hidden rounded-[24px] bg-[#0d3fa8] px-6 py-10 text-white md:px-10 md:py-12">
                        <div className="absolute -top-28 -right-20 size-72 rounded-full bg-white/5" />
                        <div className="relative">
                            <div className="flex flex-wrap items-end justify-between gap-4">
                                <div>
                                    <p className="text-xs font-black tracking-[0.24em] text-[#8fd45a] uppercase">Venue desk</p>
                                    <h1 className="mt-2 font-serif text-3xl font-semibold">Who is at the desk?</h1>
                                    <p className="mt-1.5 text-sm text-white/60">
                                        {staffName}
                                        {conferenceName ? ` · ${conferenceName}` : ''}
                                        {venue ? ` · ${venue}` : ''}
                                    </p>
                                </div>
                                <div className="text-left sm:text-right">
                                    <div className="font-serif text-3xl font-semibold tabular-nums">
                                        {stats.here_today}
                                        <span className="text-lg text-white/50">/{stats.expected}</span>
                                    </div>
                                    <div className="mt-0.5 text-xs font-semibold text-white/70">here today</div>
                                    {stats.attended_ever > stats.here_today && (
                                        <div className="mt-0.5 text-xs text-white/50">{stats.attended_ever} attended overall</div>
                                    )}
                                </div>
                            </div>

                            <form onSubmit={runSearch} className="mt-7 flex flex-col gap-2.5 sm:flex-row">
                                <div className="relative flex-1">
                                    <Search className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        placeholder="Name, email, institution, badge or control number"
                                        autoFocus
                                        className="h-14 border-0 bg-white pl-12 text-base text-slate-900 placeholder:text-slate-400 md:text-lg"
                                    />
                                </div>
                                <Button type="submit" className="h-14 bg-[#4c8a1f] px-8 text-base font-bold hover:bg-[#3f751a]">
                                    Search
                                </Button>
                            </form>

                            <p className="mt-3 text-xs text-white/60">
                                {stats.not_arrived_today} badge holder{stats.not_arrived_today === 1 ? '' : 's'} yet to be scanned today. Attendance
                                is recorded once per person per day.{' '}
                                <Link href={route('staff.registrants')} className="font-semibold text-white/85 underline underline-offset-2">
                                    Browse all registrants
                                </Link>
                                .
                            </p>
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

                    {results !== null && (
                        <section className="bg-card rounded-2xl border">
                            <div className="flex items-center justify-between border-b px-5 py-3.5">
                                <h2 className="text-sm font-bold">
                                    {results.length} {results.length === 1 ? 'match' : 'matches'} for “{filters.search}”
                                </h2>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSearch('');
                                        router.get(route('staff.dashboard'), {}, { preserveState: true });
                                    }}
                                    className="text-muted-foreground hover:text-foreground flex items-center gap-1 text-xs font-semibold"
                                >
                                    <X className="size-3.5" />
                                    Clear
                                </button>
                            </div>

                            {results.length === 0 ? (
                                <div className="p-10 text-center">
                                    <p className="font-semibold">Nobody found</p>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        They may not be registered. Register them below and they can pay on the spot.
                                    </p>
                                    <Button onClick={() => setRegistering(true)} className="mt-5 bg-[#4c8a1f] font-bold hover:bg-[#3f751a]">
                                        <UserPlus className="size-4" />
                                        Register them now
                                    </Button>
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {results.map((person) => (
                                        <PersonRow key={person.id} person={person} />
                                    ))}
                                </div>
                            )}
                        </section>
                    )}

                    <DashboardCard>
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h2 className="flex items-center gap-2 text-lg font-semibold">
                                    <UserPlus className="size-5 text-[#4c8a1f]" />
                                    Someone not registered?
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Register them here and they get a control number to pay with straight away.
                                </p>
                            </div>
                            <Button variant={registering ? 'outline' : 'default'} onClick={() => setRegistering(!registering)}>
                                {registering ? 'Cancel' : 'Register a walk-in'}
                            </Button>
                        </div>

                        {registering && (
                            <WalkInForm deskOptions={deskOptions} canManageFinance={canManageFinance} onDone={() => setRegistering(false)} />
                        )}
                    </DashboardCard>

                    <p className="text-muted-foreground px-1 text-xs leading-5">
                        A badge only exists once a payment is verified or waived
                        {canManageFinance ? '.' : ', and only finance can do that — send unpaid attendees to the finance desk.'}
                    </p>
                </div>

                <ArrivalsPanel arrivals={arrivals} today={stats.here_today} />
            </div>
        </AppLayout>
    );
}

/** Live stream of who is walking in, pinned beside the desk. */
function ArrivalsPanel({ arrivals, today }: { arrivals: Arrival[]; today: number }) {
    return (
        <aside className="bg-card w-full shrink-0 rounded-2xl border xl:sticky xl:top-6 xl:w-[340px]">
            <div className="flex items-center justify-between border-b px-5 py-4">
                <h2 className="flex items-center gap-2 text-sm font-bold">
                    <span className="relative flex size-2">
                        <span className="absolute inline-flex size-full animate-ping rounded-full bg-[#4c8a1f] opacity-60" />
                        <span className="relative inline-flex size-2 rounded-full bg-[#4c8a1f]" />
                    </span>
                    Checking in
                </h2>
                <span className="text-muted-foreground text-xs font-semibold tabular-nums">{today} today</span>
            </div>

            {arrivals.length === 0 ? (
                <div className="p-8 text-center">
                    <ScanLine className="text-muted-foreground mx-auto size-6" />
                    <p className="mt-3 text-sm font-semibold">Nobody yet</p>
                    <p className="text-muted-foreground mt-1 text-xs leading-5">Arrivals appear here as badges are scanned in the app.</p>
                </div>
            ) : (
                <div className="max-h-[70vh] divide-y overflow-y-auto">
                    {arrivals.map((arrival) => (
                        <div key={arrival.id} className="flex items-start gap-3 px-5 py-3">
                            <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-[#4c8a1f]" />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold">{arrival.name ?? 'Unknown registrant'}</p>
                                {arrival.institution && <p className="text-muted-foreground truncate text-xs">{arrival.institution}</p>}
                                <p className="text-muted-foreground mt-0.5 text-xs tabular-nums">
                                    {arrival.is_today ? formatTime(arrival.checked_in_at) : formatDateTime(arrival.checked_in_at)}
                                    {arrival.recorded_by ? ` · ${arrival.recorded_by}` : ''}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </aside>
    );
}

/**
 * Deliberately just enough to pick the right person out of a list of matches
 * — name, a hint of who they are, and their standing. Everything else (issuing
 * a control number, settling a payment, printing a badge, their full history)
 * lives on the page this opens, next to the details that justify each action.
 */
function PersonRow({ person }: { person: Person }) {
    return (
        <Link
            href={route('staff.registrant', person.id)}
            className="flex items-center justify-between gap-4 p-5 transition hover:bg-slate-50 dark:hover:bg-slate-900/40"
        >
            <div className="min-w-0">
                <p className="text-base font-semibold">{person.name}</p>
                <p className="text-muted-foreground truncate text-sm">{person.institution ?? person.email ?? person.phone ?? 'No contact on file'}</p>
            </div>
            <div className="flex shrink-0 items-center gap-3">
                <StandingBadge person={person} />
                <ChevronRight className="text-muted-foreground size-4" />
            </div>
        </Link>
    );
}

function WalkInForm({
    deskOptions,
    canManageFinance,
    onDone,
}: {
    deskOptions: StaffDashboardProps['deskOptions'];
    canManageFinance: boolean;
    onDone: () => void;
}) {
    // Free entry (media, invited guests) needs none of the tier-picking fields
    // below — asking for them anyway is what made this form slow. The tab
    // decides which section of the form is even relevant.
    const [mode, setMode] = useState<'paying' | 'free'>('paying');
    const { data, setData, post, transform, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        institution_id: '',
        institution_other: '',
        participant_type: '',
        country: '',
        fee_category: '',
        student_verified_in_person: false as boolean,
        waive: false as boolean,
        waive_notes: '',
    });

    // Paid tiers are narrowed to the one the country and participant type allow
    // — the server runs FeeTier::guard regardless, so this only stops the desk
    // picking one that would bounce.
    const paidCategories = useMemo(() => {
        const isEastAfrican = deskOptions.east_africa_countries.includes(data.country);
        const wantsStudent = data.participant_type === 'student';

        return deskOptions.fee_categories.filter((category) => {
            if (category.is_complimentary) return false;

            const isStudent = category.key.startsWith('student_');
            if (isStudent !== wantsStudent) return false;
            if (!data.country) return true;
            return isEastAfrican ? category.key.endsWith('_east_africa') : category.key.endsWith('_non_east_africa');
        });
    }, [deskOptions, data.country, data.participant_type]);

    // Complimentary categories have no region or student split to narrow by, so
    // they are always offered.
    const complimentaryCategories = useMemo(() => deskOptions.fee_categories.filter((category) => category.is_complimentary), [deskOptions]);

    const switchMode = (next: 'paying' | 'free') => {
        setMode(next);
        setData((current) => ({
            ...current,
            fee_category: '',
            country: '',
            student_verified_in_person: false,
            waive: false,
            waive_notes: '',
        }));
    };

    // Country only ever decides a fee tier — nothing to send when the category
    // has none. Sent as null (not '') so the nullable rule on the server
    // actually skips it, rather than validating an empty string as a country.
    transform((form) => ({ ...form, country: mode === 'free' ? null : form.country }));

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('staff.walk-ins.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setMode('paying');
                onDone();
            },
        });
    };

    return (
        <form onSubmit={submit} className="mt-5 flex flex-col gap-4 border-t pt-5">
            <div className="flex gap-2">
                <ModeTab active={mode === 'paying'} onClick={() => switchMode('paying')} icon={CreditCard} label="Paying a fee" />
                <ModeTab active={mode === 'free'} onClick={() => switchMode('free')} icon={Gift} label="Attending free" />
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <Field label="Full name" required error={errors.name}>
                    <Input value={data.name} onChange={(event) => setData('name', event.target.value)} autoComplete="off" />
                </Field>
                <Field label="Phone" required error={errors.phone} hint="The control number is sent here by SMS.">
                    <Input value={data.phone} onChange={(event) => setData('phone', event.target.value)} inputMode="tel" />
                </Field>
                <Field label="Institution" required error={errors.institution_id}>
                    <Select
                        value={data.institution_id}
                        onValueChange={(value) => {
                            setData('institution_id', value);
                            if (value !== 'other') setData('institution_other', '');
                        }}
                    >
                        <SelectTrigger aria-invalid={Boolean(errors.institution_id)}>
                            <SelectValue placeholder="Select institution" />
                        </SelectTrigger>
                        <SelectContent>
                            {deskOptions.institutions.map((institution) => (
                                <SelectItem key={institution.id} value={String(institution.id)}>
                                    {institution.name}
                                </SelectItem>
                            ))}
                            <SelectItem value="other">Other (not listed)</SelectItem>
                        </SelectContent>
                    </Select>
                    {data.institution_id === 'other' && (
                        <Input
                            className="mt-2"
                            value={data.institution_other}
                            onChange={(event) => setData('institution_other', event.target.value)}
                            placeholder="Enter their institution or organization"
                        />
                    )}
                    {errors.institution_other && <p className="text-destructive text-xs">{errors.institution_other}</p>}
                </Field>
                <Field label="Email" error={errors.email} hint="Optional — leave blank if they do not have one.">
                    <Input type="email" value={data.email} onChange={(event) => setData('email', event.target.value.toLowerCase())} />
                </Field>

                <Field label="Participant type" required error={errors.participant_type}>
                    <select
                        value={data.participant_type}
                        onChange={(event) => {
                            setData('participant_type', event.target.value);
                            setData('fee_category', '');
                        }}
                        className="border-input bg-background text-foreground h-10 w-full rounded-md border px-3 text-sm"
                    >
                        <option value="">Choose…</option>
                        {Object.entries(deskOptions.participant_types).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </Field>

                {/* A plain select, not a type-ahead. The desk types a country, sees
                    no dropdown affordance, moves on — and the category list stays
                    locked with no way to tell why. Only paying tiers need it: a
                    complimentary category has no region to price by. */}
                {mode === 'paying' && (
                    <Field label="Country" required error={errors.country}>
                        <select
                            value={data.country}
                            onChange={(event) => {
                                setData('country', event.target.value);
                                setData('fee_category', '');
                            }}
                            className="border-input bg-background text-foreground h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="">Choose…</option>
                            <optgroup label="East Africa">
                                {deskOptions.east_africa_countries.map((country) => (
                                    <option key={country} value={country}>
                                        {country}
                                    </option>
                                ))}
                            </optgroup>
                            <optgroup label="All countries">
                                {deskOptions.countries.map((country) => (
                                    <option key={country} value={country}>
                                        {country}
                                    </option>
                                ))}
                            </optgroup>
                        </select>
                    </Field>
                )}
            </div>

            {mode === 'paying' ? (
                <div className="grid gap-4">
                    <Field label="Registration category" required error={errors.fee_category}>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {paidCategories.map((category) => (
                                <CategoryOption
                                    key={category.key}
                                    category={category}
                                    selected={data.fee_category === category.key}
                                    onSelect={() => setData('fee_category', category.key)}
                                />
                            ))}
                        </div>

                        {!data.country || !data.participant_type ? (
                            <p className="text-muted-foreground text-sm">Pick a participant type and country to see the paying rates.</p>
                        ) : paidCategories.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No paying category matches that combination.</p>
                        ) : null}
                    </Field>

                    {data.participant_type === 'student' && (
                        <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-amber-300/60 bg-amber-50 p-3.5 text-sm dark:bg-amber-950/30">
                            <input
                                type="checkbox"
                                checked={data.student_verified_in_person}
                                onChange={(event) => setData('student_verified_in_person', event.target.checked)}
                                className="mt-0.5 size-4"
                            />
                            <span>
                                <span className="flex items-center gap-1.5 font-semibold text-amber-900 dark:text-amber-300">
                                    <ShieldCheck className="size-4" />I checked their student ID in person
                                </span>
                                <span className="text-amber-800/80 dark:text-amber-400/80">
                                    Ticking this verifies them on the spot, same as an online document review — a control number can be issued right
                                    away. Leave it unchecked if they don't have their ID with them; they can still register and verify online before
                                    paying.
                                </span>
                            </span>
                        </label>
                    )}

                    {canManageFinance && data.fee_category && (
                        <div className="rounded-xl border p-3.5">
                            <label className="flex cursor-pointer items-start gap-3 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.waive}
                                    onChange={(event) => setData('waive', event.target.checked)}
                                    className="mt-0.5 size-4"
                                />
                                <span>
                                    <span className="font-semibold">Waive this fee</span>
                                    <span className="text-muted-foreground block">
                                        Registers them on the rate above but skips billing — badge ready immediately, no control number. For an
                                        invited guest or other one-off exception who isn't paying, without needing a free-entry category for it.
                                    </span>
                                </span>
                            </label>
                            {errors.waive && <p className="text-destructive mt-1 text-xs">{errors.waive}</p>}

                            {data.waive && (
                                <div className="mt-3">
                                    <Textarea
                                        value={data.waive_notes}
                                        onChange={(event) => setData('waive_notes', event.target.value)}
                                        placeholder="Notes — required (e.g. who authorised this and why)"
                                        rows={2}
                                        disabled={processing}
                                        className="bg-background text-sm"
                                    />
                                    {errors.waive_notes && <p className="text-destructive mt-1 text-xs">{errors.waive_notes}</p>}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            ) : (
                <Field label="Attending as" required error={errors.fee_category}>
                    <p className="text-muted-foreground mb-2.5 text-xs leading-5">
                        Media and invited guests owe nothing. No control number is issued and their badge is ready immediately.
                    </p>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {complimentaryCategories.map((category) => (
                            <CategoryOption
                                key={category.key}
                                category={category}
                                selected={data.fee_category === category.key}
                                onSelect={() => setData('fee_category', category.key)}
                            />
                        ))}
                    </div>
                </Field>
            )}

            <div>
                <Button type="submit" disabled={processing} className="h-11 bg-[#4c8a1f] px-6 font-bold hover:bg-[#3f751a]">
                    {mode === 'free' ? 'Register — free entry' : data.waive ? 'Register — fee waived' : 'Register and issue control number'}
                </Button>
            </div>
        </form>
    );
}

function ModeTab({ active, onClick, icon: Icon, label }: { active: boolean; onClick: () => void; icon: typeof CreditCard; label: string }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold transition',
                active ? 'border-[#4c8a1f] bg-[#f3f9ee] text-[#33620f] dark:bg-[#4c8a1f]/10' : 'text-muted-foreground hover:border-[#4c8a1f]/40',
            )}
        >
            <Icon className="size-4" />
            {label}
        </button>
    );
}

function CategoryOption({ category, selected, onSelect }: { category: FeeCategory; selected: boolean; onSelect: () => void }) {
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={cn(
                'rounded-xl border p-3 text-left transition',
                selected ? 'border-[#4c8a1f] bg-[#f3f9ee] dark:bg-[#4c8a1f]/10' : 'hover:border-[#4c8a1f]/40',
            )}
        >
            <div className="text-sm font-semibold">{category.label}</div>
            <div className={cn('mt-0.5 text-sm', category.is_complimentary ? 'font-semibold text-[#4c8a1f]' : 'text-muted-foreground')}>
                {category.is_complimentary ? 'No fee' : formatAmount(category.amount, category.currency)}
            </div>
        </button>
    );
}

function Field({
    label,
    error,
    hint,
    required = false,
    children,
}: {
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <label className="text-sm font-semibold">
                {label}
                {required && <span className="text-destructive ml-1">*</span>}
            </label>
            {children}
            {hint && !error && <p className="text-muted-foreground text-xs">{hint}</p>}
            {error && <p className="text-destructive text-xs">{error}</p>}
        </div>
    );
}
