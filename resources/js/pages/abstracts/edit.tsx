import AbstractAuthorsField, { Author } from '@/components/abstract-authors-field';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Subtheme {
    id: number;
    title: string;
}

const ABSTRACT_SECTIONS = [
    { key: 'background', label: 'Background' },
    { key: 'objective', label: 'Objective' },
    { key: 'methods', label: 'Methods' },
    { key: 'results', label: 'Results' },
    { key: 'conclusion', label: 'Conclusion' },
] as const;

type SectionKey = (typeof ABSTRACT_SECTIONS)[number]['key'];

function countWords(value: string): number {
    return value.trim() ? value.trim().split(/\s+/).length : 0;
}

interface Submission {
    id: number;
    title: string;
    subtheme_id: number;
    presentation_type: string;
    authors: Author[];
    background: string;
    objective: string;
    methods: string;
    results: string;
    conclusion: string;
    status: 'submitted' | 'revision_requested' | 'accepted' | 'rejected';
    decision_notes: string | null;
}

interface EditAbstractProps {
    submission: Submission;
    subthemes: Subtheme[];
    presentationTypes: Record<string, string>;
}

interface AbstractFormData {
    title: string;
    subtheme_id: string;
    presentation_type: string;
    authors: Author[];
    background: string;
    objective: string;
    methods: string;
    results: string;
    conclusion: string;
    [key: string]: string | Author[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Abstracts', href: '/abstracts' },
    { title: 'Edit Abstract', href: '#' },
];

export default function EditAbstract({ submission, subthemes, presentationTypes }: EditAbstractProps) {
    const isRevision = submission.status === 'revision_requested';
    const editable = submission.status === 'submitted' || isRevision;

    const { data, setData, put, processing, errors } = useForm<AbstractFormData>({
        title: submission.title,
        subtheme_id: String(submission.subtheme_id),
        presentation_type: submission.presentation_type,
        authors: submission.authors,
        background: submission.background,
        objective: submission.objective,
        methods: submission.methods,
        results: submission.results,
        conclusion: submission.conclusion,
    });

    const wordCount = ABSTRACT_SECTIONS.reduce((total, { key }) => total + countWords(data[key as SectionKey]), 0);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('abstracts.update', submission.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Abstract" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                {isRevision && (
                    <Alert className="border-[#135eeb]/25 bg-[#135eeb]/5">
                        <AlertTitle>Revision requested</AlertTitle>
                        <AlertDescription>
                            <p>Update the abstract using the reviewer’s comments, then resubmit it for another review.</p>
                            <div className="bg-background mt-3 rounded-lg p-4 text-sm leading-6 font-medium">{submission.decision_notes}</div>
                        </AlertDescription>
                    </Alert>
                )}

                {!editable && (
                    <Alert>
                        <AlertTitle>This abstract has already been reviewed</AlertTitle>
                        <AlertDescription>It can no longer be edited. You're viewing it read-only.</AlertDescription>
                    </Alert>
                )}

                <form onSubmit={submit} className="flex flex-col gap-6">
                    <fieldset disabled={!editable} className="contents">
                        <div className="grid gap-2">
                            <Label htmlFor="title">Abstract title</Label>
                            <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="subtheme_id">Sub-theme</Label>
                            <Select value={data.subtheme_id} onValueChange={(value) => setData('subtheme_id', value)} disabled={!editable}>
                                <SelectTrigger id="subtheme_id">
                                    <SelectValue placeholder="Select a sub-theme" />
                                </SelectTrigger>
                                <SelectContent>
                                    {subthemes.map((s) => (
                                        <SelectItem key={s.id} value={String(s.id)}>
                                            {s.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.subtheme_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="presentation_type">Presentation type</Label>
                            <Select
                                value={data.presentation_type}
                                onValueChange={(value) => setData('presentation_type', value)}
                                disabled={!editable}
                            >
                                <SelectTrigger id="presentation_type">
                                    <SelectValue placeholder="Oral or Poster" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(presentationTypes).map(([key, label]) => (
                                        <SelectItem key={key} value={key}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.presentation_type} />
                        </div>

                        <AbstractAuthorsField authors={data.authors} onChange={(authors) => setData('authors', authors)} errors={errors} />

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between">
                                <Label>Abstract</Label>
                                <p className={`text-xs ${wordCount > 300 ? 'text-destructive' : 'text-muted-foreground'}`}>
                                    {wordCount} / 300 words combined
                                </p>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Fill in each section separately. The 300-word limit applies to all five sections combined.
                            </p>
                        </div>

                        {ABSTRACT_SECTIONS.map(({ key, label }) => (
                            <div key={key} className="grid gap-2">
                                <Label htmlFor={key}>{label}</Label>
                                <Textarea id={key} rows={4} value={data[key]} onChange={(e) => setData(key, e.target.value)} disabled={!editable} />
                                <InputError message={errors[key]} />
                            </div>
                        ))}
                    </fieldset>

                    {editable && (
                        <Button type="submit" disabled={processing} className="w-fit bg-[#4c8a1f] font-bold hover:bg-[#3f751a]">
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            {isRevision ? 'Resubmit revised abstract' : 'Save changes'}
                        </Button>
                    )}
                </form>
            </div>
        </AppLayout>
    );
}
