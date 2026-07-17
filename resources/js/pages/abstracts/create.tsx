import AbstractAuthorsField, { Author } from '@/components/abstract-authors-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
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
    { key: 'background', label: 'Background', placeholder: 'What problem or gap does this work address?' },
    { key: 'objective', label: 'Objective', placeholder: 'What did the study set out to do?' },
    { key: 'methods', label: 'Methods', placeholder: 'Design, setting, participants, and analysis.' },
    { key: 'results', label: 'Results', placeholder: 'Key findings, with numbers where possible.' },
    { key: 'conclusion', label: 'Conclusion', placeholder: 'What do the findings mean for practice or policy?' },
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

function formatDate(value: string | null): string | null {
    if (!value) return null;
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('en', { day: 'numeric', month: 'long', year: 'numeric' }).format(date);
}

export default function CreateAbstract({ subthemes, presentationTypes }: CreateAbstractProps) {
    const { conference } = usePage<SharedData>().props;
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
    const deadline = formatDate(conference.submission_deadline);
    const notificationDate = formatDate(conference.abstract_notification_date);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('abstracts.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Submit Abstract" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-2 p-4 pb-10 md:p-6">
                <p className="text-xs font-bold tracking-[0.14em] text-[#4c8a1f] uppercase">Call for abstracts</p>
                <h1 className="font-serif text-3xl font-semibold">Submit your abstract</h1>
                <p className="text-muted-foreground mt-1 max-w-2xl text-sm leading-6">
                    Fill in each section separately — the reviewers see them exactly as structured below. The 300-word limit applies to all five
                    sections combined.
                </p>

                <div className="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <form
                        onSubmit={submit}
                        className="dark:bg-card flex flex-col gap-6 rounded-2xl border border-[#135eeb]/10 bg-white p-6 shadow-[0_1px_2px_rgba(19,94,235,0.06)] md:p-7"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="title">Abstract title *</Label>
                            <Input
                                id="title"
                                className="font-semibold uppercase"
                                placeholder="BOLD CAPITAL LETTERS, e.g. ANTIMICROBIAL ACTIVITY OF…"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                            />
                            <p className="text-muted-foreground text-xs">The title should be in bold capital letters.</p>
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-6 sm:grid-cols-[1fr_auto]">
                            <div className="grid gap-2">
                                <Label htmlFor="subtheme_id">Sub-theme *</Label>
                                <Select value={data.subtheme_id} onValueChange={(value) => setData('subtheme_id', value)}>
                                    <SelectTrigger id="subtheme_id" className="h-11">
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
                                <Label>Presentation type *</Label>
                                <div className="flex gap-2">
                                    {Object.entries(presentationTypes).map(([key, label]) => {
                                        const selected = data.presentation_type === key;
                                        return (
                                            <button
                                                key={key}
                                                type="button"
                                                onClick={() => setData('presentation_type', key)}
                                                className={`h-11 flex-1 rounded-md border px-5 text-sm font-semibold whitespace-nowrap transition-colors ${
                                                    selected
                                                        ? 'border-[#4c8a1f] bg-[#eef7e6] text-[#4c8a1f]'
                                                        : 'border-input bg-background text-foreground'
                                                }`}
                                            >
                                                {label}
                                            </button>
                                        );
                                    })}
                                </div>
                                <InputError message={errors.presentation_type} />
                            </div>
                        </div>

                        <AbstractAuthorsField authors={data.authors} onChange={(authors) => setData('authors', authors)} errors={errors} />

                        <div className="grid gap-2 border-t pt-5">
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

                        {ABSTRACT_SECTIONS.map(({ key, label, placeholder }) => (
                            <div key={key} className="grid gap-2">
                                <div className="flex items-baseline justify-between">
                                    <Label htmlFor={key} className="text-[#4c8a1f]">
                                        {label}
                                    </Label>
                                    <span className="text-muted-foreground text-[11px] tabular-nums">{countWords(data[key])} words</span>
                                </div>
                                <Textarea
                                    id={key}
                                    rows={4}
                                    placeholder={placeholder}
                                    value={data[key]}
                                    onChange={(e) => setData(key, e.target.value)}
                                />
                                <InputError message={errors[key]} />
                            </div>
                        ))}

                        <div className="flex items-center gap-3.5 border-t pt-5">
                            <Button type="submit" disabled={processing} className="shrink-0 bg-[#4c8a1f] font-bold hover:bg-[#3f751a]">
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Submit abstract
                            </Button>
                            <p className="text-muted-foreground text-xs leading-5">
                                You can still edit your abstract after submitting, until a decision is made.
                            </p>
                        </div>
                    </form>

                    <aside className="flex flex-col gap-3.5 lg:sticky lg:top-6">
                        <div className="rounded-xl bg-[#eaf1ff] p-4.5">
                            <div className="text-[11px] font-semibold tracking-wide text-[#135eeb] uppercase">Word limit</div>
                            <p className="mt-2 font-serif text-sm leading-6 font-semibold">300 words + 5 keywords</p>
                        </div>
                        <div className="rounded-xl bg-[#eef7e6] p-4.5">
                            <div className="text-[11px] font-semibold tracking-wide text-[#4c8a1f] uppercase">Title</div>
                            <p className="mt-2 font-serif text-sm leading-6 font-semibold">Bold capital letters</p>
                        </div>
                        <div className="rounded-xl bg-[#eaf1ff] p-4.5">
                            <div className="text-[11px] font-semibold tracking-wide text-[#135eeb] uppercase">Structure</div>
                            <p className="mt-2 font-serif text-sm leading-6 font-semibold">Background, Objective, Methods, Results, Conclusion</p>
                        </div>
                        <div className="dark:bg-card rounded-xl border border-[#135eeb]/10 bg-white p-4.5">
                            <div className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">Author format</div>
                            <p className="text-muted-foreground mt-2 text-sm leading-6">
                                Write names as initials followed by surname, e.g. <strong className="text-foreground">H Malinye; JJ Kayumba</strong>.
                                The presenting author's name must be bolded and underlined in the programme.
                            </p>
                        </div>
                        {deadline && (
                            <div className="rounded-xl bg-[#67b52f] p-4.5 text-white">
                                <div className="text-[11px] font-semibold tracking-wide text-white/80 uppercase">Abstract deadline</div>
                                <p className="mt-2 font-serif text-lg font-semibold">{deadline}</p>
                                {notificationDate && (
                                    <p className="mt-1.5 text-xs leading-5 text-white/85">Notification of acceptance by {notificationDate}.</p>
                                )}
                            </div>
                        )}
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
