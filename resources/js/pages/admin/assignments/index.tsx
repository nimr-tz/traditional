import { IconTile } from '@/components/dashboard-card';
import { StatusPill, type PillTone } from '@/components/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, ClipboardCheck, Clock3, Gavel, LoaderCircle, Search, UserPlus, Users } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Reviewer Assignments', href: '/admin/assignments' },
];

type Stage = 'unassigned' | 'awaiting_reviews' | 'ready_for_decision' | 'decided';

interface Ref {
    id: number;
    name: string;
}

interface Submission {
    id: number;
    title: string;
    status: string;
    created_at: string;
    author: { id: number; name: string; email: string } | null;
    subtheme: { id: number; title: string } | null;
    reviewer_one: Ref | null;
    reviewer_two: Ref | null;
    decided_reviewer_ids: number[];
    decisions_count: number;
    stage: Stage;
}

interface Reviewer {
    id: number;
    name: string;
    email: string;
    assigned_count: number;
    awaiting_count: number;
    decisions_count: number;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface AssignmentsIndexProps {
    submissions: Paginated<Submission>;
    filters: { stage?: Stage; subtheme_id?: number; reviewer_id?: number; search?: string };
    stats: { total: number; unassigned: number; awaiting_reviews: number; ready_for_decision: number; decided: number };
    reviewers: Reviewer[];
    subthemes: { id: number; title: string }[];
}

const STAGE_CONFIG: Record<Stage, { label: string; tone: PillTone; hint: string }> = {
    unassigned: { label: 'Needs reviewers', tone: 'negative', hint: 'No reviewers assigned yet' },
    awaiting_reviews: { label: 'Awaiting reviews', tone: 'attention', hint: 'Assigned, waiting on recommendations' },
    ready_for_decision: { label: 'Ready to decide', tone: 'positive', hint: 'Both reviewers recommended — needs your final call' },
    decided: { label: 'Decided', tone: 'neutral', hint: 'Final decision recorded' },
};

const STAT_TILES: { key: keyof AssignmentsIndexProps['stats']; label: string; stage: Stage | null; icon: typeof Users }[] = [
    { key: 'total', label: 'All abstracts', stage: null, icon: ClipboardCheck },
    { key: 'unassigned', label: 'Needs reviewers', stage: 'unassigned', icon: UserPlus },
    { key: 'awaiting_reviews', label: 'Awaiting reviews', stage: 'awaiting_reviews', icon: Clock3 },
    { key: 'ready_for_decision', label: 'Ready to decide', stage: 'ready_for_decision', icon: Gavel },
    { key: 'decided', label: 'Decided', stage: 'decided', icon: CheckCircle2 },
];

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en', { dateStyle: 'medium' }).format(new Date(value));
}

/**
 * Inline assignment for one abstract. Posts to the same endpoint the abstract's
 * own page uses, so every guard (two distinct reviewers, never the author, must
 * hold a reviewer role) is enforced server-side exactly once.
 */
function AssignRow({ submission, reviewers, onDone }: { submission: Submission; reviewers: Reviewer[]; onDone: () => void }) {
    const [one, setOne] = useState(submission.reviewer_one ? String(submission.reviewer_one.id) : '');
    const [two, setTwo] = useState(submission.reviewer_two ? String(submission.reviewer_two.id) : '');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // The author can never review their own abstract, so never offer them.
    const eligible = reviewers.filter((reviewer) => reviewer.id !== submission.author?.id);

    const save = () => {
        setError(null);
        setSaving(true);

        router.post(
            route('admin.abstracts.reviewers.assign', submission.id),
            { reviewer_one_id: one, reviewer_two_id: two },
            {
                preserveScroll: true,
                onSuccess: onDone,
                onError: (errors) => setError(String(Object.values(errors)[0] ?? 'Reviewers could not be assigned.')),
                onFinish: () => setSaving(false),
            },
        );
    };

    const unchanged = one === String(submission.reviewer_one?.id ?? '') && two === String(submission.reviewer_two?.id ?? '');

    const reviewerOption = (reviewer: Reviewer) => (
        <SelectItem key={reviewer.id} value={String(reviewer.id)}>
            {reviewer.name} · {reviewer.awaiting_count} pending
        </SelectItem>
    );

    return (
        <div className="mt-3 flex flex-col gap-2 rounded-lg border bg-slate-50/60 p-3 sm:flex-row sm:items-end dark:bg-slate-900/40">
            <div className="grid flex-1 gap-1">
                <label className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">Reviewer A</label>
                <Select value={one} onValueChange={setOne}>
                    <SelectTrigger>
                        <SelectValue placeholder="Select a reviewer" />
                    </SelectTrigger>
                    <SelectContent>{eligible.map(reviewerOption)}</SelectContent>
                </Select>
            </div>
            <div className="grid flex-1 gap-1">
                <label className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">Reviewer B</label>
                <Select value={two} onValueChange={setTwo}>
                    <SelectTrigger>
                        <SelectValue placeholder="Select a reviewer" />
                    </SelectTrigger>
                    <SelectContent>{eligible.filter((reviewer) => String(reviewer.id) !== one).map(reviewerOption)}</SelectContent>
                </Select>
            </div>
            <Button type="button" onClick={save} disabled={!one || !two || unchanged || saving} className="shrink-0">
                {saving && <LoaderCircle className="size-4 animate-spin" />}
                {submission.reviewer_one && submission.reviewer_two ? 'Reassign' : 'Assign'}
            </Button>

            {error && <p className="text-sm text-red-700 sm:w-full">{error}</p>}
        </div>
    );
}

export default function AssignmentsIndex({ submissions, filters, stats, reviewers, subthemes }: AssignmentsIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [expanded, setExpanded] = useState<number | null>(null);

    const applyFilter = (patch: Record<string, string | number | undefined>) => {
        router.get(route('admin.assignments.index'), { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const submitSearch: FormEventHandler = (event) => {
        event.preventDefault();
        applyFilter({ search: search || undefined });
    };

    const busiest = reviewers.reduce((max, reviewer) => Math.max(max, reviewer.assigned_count), 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reviewer Assignments" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex items-start gap-4 border-b pb-5">
                    <IconTile tone="blue">
                        <Users className="size-5" />
                    </IconTile>
                    <div>
                        <p className="text-xs font-bold tracking-[0.18em] text-[#4c8a1f] uppercase">Conference operations</p>
                        <h1 className="mt-2 font-serif text-3xl font-semibold">Reviewer Assignments</h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Every abstract needs two reviewers. Once both have recommended, it comes back to you for the final decision — unless they
                            both accepted, in which case it is accepted automatically.
                        </p>
                    </div>
                </header>

                <section className="grid grid-cols-2 gap-3 lg:grid-cols-5" aria-label="Review pipeline">
                    {STAT_TILES.map(({ key, label, stage, icon: Icon }) => {
                        const selected = stage === null ? !filters.stage : filters.stage === stage;

                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => applyFilter({ stage: stage ?? undefined })}
                                className={cn(
                                    'rounded-2xl border p-4 text-left transition',
                                    selected ? 'border-[#4c8a1f] bg-[#f3f9ee] shadow-sm dark:bg-[#4c8a1f]/10' : 'bg-card hover:border-[#4c8a1f]/40',
                                )}
                            >
                                <Icon className="text-muted-foreground size-4" />
                                <div className="mt-2 text-2xl font-bold tabular-nums">{stats[key]}</div>
                                <div className="text-muted-foreground mt-1 text-xs font-semibold">{label}</div>
                            </button>
                        );
                    })}
                </section>

                <section className="bg-card rounded-2xl border p-5">
                    <h2 className="font-serif text-lg font-semibold">Reviewer workload</h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Check who is already carrying work before handing out more. &ldquo;Pending&rdquo; is what still needs their recommendation.
                    </p>

                    {reviewers.length === 0 ? (
                        <p className="text-muted-foreground mt-4 text-sm">
                            No one holds a reviewer role yet — grant one under{' '}
                            <Link href={route('admin.users.index')} className="font-medium underline underline-offset-2">
                                Users &amp; Roles
                            </Link>
                            .
                        </p>
                    ) : (
                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full min-w-[640px] text-sm">
                                <thead className="text-muted-foreground text-left text-xs tracking-wide uppercase">
                                    <tr>
                                        <th className="pb-2">Reviewer</th>
                                        <th className="pb-2">Load</th>
                                        <th className="pb-2 text-right">Assigned</th>
                                        <th className="pb-2 text-right">Pending</th>
                                        <th className="pb-2 text-right">Submitted</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {reviewers.map((reviewer) => (
                                        <tr key={reviewer.id} className="hover:bg-muted/30">
                                            <td className="py-2.5">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        applyFilter({
                                                            reviewer_id: filters.reviewer_id === reviewer.id ? undefined : reviewer.id,
                                                        })
                                                    }
                                                    className={cn(
                                                        'text-left font-medium hover:underline',
                                                        filters.reviewer_id === reviewer.id && 'text-[#4c8a1f]',
                                                    )}
                                                >
                                                    {reviewer.name}
                                                </button>
                                                <div className="text-muted-foreground truncate text-xs">{reviewer.email}</div>
                                            </td>
                                            <td className="w-40 py-2.5">
                                                <div className="h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                                    <div
                                                        className="h-full rounded-full bg-[#4c8a1f]"
                                                        style={{ width: busiest ? `${(reviewer.assigned_count / busiest) * 100}%` : '0%' }}
                                                    />
                                                </div>
                                            </td>
                                            <td className="py-2.5 text-right tabular-nums">{reviewer.assigned_count}</td>
                                            <td className="py-2.5 text-right font-semibold tabular-nums">
                                                {reviewer.awaiting_count > 0 ? (
                                                    <span className="text-[#b5651d]">{reviewer.awaiting_count}</span>
                                                ) : (
                                                    <span className="text-muted-foreground">0</span>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground py-2.5 text-right tabular-nums">{reviewer.decisions_count}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="bg-card overflow-hidden rounded-2xl border">
                    <div className="grid gap-3 border-b p-4 md:grid-cols-[minmax(0,1fr)_200px_200px]">
                        <form onSubmit={submitSearch} className="flex gap-2">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search title, author, or email"
                                    className="pl-9"
                                />
                            </div>
                            <Button type="submit" variant="secondary">
                                Search
                            </Button>
                        </form>

                        <Select
                            value={filters.subtheme_id ? String(filters.subtheme_id) : 'all'}
                            onValueChange={(value) => applyFilter({ subtheme_id: value === 'all' ? undefined : Number(value) })}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All sub-themes</SelectItem>
                                {subthemes.map((subtheme) => (
                                    <SelectItem key={subtheme.id} value={String(subtheme.id)}>
                                        {subtheme.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.reviewer_id ? String(filters.reviewer_id) : 'all'}
                            onValueChange={(value) => applyFilter({ reviewer_id: value === 'all' ? undefined : Number(value) })}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All reviewers</SelectItem>
                                {reviewers.map((reviewer) => (
                                    <SelectItem key={reviewer.id} value={String(reviewer.id)}>
                                        {reviewer.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <ul className="divide-y">
                        {submissions.data.length === 0 && (
                            <li className="p-12 text-center">
                                <Search className="text-muted-foreground mx-auto size-7" />
                                <p className="mt-3 font-semibold">No abstracts match these filters</p>
                                <p className="text-muted-foreground mt-1 text-sm">Clear a filter or try a different search.</p>
                            </li>
                        )}

                        {submissions.data.map((submission) => {
                            const stage = STAGE_CONFIG[submission.stage];
                            const isOpen = expanded === submission.id;

                            const reviewerChip = (reviewer: Ref | null, label: string) => {
                                if (!reviewer) {
                                    return (
                                        <span className="text-muted-foreground rounded-full border border-dashed px-2 py-0.5 text-xs">
                                            {label}: unassigned
                                        </span>
                                    );
                                }

                                const decided = submission.decided_reviewer_ids.includes(reviewer.id);

                                return (
                                    <span
                                        className={cn(
                                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs',
                                            decided
                                                ? 'bg-[#eef7e6] text-[#4c8a1f] dark:bg-[#4c8a1f]/15 dark:text-[#8fd45a]'
                                                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                        )}
                                        title={decided ? 'Recommendation submitted' : 'Recommendation outstanding'}
                                    >
                                        {decided ? <CheckCircle2 className="size-3" /> : <Clock3 className="size-3" />}
                                        {reviewer.name}
                                    </span>
                                );
                            };

                            return (
                                <li key={submission.id} className="p-4">
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                        <div className="min-w-0">
                                            <Link href={route('admin.abstracts.show', submission.id)} className="font-semibold hover:underline">
                                                {submission.title}
                                            </Link>
                                            <p className="text-muted-foreground mt-0.5 text-xs">
                                                {submission.author?.name ?? 'Unknown author'} · {submission.subtheme?.title ?? 'No sub-theme'} ·
                                                submitted {formatDate(submission.created_at)}
                                            </p>
                                            <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                                {reviewerChip(submission.reviewer_one, 'Reviewer A')}
                                                {reviewerChip(submission.reviewer_two, 'Reviewer B')}
                                            </div>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-2">
                                            <span title={stage.hint}>
                                                <StatusPill tone={stage.tone}>{stage.label}</StatusPill>
                                            </span>
                                            {submission.stage !== 'decided' && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => setExpanded(isOpen ? null : submission.id)}
                                                >
                                                    <UserPlus className="size-4" />
                                                    {submission.reviewer_one && submission.reviewer_two ? 'Change' : 'Assign'}
                                                </Button>
                                            )}
                                        </div>
                                    </div>

                                    {isOpen && <AssignRow submission={submission} reviewers={reviewers} onDone={() => setExpanded(null)} />}
                                </li>
                            );
                        })}
                    </ul>
                </section>

                {submissions.links.length > 3 && (
                    <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                        {submissions.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveState
                                preserveScroll
                                className={cn(
                                    'rounded-lg px-3 py-1.5 text-sm',
                                    link.active ? 'bg-[#4c8a1f] text-white' : 'text-muted-foreground hover:bg-muted',
                                    !link.url && 'pointer-events-none opacity-40',
                                )}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
