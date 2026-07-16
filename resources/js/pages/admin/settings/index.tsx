import { IconTile } from '@/components/dashboard-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Building2, CalendarRange, FolderKanban, LucideIcon, Settings, UsersRound, Wallet } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

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

type AssignableRole = 'reviewer' | 'staff' | 'admin' | 'super_admin';

const ROLE_LABELS: Record<AssignableRole, string> = {
    reviewer: 'Reviewer',
    staff: 'Staff (check-in)',
    admin: 'Admin',
    super_admin: 'Super Admin',
};

interface Candidate {
    id: number;
    name: string;
    email: string;
}

interface TeamMember {
    id: number;
    name: string;
    email: string;
    role: AssignableRole;
}

interface TeamAccessChange {
    id: number;
    target_name: string;
    target_email: string;
    changed_by_name: string;
    action: 'granted' | 'revoked';
    role: AssignableRole | null;
    created_at: string;
}

interface SettingsIndexProps {
    feeCategories: FeeCategory[];
    subthemes: Subtheme[];
    institutions: Institution[];
    conferenceSettings: Record<string, string | null>;
    canManageTeam: boolean;
    team: TeamMember[];
    teamAccessChanges: TeamAccessChange[];
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

function formatAccessChangeDate(value: string): string {
    return new Intl.DateTimeFormat('en-TZ', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function TeamSection({ team, accessChanges }: { team: TeamMember[]; accessChanges: TeamAccessChange[] }) {
    const { auth, flash } = usePage<SharedData & { flash?: { success?: string } }>().props;
    const [search, setSearch] = useState('');
    const [results, setResults] = useState<Candidate[]>([]);
    const [searching, setSearching] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [newRole, setNewRole] = useState<AssignableRole>('reviewer');
    const [grantingId, setGrantingId] = useState<number | null>(null);
    const [revokingId, setRevokingId] = useState<number | null>(null);

    useEffect(() => {
        const query = search.trim();

        if (query.length < 2) {
            setResults([]);
            setSearching(false);
            setSearchError(null);
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setSearching(true);
            setSearchError(null);

            try {
                const response = await fetch(route('admin.settings.administrators.search', { query }), {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!response.ok) throw new Error('Search failed');

                const payload = (await response.json()) as { users: Candidate[] };
                setResults(payload.users);
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') return;
                setSearchError('Could not search users. Please try again.');
            } finally {
                if (!controller.signal.aborted) setSearching(false);
            }
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [search]);

    const grantRole = (user: Candidate) => {
        setActionError(null);
        setGrantingId(user.id);

        router.post(
            route('admin.settings.administrators.store'),
            { user_id: user.id, role: newRole },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSearch('');
                    setResults([]);
                },
                onError: (errors) => setActionError(String(errors.user_id ?? errors.role ?? 'Role could not be granted.')),
                onFinish: () => setGrantingId(null),
            },
        );
    };

    const revokeRole = (member: TeamMember) => {
        if (!window.confirm(`Remove the ${ROLE_LABELS[member.role]} role from ${member.name}?`)) return;

        setActionError(null);
        setRevokingId(member.id);

        router.delete(route('admin.settings.administrators.destroy', member.id), {
            preserveScroll: true,
            onError: (errors) => setActionError(String(errors.administrator ?? 'Role could not be removed.')),
            onFinish: () => setRevokingId(null),
        });
    };

    const superAdminCount = team.filter((member) => member.role === 'super_admin').length;

    return (
        <SettingsSection icon={UsersRound} title="Team & roles" description="Grant or revoke reviewer, staff, admin, and super admin access.">
            <div className="flex flex-col gap-8">
                <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.72fr)]">
                    <section>
                        <h3 className="text-sm font-semibold">Current team</h3>
                        <div className="mt-3 divide-y rounded-lg border">
                            {team.map((member) => {
                                const isCurrentUser = member.id === auth.user.id;
                                const cannotRemove = isCurrentUser || (member.role === 'super_admin' && superAdminCount <= 1);

                                return (
                                    <div
                                        key={member.id}
                                        className="flex flex-col items-stretch gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <p className="truncate text-sm font-medium">{member.name}</p>
                                                <span className="bg-primary/10 text-primary rounded-full px-2 py-0.5 text-[11px] font-semibold">
                                                    {ROLE_LABELS[member.role]}
                                                </span>
                                                {isCurrentUser && (
                                                    <span className="bg-primary/10 text-primary rounded-full px-2 py-0.5 text-[11px] font-semibold">
                                                        You
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-muted-foreground truncate text-sm">{member.email}</p>
                                        </div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            className="w-full shrink-0 border-red-200 text-red-700 hover:bg-red-50 hover:text-red-800 sm:w-auto"
                                            disabled={cannotRemove || revokingId === member.id}
                                            title={isCurrentUser ? 'Another admin must remove your role' : undefined}
                                            onClick={() => revokeRole(member)}
                                        >
                                            {revokingId === member.id ? 'Removing…' : 'Remove role'}
                                        </Button>
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    <section>
                        <Label htmlFor="team-search" className="text-sm font-semibold">
                            Add team member
                        </Label>
                        <Input
                            id="team-search"
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search by name or email"
                            className="mt-3"
                            autoComplete="off"
                        />
                        <div className="mt-3 grid gap-1">
                            <Label className="text-xs">Role to assign</Label>
                            <Select value={newRole} onValueChange={(value) => setNewRole(value as AssignableRole)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {(Object.keys(ROLE_LABELS) as AssignableRole[]).map((role) => (
                                        <SelectItem key={role} value={role}>
                                            {ROLE_LABELS[role]}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="text-muted-foreground mt-2 min-h-6 text-xs" aria-live="polite">
                            {search.trim().length < 2 && 'Enter at least 2 characters.'}
                            {searching && 'Searching…'}
                            {searchError}
                            {!searching && !searchError && search.trim().length >= 2 && results.length === 0 && 'No eligible users found.'}
                        </div>

                        {results.length > 0 && (
                            <div className="mt-1 divide-y rounded-lg border">
                                {results.map((user) => (
                                    <div
                                        key={user.id}
                                        className="flex flex-col items-stretch gap-3 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">{user.name}</p>
                                            <p className="text-muted-foreground truncate text-xs">{user.email}</p>
                                        </div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            className="w-full sm:w-auto"
                                            disabled={grantingId === user.id}
                                            onClick={() => grantRole(user)}
                                        >
                                            {grantingId === user.id ? 'Granting…' : `Make ${ROLE_LABELS[newRole]}`}
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </div>

                {(flash?.success || actionError) && (
                    <div
                        className={`mt-5 rounded-md px-3 py-2 text-sm ${actionError ? 'bg-red-50 text-red-800' : 'bg-primary/10 text-primary'}`}
                        role="status"
                    >
                        {actionError ?? flash?.success}
                    </div>
                )}

                <section className="mt-8 border-t pt-6">
                    <h3 className="text-sm font-semibold">Recent role changes</h3>
                    {accessChanges.length === 0 ? (
                        <p className="text-muted-foreground mt-2 text-sm">No role changes have been recorded yet.</p>
                    ) : (
                        <div className="mt-3 divide-y">
                            {accessChanges.map((change) => (
                                <div key={change.id} className="grid gap-1 py-3 text-sm sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-4">
                                    <p>
                                        <span className={change.action === 'granted' ? 'text-primary font-semibold' : 'font-semibold text-red-700'}>
                                            {change.action === 'granted' ? 'Granted' : 'Removed'}
                                        </span>{' '}
                                        {change.role ? ROLE_LABELS[change.role] : 'role'} for{' '}
                                        <span className="font-medium">{change.target_name}</span> by {change.changed_by_name}
                                    </p>
                                    <time className="text-muted-foreground text-xs" dateTime={change.created_at}>
                                        {formatAccessChangeDate(change.created_at)}
                                    </time>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
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
        patch(route('admin.settings.fee-categories.update', category.id), { preserveScroll: true });
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
        patch(route('admin.settings.subthemes.update', subtheme.id), { preserveScroll: true });
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
        patch(route('admin.settings.institutions.update', institution.id), { preserveScroll: true });
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
        post(route('admin.settings.institutions.store'), { preserveScroll: true, onSuccess: () => reset() });
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
        post(route('admin.settings.subthemes.store'), { preserveScroll: true, onSuccess: () => reset() });
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

export default function SettingsIndex({
    feeCategories,
    subthemes,
    institutions,
    conferenceSettings,
    canManageTeam,
    team,
    teamAccessChanges,
}: SettingsIndexProps) {
    const [conf, setConf] = useState<Record<string, string | null>>(() => ({
        ...conferenceSettings,
        start_date: normalizeDateInput(conferenceSettings.start_date),
        end_date: normalizeDateInput(conferenceSettings.end_date),
        submission_deadline: normalizeDateInput(conferenceSettings.submission_deadline),
        abstract_notification_date: normalizeDateInput(conferenceSettings.abstract_notification_date),
    }));
    const confForm = useForm(conf);

    const submitConference: FormEventHandler = (e) => {
        e.preventDefault();
        confForm.transform(() => conf);
        confForm.patch(route('admin.settings.conference.update'), { preserveScroll: true });
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
                        <p className="text-xs font-bold tracking-[0.18em] text-[#135eeb] uppercase">Conference operations</p>
                        <h1 className="mt-2 font-serif text-3xl font-semibold">Settings</h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Conference details, fees, sub-themes, institutions, and team access all live here.
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
                            <Label>Abstract submission deadline</Label>
                            <Input
                                type="date"
                                value={conf.submission_deadline ?? ''}
                                onChange={(e) => setConf({ ...conf, submission_deadline: e.target.value })}
                            />
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

                {canManageTeam && <TeamSection team={team} accessChanges={teamAccessChanges} />}

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
            </div>
        </AppLayout>
    );
}
