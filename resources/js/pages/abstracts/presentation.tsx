import { DashboardCard, IconTile } from '@/components/dashboard-card';
import InputError from '@/components/input-error';
import { StatusBanner } from '@/components/status-banner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { BadgeCheck, CircleCheck, Clock3, Download, FileUp, Languages, LoaderCircle, ShieldAlert, Trash2, XCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

/** Presentations aren't reviewed — a file is either on record or it isn't. */
type PresentationStatus = 'pending' | 'uploaded';

interface Submission {
    id: number;
    title: string;
    presentation_type: 'oral' | 'poster';
    presentation_status: PresentationStatus;
    presentation_original_name: string | null;
    presentation_uploaded_at: string | null;
    presentation_notes: string | null;
    can_upload: boolean;
}

interface Requirements {
    extensions: string[];
    max_kb: number;
}

interface PresentationWindow {
    deadline: string | null;
    is_open: boolean;
    closed_message: string | null;
}

interface PresentationProps {
    submission: Submission;
    requirements: Requirements;
    window: PresentationWindow;
}

function formatDeadline(value: string) {
    const [y, m, d] = value.split('-').map(Number);
    return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(
        new Date(Date.UTC(y, m - 1, d)),
    );
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

/**
 * The file card carries its own state in colour, so a glance is enough to know
 * where the presentation stands without reading the banner above it. A plain
 * grey block looked identical whether a file was on record or not.
 */
const FILE_STATE = {
    uploaded: {
        tile: 'green' as const,
        label: 'Submitted',
        row: 'border-[#4c8a1f]/30 bg-[#eef7e6] dark:border-[#67b52f]/25 dark:bg-[#67b52f]/10',
        chip: 'bg-[#4c8a1f] text-white',
    },
    pending: {
        tile: 'blue' as const,
        label: 'Not uploaded',
        row: 'border-transparent bg-slate-50 dark:bg-muted/40',
        chip: 'bg-slate-500 text-white',
    },
};

/** From the conference's official "Presentation Instructions" document. */
const LETTERING_GUIDE = [
    {
        type: 'Title',
        size: '48 point maximum',
        style: 'Title Case / Bold',
        advice: 'Include the presentation title, author names, and the corresponding author’s contact details at the top.',
    },
    {
        type: 'Headings',
        size: '34pt suggested, 36pt maximum',
        style: 'Title Case',
        advice: 'Headings such as Introduction, Methods, Results, Discussion, and Conclusion are useful.',
    },
    {
        type: 'Content',
        size: '24–26 point, single-spaced',
        style: 'Upper and lower case',
        advice: 'Keep text brief throughout. Any description of methods should be simple and concise.',
    },
    {
        type: 'Pictures',
        size: '640×360 min, 1920×1080 max',
        style: 'High resolution',
        advice: 'Use pictures in an organized manner and don’t overcrowd the slide.',
    },
];

export default function Presentation({ submission, requirements, window: presentationWindow }: PresentationProps) {
    const { props } = usePage<{ flash: { success?: string; error?: string } }>();
    const [uploadFailure, setUploadFailure] = useState<string | null>(null);
    const fileState = FILE_STATE[submission.presentation_status];

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

                <DashboardCard className="bg-slate-50/70 dark:bg-slate-900/40">
                    <h2 className="text-sm font-bold tracking-wide uppercase">How this works</h2>
                    <ul className="mt-3 space-y-2.5 text-sm leading-6">
                        <li className="flex items-start gap-2.5">
                            <CircleCheck className="mt-0.5 size-4 shrink-0 text-[#4c8a1f]" />
                            <span>
                                Presentations are not reviewed. Whatever file is on record when the deadline passes is what you'll present — there's
                                no approval step.
                            </span>
                        </li>
                        <li className="flex items-start gap-2.5">
                            <CircleCheck className="mt-0.5 size-4 shrink-0 text-[#4c8a1f]" />
                            <span>
                                Accepted formats: {requirements.extensions.join(', ').toUpperCase()} — max {maxMb} MB.
                            </span>
                        </li>
                        <li className="flex items-start gap-2.5">
                            <CircleCheck className="mt-0.5 size-4 shrink-0 text-[#4c8a1f]" />
                            <span>You can replace your file as many times as you need to, right up until the deadline below.</span>
                        </li>
                        <li className="flex items-start gap-2.5">
                            <CircleCheck className="mt-0.5 size-4 shrink-0 text-[#4c8a1f]" />
                            <span>Your assigned reviewers and the conference organizers can view whatever file you've uploaded.</span>
                        </li>
                    </ul>
                </DashboardCard>

                <DashboardCard>
                    <h2 className="font-serif text-lg font-semibold">Preparing your {submission.presentation_type}</h2>
                    <p className="text-muted-foreground mt-1.5 text-sm leading-6">
                        Your {submission.presentation_type} should represent your submitted abstract, and its title should match your abstract's
                        title. On the title slide, after the title, include:
                    </p>
                    <p className="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-sm leading-6 font-semibold italic dark:border-slate-800 dark:bg-slate-900/50">
                        “Presented at the 5th Traditional Medicine Scientific Conference, 28th August 2026”
                    </p>

                    <h3 className="mt-6 text-sm font-bold tracking-wide uppercase">Lettering</h3>
                    <p className="text-muted-foreground mt-1.5 text-sm leading-6">
                        Use lower case for general content — all-capital text is hard to read. The recommended font is{' '}
                        <span className="font-semibold">Arial</span>.
                    </p>
                    <div className="mt-3 overflow-x-auto">
                        <table className="w-full min-w-[560px] text-sm">
                            <thead className="text-muted-foreground text-left text-xs tracking-wide uppercase">
                                <tr>
                                    <th className="pr-3 pb-2">Type</th>
                                    <th className="pr-3 pb-2">Font size</th>
                                    <th className="pr-3 pb-2">Case / style</th>
                                    <th className="pb-2">General advice</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {LETTERING_GUIDE.map((row) => (
                                    <tr key={row.type}>
                                        <td className="py-2.5 pr-3 align-top font-semibold whitespace-nowrap">{row.type}</td>
                                        <td className="text-muted-foreground py-2.5 pr-3 align-top whitespace-nowrap">{row.size}</td>
                                        <td className="text-muted-foreground py-2.5 pr-3 align-top whitespace-nowrap">{row.style}</td>
                                        <td className="text-muted-foreground py-2.5 align-top leading-6">{row.advice}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {submission.presentation_type === 'oral' && (
                        <>
                            <h3 className="mt-6 text-sm font-bold tracking-wide uppercase">PowerPoint hints</h3>
                            <ul className="mt-2 space-y-2 text-sm leading-6">
                                <li className="flex items-start gap-2.5">
                                    <CircleCheck className="mt-0.5 size-4 shrink-0 text-[#4c8a1f]" />
                                    <span>Maximum 20 slides.</span>
                                </li>
                                <li className="flex items-start gap-2.5">
                                    <CircleCheck className="mt-0.5 size-4 shrink-0 text-[#4c8a1f]" />
                                    <span>You'll be given 10 minutes to present.</span>
                                </li>
                                <li className="flex items-start gap-2.5">
                                    <CircleCheck className="mt-0.5 size-4 shrink-0 text-[#4c8a1f]" />
                                    <span>Use your institution's PowerPoint template and/or logo (e.g. NIMR, MUHAS).</span>
                                </li>
                            </ul>
                        </>
                    )}

                    <h3 className="mt-6 flex items-center gap-2 text-sm font-bold tracking-wide uppercase">
                        <Languages className="size-4" />
                        Language
                    </h3>
                    <p className="text-muted-foreground mt-1.5 text-sm leading-6">
                        Prepare your {submission.presentation_type} in proper English or proper Swahili. Swahili is the recommended language for
                        presentations, except where English terminology is unavoidable.
                    </p>
                </DashboardCard>

                {props.flash?.success && <StatusBanner tone="success" icon={BadgeCheck} title="Done" description={props.flash.success} />}
                {props.flash?.error && (
                    <StatusBanner tone="negative" icon={XCircle} title="We could not complete that request" description={props.flash.error} />
                )}
                {uploadFailure && <StatusBanner tone="negative" icon={XCircle} title="Upload failed" description={uploadFailure} />}

                {/* Nobody reviews a presentation: uploading it is the whole
                    process. The only thing that ends it is the deadline. */}
                {submission.presentation_status === 'uploaded' && presentationWindow.is_open && (
                    <StatusBanner
                        tone="success"
                        icon={BadgeCheck}
                        title="Presentation submitted"
                        description={
                            presentationWindow.deadline
                                ? `Your file is on record. You can replace it any time until ${formatDeadline(presentationWindow.deadline)} — whatever is uploaded then is what you'll present.`
                                : 'Your file is on record. You can replace it at any time.'
                        }
                    />
                )}

                {submission.presentation_status === 'pending' && presentationWindow.is_open && presentationWindow.deadline && (
                    <StatusBanner
                        tone="info"
                        icon={Clock3}
                        title={`Upload by ${formatDeadline(presentationWindow.deadline)}`}
                        description="Presentations are not reviewed — upload your file and you're done."
                    />
                )}

                {!presentationWindow.is_open && (
                    <StatusBanner
                        tone="warning"
                        icon={ShieldAlert}
                        title="The upload deadline has passed"
                        description={presentationWindow.closed_message ?? 'Presentation files can no longer be changed.'}
                    />
                )}

                <DashboardCard>
                    <div className="flex items-start gap-4">
                        <IconTile tone={fileState.tile}>
                            <FileUp className="size-5" />
                        </IconTile>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="font-serif text-lg font-semibold">Your presentation file</h2>
                                <span className={cn('rounded-full px-2.5 py-0.5 text-[11px] font-bold tracking-wide', fileState.chip)}>
                                    {fileState.label}
                                </span>
                            </div>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Accepted formats: {requirements.extensions.join(', ').toUpperCase()} — max {maxMb} MB.
                            </p>
                        </div>
                    </div>

                    {submission.presentation_original_name && (
                        <div className={cn('mt-6 flex items-center justify-between gap-4 rounded-xl border p-4', fileState.row)}>
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
