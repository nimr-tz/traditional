import { PrintBadgeButton, StandingBadge, formatAmount } from '@/components/registrant-standing';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn, formatPersonName } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight, Search, Users, X } from 'lucide-react';
import { FormEventHandler, KeyboardEvent, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Check-in', href: '/staff' },
    { title: 'All registrants', href: '/staff/registrants' },
];

type StatusFilter = 'all' | 'here_today' | 'not_arrived' | 'unpaid' | 'complimentary' | 'never_attended';

interface Person {
    id: number;
    name: string;
    salutation: string | null;
    email: string | null;
    phone: string | null;
    institution: string | null;
    fee_category: string | null;
    is_paid: boolean;
    payment_status: string | null;
    fee_amount: string | null;
    currency: string | null;
    control_number: string | null;
    registration_code: string | null;
    checked_in_at: string | null;
    days_attended: number;
    last_seen_at: string | null;
    can_print_badge: boolean;
    badges_printed: number;
}

interface Category {
    key: string;
    label: string;
    is_complimentary: boolean;
}

interface Paginated<T> {
    data: T[];
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

interface RegistrantsProps {
    people: Paginated<Person>;
    filters: { search: string | null; status: StatusFilter; category: string };
    counts: Record<StatusFilter, number>;
    categories: Category[];
}

const tabs: { key: StatusFilter; label: string }[] = [
    { key: 'all', label: 'Everyone' },
    { key: 'here_today', label: 'Here today' },
    { key: 'not_arrived', label: 'Not arrived today' },
    { key: 'never_attended', label: 'Never attended' },
    { key: 'unpaid', label: 'Unpaid' },
    { key: 'complimentary', label: 'Attending free' },
];

export default function StaffRegistrants({ people, filters, counts, categories }: RegistrantsProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const tabRefs = useRef<(HTMLButtonElement | null)[]>([]);

    const go = (params: Record<string, string | undefined>) => {
        router.get(
            route('staff.registrants'),
            {
                status: filters.status === 'all' ? undefined : filters.status,
                category: filters.category === 'all' ? undefined : filters.category,
                search: search || undefined,
                ...params,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        go({});
    };

    const hasFilters = filters.status !== 'all' || filters.category !== 'all' || Boolean(filters.search);

    // Back to the unfiltered register in one click. The search box is cleared
    // alongside the query string, or the input would go on showing a term that
    // is no longer narrowing anything.
    const clearFilters = () => {
        setSearch('');
        router.get(route('staff.registrants'), {}, { preserveState: true, preserveScroll: true });
    };

    // Arrow keys move focus along the filters without selecting one: every
    // selection is a round trip, so arrowing across six of them should not fire
    // six requests. The focused filter is applied with Enter or Space, as any
    // button would be.
    const onTabKeyDown = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
        const offsets: Record<string, number> = { ArrowRight: 1, ArrowLeft: -1 };

        let next: number | null = null;

        if (event.key in offsets) {
            next = (index + offsets[event.key] + tabs.length) % tabs.length;
        } else if (event.key === 'Home') {
            next = 0;
        } else if (event.key === 'End') {
            next = tabs.length - 1;
        }

        if (next !== null) {
            event.preventDefault();
            tabRefs.current[next]?.focus();
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="All registrants" />

            <div className="flex w-full flex-col gap-5 p-4 pb-10 md:p-6 md:pb-12">
                <header className="flex flex-col gap-4 border-b pb-5 md:flex-row md:items-end md:justify-between">
                    <div className="flex items-start gap-4">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#eaf1ff] text-[#135eeb] dark:bg-[#135eeb]/15">
                            <Users className="size-5" />
                        </div>
                        <div>
                            <p className="text-xs font-bold tracking-[0.18em] text-[#135eeb] uppercase">Venue desk</p>
                            <h1 className="mt-2 font-serif text-3xl font-semibold">All registrants</h1>
                            <p className="text-muted-foreground mt-2 text-sm">
                                The whole register. To deal with one person quickly, use the search on the{' '}
                                <Link href={route('staff.dashboard')} className="font-semibold underline underline-offset-2">
                                    check-in desk
                                </Link>
                                .
                            </p>
                        </div>
                    </div>
                </header>

                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-wrap gap-2" role="tablist" aria-label="Filter registrants">
                        {tabs.map(({ key, label }, index) => {
                            const selected = filters.status === key;

                            return (
                                <button
                                    key={key}
                                    id={`registrant-filter-${key}`}
                                    ref={(element) => {
                                        tabRefs.current[index] = element;
                                    }}
                                    type="button"
                                    role="tab"
                                    aria-selected={selected}
                                    aria-controls="registrant-list"
                                    tabIndex={selected ? 0 : -1}
                                    onKeyDown={(event) => onTabKeyDown(event, index)}
                                    onClick={() => go({ status: key === 'all' ? undefined : key })}
                                    className={cn(
                                        'rounded-full border px-3.5 py-1.5 text-sm font-semibold transition',
                                        selected ? 'border-[#135eeb] bg-[#eaf1ff] text-[#135eeb] dark:bg-[#135eeb]/15' : 'hover:border-[#135eeb]/40',
                                    )}
                                >
                                    {label}
                                    <span className="text-muted-foreground ml-2 tabular-nums">{counts[key]}</span>
                                </button>
                            );
                        })}
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row lg:shrink-0">
                        {hasFilters && (
                            <Button type="button" variant="ghost" className="h-9" onClick={clearFilters}>
                                Clear filters
                            </Button>
                        )}

                        <select
                            value={filters.category}
                            aria-label="Filter by fee category"
                            onChange={(event) => go({ category: event.target.value === 'all' ? undefined : event.target.value })}
                            className="border-input bg-background text-foreground h-9 rounded-md border px-3 text-sm"
                        >
                            <option value="all">All categories</option>
                            {categories.map((category) => (
                                <option key={category.key} value={category.key}>
                                    {category.label}
                                    {category.is_complimentary ? ' (free)' : ''}
                                </option>
                            ))}
                        </select>

                        <form onSubmit={submit} className="flex gap-2">
                            <div className="relative">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    type="search"
                                    aria-label="Search registrants"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Name, email, phone, badge, control number"
                                    className="h-9 w-full pr-9 pl-9 sm:w-72"
                                />
                                {search && (
                                    <button
                                        type="button"
                                        aria-label="Clear search"
                                        onClick={() => {
                                            setSearch('');
                                            go({ search: undefined });
                                        }}
                                        className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 -translate-y-1/2 rounded p-1"
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                )}
                            </div>
                            <Button type="submit" variant="secondary" className="h-9">
                                Search
                            </Button>
                        </form>
                    </div>
                </div>

                <section className="bg-card overflow-hidden rounded-2xl border">
                    <div className="divide-y" id="registrant-list" role="tabpanel" aria-labelledby={`registrant-filter-${filters.status}`}>
                        {people.data.length === 0 ? (
                            <div className="p-12 text-center">
                                <Users className="text-muted-foreground mx-auto size-7" />
                                <p className="mt-3 font-semibold">Nobody matches this view</p>
                                <p className="text-muted-foreground mt-1 text-sm">Clear the search or pick a different filter.</p>
                                {hasFilters && (
                                    <Button type="button" variant="secondary" className="mt-4" onClick={clearFilters}>
                                        Clear filters
                                    </Button>
                                )}
                            </div>
                        ) : (
                            people.data.map((person) => (
                                // The name carries the link, but its ::before is stretched over the
                                // whole row so anywhere on the row opens the person — while the print
                                // button stays a sibling rather than a button nested inside an anchor.
                                <article
                                    key={person.id}
                                    className="relative grid gap-3 p-4 transition hover:bg-slate-50 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] md:items-center dark:hover:bg-slate-900/40"
                                >
                                    <div className="min-w-0">
                                        <Link
                                            href={route('staff.registrant', person.id)}
                                            className="font-semibold before:absolute before:inset-0 before:content-['']"
                                        >
                                            {formatPersonName(person)}
                                        </Link>
                                        <p className="text-muted-foreground truncate text-sm">
                                            {person.email ?? person.phone ?? 'No contact on file'}
                                        </p>
                                        {person.institution && <p className="text-muted-foreground truncate text-xs">{person.institution}</p>}
                                    </div>

                                    <div className="text-muted-foreground min-w-0 text-xs">
                                        {person.fee_category && <p className="capitalize">{person.fee_category.replaceAll('_', ' ')}</p>}
                                        {!person.is_paid && formatAmount(person.fee_amount, person.currency) && (
                                            <p className="mt-0.5">{formatAmount(person.fee_amount, person.currency)} due</p>
                                        )}
                                        {person.control_number && <p className="mt-0.5 font-mono">CN {person.control_number}</p>}
                                    </div>

                                    <div className="relative z-10 flex flex-wrap items-center justify-end gap-3">
                                        <StandingBadge person={person} />
                                        {person.can_print_badge && <PrintBadgeButton person={person} printRoute={route('staff.badge', person.id)} />}
                                        <ChevronRight className="text-muted-foreground size-4 shrink-0" />
                                    </div>
                                </article>
                            ))
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
            </div>
        </AppLayout>
    );
}
