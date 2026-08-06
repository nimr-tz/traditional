import { IconTile } from '@/components/dashboard-card';
import { StatusPill, type PillTone } from '@/components/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Check, FileText, GraduationCap, RotateCcw, Search, ShieldCheck, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Student Verification', href: '/admin/students' },
];

type VerificationStatus = 'pending' | 'verified' | 'rejected';

interface Student {
    id: number;
    name: string;
    email: string;
    institution: string | null;
    fee_category: string;
    student_document_path: string | null;
    student_verification_status: VerificationStatus | null;
    student_verified_at: string | null;
    student_verification_notes: string | null;
    control_number: string | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

interface StudentVerificationProps {
    students: Paginated<Student>;
    filters: { status?: string; search?: string };
    stats: { pending: number; verified: number; rejected: number; total: number };
}

const statusConfig: Record<VerificationStatus, { label: string; tone: PillTone }> = {
    pending: { label: 'Pending', tone: 'attention' },
    verified: { label: 'Verified', tone: 'positive' },
    rejected: { label: 'Rejected', tone: 'negative' },
};

const statTiles: { key: keyof StudentVerificationProps['stats']; label: string }[] = [
    { key: 'total', label: 'Total students' },
    { key: 'pending', label: 'Pending review' },
    { key: 'verified', label: 'Verified' },
    { key: 'rejected', label: 'Rejected' },
];

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en', { dateStyle: 'medium' }).format(new Date(value));
}

function StudentRow({ student }: { student: Student }) {
    const { data, setData, post, processing, errors, reset } = useForm({ notes: '' });

    const verify = () => {
        post(route('admin.students.verify', student.id), { preserveScroll: true, onSuccess: () => reset() });
    };

    const reject = () => {
        post(route('admin.students.reject', student.id), { preserveScroll: true, onSuccess: () => reset() });
    };

    const reopen = () => {
        const warning = student.control_number
            ? `${student.name} already has control number ${student.control_number} at the student rate. Reopening the review does not void it — finance must reset the billing. Continue?`
            : `Undo this decision and put ${student.name} back in the review queue?`;

        if (!window.confirm(warning)) {
            return;
        }

        post(route('admin.students.reopen', student.id), { preserveScroll: true, onSuccess: () => reset() });
    };

    const status = student.student_verification_status;
    const decided = status === 'verified' || status === 'rejected';

    return (
        <article className="grid gap-4 p-5 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] md:items-start">
            <div className="flex items-start gap-3.5">
                <div className="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#eaf1ff] font-serif text-sm font-bold text-[#135eeb] dark:bg-[#135eeb]/15">
                    {initials(student.name)}
                </div>
                <div className="min-w-0">
                    <p className="font-semibold">{student.name}</p>
                    <p className="text-muted-foreground mt-0.5 truncate text-sm">{student.email}</p>
                    <p className="text-muted-foreground mt-1 truncate text-xs">{student.institution}</p>
                    <div className="mt-2.5 flex flex-wrap items-center gap-2">
                        <span className="text-muted-foreground text-xs capitalize">{student.fee_category.replaceAll('_', ' ')}</span>
                        {status ? (
                            <StatusPill tone={statusConfig[status].tone}>{statusConfig[status].label}</StatusPill>
                        ) : (
                            <StatusPill tone="neutral">No document</StatusPill>
                        )}
                    </div>
                    {student.student_verified_at && (
                        <p className="text-muted-foreground mt-2 text-xs">Reviewed {formatDate(student.student_verified_at)}</p>
                    )}
                    {student.student_document_path && (
                        <Button asChild size="sm" variant="outline" className="mt-3">
                            <a href={route('admin.students.document', student.id)} target="_blank" rel="noreferrer">
                                <FileText className="size-4" />
                                View document
                            </a>
                        </Button>
                    )}
                </div>
            </div>

            <div>
                {status === 'pending' ? (
                    <div className="flex flex-col gap-2.5">
                        <Textarea
                            value={data.notes}
                            onChange={(event) => setData('notes', event.target.value)}
                            placeholder="Review notes; required when rejecting"
                            rows={2}
                            disabled={processing}
                            className="text-sm"
                        />
                        {errors.notes && <p className="text-destructive text-xs">{errors.notes}</p>}
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                onClick={verify}
                                disabled={processing || !student.student_document_path}
                                className="bg-[#4c8a1f] font-bold hover:bg-[#3f751a]"
                            >
                                <Check className="size-4" />
                                Verify
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={reject}
                                disabled={processing || !student.student_document_path}
                                className="border-red-200 font-bold text-red-700 hover:bg-red-50 hover:text-red-800"
                            >
                                <X className="size-4" />
                                Reject
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2.5">
                        <p className="text-muted-foreground rounded-xl bg-slate-50 p-3.5 text-sm leading-6 dark:bg-slate-900">
                            {status ? (student.student_verification_notes ?? 'No review notes.') : 'Waiting for the student to submit a document.'}
                        </p>

                        {decided && student.control_number && (
                            <p className="flex items-start gap-2 text-xs leading-5 text-amber-700 dark:text-amber-500">
                                <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                                <span>
                                    Control number {student.control_number} was already issued at the student rate. Reopening the review will not void
                                    it.
                                </span>
                            </p>
                        )}

                        {decided && (
                            <div>
                                <Button size="sm" variant="outline" onClick={reopen} disabled={processing}>
                                    <RotateCcw className="size-4" />
                                    Reopen review
                                </Button>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </article>
    );
}

export default function StudentVerification({ students, filters, stats }: StudentVerificationProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(route('admin.students.index'), { ...filters, search: search || undefined }, { preserveState: true });
    };

    const setStatus = (status: string) => {
        router.get(route('admin.students.index'), { ...filters, status: status === 'all' ? undefined : status }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Student Verification" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex flex-col gap-4 border-b pb-5 md:flex-row md:items-end md:justify-between">
                    <div className="flex items-start gap-4">
                        <IconTile tone="green">
                            <GraduationCap className="size-5" />
                        </IconTile>
                        <div>
                            <p className="text-xs font-bold tracking-[0.18em] text-[#4c8a1f] uppercase">Conference operations</p>
                            <h1 className="mt-2 font-serif text-3xl font-semibold">Student verification</h1>
                            <p className="text-muted-foreground mt-2 text-sm">Review student documents before student-rate billing is enabled.</p>
                        </div>
                    </div>
                </header>

                <section className="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Verification totals">
                    {statTiles.map(({ key, label }) => {
                        const selected = filters.status === key || (key === 'total' && !filters.status);

                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setStatus(key === 'total' ? 'all' : key)}
                                className={cn(
                                    'rounded-2xl border p-4 text-left transition',
                                    selected ? 'border-[#4c8a1f] bg-[#f3f9ee] shadow-sm dark:bg-[#4c8a1f]/10' : 'bg-card hover:border-[#4c8a1f]/40',
                                )}
                            >
                                <div className="text-2xl font-bold tabular-nums">{stats[key]}</div>
                                <div className="text-muted-foreground mt-1 text-xs font-semibold">{label}</div>
                            </button>
                        );
                    })}
                </section>

                <section className="bg-card rounded-2xl border">
                    <div className="border-b p-4">
                        <form onSubmit={applyFilters} className="flex max-w-xl gap-2">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search name, email, or institution"
                                    className="pl-9"
                                />
                            </div>
                            <Button type="submit" variant="secondary">
                                Search
                            </Button>
                        </form>
                    </div>

                    <div className="divide-y">
                        {students.data.length ? (
                            students.data.map((student) => <StudentRow key={student.id} student={student} />)
                        ) : (
                            <div className="p-12 text-center">
                                <ShieldCheck className="text-muted-foreground mx-auto size-7" />
                                <p className="mt-3 font-semibold">No students match these filters</p>
                                <p className="text-muted-foreground mt-1 text-sm">Clear a filter or try a different search.</p>
                            </div>
                        )}
                    </div>
                </section>

                {students.links.length > 3 && (
                    <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                        {students.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveState
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
