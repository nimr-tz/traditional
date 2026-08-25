import { IconTile } from '@/components/dashboard-card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { csrfHeader } from '@/lib/csrf';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Building2, CalendarRange, Eraser, FolderKanban, LoaderCircle, LucideIcon, Settings, TriangleAlert, Wallet } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Settings', href: '/admin/settings' },
];

const DATE_MONTHS: Record<string, string> = {
    january: '01',
    february: '02',
    march: '03',
    april: '04',
    may: '05',
    june: '06',
    july: '07',
    august: '08',
    september: '09',
    october: '10',
    november: '11',
    december: '12',
};

function normalizeDateInput(value: string | null | undefined): string {
    if (!value) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;

    const match = value.trim().match(/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/);
    if (!match) return '';

    const month = DATE_MONTHS[match[2].toLowerCase()];
    if (!month) return '';

    return `${match[3]}-${month}-${match[1].padStart(2, '0')}`;
}

interface FeeCategory {
    id: number;
    key: string;
    label: string;
    amount: string;
    currency: string;
    active: boolean;
}

interface Subtheme {
    id: number;
    title: string;
    description: string | null;
    active: boolean;
}

interface Institution {
    id: number;
    name: string;
    active: boolean;
}

interface SettingsIndexProps {
    feeCategories: FeeCategory[];
    subthemes: Subtheme[];
    institutions: Institution[];
    conferenceSettings: Record<string, string | null>;
}

function SettingsSection({
    icon: Icon,
    tone = 'blue',
    title,
    description,
    children,
}: {
    icon: LucideIcon;
    tone?: 'blue' | 'green';
    title: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <section className="dark:bg-card overflow-hidden rounded-2xl border border-[#135eeb]/10 bg-white shadow-[0_1px_2px_rgba(19,94,235,0.06)]">
            <div className="flex items-center gap-3.5 border-b border-slate-100 p-6 dark:border-slate-800">
                <IconTile tone={tone}>
                    <Icon className="size-5" />
                </IconTile>
                <div>
                    <h2 className="text-lg font-semibold">{title}</h2>
                    {description && <p className="text-muted-foreground mt-0.5 text-sm">{description}</p>}
                </div>
            </div>
            <div className="p-6">{children}</div>
        </section>
    );
}

interface PurgeRecord {
    id: number;
    name: string;
    email: string;
    payment_status: string;
    control_number: string | null;
    billing_request_id: string | null;
    registration_code: string | null;
    action: 'strip_ids' | 'revoke_simulated_payment' | 'reset_to_pending';
    action_label: string;
    attendance_count: number;
    certificate_count: number;
    needs_manual_review: boolean;
}

interface PurgeResponse {
    applied: boolean;
    dry_run?: boolean;
    sandbox_mode?: boolean;
    count?: number;
    records?: PurgeRecord[];
    message: string;
}

/**
 * Clears the control numbers and simulated payments sandbox mode left behind,
 * once the real NIMR Billing / GePG gateway is live. Production has no shell to
 * run `php artisan billing:purge-sandbox` from, so the same service is exposed
 * here. Always previews before it will apply anything.
 */
function SandboxBillingSection() {
    const [result, setResult] = useState<PurgeResponse | null>(null);
    const [busy, setBusy] = useState<false | 'preview' | 'apply'>(false);
    const [confirming, setConfirming] = useState(false);

    const run = async (dryRun: boolean) => {
        setBusy(dryRun ? 'preview' : 'apply');

        try {
            const response = await fetch(route('admin.billing.purge-sandbox'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeader() },
                body: JSON.stringify({ dry_run: dryRun }),
            });

            const payload: PurgeResponse = await response.json();
            setResult(payload);

            if (payload.applied) {
                toast.success(payload.message);
            } else if (payload.sandbox_mode) {
                toast.error('Billing is still in sandbox mode');
            }
        } catch {
            toast.error('Could not reach the billing maintenance endpoint');
            setResult(null);
        } finally {
            setBusy(false);
            setConfirming(false);
        }
    };

    const records = result?.records ?? [];
    const canApply = !result?.applied && !result?.sandbox_mode && records.length > 0;
    const needsReview = records.filter((record) => record.needs_manual_review);

    return (
        <SettingsSection
            icon={Eraser}
            title="Sandbox billing cleanup"
            description="Clear the control numbers and simulated payments left over from sandbox testing."
        >
            <div className="grid gap-4">
                <p className="text-muted-foreground text-sm">
                    While billing ran in sandbox mode, control numbers were generated locally and payments could be simulated. Those numbers are
                    rejected at every bank and mobile-money channel, and a simulated payment counts as real revenue and issues a badge. Preview first
                    — nothing is written until you confirm.
                </p>

                <div className="flex flex-wrap items-center gap-2">
                    <Button type="button" variant="outline" disabled={busy !== false} onClick={() => run(true)}>
                        {busy === 'preview' && <LoaderCircle className="size-4 animate-spin" />}
                        Preview affected registrants
                    </Button>

                    {canApply && (
                        <Button type="button" variant="destructive" disabled={busy !== false} onClick={() => setConfirming(true)}>
                            Apply cleanup
                        </Button>
                    )}
                </div>

                {result?.sandbox_mode && (
                    <div className="flex gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-900 dark:bg-amber-950/40">
                        <TriangleAlert className="size-5 shrink-0 text-amber-600" />
                        <div>
                            <p className="font-semibold">Billing is still in sandbox mode</p>
                            <p className="text-muted-foreground mt-1">{result.message}</p>
                        </div>
                    </div>
                )}

                {result && !result.sandbox_mode && records.length === 0 && <p className="text-sm font-medium">{result.message}</p>}

                {records.length > 0 && (
                    <div className="grid gap-3">
                        <p className="text-sm font-medium">
                            {result?.applied ? 'Cleared' : 'Would change'} {records.length} registrant{records.length === 1 ? '' : 's'}
                        </p>

                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-xs uppercase">
                                    <tr>
                                        <th className="p-3 font-semibold">Registrant</th>
                                        <th className="p-3 font-semibold">Status</th>
                                        <th className="p-3 font-semibold">Control number</th>
                                        <th className="p-3 font-semibold">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {records.map((record) => (
                                        <tr key={record.id}>
                                            <td className="p-3">
                                                <p className="font-medium">{record.name}</p>
                                                <p className="text-muted-foreground text-xs">{record.email}</p>
                                            </td>
                                            <td className="p-3">{record.payment_status}</td>
                                            <td className="p-3 font-mono text-xs">{record.control_number ?? '—'}</td>
                                            <td className="p-3">
                                                <span
                                                    className={
                                                        record.action === 'revoke_simulated_payment' ? 'font-semibold text-red-700' : undefined
                                                    }
                                                >
                                                    {record.action_label}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {needsReview.length > 0 && (
                            <div className="flex gap-3 rounded-xl border border-red-300 bg-red-50 p-4 text-sm dark:border-red-900 dark:bg-red-950/40">
                                <TriangleAlert className="size-5 shrink-0 text-red-600" />
                                <div>
                                    <p className="font-semibold">Review these by hand</p>
                                    <p className="text-muted-foreground mt-1">
                                        {needsReview.map((record) => record.name).join(', ')} already checked in or received a certificate against a
                                        simulated payment. Those records are left untouched — decide what to do about them separately.
                                    </p>
                                </div>
                            </div>
                        )}

                        {!result?.applied && (
                            <p className="text-muted-foreground text-xs">
                                Anyone holding a sandbox control number was emailed it earlier. Clearing it here does not retract that email — tell
                                them directly to request a new one.
                            </p>
                        )}
                    </div>
                )}
            </div>

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Apply sandbox cleanup?</DialogTitle>
                        <DialogDescription>
                            This clears sandbox control numbers and revokes simulated payments for {records.length} registrant
                            {records.length === 1 ? '' : 's'}, including any badge codes those payments issued. Payments a person at finance verified
                            or waived are kept. This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setConfirming(false)} disabled={busy !== false}>
                            Cancel
                        </Button>
                        <Button type="button" variant="destructive" onClick={() => run(false)} disabled={busy !== false}>
                            {busy === 'apply' && <LoaderCircle className="size-4 animate-spin" />}
                            Apply cleanup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsSection>
    );
}

function FeeCategoryRow({ category }: { category: FeeCategory }) {
    const { data, setData, patch, processing } = useForm({
        label: category.label,
        amount: category.amount,
        currency: category.currency,
        active: category.active,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('admin.settings.fee-categories.update', category.id), {
            preserveScroll: true,
            onSuccess: () => toast.success('Fee category updated'),
            onError: () => toast.error('Could not update fee category'),
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-center gap-2 border-b py-2 last:border-b-0">
            <Input value={data.label} onChange={(e) => setData('label', e.target.value)} className="min-w-64 flex-1" />
            <Input type="number" value={data.amount} onChange={(e) => setData('amount', e.target.value)} className="w-32" />
            <Input value={data.currency} onChange={(e) => setData('currency', e.target.value)} className="w-20" />
            <label className="flex items-center gap-1 text-sm">
                <input type="checkbox" checked={data.active} onChange={(e) => setData('active', e.target.checked)} />
                Active
            </label>
            <Button type="submit" size="sm" disabled={processing}>
                Save
            </Button>
        </form>
    );
}

function SubthemeRow({ subtheme }: { subtheme: Subtheme }) {
    const { data, setData, patch, processing } = useForm({
        title: subtheme.title,
        description: subtheme.description ?? '',
        active: subtheme.active,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('admin.settings.subthemes.update', subtheme.id), {
            preserveScroll: true,
            onSuccess: () => toast.success('Sub-theme updated'),
            onError: () => toast.error('Could not update sub-theme'),
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-2 border-b py-2 last:border-b-0">
            <div className="flex flex-wrap items-center gap-2">
                <Input value={data.title} onChange={(e) => setData('title', e.target.value)} className="min-w-64 flex-1" />
                <label className="flex items-center gap-1 text-sm">
                    <input type="checkbox" checked={data.active} onChange={(e) => setData('active', e.target.checked)} />
                    Active
                </label>
                <Button type="submit" size="sm" disabled={processing}>
                    Save
                </Button>
            </div>
            <Textarea
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                placeholder="Sub-bullet points, one per line (optional)"
                rows={3}
                className="text-xs"
            />
        </form>
    );
}

function InstitutionRow({ institution }: { institution: Institution }) {
    const { data, setData, patch, processing } = useForm({ name: institution.name, active: institution.active });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('admin.settings.institutions.update', institution.id), {
            preserveScroll: true,
            onSuccess: () => toast.success('Institution updated'),
            onError: () => toast.error('Could not update institution'),
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-center gap-2 border-b py-2 last:border-b-0">
            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} className="min-w-64 flex-1" />
            <label className="flex items-center gap-1 text-sm">
                <input type="checkbox" checked={data.active} onChange={(e) => setData('active', e.target.checked)} />
                Active
            </label>
            <Button type="submit" size="sm" disabled={processing}>
                Save
            </Button>
        </form>
    );
}

function NewInstitutionForm() {
    const { data, setData, post, processing, reset } = useForm({ name: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.settings.institutions.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                toast.success('Institution added');
            },
            onError: () => toast.error('Could not add institution'),
        });
    };

    return (
        <form onSubmit={submit} className="flex items-center gap-2 pt-2">
            <Input placeholder="New institution name" value={data.name} onChange={(e) => setData('name', e.target.value)} className="flex-1" />
            <Button type="submit" size="sm" disabled={processing}>
                Add
            </Button>
        </form>
    );
}

function NewSubthemeForm() {
    const { data, setData, post, processing, reset } = useForm({ title: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.settings.subthemes.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                toast.success('Sub-theme added');
            },
            onError: () => toast.error('Could not add sub-theme'),
        });
    };

    return (
        <form onSubmit={submit} className="flex items-center gap-2 pt-2">
            <Input placeholder="New sub-theme title" value={data.title} onChange={(e) => setData('title', e.target.value)} className="flex-1" />
            <Button type="submit" size="sm" disabled={processing}>
                Add
            </Button>
        </form>
    );
}

export default function SettingsIndex({ feeCategories, subthemes, institutions, conferenceSettings }: SettingsIndexProps) {
    const [conf, setConf] = useState<Record<string, string | null>>(() => ({
        ...conferenceSettings,
        start_date: normalizeDateInput(conferenceSettings.start_date),
        end_date: normalizeDateInput(conferenceSettings.end_date),
        registration_deadline: normalizeDateInput(conferenceSettings.registration_deadline),
        registration_closed: conferenceSettings.registration_closed === '1' ? '1' : '0',
        abstract_notification_date: normalizeDateInput(conferenceSettings.abstract_notification_date),
        presentation_deadline: normalizeDateInput(conferenceSettings.presentation_deadline),
    }));
    const confForm = useForm(conf);

    const submitConference: FormEventHandler = (e) => {
        e.preventDefault();
        confForm.transform(() => conf);
        confForm.patch(route('admin.settings.conference.update'), {
            preserveScroll: true,
            onSuccess: () => toast.success('Conference details saved'),
            onError: () => toast.error('Could not save conference details'),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Conference Settings" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex items-start gap-4 border-b pb-5">
                    <IconTile tone="blue">
                        <Settings className="size-5" />
                    </IconTile>
                    <div>
                        <p className="text-xs font-bold tracking-[0.18em] text-[#135eeb] uppercase">Super admin</p>
                        <h1 className="mt-2 font-serif text-3xl font-semibold">Conference Settings</h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Conference details, fees, sub-themes, and institutions. User roles live under Users &amp; Roles.
                        </p>
                    </div>
                </header>

                <SettingsSection icon={CalendarRange} title="Conference details" description="Shown across the site, emails, and badges.">
                    <form onSubmit={submitConference} className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1">
                            <Label>Conference name</Label>
                            <Input value={conf.conference_name ?? ''} onChange={(e) => setConf({ ...conf, conference_name: e.target.value })} />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="grid gap-1">
                                <Label>Edition</Label>
                                <Input
                                    value={conf.edition_number ?? ''}
                                    onChange={(e) => setConf({ ...conf, edition_number: e.target.value })}
                                    placeholder="e.g. 5th"
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label>Year</Label>
                                <Input value={conf.conference_year ?? ''} onChange={(e) => setConf({ ...conf, conference_year: e.target.value })} />
                            </div>
                        </div>
                        <div className="grid gap-1 sm:col-span-2">
                            <Label>Theme</Label>
                            <Textarea value={conf.theme ?? ''} onChange={(e) => setConf({ ...conf, theme: e.target.value })} rows={2} />
                        </div>
                        <div className="grid gap-1">
                            <Label>Venue</Label>
                            <Input value={conf.venue ?? ''} onChange={(e) => setConf({ ...conf, venue: e.target.value })} />
                        </div>
                        <div className="grid gap-1">
                            <Label>Payee name (GePG)</Label>
                            <Input value={conf.gepg_payee_name ?? ''} onChange={(e) => setConf({ ...conf, gepg_payee_name: e.target.value })} />
                        </div>
                        <div className="grid gap-1">
                            <Label>Start date</Label>
                            <Input type="date" value={conf.start_date ?? ''} onChange={(e) => setConf({ ...conf, start_date: e.target.value })} />
                        </div>
                        <div className="grid gap-1">
                            <Label>End date</Label>
                            <Input type="date" value={conf.end_date ?? ''} onChange={(e) => setConf({ ...conf, end_date: e.target.value })} />
                        </div>
                        <div className="grid gap-1">
                            <Label>Abstract notification date</Label>
                            <Input
                                type="date"
                                value={conf.abstract_notification_date ?? ''}
                                onChange={(e) => setConf({ ...conf, abstract_notification_date: e.target.value })}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label>Presentation upload deadline</Label>
                            <Input
                                type="date"
                                value={conf.presentation_deadline ?? ''}
                                onChange={(e) => setConf({ ...conf, presentation_deadline: e.target.value })}
                            />
                            <p className="text-muted-foreground text-xs leading-5">
                                Presenters can replace their file until the end of this day. Presentations are not reviewed — whatever is uploaded by
                                then is what gets presented.
                            </p>
                        </div>
                        <div className="grid gap-1 sm:col-span-2">
                            <div className="rounded-xl border p-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-1">
                                        <Label>Registration deadline</Label>
                                        <Input
                                            type="date"
                                            value={conf.registration_deadline ?? ''}
                                            onChange={(e) => setConf({ ...conf, registration_deadline: e.target.value })}
                                        />
                                        <p className="text-muted-foreground text-xs leading-5">
                                            New accounts stop being accepted at the end of this day. Leave blank for no date cutoff.
                                        </p>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label>Registration status</Label>
                                        <Select
                                            value={conf.registration_closed === '1' ? '1' : '0'}
                                            onValueChange={(value) => setConf({ ...conf, registration_closed: value })}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="0">Open (follow the deadline)</SelectItem>
                                                <SelectItem value="1">Closed now</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p className="text-muted-foreground text-xs leading-5">
                                            Closing here takes effect immediately, whatever the deadline says. Existing registrants can still log in
                                            and pay.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="grid gap-1">
                            <Label>African Traditional Medicine Week dates</Label>
                            <Input
                                value={conf.tm_week_dates ?? ''}
                                onChange={(e) => setConf({ ...conf, tm_week_dates: e.target.value })}
                                placeholder="e.g. 26–31 August 2026"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label>Contact phone</Label>
                            <Input
                                value={conf.contact_phone ?? ''}
                                onChange={(e) => setConf({ ...conf, contact_phone: e.target.value })}
                                placeholder="Shown in the site footer, optional"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label>Contact email</Label>
                            <Input
                                value={conf.contact_email ?? ''}
                                onChange={(e) => setConf({ ...conf, contact_email: e.target.value })}
                                placeholder="Shown in the site footer, optional"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label>Website</Label>
                            <Input value={conf.website ?? ''} onChange={(e) => setConf({ ...conf, website: e.target.value })} />
                        </div>
                        <div className="grid gap-1">
                            <Label>Footer tagline</Label>
                            <Input
                                value={conf.tagline ?? ''}
                                onChange={(e) => setConf({ ...conf, tagline: e.target.value })}
                                placeholder='e.g. "Together for Healthier Communities"'
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <Button type="submit" disabled={confForm.processing} className="bg-[#135eeb] font-bold hover:bg-[#135eeb]/90">
                                Save conference details
                            </Button>
                        </div>
                    </form>
                </SettingsSection>

                <SettingsSection icon={Wallet} tone="green" title="Fee categories" description="Pricing shown at registration and on invoices.">
                    {feeCategories.map((category) => (
                        <FeeCategoryRow key={category.id} category={category} />
                    ))}
                </SettingsSection>

                <SettingsSection icon={FolderKanban} title="Abstract sub-themes" description="Categories authors choose from when submitting.">
                    {subthemes.map((subtheme) => (
                        <SubthemeRow key={subtheme.id} subtheme={subtheme} />
                    ))}
                    <NewSubthemeForm />
                </SettingsSection>

                <SettingsSection icon={Building2} tone="green" title="Institutions" description="The list registrants pick from during sign-up.">
                    {institutions.map((institution) => (
                        <InstitutionRow key={institution.id} institution={institution} />
                    ))}
                    <NewInstitutionForm />
                </SettingsSection>

                <SandboxBillingSection />
            </div>
        </AppLayout>
    );
}
