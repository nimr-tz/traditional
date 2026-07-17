import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type SharedData, type UserRole } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, Repeat } from 'lucide-react';

const ROLE_LABELS: Record<UserRole, string> = {
    user: 'Participant',
    reviewer: 'Reviewer',
    staff: 'Staff (check-in)',
    finance: 'Finance',
    admin: 'Admin',
    super_admin: 'Super Admin',
};

/**
 * Shown only for users holding more than one role. Switching is cosmetic —
 * it changes which role's nav/dashboard is displayed, not what the account
 * is authorized to reach; backend access is always the union of all
 * assigned roles regardless of which one is active here.
 */
export function RoleSwitcher() {
    const { auth } = usePage<SharedData>().props;
    const roles = auth.user.roles?.length ? auth.user.roles : [auth.user.role];
    const activeRole = auth.user.active_role ?? auth.user.role;

    if (roles.length < 2) {
        return null;
    }

    const switchTo = (role: UserRole) => {
        if (role === activeRole) return;
        router.patch(route('active-role.update'), { role });
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton size="sm" tooltip="Switch view">
                            <Repeat className="size-4" />
                            <span>Viewing as {ROLE_LABELS[activeRole]}</span>
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-56">
                        <DropdownMenuLabel>Switch view</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {roles.map((role) => (
                            <DropdownMenuItem key={role} className="flex items-center gap-2" onSelect={() => switchTo(role)}>
                                <Check className={`size-4 ${role === activeRole ? 'opacity-100' : 'opacity-0'}`} />
                                <span className={role === activeRole ? 'font-semibold' : ''}>{ROLE_LABELS[role]}</span>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
