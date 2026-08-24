import { PrintBadgeButton, StandingBadge, formatAmount } from '@/components/registrant-standing';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn, formatPersonName } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Search, Users } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

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
                        {tabs.map(({ key, label }) => {
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
                        <select
                            value={filters.category}
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
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Name, email, phone, badge, control number"
                                    className="h-9 w-full pl-9 sm:w-72"
                                />
                            </div>
                            <Button type="submit" variant="secondary" className="h-9">
                                Search
                            </Button>
                        </form>
                    </div>
                </div>

                <section className="bg-card overflow-hidden rounded-2xl border">
                    <div className="divide-y">
                        {people.data.length === 0 ? (
                            <div className="p-12 text-center">
                                <Users className="text-muted-foreground mx-auto size-7" />
                                <p className="mt-3 font-semibold">Nobody matches this view</p>
                                <p className="text-muted-foreground mt-1 text-sm">Clear the search or pick a different filter.</p>
                            </div>
                        ) : (
                            people.data.map((person) => (
                                <article key={person.id} className="grid gap-3 p-4 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] md:items-center">
                                    <div className="min-w-0">
                                        <p className="font-semibold">{formatPersonName(person)}</p>
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

                                    <div className="flex flex-wrap items-center justify-end gap-3">
                                        <StandingBadge person={person} />
                                        {person.can_print_badge && <PrintBadgeButton person={person} printRoute={route('staff.badge', person.id)} />}
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
