import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { RoleSwitcher } from '@/components/role-switcher';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    ChartColumn,
    ClipboardList,
    GraduationCap,
    LayoutGrid,
    Mail,
    ScrollText,
    Settings,
    Users,
    UsersRound,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';

const registrantNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
    { title: 'Payment', url: '/payment', icon: Wallet },
    { title: 'My Abstracts', url: '/abstracts', icon: ScrollText },
];

// Staff only use the separate check-in app at the venue — the web app has
// nothing else for them, so keep this to a plain landing page.
const staffNavItems: NavItem[] = [{ title: 'Dashboard', url: '/dashboard', icon: LayoutGrid }];

const reviewerNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/admin', icon: BadgeCheck },
    { title: 'Abstracts Review', url: '/admin/abstracts', icon: ScrollText },
];

const financeNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/admin/finance', icon: Wallet },
    { title: 'Payment Ledger', url: '/admin/finance/payments', icon: ClipboardList },
];

// Finance is its own separate role/panel — kept out of the admin nav on purpose,
// even though admin/super_admin can still reach it directly by URL if needed.
const adminNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/admin', icon: BadgeCheck },
    { title: 'Management Report', url: '/admin/management', icon: ChartColumn },
    { title: 'Registrations', url: '/admin/registrations', icon: ClipboardList },
    { title: 'Email Manager', url: '/admin/emails', icon: Mail },
    { title: 'Student Verification', url: '/admin/students', icon: GraduationCap },
    { title: 'Abstracts Review', url: '/admin/abstracts', icon: ScrollText },
    { title: 'Reviewer Assignments', url: '/admin/assignments', icon: Users },
];

// Super admins are implicitly allowed everywhere (see EnsureUserHasRole), so
// this list is about what they can *find*, not what they may do. Abstracts are
// linked here because a super admin is an eligible reviewer and makes final
// decisions — leaving the link out made the whole review side of the app
// reachable only by typing the URL.
const superAdminNavItems: NavItem[] = [
    { title: 'Management Report', url: '/admin/management', icon: ChartColumn },
    { title: 'Registrations', url: '/admin/registrations', icon: ClipboardList },
    { title: 'Email Manager', url: '/admin/emails', icon: Mail },
    { title: 'Abstracts Review', url: '/admin/abstracts', icon: ScrollText },
    { title: 'Reviewer Assignments', url: '/admin/assignments', icon: Users },
    { title: 'Users & Roles', url: '/admin/users', icon: UsersRound },
    { title: 'Conference Settings', url: '/admin/settings', icon: Settings },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    // Nav/home follow the user's active (currently viewed) role, not
    // necessarily their primary one — see RoleSwitcher for how it's chosen.
    const role = auth.user.active_role ?? auth.user.role;
    const isSuperAdmin = role === 'super_admin';
    const isAdmin = role === 'admin';
    const isReviewer = role === 'reviewer';
    const isFinance = role === 'finance';
    const isStaff = role === 'staff';
    const homeUrl = isSuperAdmin ? '/admin/settings' : isAdmin || isReviewer ? '/admin' : isFinance ? '/admin/finance' : '/dashboard';
    const navItems = isSuperAdmin
        ? superAdminNavItems
        : isAdmin
          ? adminNavItems
          : isReviewer
            ? reviewerNavItems
            : isFinance
              ? financeNavItems
              : isStaff
                ? staffNavItems
                : registrantNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" className="h-16" asChild>
                            <Link href={homeUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <RoleSwitcher />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
