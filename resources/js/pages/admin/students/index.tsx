import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
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

const statusVariant: Record<VerificationStatus, 'secondary' | 'default' | 'destructive'> = {
    pending: 'secondary',
    verified: 'default',
    rejected: 'destructive',
};

function StudentRow({ student }: { student: Student }) {
    const { data, setData, post, processing, errors, reset } = useForm({ notes: '' });

    const verify = () => {
        post(route('admin.students.verify', student.id), { preserveScroll: true, onSuccess: () => reset() });
    };

    const reject = () => {
        post(route('admin.students.reject', student.id), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <tr className="border-t align-top">
            <td className="p-3">
                <div className="font-medium">{student.name}</div>
                <div className="text-muted-foreground text-xs">{student.email}</div>
                <div className="text-muted-foreground mt-1 text-xs">{student.institution}</div>
            </td>
            <td className="p-3">{student.fee_category.replaceAll('_', ' ')}</td>
            <td className="p-3">
                {student.student_verification_status ? (
                    <Badge variant={statusVariant[student.student_verification_status]}>{student.student_verification_status}</Badge>
                ) : (
                    <Badge variant="secondary">No document</Badge>
                )}
                {student.student_verified_at && (
                    <div className="text-muted-foreground mt-1 text-xs">{new Date(student.student_verified_at).toLocaleString()}</div>
                )}
            </td>
            <td className="p-3">
                {student.student_document_path ? (
                    <Button asChild size="sm" variant="outline">
                        <a href={route('admin.students.document', student.id)} target="_blank" rel="noreferrer">
                            View document
                        </a>
                    </Button>
                ) : (
                    <span className="text-muted-foreground text-xs">No document</span>
                )}
            </td>
            <td className="min-w-64 p-3">
                {student.student_verification_status === 'pending' ? (
                    <div className="grid gap-2">
                        <Input
                            value={data.notes}
                            onChange={(event) => setData('notes', event.target.value)}
                            placeholder="Review notes; required when rejecting"
                            disabled={processing}
                        />
                        {errors.notes && <p className="text-destructive text-xs">{errors.notes}</p>}
                        <div className="flex gap-2">
                            <Button size="sm" onClick={verify} disabled={processing || !student.student_document_path}>
                                Verify
                            </Button>
                            <Button size="sm" variant="destructive" onClick={reject} disabled={processing || !student.student_document_path}>
                                Reject
                            </Button>
                        </div>
                    </div>
                ) : (
                    <p className="text-muted-foreground max-w-64 text-xs">
                        {student.student_verification_status
                            ? student.student_verification_notes || 'No review notes.'
                            : 'Waiting for the student to submit a document.'}
                    </p>
                )}
            </td>
        </tr>
    );
}

export default function StudentVerification({ students, filters, stats }: StudentVerificationProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(route('admin.students.index'), { ...filters, search }, { preserveState: true });
    };

    const setStatus = (status: string) => {
        router.get(route('admin.students.index'), { ...filters, status: status === 'all' ? undefined : status }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Student Verification" />
            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Student verification</h1>
                    <p className="text-muted-foreground text-sm">Review student documents before student-rate billing is enabled.</p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {Object.entries(stats).map(([label, value]) => (
                        <button
                            key={label}
                            type="button"
                            onClick={() => setStatus(label === 'total' ? 'all' : label)}
                            className="bg-card rounded-xl border p-4 text-left"
                        >
                            <div className="text-2xl font-semibold tabular-nums">{value}</div>
                            <div className="text-muted-foreground text-xs font-medium uppercase">{label}</div>
                        </button>
                    ))}
                </div>

                <form onSubmit={applyFilters} className="flex max-w-xl gap-2">
                    <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search name, email, or institution" />
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3">Student</th>
                                <th className="p-3">Category</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Document</th>
                                <th className="p-3">Review</th>
                            </tr>
                        </thead>
                        <tbody>
                            {students.data.length ? (
                                students.data.map((student) => <StudentRow key={student.id} student={student} />)
                            ) : (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground p-8 text-center">
                                        No student verifications match this filter.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex gap-1">
                    {students.links.map((link, index) => (
                        <Link
                            key={index}
                            href={link.url ?? '#'}
                            preserveState
                            className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
