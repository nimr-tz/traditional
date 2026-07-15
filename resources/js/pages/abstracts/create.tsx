import AbstractAuthorsField, { Author } from '@/components/abstract-authors-field';
import InputError from '@/components/input-error';
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Abstracts', href: '/abstracts' },
    { title: 'Submit Abstract', href: '/abstracts/create' },
];

interface Subtheme {
    id: number;
    title: string;
}

interface CreateAbstractProps {
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

export default function CreateAbstract({ subthemes, presentationTypes }: CreateAbstractProps) {
    const { data, setData, post, processing, errors } = useForm<AbstractFormData>({
        title: '',
        subtheme_id: '',
        presentation_type: '',
        authors: [{ name: '', institution: '', is_presenter: true }],
        abstract_text: '',
    });

    const wordCount = data.abstract_text.trim() ? data.abstract_text.trim().split(/\s+/).length : 0;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('abstracts.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Submit Abstract" />
            <form onSubmit={submit} className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
                <div className="grid gap-2">
                    <Label htmlFor="title">Abstract title</Label>
                    <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                    <InputError message={errors.title} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="subtheme_id">Sub-theme</Label>
                    <Select value={data.subtheme_id} onValueChange={(value) => setData('subtheme_id', value)}>
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
                    <Select value={data.presentation_type} onValueChange={(value) => setData('presentation_type', value)}>
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
                    <Textarea id="abstract_text" rows={10} value={data.abstract_text} onChange={(e) => setData('abstract_text', e.target.value)} />
                    <p className={`text-xs ${wordCount > 300 ? 'text-destructive' : 'text-muted-foreground'}`}>{wordCount} / 300 words</p>
                    <InputError message={errors.abstract_text} />
                </div>

                <Button type="submit" disabled={processing} className="w-fit">
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Submit abstract
                </Button>
            </form>
        </AppLayout>
    );
}
