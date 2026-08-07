import { IconTile } from '@/components/dashboard-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { ChartColumn } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Management Report', href: '/admin/management' },
];

/**
 * Two categorical hues, validated for colourblind separation against both the
 * light and dark chart surfaces. Their tritan separation sits in the floor
 * band, which is only legal alongside a second, non-colour encoding — so every
 * segment below is directly labelled, never identified by colour alone.
 */
const SERIES_A = '#135eeb';
const SERIES_B = '#4c8a1f';

interface Totals {
    registered: number;
    paid: number;
    unpaid: number;
    awaiting_payment: number;
    with_abstracts: number;
    paid_with_abstracts: number;
    students: number;
    non_students: number;
    east_africa: number;
    international: number;
    checked_in: number;
}

interface Abstracts {
    total: number;
    oral: number;
    poster: number;
    submitted: number;
    accepted: number;
    revision_requested: number;
    rejected: number;
    by_subtheme: { label: string; total: number }[];
}

interface CountryRow {
    label: string;
    total: number;
    paid: number;
    region: 'east_africa' | 'international';
}

interface InstitutionRow {
    label: string;
    total: number;
    paid: number;
    is_custom: boolean;
}

interface AbstractCountryRow {
    label: string;
    total: number;
    oral: number;
    poster: number;
    accepted: number;
}

interface ManagementProps {
    totals: Totals;
    abstracts: Abstracts;
    byCountry: CountryRow[];
    byInstitution: InstitutionRow[];
    abstractsByCountry: AbstractCountryRow[];
}

const pct = (value: number, total: number) => (total > 0 ? Math.round((value / total) * 100) : 0);

/** A headline number is not a chart — no plot, no legend, no hover layer. */
function StatTile({ label, value, sub }: { label: string; value: number; sub?: string }) {
    return (
        <div className="bg-card rounded-2xl border p-4">
            <div className="text-muted-foreground text-xs font-semibold">{label}</div>
            <div className="mt-2 text-3xl font-bold tabular-nums">{value.toLocaleString()}</div>
            {sub && <div className="text-muted-foreground mt-1 text-xs">{sub}</div>}
        </div>
    );
}

/**
 * One stacked bar for a two-way split. Both segments carry a visible count and
 * share, so identity never depends on the colour swatch alone.
 */
function SplitBar({ title, aLabel, aValue, bLabel, bValue }: { title: string; aLabel: string; aValue: number; bLabel: string; bValue: number }) {
    const total = aValue + bValue;

    return (
        <div className="bg-card rounded-2xl border p-5">
            <h3 className="text-sm font-semibold">{title}</h3>

            {total === 0 ? (
                <p className="text-muted-foreground mt-4 text-sm">Nothing recorded yet.</p>
            ) : (
                <>
                    {/* 2px surface gap between the fills keeps the boundary legible. */}
                    <div className="mt-4 flex h-3 w-full gap-[2px] overflow-hidden">
                        <div
                            className="h-full rounded-l-[4px]"
                            style={{ width: `${(aValue / total) * 100}%`, backgroundColor: SERIES_A }}
                            title={`${aLabel}: ${aValue} (${pct(aValue, total)}%)`}
                        />
                        <div
                            className="h-full rounded-r-[4px]"
                            style={{ width: `${(bValue / total) * 100}%`, backgroundColor: SERIES_B }}
                            title={`${bLabel}: ${bValue} (${pct(bValue, total)}%)`}
                        />
                    </div>

                    <dl className="mt-4 grid grid-cols-2 gap-3">
                        {[
                            { label: aLabel, value: aValue, color: SERIES_A },
                            { label: bLabel, value: bValue, color: SERIES_B },
                        ].map((s) => (
                            <div key={s.label} className="flex items-start gap-2">
                                <span className="mt-1 size-2.5 shrink-0 rounded-full" style={{ backgroundColor: s.color }} aria-hidden />
                                <div>
                                    <dt className="text-muted-foreground text-xs">{s.label}</dt>
                                    <dd className="text-sm font-semibold tabular-nums">
                                        {s.value.toLocaleString()} <span className="text-muted-foreground font-normal">({pct(s.value, total)}%)</span>
                                    </dd>
                                </div>
                            </div>
                        ))}
                    </dl>
                </>
            )}
        </div>
    );
}

/**
 * Ranked magnitude: bar length carries the value, so a single hue is correct —
 * colouring by rank would encode the same thing twice and repaint on filtering.
 * Every row is labelled with its value, which also makes this its own table view.
 */
function RankedBars<T extends { label: string; total: number }>({
    title,
    description,
    rows,
    secondary,
    emptyText,
}: {
    title: string;
    description?: string;
    rows: T[];
    secondary?: (row: T) => string;
    emptyText: string;
}) {
    const [showAll, setShowAll] = useState(false);
    const visible = showAll ? rows : rows.slice(0, 10);
    const max = rows.reduce((m, r) => Math.max(m, r.total), 0);

    return (
        <section className="bg-card rounded-2xl border p-5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="font-serif text-lg font-semibold">{title}</h2>
                    {description && <p className="text-muted-foreground mt-1 text-sm">{description}</p>}
                </div>
                <span className="text-muted-foreground shrink-0 text-xs tabular-nums">{rows.length} total</span>
            </div>

            {rows.length === 0 ? (
                <p className="text-muted-foreground mt-4 text-sm">{emptyText}</p>
            ) : (
                <>
                    <ul className="mt-4 space-y-3">
                        {visible.map((row) => (
                            <li key={row.label}>
                                <div className="flex items-baseline justify-between gap-3">
                                    <span className="truncate text-sm">{row.label}</span>
                                    <span className="shrink-0 text-sm font-semibold tabular-nums">
                                        {row.total.toLocaleString()}
                                        {secondary && <span className="text-muted-foreground ml-2 font-normal">{secondary(row)}</span>}
                                    </span>
                                </div>
                                <div className="mt-1.5 h-2 w-full rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div
                                        className="h-full rounded-[4px]"
                                        style={{ width: `${max ? (row.total / max) * 100 : 0}%`, backgroundColor: SERIES_B }}
                                        title={`${row.label}: ${row.total}`}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>

                    {rows.length > 10 && (
                        <Button type="button" variant="outline" size="sm" className="mt-4" onClick={() => setShowAll((v) => !v)}>
                            {showAll ? 'Show top 10' : `Show all ${rows.length}`}
                        </Button>
                    )}
                </>
            )}
        </section>
    );
}

export default function ManagementIndex({ totals, abstracts, byCountry, byInstitution, abstractsByCountry }: ManagementProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Management Report" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex items-start gap-4 border-b pb-5">
                    <IconTile tone="green">
                        <ChartColumn className="size-5" />
                    </IconTile>
                    <div>
                        <p className="text-xs font-bold tracking-[0.18em] text-[#4c8a1f] uppercase">Conference operations</p>
                        <h1 className="mt-2 font-serif text-3xl font-semibold">Management Report</h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Live totals across registration, payment, and abstracts. Counts cover participant accounts only — staff, reviewer, and
                            admin accounts are excluded throughout.
                        </p>
                    </div>
                </header>

                <section className="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Headline totals">
                    <StatTile label="All registered" value={totals.registered} sub={`${totals.checked_in.toLocaleString()} checked in`} />
                    <StatTile
                        label="Registered and paid"
                        value={totals.paid}
                        sub={`${pct(totals.paid, totals.registered)}% of registrants · includes waived`}
                    />
                    <StatTile
                        label="Registered with abstracts"
                        value={totals.with_abstracts}
                        sub={`${totals.paid_with_abstracts.toLocaleString()} of them have paid`}
                    />
                    <StatTile
                        label="All abstracts"
                        value={abstracts.total}
                        sub={`${abstracts.accepted.toLocaleString()} accepted · ${abstracts.submitted.toLocaleString()} awaiting review`}
                    />
                </section>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Splits">
                    <SplitBar title="Oral vs poster" aLabel="Oral" aValue={abstracts.oral} bLabel="Poster" bValue={abstracts.poster} />
                    <SplitBar title="Students vs normal" aLabel="Students" aValue={totals.students} bLabel="Normal" bValue={totals.non_students} />
                    <SplitBar title="Paid vs unpaid" aLabel="Paid" aValue={totals.paid} bLabel="Unpaid" bValue={totals.unpaid} />
                    <SplitBar
                        title="East Africa vs international"
                        aLabel="East Africa"
                        aValue={totals.east_africa}
                        bLabel="International"
                        bValue={totals.international}
                    />
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <RankedBars
                        title="Registration by country"
                        description="Paid share shown alongside each total."
                        rows={byCountry}
                        secondary={(row) => `${row.paid} paid`}
                        emptyText="No registrations recorded yet."
                    />

                    <RankedBars
                        title="Abstracts by country"
                        description="Attributed to the submitting author's country."
                        rows={abstractsByCountry}
                        secondary={(row) => `${row.oral} oral · ${row.poster} poster`}
                        emptyText="No abstracts submitted yet."
                    />
                </div>

                <RankedBars
                    title="Registration by institution"
                    description="Includes organisations typed in via “Other”, whose spelling may vary between registrants."
                    rows={byInstitution}
                    secondary={(row) => `${row.paid} paid`}
                    emptyText="No registrations recorded yet."
                />

                <section className="bg-card rounded-2xl border p-5">
                    <h2 className="font-serif text-lg font-semibold">Abstracts by sub-theme</h2>
                    {abstracts.by_subtheme.length === 0 ? (
                        <p className="text-muted-foreground mt-4 text-sm">No abstracts submitted yet.</p>
                    ) : (
                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full min-w-[520px] text-sm">
                                <thead className="text-muted-foreground text-left text-xs tracking-wide uppercase">
                                    <tr>
                                        <th className="pb-2">Sub-theme</th>
                                        <th className="pb-2 text-right">Abstracts</th>
                                        <th className="pb-2 text-right">Share</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {abstracts.by_subtheme.map((row) => (
                                        <tr key={row.label}>
                                            <td className="py-2.5">{row.label}</td>
                                            <td className="py-2.5 text-right font-semibold tabular-nums">{row.total}</td>
                                            <td className="text-muted-foreground py-2.5 text-right tabular-nums">
                                                {pct(row.total, abstracts.total)}%
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="bg-card rounded-2xl border p-5">
                    <h2 className="font-serif text-lg font-semibold">Abstract review status</h2>
                    <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {[
                            { label: 'Awaiting review', value: abstracts.submitted },
                            { label: 'Revision requested', value: abstracts.revision_requested },
                            { label: 'Accepted', value: abstracts.accepted },
                            { label: 'Rejected', value: abstracts.rejected },
                        ].map((row) => (
                            <div key={row.label} className={cn('rounded-xl border p-3')}>
                                <div className="text-xl font-bold tabular-nums">{row.value.toLocaleString()}</div>
                                <div className="text-muted-foreground mt-0.5 text-xs font-semibold">{row.label}</div>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
