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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Search, UsersRound } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users & Roles', href: '/admin/users' },
];

type UserRole = 'user' | 'reviewer' | 'staff' | 'finance' | 'admin' | 'super_admin';

const ROLE_LABELS: Record<UserRole, string> = {
    user: 'Participant',
    reviewer: 'Reviewer',
    staff: 'Staff (check-in)',
    finance: 'Finance',
    admin: 'Admin',
    super_admin: 'Super Admin',
};

const ALL_ROLES = Object.keys(ROLE_LABELS) as UserRole[];

interface UserRow {
    id: number;
    name: string;
    full_name: string;
    email: string;
    role: UserRole;
    roles: UserRole[];
    email_verified: boolean;
}

interface PaginatedUsers {
    data: UserRow[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface UserSearchResponse {
    users: PaginatedUsers;
    /** Across all users, not just this page — see the controller. */
    super_admin_count: number;
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

interface UsersIndexProps {
    roleAccessChanges: RoleAccessChange[];
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

export default function UsersIndex({ roleAccessChanges }: UsersIndexProps) {
    const { auth } = usePage<SharedData>().props;
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState<UserRole | 'all'>('all');
    const [page, setPage] = useState(1);
    const [users, setUsers] = useState<PaginatedUsers | null>(null);
    const [superAdminCount, setSuperAdminCount] = useState(0);
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

        fetch(route('admin.users.search', params), { headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) throw new Error('Failed to load users');
                return response.json() as Promise<UserSearchResponse>;
            })
            .then((data) => {
                setUsers(data.users);
                setSuperAdminCount(data.super_admin_count);
            })
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

    const changeRoles = (user: UserRow, roles: UserRole[], primaryRole: UserRole) => {
        setActionError(null);
        setActionSuccess(null);
        setUpdatingId(user.id);

        router.patch(
            route('admin.users.update-roles', user.id),
            { roles, primary_role: primaryRole },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setActionSuccess(`${user.full_name}'s roles were updated.`);
                    fetchUsers();
                },
                onError: (errors) => setActionError(String(errors.roles ?? errors.primary_role ?? 'Roles could not be updated.')),
                onFinish: () => setUpdatingId(null),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users & Roles" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex items-start gap-4 border-b pb-5">
                    <IconTile tone="blue">
                        <UsersRound className="size-5" />
                    </IconTile>
                    <div>
                        <p className="text-xs font-bold tracking-[0.18em] text-[#135eeb] uppercase">Super admin</p>
                        <h1 className="mt-2 font-serif text-3xl font-semibold">Users &amp; Roles</h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Search every account and change what it can do. A user must have verified their email before receiving any role beyond
                            Participant.
                        </p>
                    </div>
                </header>

                <section className="dark:bg-card overflow-hidden rounded-2xl border border-[#135eeb]/10 bg-white p-6 shadow-[0_1px_2px_rgba(19,94,235,0.06)]">
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
                                    {ALL_ROLES.map((role) => (
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
                                                    <p className="truncate text-sm font-medium">{user.full_name}</p>
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
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={!users.next_page_url}
                                        onClick={() => setPage((p) => p + 1)}
                                    >
                                        Next
                                        <ChevronRight className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </section>

                <section className="dark:bg-card overflow-hidden rounded-2xl border border-[#135eeb]/10 bg-white p-6 shadow-[0_1px_2px_rgba(19,94,235,0.06)]">
                    <h2 className="text-lg font-semibold">Recent role changes</h2>
                    <p className="text-muted-foreground mt-0.5 text-sm">Every grant and removal is recorded permanently.</p>

                    {roleAccessChanges.length === 0 ? (
                        <p className="text-muted-foreground mt-4 text-sm">No role changes have been recorded yet.</p>
                    ) : (
                        <div className="mt-4 divide-y">
                            {roleAccessChanges.map((change) => (
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
        </AppLayout>
    );
}
