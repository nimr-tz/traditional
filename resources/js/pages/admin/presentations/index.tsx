import { IconTile } from '@/components/dashboard-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Clock3, Download, FileStack, FileX2, Presentation, ShieldAlert, UserRound } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Presentations', href: '/admin/presentations' },
];

type PresentationStatus = 'uploaded' | 'pending';

interface Submission {
    id: number;
    title: string;
    presentation_type: 'oral' | 'poster';
    presentation_status: PresentationStatus;
    presentation_original_name: string | null;
    presentation_uploaded_at: string | null;
    user: { full_name: string; email: string; institution: string | null } | null;
    subtheme: { title: string } | null;
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

interface PresentationWindow {
    deadline: string | null;
    is_open: boolean;
    closed_message: string | null;
}

interface AdminPresentationsIndexProps {
    submissions: Paginated<Submission>;
    subthemes: Subtheme[];
    filters: { status?: string; subtheme_id?: string; search?: string };
    counts: { total: number; uploaded: number; pending: number };
    window: PresentationWindow;
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value));
}

export default function AdminPresentationsIndex({
    submissions,
    subthemes,
    filters,
    counts,
    window: presentationWindow,
}: AdminPresentationsIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    const visit = (next: Partial<typeof filters>) => {
        router.get(route('admin.presentations.index'), { ...filters, ...next }, { preserveState: true, replace: true });
    };

    const submitSearch: FormEventHandler = (event) => {
        event.preventDefault();
        visit({ search: search || undefined });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Presentations" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex flex-col gap-4 border-b pb-5 md:flex-row md:items-end md:justify-between">
                    <div className="flex items-start gap-4">
                        <IconTile tone="green">
                            <Presentation className="size-5" />
                        </IconTile>
                        <div>
                            <p className="text-xs font-bold tracking-[0.18em] text-[#4c8a1f] uppercase">Conference operations</p>
                            <h1 className="mt-2 font-serif text-3xl font-semibold">Presentations</h1>
                            <p className="text-muted-foreground mt-2 text-sm">
                                Every accepted abstract's slide or poster file — who has uploaded, and who hasn't. Presentations aren't reviewed, so
                                there's nothing to decide here, only to check and download.
                            </p>
                        </div>
                    </div>
                    <p className="text-muted-foreground text-sm tabular-nums">
                        {submissions.from ?? 0}–{submissions.to ?? 0} of {submissions.total}
                    </p>
                </header>

                {!presentationWindow.is_open && (
                    <div className="flex items-start gap-3 rounded-2xl border border-amber-300/40 bg-[linear-gradient(135deg,#fef6e6_0%,#ffffff_75%)] p-5 text-sm leading-6 dark:border-amber-400/25 dark:bg-[linear-gradient(135deg,rgba(217,158,10,0.16)_0%,rgba(217,158,10,0.03)_100%)]">
                        <ShieldAlert className="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <p>
                            {presentationWindow.closed_message ??
                                'The upload deadline has passed. Whatever is on file below is what will be presented.'}
                        </p>
                    </div>
                )}

                <section className="grid gap-3 sm:grid-cols-3" aria-label="Presentation totals">
                    <button
                        type="button"
                        onClick={() => visit({ status: undefined })}
                        className={`flex min-h-24 items-center justify-between rounded-2xl border p-4 text-left transition ${
                            !filters.status ? 'border-[#135eeb] bg-[#eaf1ff] shadow-sm dark:bg-[#135eeb]/10' : 'bg-card hover:border-[#135eeb]/40'
                        }`}
                    >
                        <div>
                            <div className="text-2xl font-bold tabular-nums">{counts.total}</div>
                            <div className="text-muted-foreground mt-1 text-xs font-semibold">Accepted abstracts</div>
                        </div>
                        <FileStack className="size-5 text-[#135eeb]" />
                    </button>
                    <button
                        type="button"
                        onClick={() => visit({ status: filters.status === 'uploaded' ? undefined : 'uploaded' })}
                        className={`flex min-h-24 items-center justify-between rounded-2xl border p-4 text-left transition ${
                            filters.status === 'uploaded'
                                ? 'border-[#4c8a1f] bg-[#f3f9ee] shadow-sm dark:bg-[#4c8a1f]/10'
                                : 'bg-card hover:border-[#4c8a1f]/40'
                        }`}
                    >
                        <div>
                            <div className="text-2xl font-bold tabular-nums">{counts.uploaded}</div>
                            <div className="text-muted-foreground mt-1 text-xs font-semibold">Submitted</div>
                        </div>
                        <Presentation className="size-5 text-[#4c8a1f]" />
                    </button>
                    <button
                        type="button"
                        onClick={() => visit({ status: filters.status === 'pending' ? undefined : 'pending' })}
                        className={`flex min-h-24 items-center justify-between rounded-2xl border p-4 text-left transition ${
                            filters.status === 'pending'
                                ? 'border-amber-500 bg-amber-50 shadow-sm dark:bg-amber-950/20'
                                : 'bg-card hover:border-amber-400/50'
                        }`}
                    >
                        <div>
                            <div className="text-2xl font-bold tabular-nums">{counts.pending}</div>
                            <div className="text-muted-foreground mt-1 text-xs font-semibold">Not uploaded yet</div>
                        </div>
                        <Clock3 className="size-5 text-amber-600" />
                    </button>
                </section>

                <section className="bg-card rounded-2xl border">
                    <div className="grid gap-3 border-b p-4 md:grid-cols-[minmax(0,1fr)_240px]">
                        <form onSubmit={submitSearch} className="flex gap-2">
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search title, author, or email"
                                className="flex-1"
                            />
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
                            submissions.data.map((submission) => (
                                <article key={submission.id} className="grid gap-4 p-5 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            {submission.presentation_status === 'uploaded' ? (
                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[#eef7e6] px-2.5 py-1 text-xs font-bold text-[#4c8a1f] dark:bg-[#67b52f]/15 dark:text-[#8fd45a]">
                                                    <Presentation className="size-3.5" />
                                                    Submitted
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                                                    <FileX2 className="size-3.5" />
                                                    Not uploaded
                                                </span>
                                            )}
                                            <span className="text-muted-foreground text-xs capitalize">
                                                {submission.presentation_type} presentation
                                            </span>
                                        </div>
                                        <h2 className="mt-3 text-lg leading-7 font-semibold text-balance">{submission.title}</h2>
                                        {submission.user && (
                                            <p className="text-muted-foreground mt-1 flex items-center gap-1.5 text-sm">
                                                <UserRound className="size-3.5" />
                                                {submission.user.full_name} · {submission.user.institution || submission.user.email}
                                            </p>
                                        )}
                                        <div className="text-muted-foreground mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                            {submission.subtheme && <span>{submission.subtheme.title}</span>}
                                            {submission.presentation_original_name && (
                                                <span className="truncate">{submission.presentation_original_name}</span>
                                            )}
                                            {submission.presentation_uploaded_at && (
                                                <span>Uploaded {formatDate(submission.presentation_uploaded_at)}</span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button asChild variant="outline">
                                            <Link href={route('admin.abstracts.show', submission.id)}>View abstract</Link>
                                        </Button>
                                        {submission.presentation_status === 'uploaded' && (
                                            <Button asChild className="bg-[#4c8a1f] hover:bg-[#3f751a]">
                                                <a href={route('admin.abstracts.presentation.download', submission.id)}>
                                                    <Download className="size-4" />
                                                    Download
                                                </a>
                                            </Button>
                                        )}
                                    </div>
                                </article>
                            ))
                        ) : (
                            <div className="p-12 text-center">
                                <Presentation className="text-muted-foreground mx-auto size-7" />
                                <p className="mt-3 font-semibold">No presentations match these filters</p>
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
