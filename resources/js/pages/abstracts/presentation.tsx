import { DashboardCard, IconTile } from '@/components/dashboard-card';
import InputError from '@/components/input-error';
import { StatusBanner } from '@/components/status-banner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { BadgeCheck, Clock3, Download, FileUp, LoaderCircle, ShieldAlert, Trash2, XCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type PresentationStatus = 'pending' | 'uploaded' | 'approved';

interface Submission {
    id: number;
    title: string;
    presentation_type: 'oral' | 'poster';
    presentation_status: PresentationStatus;
    presentation_original_name: string | null;
    presentation_uploaded_at: string | null;
    presentation_notes: string | null;
    presentation_review_notes: string | null;
    can_upload: boolean;
}

interface Requirements {
    extensions: string[];
    max_kb: number;
}

interface PresentationProps {
    submission: Submission;
    requirements: Requirements;
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

export default function Presentation({ submission, requirements }: PresentationProps) {
    const { props } = usePage<{ flash: { success?: string; error?: string } }>();
    const wasRejected = submission.presentation_status === 'pending' && Boolean(submission.presentation_review_notes);
    const [uploadFailure, setUploadFailure] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'My Abstracts', href: '/abstracts' },
        { title: 'Presentation', href: route('abstracts.presentation.show', submission.id) },
    ];

    const uploadForm = useForm<{ presentation_file: File | null; presentation_notes: string }>({
        presentation_file: null,
        presentation_notes: submission.presentation_notes ?? '',
    });

    const submitUpload: FormEventHandler = (e) => {
        e.preventDefault();
        uploadForm.post(route('abstracts.presentation.store', submission.id), {
            preserveScroll: true,
            forceFormData: true,
            onStart: () => setUploadFailure(null),
            onSuccess: () => uploadForm.setData('presentation_file', null),
            onError: (errors) => {
                if (!errors.presentation_file) {
                    setUploadFailure('The upload could not be completed. Check your connection and try again.');
                }
            },
        });
    };

    const selectFile = (file: File | null) => {
        uploadForm.clearErrors('presentation_file');
        setUploadFailure(null);

        if (!file) {
            uploadForm.setData('presentation_file', null);
            return;
        }

        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
        if (!requirements.extensions.includes(extension)) {
            uploadForm.setError('presentation_file', `Choose a ${requirements.extensions.join(', ').toUpperCase()} file.`);
            uploadForm.setData('presentation_file', null);
            return;
        }

        if (file.size > requirements.max_kb * 1024) {
            uploadForm.setError('presentation_file', `The selected file exceeds the ${Math.round(requirements.max_kb / 1024)} MB limit.`);
            uploadForm.setData('presentation_file', null);
            return;
        }

        uploadForm.setData('presentation_file', file);
    };

    const removeFile = () => {
        if (!window.confirm('Remove your uploaded presentation file?')) return;

        uploadForm.delete(route('abstracts.presentation.destroy', submission.id), { preserveScroll: true });
    };

    const maxMb = Math.round(requirements.max_kb / 1024);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Presentation: ${submission.title}`} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4 pb-10 md:p-6 md:pb-12">
                <div>
                    <p className="text-xs font-semibold tracking-widest text-[#135eeb] uppercase">Presentation</p>
                    <h1 className="mt-2 font-serif text-2xl font-semibold md:text-3xl">{submission.title}</h1>
                    <p className="text-muted-foreground mt-2 text-sm capitalize">{submission.presentation_type} presentation</p>
                </div>

                {props.flash?.success && <StatusBanner tone="success" icon={BadgeCheck} title="Done" description={props.flash.success} />}
                {props.flash?.error && (
                    <StatusBanner tone="negative" icon={XCircle} title="We could not complete that request" description={props.flash.error} />
                )}
                {uploadFailure && <StatusBanner tone="negative" icon={XCircle} title="Upload failed" description={uploadFailure} />}

                {submission.presentation_status === 'approved' && (
                    <StatusBanner
                        tone="success"
                        icon={BadgeCheck}
                        title="Presentation approved"
                        description="No further action is needed. We look forward to your presentation."
                    />
                )}

                {submission.presentation_status === 'uploaded' && (
                    <StatusBanner
                        tone="info"
                        icon={Clock3}
                        title="Awaiting review"
                        description="Your presentation file is being reviewed by the organizers."
                    />
                )}

                {wasRejected && (
                    <StatusBanner
                        tone="warning"
                        icon={ShieldAlert}
                        title="Please upload a replacement file"
                        note={submission.presentation_review_notes}
                        noteLabel="Organizer feedback"
                    />
                )}

                <DashboardCard>
                    <div className="flex items-start gap-4">
                        <IconTile tone="blue">
                            <FileUp className="size-5" />
                        </IconTile>
                        <div>
                            <h2 className="font-serif text-lg font-semibold">Your presentation file</h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Accepted formats: {requirements.extensions.join(', ').toUpperCase()} — max {maxMb} MB.
                            </p>
                        </div>
                    </div>

                    {submission.presentation_original_name && (
                        <div className="dark:bg-muted/40 mt-6 flex items-center justify-between gap-4 rounded-xl bg-slate-50 p-4">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold">{submission.presentation_original_name}</p>
                                {submission.presentation_uploaded_at && (
                                    <p className="text-muted-foreground mt-0.5 text-xs">Uploaded {formatDate(submission.presentation_uploaded_at)}</p>
                                )}
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <Button asChild variant="outline" size="sm">
                                    <a href={route('abstracts.presentation.download', submission.id)}>
                                        <Download className="size-4" />
                                        Download
                                    </a>
                                </Button>
                                {submission.can_upload && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="border-red-200 text-red-700 hover:bg-red-50 hover:text-red-800"
                                        onClick={removeFile}
                                        disabled={uploadForm.processing}
                                    >
                                        <Trash2 className="size-4" />
                                        Remove
                                    </Button>
                                )}
                            </div>
                        </div>
                    )}

                    {submission.can_upload && (
                        <form onSubmit={submitUpload} className="mt-6 space-y-5">
                            <div className="grid gap-2">
                                <Label htmlFor="presentation_file">
                                    {submission.presentation_original_name ? 'Upload a replacement file' : 'Upload your presentation file'}
                                </Label>
                                <Input
                                    id="presentation_file"
                                    type="file"
                                    accept={requirements.extensions.map((ext) => `.${ext}`).join(',')}
                                    onChange={(e) => selectFile(e.target.files?.[0] ?? null)}
                                />
                                <InputError message={uploadForm.errors.presentation_file} />
                            </div>

                            {uploadForm.processing && uploadForm.progress && (
                                <div aria-live="polite">
                                    <div className="mb-2 flex items-center justify-between text-xs font-semibold">
                                        <span>Uploading</span>
                                        <span>{uploadForm.progress.percentage}%</span>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-slate-200">
                                        <div
                                            className="h-full bg-[#135eeb] transition-[width]"
                                            style={{ width: `${uploadForm.progress.percentage}%` }}
                                        />
                                    </div>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="presentation_notes">Notes for the organizers (optional)</Label>
                                <Textarea
                                    id="presentation_notes"
                                    rows={3}
                                    value={uploadForm.data.presentation_notes}
                                    onChange={(e) => uploadForm.setData('presentation_notes', e.target.value)}
                                />
                                <InputError message={uploadForm.errors.presentation_notes} />
                            </div>

                            <Button
                                disabled={!uploadForm.data.presentation_file || uploadForm.processing}
                                className="bg-[#135eeb] font-bold hover:bg-[#0e4bc2]"
                            >
                                {uploadForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Submit presentation
                            </Button>
                        </form>
                    )}
                </DashboardCard>

                <Link href={route('abstracts.index')} className="text-muted-foreground hover:text-foreground text-sm font-semibold">
                    Back to my abstracts
                </Link>
            </div>
        </AppLayout>
    );
}
