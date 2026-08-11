import { DashboardCard } from '@/components/dashboard-card';
import { StatusPill, type PillTone } from '@/components/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Clock3, ScanLine, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Check-in', href: '/staff' }];

type StatusFilter = 'all' | 'arrived' | 'not_arrived' | 'unpaid';

interface Person {
    id: number;
    name: string;
    email: string;
    institution: string | null;
    participant_type: string | null;
    is_paid: boolean;
    payment_status: string | null;
    fee_amount: string | null;
    currency: string | null;
    registration_code: string | null;
    checked_in_at: string | null;
}

interface RecentCheckin {
    id: number;
    checked_in_at: string;
    name: string | null;
    institution: string | null;
    recorded_by: string | null;
}

interface Paginated<T> {
    data: T[];
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

interface StaffDashboardProps {
    staffName: string;
    conferenceName: string | null;
    venue: string | null;
    people: Paginated<Person>;
    filters: { search: string | null; status: StatusFilter };
    stats: {
        registered: number;
        expected: number;
        checked_in: number;
        today: number;
        not_arrived: number;
        unpaid: number;
        recorded_by_me: number;
    };
    recent: RecentCheckin[];
}

function formatTime(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('en', { hour: '2-digit', minute: '2-digit' }).format(date);
}

function formatDateTime(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(date);
}

function formatAmount(amount: string | null, currency: string | null): string | null {
    if (!amount || !currency) return null;

    const value = Number(amount);

    return `${currency} ${Number.isNaN(value) ? amount : value.toLocaleString()}`;
}

/** The one line that tells a door person what to do with whoever is in front of them. */
function standing(person: Person): { label: string; tone: PillTone; detail: string } {
    if (person.checked_in_at) {
        return { label: 'Already inside', tone: 'positive', detail: `Arrived ${formatDateTime(person.checked_in_at)}` };
    }

    if (person.is_paid) {
        return {
            label: 'Ready to scan',
            tone: 'neutral',
            detail: person.registration_code ? `Badge ${person.registration_code}` : 'Paid — badge pending',
        };
    }

    const owed = formatAmount(person.fee_amount, person.currency);

    return {
        label: 'Cannot enter — unpaid',
        tone: 'attention',
        detail: owed ? `${owed} outstanding` : 'No payment recorded',
    };
}

const filterTabs: { key: StatusFilter; label: string; statKey: keyof StaffDashboardProps['stats'] }[] = [
    { key: 'all', label: 'Everyone', statKey: 'registered' },
    { key: 'not_arrived', label: 'Expected, not arrived', statKey: 'not_arrived' },
    { key: 'arrived', label: 'Already inside', statKey: 'checked_in' },
    { key: 'unpaid', label: 'Unpaid', statKey: 'unpaid' },
];

export default function StaffDashboard({ staffName, conferenceName, venue, people, filters, stats, recent }: StaffDashboardProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    const go = (params: Record<string, string | undefined>) => {
        router.get(
            route('staff.dashboard'),
            { status: filters.status === 'all' ? undefined : filters.status, search: search || undefined, ...params },
            { preserveState: true, preserveScroll: true },
        );
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        go({});
    };

    const arrivalRate = stats.expected > 0 ? Math.round((stats.checked_in / stats.expected) * 100) : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Check-in" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 pb-10 md:p-6 md:pb-12">
                <section className="relative overflow-hidden rounded-[24px] bg-[#0d3fa8] px-6 py-8 text-white md:px-10">
                    <div className="absolute -top-28 -right-20 size-72 rounded-full bg-white/5" />
                    <div className="relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p className="text-xs font-black tracking-[0.24em] text-[#8fd45a] uppercase">Venue check-in</p>
                            <h1 className="mt-3 font-serif text-3xl font-semibold">Welcome, {staffName.split(' ')[0]}</h1>
                            <p className="mt-2 max-w-xl text-sm leading-6 text-white/75">
                                {conferenceName}
                                {venue ? ` · ${venue}` : ''}. Badges are scanned in the check-in app — this page is the register.
                            </p>
                        </div>
                        <div className="shrink-0 text-left md:text-right">
                            <div className="font-serif text-4xl font-semibold tabular-nums">{arrivalRate}%</div>
                            <div className="mt-1 text-xs font-semibold text-white/70">
                                {stats.checked_in} of {stats.expected} arrived
                            </div>
                            {stats.recorded_by_me > 0 && (
                                <div className="mt-2 text-xs font-semibold text-[#8fd45a]">You scanned {stats.recorded_by_me}</div>
                            )}
                        </div>
                    </div>
                </section>

                <section className="bg-card rounded-2xl border">
                    <div className="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex flex-wrap gap-2" role="tablist" aria-label="Filter registrants">
                            {filterTabs.map(({ key, label, statKey }) => {
                                const selected = filters.status === key;

                                return (
                                    <button
                                        key={key}
                                        type="button"
                                        role="tab"
                                        aria-selected={selected}
                                        onClick={() => go({ status: key === 'all' ? undefined : key })}
                                        className={cn(
                                            'rounded-full border px-3.5 py-1.5 text-sm font-semibold transition',
                                            selected
                                                ? 'border-[#135eeb] bg-[#eaf1ff] text-[#135eeb] dark:bg-[#135eeb]/15'
                                                : 'hover:border-[#135eeb]/40',
                                        )}
                                    >
                                        {label}
                                        <span className="text-muted-foreground ml-2 tabular-nums">{stats[statKey]}</span>
                                    </button>
                                );
                            })}
                        </div>

                        <form onSubmit={submit} className="flex w-full gap-2 lg:max-w-sm">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Name, email, institution, or badge code"
                                    className="pl-9"
                                />
                            </div>
                            <Button type="submit" variant="secondary">
                                Search
                            </Button>
                        </form>
                    </div>

                    <div className="divide-y">
                        {people.data.length === 0 ? (
                            <div className="p-12 text-center">
                                <Search className="text-muted-foreground mx-auto size-7" />
                                <p className="mt-3 font-semibold">Nobody matches this view</p>
                                <p className="text-muted-foreground mt-1 text-sm">Clear the search or pick a different filter.</p>
                            </div>
                        ) : (
                            people.data.map((person) => {
                                const state = standing(person);

                                return (
                                    <article key={person.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                        <div className="min-w-0 flex-1">
                                            <p className="font-semibold">{person.name}</p>
                                            <p className="text-muted-foreground truncate text-sm">{person.email}</p>
                                            {person.institution && <p className="text-muted-foreground truncate text-xs">{person.institution}</p>}
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <StatusPill tone={state.tone}>{state.label}</StatusPill>
                                            <p className="text-muted-foreground mt-1.5 text-xs">{state.detail}</p>
                                        </div>
                                    </article>
                                );
                            })
                        )}
                    </div>

                    {people.total > 0 && (
                        <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-3 border-t p-4 text-xs">
                            <span>
                                Showing {people.from}–{people.to} of {people.total}
                            </span>
                            {people.links.length > 3 && (
                                <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                                    {people.links.map((link, index) => (
                                        <Link
                                            key={index}
                                            href={link.url ?? '#'}
                                            preserveState
                                            preserveScroll
                                            className={cn(
                                                'rounded-lg px-2.5 py-1',
                                                link.active ? 'bg-[#135eeb] text-white' : 'hover:bg-muted',
                                                !link.url && 'pointer-events-none opacity-40',
                                            )}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </nav>
                            )}
                        </div>
                    )}
                </section>

                <DashboardCard className="p-0">
                    <div className="flex items-center gap-3 border-b p-5">
                        <Clock3 className="size-5 text-[#135eeb]" />
                        <h2 className="text-lg font-semibold">Recent arrivals</h2>
                        <span className="text-muted-foreground ml-auto text-xs">{stats.today} today</span>
                    </div>

                    {recent.length === 0 ? (
                        <div className="p-10 text-center">
                            <ScanLine className="text-muted-foreground mx-auto size-7" />
                            <p className="mt-3 font-semibold">Nobody has been checked in yet</p>
                            <p className="text-muted-foreground mt-1 text-sm">Arrivals appear here as badges are scanned in the app.</p>
                        </div>
                    ) : (
                        <div className="divide-y">
                            {recent.map((entry) => (
                                <div key={entry.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                    <div className="flex min-w-0 items-center gap-3">
                                        <CheckCircle2 className="size-4 shrink-0 text-[#4c8a1f]" />
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold">{entry.name ?? 'Unknown registrant'}</p>
                                            {entry.institution && <p className="text-muted-foreground truncate text-xs">{entry.institution}</p>}
                                        </div>
                                    </div>
                                    <div className="text-muted-foreground text-right text-xs">
                                        <div className="font-semibold tabular-nums">{formatTime(entry.checked_in_at)}</div>
                                        {entry.recorded_by && <div className="mt-0.5">by {entry.recorded_by}</div>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </DashboardCard>

                <p className="text-muted-foreground px-1 text-xs leading-5">
                    Only a verified or waived payment produces a badge, so anyone marked unpaid cannot be checked in until finance settles it. Send
                    them to the finance desk rather than turning them away.
                </p>
            </div>
        </AppLayout>
    );
}
