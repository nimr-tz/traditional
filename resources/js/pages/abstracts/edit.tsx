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

interface Submission {
    id: number;
    title: string;
    subtheme_id: number;
    presentation_type: string;
    authors: Author[];
    abstract_text: string;
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
    abstract_text: string;
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
        abstract_text: submission.abstract_text,
    });

    const wordCount = data.abstract_text.trim() ? data.abstract_text.trim().split(/\s+/).length : 0;

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
                            <Label htmlFor="abstract_text">Abstract (max 300 words)</Label>
                            <Textarea
                                id="abstract_text"
                                rows={10}
                                value={data.abstract_text}
                                onChange={(e) => setData('abstract_text', e.target.value)}
                            />
                            <p className={`text-xs ${wordCount > 300 ? 'text-destructive' : 'text-muted-foreground'}`}>{wordCount} / 300 words</p>
                            <InputError message={errors.abstract_text} />
                        </div>
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
