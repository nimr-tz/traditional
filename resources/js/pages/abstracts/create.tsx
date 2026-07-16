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

const ABSTRACT_SECTIONS = [
    { key: 'background', label: 'Background' },
    { key: 'objective', label: 'Objective' },
    { key: 'methods', label: 'Methods' },
    { key: 'results', label: 'Results' },
    { key: 'conclusion', label: 'Conclusion' },
] as const;

type SectionKey = (typeof ABSTRACT_SECTIONS)[number]['key'];

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

function countWords(value: string): number {
    return value.trim() ? value.trim().split(/\s+/).length : 0;
}

export default function CreateAbstract({ subthemes, presentationTypes }: CreateAbstractProps) {
    const { data, setData, post, processing, errors } = useForm<AbstractFormData>({
        title: '',
        subtheme_id: '',
        presentation_type: '',
        authors: [{ name: '', institution: '', is_presenter: true }],
        background: '',
        objective: '',
        methods: '',
        results: '',
        conclusion: '',
    });

    const wordCount = ABSTRACT_SECTIONS.reduce((total, { key }) => total + countWords(data[key as SectionKey]), 0);

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
                    <div className="flex items-center justify-between">
                        <Label>Abstract</Label>
                        <p className={`text-xs ${wordCount > 300 ? 'text-destructive' : 'text-muted-foreground'}`}>{wordCount} / 300 words combined</p>
                    </div>
                    <p className="text-muted-foreground text-xs">
                        Fill in each section separately. The 300-word limit applies to all five sections combined.
                    </p>
                </div>

                {ABSTRACT_SECTIONS.map(({ key, label }) => (
                    <div key={key} className="grid gap-2">
                        <Label htmlFor={key}>{label}</Label>
                        <Textarea id={key} rows={4} value={data[key]} onChange={(e) => setData(key, e.target.value)} />
                        <InputError message={errors[key]} />
                    </div>
                ))}

                <Button type="submit" disabled={processing} className="w-fit">
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Submit abstract
                </Button>
            </form>
        </AppLayout>
    );
}
