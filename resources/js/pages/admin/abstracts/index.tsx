import { IconTile } from '@/components/dashboard-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, EyeOff, FileCheck2, FileClock, FilePenLine, FileStack, FileX2, History, ScrollText, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Abstract Review', href: '/admin/abstracts' },
];

type AbstractStatus = 'submitted' | 'revision_requested' | 'accepted' | 'rejected';

interface Submission {
    id: number;
    title: string;
    presentation_type: 'oral' | 'poster';
    status: AbstractStatus;
    created_at: string;
    resubmitted_at: string | null;
    review_round: number;
    // Omitted for blind reviewers — the server never sends it.
    user?: { name: string; email: string; institution: string | null } | null;
    subtheme: { title: string } | null;
    reviewer_one: { name: string } | null;
    reviewer_two: { name: string } | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
}

interface Subtheme {
    id: number;
    title: string;
}

interface AdminAbstractsIndexProps {
    submissions: Paginated<Submission>;
    subthemes: Subtheme[];
    filters: { status?: string; subtheme_id?: string; search?: string };
    counts: Record<AbstractStatus, number> & { total: number };
    /** True for reviewers: author identity is withheld and not searchable. */
    isBlinded: boolean;
}

const statusConfig: Record<AbstractStatus, { label: string; badge: string; icon: typeof FileClock; countLabel: string }> = {
    submitted: {
        label: 'Awaiting review',
        countLabel: 'Awaiting review',
        badge: 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900',
        icon: FileClock,
    },
    revision_requested: {
        label: 'Revision requested',
        countLabel: 'With authors',
        badge: 'bg-blue-50 text-blue-800 ring-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:ring-blue-900',
        icon: FilePenLine,
    },
    accepted: {
        label: 'Accepted',
        countLabel: 'Accepted',
        badge: 'bg-green-50 text-green-800 ring-green-200 dark:bg-green-950/40 dark:text-green-300 dark:ring-green-900',
        icon: FileCheck2,
    },
    rejected: {
        label: 'Rejected',
        countLabel: 'Rejected',
        badge: 'bg-red-50 text-red-800 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900',
        icon: FileX2,
    },
};

function formatDate(value: string) {
    return new Intl.DateTimeFormat('en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value));
}

export default function AdminAbstractsIndex({ submissions, subthemes, filters, counts, isBlinded }: AdminAbstractsIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    const visit = (next: Partial<typeof filters>) => {
        router.get(route('admin.abstracts.index'), { ...filters, ...next }, { preserveState: true, replace: true });
    };

    const submitSearch: FormEventHandler = (event) => {
        event.preventDefault();
        visit({ search: search || undefined });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Abstract Review" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex flex-col gap-4 border-b pb-5 md:flex-row md:items-end md:justify-between">
                    <div className="flex items-start gap-4">
                        <IconTile tone="green">
                            <ScrollText className="size-5" />
                        </IconTile>
                        <div>
                            <p className="text-xs font-bold tracking-[0.18em] text-[#4c8a1f] uppercase">Reviewer workspace</p>
                            <h1 className="mt-2 font-serif text-3xl font-semibold text-balance">Abstract review</h1>
                            <p className="text-muted-foreground mt-2 text-sm">Open an abstract to read it fully before recording a decision.</p>
                        </div>
                    </div>
                    <p className="text-muted-foreground text-sm tabular-nums">
                        {submissions.from ?? 0}–{submissions.to ?? 0} of {submissions.total}
                    </p>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" aria-label="Abstract status totals">
                    <button
                        type="button"
                        onClick={() => visit({ status: undefined })}
                        className={`flex min-h-24 items-center justify-between rounded-2xl border p-4 text-left transition ${
                            !filters.status ? 'border-[#135eeb] bg-[#eaf1ff] shadow-sm dark:bg-[#135eeb]/10' : 'bg-card hover:border-[#135eeb]/40'
                        }`}
                    >
                        <div>
                            <div className="text-2xl font-bold tabular-nums">{counts.total}</div>
                            <div className="text-muted-foreground mt-1 text-xs font-semibold">Total</div>
                        </div>
                        <FileStack className="size-5 text-[#135eeb]" />
                    </button>
                    {(Object.keys(statusConfig) as AbstractStatus[]).map((status) => {
                        const item = statusConfig[status];
                        const Icon = item.icon;
                        const selected = filters.status === status;

                        return (
                            <button
                                key={status}
                                type="button"
                                onClick={() => visit({ status: selected ? undefined : status })}
                                className={`flex min-h-24 items-center justify-between rounded-2xl border p-4 text-left transition ${
                                    selected ? 'border-[#4c8a1f] bg-[#f3f9ee] shadow-sm dark:bg-[#4c8a1f]/10' : 'bg-card hover:border-[#4c8a1f]/40'
                                }`}
                            >
                                <div>
                                    <div className="text-2xl font-bold tabular-nums">{counts[status]}</div>
                                    <div className="text-muted-foreground mt-1 text-xs font-semibold">{item.countLabel}</div>
                                </div>
                                <Icon className="size-5 text-[#4c8a1f]" />
                            </button>
                        );
                    })}
                </section>

                <section className="bg-card rounded-2xl border">
                    <div className="grid gap-3 border-b p-4 md:grid-cols-[minmax(0,1fr)_240px]">
                        <form onSubmit={submitSearch} className="flex gap-2">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder={isBlinded ? 'Search abstract titles' : 'Search title, author, or email'}
                                    className="pl-9"
                                />
                            </div>
                            <Button type="submit" variant="secondary">
                                Search
                            </Button>
                        </form>
                        <Select
                            value={filters.subtheme_id ?? 'all'}
                            onValueChange={(value) => visit({ subtheme_id: value === 'all' ? undefined : value })}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="All sub-themes" />
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
                    </div>

                    <div className="divide-y">
                        {submissions.data.length ? (
                            submissions.data.map((submission) => {
                                const config = statusConfig[submission.status];
                                const submittedDate = submission.resubmitted_at ?? submission.created_at;

                                return (
                                    <article key={submission.id} className="grid gap-4 p-5 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset ${config.badge}`}
                                                >
                                                    {config.label}
                                                </span>
                                                {/* For a reviewer this is a re-review, not a new one — say so
                                                    plainly, and how many rounds have already happened. */}
                                                {submission.resubmitted_at && submission.status === 'submitted' && (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-[#eaf1ff] px-2.5 py-1 text-xs font-bold text-[#135eeb] dark:bg-[#135eeb]/15">
                                                        <History className="size-3.5" />
                                                        Re-review
                                                        {submission.review_round > 1 ? ` · round ${submission.review_round}` : ''}
                                                    </span>
                                                )}
                                            </div>
                                            <h2 className="mt-3 text-lg leading-7 font-semibold text-balance">{submission.title}</h2>
                                            {/* Blind review: the server omits the author entirely for reviewers. */}
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                {submission.user ? (
                                                    <>
                                                        {submission.user.name} · {submission.user.institution || submission.user.email}
                                                    </>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 italic">
                                                        <EyeOff className="size-3.5" /> Author hidden for blind review
                                                    </span>
                                                )}
                                            </p>
                                            <div className="text-muted-foreground mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                                <span className="capitalize">{submission.presentation_type} presentation</span>
                                                {submission.subtheme && <span>{submission.subtheme.title}</span>}
                                                <span>
                                                    {submission.resubmitted_at ? 'Resubmitted' : 'Submitted'} {formatDate(submittedDate)}
                                                </span>
                                            </div>
                                            <p className="text-muted-foreground mt-2 text-xs">
                                                {submission.reviewer_one && submission.reviewer_two
                                                    ? `Reviewers: ${submission.reviewer_one.name}, ${submission.reviewer_two.name}`
                                                    : 'No reviewers assigned yet'}
                                            </p>
                                        </div>
                                        <Button
                                            asChild
                                            className={submission.status === 'submitted' ? 'bg-[#4c8a1f] hover:bg-[#3f751a]' : ''}
                                            variant={submission.status === 'submitted' ? 'default' : 'outline'}
                                        >
                                            {/* Admins no longer review here — reviewers recommend, admins assign
                                                and decide — so the label just describes opening the record. */}
                                            <Link href={route('admin.abstracts.show', submission.id)}>
                                                {submission.status === 'submitted' ? 'Read abstract' : 'View record'}
                                                <ArrowRight className="size-4" />
                                            </Link>
                                        </Button>
                                    </article>
                                );
                            })
                        ) : (
                            <div className="p-12 text-center">
                                <FileClock className="text-muted-foreground mx-auto size-7" />
                                <p className="mt-3 font-semibold">No abstracts match these filters</p>
                                <p className="text-muted-foreground mt-1 text-sm">Clear a filter or try a different search.</p>
                            </div>
                        )}
                    </div>
                </section>

                {submissions.links.length > 3 && (
                    <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                        {submissions.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveState
                                className={`rounded-lg px-3 py-1.5 text-sm ${link.active ? 'bg-[#4c8a1f] text-white' : 'text-muted-foreground hover:bg-muted'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
