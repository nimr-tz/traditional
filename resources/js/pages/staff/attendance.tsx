import { formatDateTime, formatTime } from '@/components/registrant-standing';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, ScanLine, Search, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Check-in', href: '/staff' },
    { title: 'Attendance', href: '/staff/attendance' },
];

interface Scan {
    id: number;
    name: string;
    institution: string | null;
    registration_code: string | null;
    checked_in_at: string | null;
    date: string | null;
    recorded_by: string | null;
}

interface Day {
    date: string;
    scans: number;
}

interface Paginated<T> {
    data: T[];
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

interface AttendanceProps {
    scans: Paginated<Scan>;
    filters: { date: string; search: string | null };
    days: Day[];
    summary: { scans: number; people: number; conference_total: number };
}

function formatDay(date: string): string {
    const parsed = new Date(`${date}T00:00:00`);

    return Number.isNaN(parsed.getTime()) ? date : new Intl.DateTimeFormat('en', { weekday: 'short', day: 'numeric', month: 'short' }).format(parsed);
}

export default function StaffAttendance({ scans, filters, days, summary }: AttendanceProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const allDays = filters.date === 'all';

    const go = (params: Record<string, string | undefined>) => {
        router.get(
            route('staff.attendance'),
            {
                date: filters.date,
                search: search || undefined,
                ...params,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const submitSearch: FormEventHandler = (event) => {
        event.preventDefault();
        go({});
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Attendance" />

            <div className="flex w-full flex-col gap-5 p-4 pb-10 md:p-6 md:pb-12">
                <header className="flex flex-col gap-4 border-b pb-5 md:flex-row md:items-end md:justify-between">
                    <div className="flex items-start gap-4">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#eaf1ff] text-[#135eeb] dark:bg-[#135eeb]/15">
                            <ScanLine className="size-5" />
                        </div>
                        <div>
                            <p className="text-xs font-bold tracking-[0.18em] text-[#135eeb] uppercase">Venue desk</p>
                            <h1 className="mt-2 font-serif text-3xl font-semibold">Attendance</h1>
                            <p className="text-muted-foreground mt-2 text-sm">
                                Every badge scanned in the check-in app, newest first. Scanning is done in the app — this is the record.
                            </p>
                        </div>
                    </div>

                    <dl className="flex gap-6 text-sm">
                        <div>
                            <dt className="text-muted-foreground text-xs">{allDays ? 'Scans' : 'Scanned in'}</dt>
                            <dd className="font-serif text-2xl font-semibold tabular-nums">{summary.scans}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground text-xs">People</dt>
                            <dd className="font-serif text-2xl font-semibold tabular-nums">{summary.people}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground text-xs">Conference total</dt>
                            <dd className="font-serif text-2xl font-semibold tabular-nums">{summary.conference_total}</dd>
                        </div>
                    </dl>
                </header>

                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-wrap gap-2" aria-label="Filter by day">
                        <DayTab active={allDays} label="All days" onClick={() => go({ date: 'all' })} />
                        {days.map((day) => (
                            <DayTab
                                key={day.date}
                                active={filters.date === day.date}
                                label={formatDay(day.date)}
                                count={day.scans}
                                onClick={() => go({ date: day.date })}
                            />
                        ))}
                        {days.length === 0 && <span className="text-muted-foreground text-sm">No scans recorded yet.</span>}
                    </div>

                    <form onSubmit={submitSearch} className="flex gap-2 lg:shrink-0">
                        <div className="relative">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                type="search"
                                aria-label="Search attendance"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Name, institution, badge code"
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

                <section className="bg-card overflow-hidden rounded-2xl border">
                    <div className="divide-y">
                        {scans.data.length === 0 ? (
                            <div className="p-12 text-center">
                                <CalendarDays className="text-muted-foreground mx-auto size-7" />
                                <p className="mt-3 font-semibold">Nobody scanned in for this view</p>
                                <p className="text-muted-foreground mt-1 text-sm">Pick another day, or clear the search.</p>
                            </div>
                        ) : (
                            scans.data.map((scan) => (
                                <article key={scan.id} className="grid gap-3 p-4 sm:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_auto] sm:items-center">
                                    <div className="min-w-0">
                                        <p className="font-semibold">{scan.name}</p>
                                        {scan.institution && <p className="text-muted-foreground truncate text-xs">{scan.institution}</p>}
                                    </div>

                                    <div className="text-muted-foreground min-w-0 text-xs">
                                        {scan.registration_code && <p className="font-mono">{scan.registration_code}</p>}
                                        {scan.recorded_by && <p className="mt-0.5">by {scan.recorded_by}</p>}
                                    </div>

                                    <div className="text-right text-sm font-medium tabular-nums sm:whitespace-nowrap">
                                        {scan.checked_in_at
                                            ? allDays
                                                ? formatDateTime(scan.checked_in_at)
                                                : formatTime(scan.checked_in_at)
                                            : (scan.date ?? '—')}
                                    </div>
                                </article>
                            ))
                        )}
                    </div>

                    {scans.total > 0 && (
                        <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-3 border-t p-4 text-xs">
                            <span>
                                Showing {scans.from}–{scans.to} of {scans.total}
                            </span>
                            {scans.links.length > 3 && (
                                <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                                    {scans.links.map((link, index) => (
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

function DayTab({ active, label, count, onClick }: { active: boolean; label: string; count?: number; onClick: () => void }) {
    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            className={cn(
                'rounded-full border px-3.5 py-1.5 text-sm font-semibold transition',
                active ? 'border-[#135eeb] bg-[#eaf1ff] text-[#135eeb] dark:bg-[#135eeb]/15' : 'hover:border-[#135eeb]/40',
            )}
        >
            {label}
            {count !== undefined && <span className="text-muted-foreground ml-2 tabular-nums">{count}</span>}
        </button>
    );
}
