import { IconTile } from '@/components/dashboard-card';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Building2, CalendarRange, ChevronLeft, ChevronRight, FolderKanban, LucideIcon, Search, Settings, UsersRound, Wallet } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';
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

type UserRole = 'user' | 'reviewer' | 'staff' | 'finance' | 'admin' | 'super_admin';

const ROLE_LABELS: Record<UserRole, string> = {
    user: 'Participant',
    reviewer: 'Reviewer',
    staff: 'Staff (check-in)',
    finance: 'Finance',
    admin: 'Admin',
    super_admin: 'Super Admin',
};

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    roles: UserRole[];
    email_verified: boolean;
}

const ALL_ROLES = Object.keys(ROLE_LABELS) as UserRole[];

interface PaginatedUsers {
    data: UserRow[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface RoleAccessChange {
    id: number;
    target_name: string;
    target_email: string;
    changed_by_name: string;
    action: 'granted' | 'revoked';
    role: UserRole | null;
    created_at: string;
}

interface SettingsIndexProps {
    feeCategories: FeeCategory[];
    subthemes: Subtheme[];
    institutions: Institution[];
    conferenceSettings: Record<string, string | null>;
    roleAccessChanges: RoleAccessChange[];
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

function RoleEditor({ user, locked, onSave }: { user: UserRow; locked: boolean; onSave: (roles: UserRole[], primaryRole: UserRole) => void }) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<UserRole[]>(user.roles);
    const [primary, setPrimary] = useState<UserRole>(user.role);

    useEffect(() => {
        if (open) {
            setSelected(user.roles);
            setPrimary(user.role);
        }
    }, [open, user.roles, user.role]);

    const toggleRole = (role: UserRole) => {
        setSelected((current) => {
            const next = current.includes(role) ? current.filter((r) => r !== role) : [...current, role];
            if (next.length > 0 && !next.includes(primary)) {
                setPrimary(next[0]);
            }
            return next;
        });
    };

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="outline" size="sm" disabled={locked} className="h-auto min-h-9 w-full justify-start sm:w-56">
                    <div className="flex flex-wrap gap-1">
                        {user.roles.map((role) => (
                            <span key={role} className="bg-primary/10 text-primary rounded-full px-2 py-0.5 text-[11px] font-semibold">
                                {ROLE_LABELS[role]}
                            </span>
                        ))}
                    </div>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel>Assign roles</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {ALL_ROLES.map((role) => (
                    <DropdownMenuCheckboxItem
                        key={role}
                        checked={selected.includes(role)}
                        onSelect={(event) => event.preventDefault()}
                        onCheckedChange={() => toggleRole(role)}
                    >
                        {ROLE_LABELS[role]}
                    </DropdownMenuCheckboxItem>
                ))}
                {selected.length > 1 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">Primary role (default view)</DropdownMenuLabel>
                        <DropdownMenuRadioGroup value={primary} onValueChange={(value) => setPrimary(value as UserRole)}>
                            {selected.map((role) => (
                                <DropdownMenuRadioItem key={role} value={role} onSelect={(event) => event.preventDefault()}>
                                    {ROLE_LABELS[role]}
                                </DropdownMenuRadioItem>
                            ))}
                        </DropdownMenuRadioGroup>
                    </>
                )}
                <DropdownMenuSeparator />
                <div className="p-1">
                    <Button
                        type="button"
                        size="sm"
                        className="w-full"
                        disabled={selected.length === 0}
                        onClick={() => {
                            onSave(selected, primary);
                            setOpen(false);
                        }}
                    >
                        Save
                    </Button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function UsersAndRolesSection({ accessChanges }: { accessChanges: RoleAccessChange[] }) {
    const { auth } = usePage<SharedData>().props;
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState<UserRole | 'all'>('all');
    const [page, setPage] = useState(1);
    const [users, setUsers] = useState<PaginatedUsers | null>(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [actionSuccess, setActionSuccess] = useState<string | null>(null);
    const [updatingId, setUpdatingId] = useState<number | null>(null);

    const fetchUsers = () => {
        setLoading(true);
        setLoadError(null);

        const params: Record<string, string> = { page: String(page) };
        if (search.trim()) params.query = search.trim();
        if (roleFilter !== 'all') params.role = roleFilter;

        fetch(route('admin.settings.users.index', params), { headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) throw new Error('Failed to load users');
                return response.json() as Promise<PaginatedUsers>;
            })
            .then(setUsers)
            .catch(() => setLoadError('Could not load users. Please try again.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        const timer = window.setTimeout(fetchUsers, search ? 250 : 0);
        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, roleFilter, page]);

    useEffect(() => {
        setPage(1);
    }, [search, roleFilter]);

    const superAdminCount = users?.data.filter((u) => u.roles.includes('super_admin')).length ?? 0;

    const changeRoles = (user: UserRow, roles: UserRole[], primaryRole: UserRole) => {
        setActionError(null);
        setActionSuccess(null);
        setUpdatingId(user.id);

        router.patch(
            route('admin.settings.users.update-roles', user.id),
            { roles, primary_role: primaryRole },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setActionSuccess(`${user.name}'s roles were updated.`);
                    fetchUsers();
                },
                onError: (errors) => setActionError(String(errors.roles ?? errors.primary_role ?? 'Roles could not be updated.')),
                onFinish: () => setUpdatingId(null),
            },
        );
    };

    return (
        <SettingsSection icon={UsersRound} title="Users & roles" description="Search every user and change their role directly.">
            <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-2 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search by name or email"
                            className="pl-9"
                        />
                    </div>
                    <Select value={roleFilter} onValueChange={(value) => setRoleFilter(value as UserRole | 'all')}>
                        <SelectTrigger className="sm:w-56">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All roles</SelectItem>
                            {(Object.keys(ROLE_LABELS) as UserRole[]).map((role) => (
                                <SelectItem key={role} value={role}>
                                    {ROLE_LABELS[role]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {(actionError || actionSuccess) && (
                    <div
                        className={`rounded-md px-3 py-2 text-sm ${actionError ? 'bg-red-50 text-red-800' : 'bg-primary/10 text-primary'}`}
                        role="status"
                    >
                        {actionError ?? actionSuccess}
                    </div>
                )}

                <div className="divide-y rounded-lg border">
                    {loading && !users ? (
                        <p className="text-muted-foreground p-4 text-sm">Loading users…</p>
                    ) : loadError ? (
                        <p className="p-4 text-sm text-red-700">{loadError}</p>
                    ) : users && users.data.length > 0 ? (
                        users.data.map((user) => {
                            const isCurrentUser = user.id === auth.user.id;
                            const isLastSuperAdmin = user.roles.includes('super_admin') && superAdminCount <= 1;
                            const locked = isCurrentUser || isLastSuperAdmin;

                            return (
                                <div
                                    key={user.id}
                                    className="flex flex-col items-stretch gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                >
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <p className="truncate text-sm font-medium">{user.name}</p>
                                            {isCurrentUser && (
                                                <span className="bg-primary/10 text-primary rounded-full px-2 py-0.5 text-[11px] font-semibold">
                                                    You
                                                </span>
                                            )}
                                            {!user.email_verified && (
                                                <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                                                    Unverified
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-muted-foreground truncate text-sm">{user.email}</p>
                                    </div>
                                    <div
                                        title={
                                            isCurrentUser
                                                ? 'Another super admin must change your roles'
                                                : isLastSuperAdmin
                                                  ? 'The final super admin cannot lose that role'
                                                  : undefined
                                        }
                                    >
                                        <RoleEditor
                                            user={user}
                                            locked={locked || updatingId === user.id}
                                            onSave={(roles, primaryRole) => changeRoles(user, roles, primaryRole)}
                                        />
                                    </div>
                                </div>
                            );
                        })
                    ) : (
                        <p className="text-muted-foreground p-4 text-sm">No users match this search.</p>
                    )}
                </div>

                {users && users.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-muted-foreground text-xs">
                            Page {users.current_page} of {users.last_page} · {users.total} users
                        </p>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={!users.prev_page_url}
                                onClick={() => setPage((p) => Math.max(1, p - 1))}
                            >
                                <ChevronLeft className="size-4" />
                                Prev
                            </Button>
                            <Button type="button" size="sm" variant="outline" disabled={!users.next_page_url} onClick={() => setPage((p) => p + 1)}>
                                Next
                                <ChevronRight className="size-4" />
                            </Button>
                        </div>
                    </div>
                )}

                <section className="border-t pt-6">
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

export default function SettingsIndex({ feeCategories, subthemes, institutions, conferenceSettings, roleAccessChanges }: SettingsIndexProps) {
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
                        <h1 className="mt-2 font-serif text-3xl font-semibold">Settings</h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Conference details, fees, sub-themes, institutions, and every user's role live here.
                        </p>
                    </div>
                </header>

                <UsersAndRolesSection accessChanges={roleAccessChanges} />

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
