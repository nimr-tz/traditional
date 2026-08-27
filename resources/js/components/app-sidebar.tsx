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
    MessageSquare,
    Presentation,
    ScanLine,
    ScrollText,
    Settings,
    Users,
    UsersRound,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';

const registrantNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
    { title: 'My Abstracts', url: '/abstracts', icon: ScrollText },
    { title: 'Presentations', url: '/presentations', icon: Presentation },
    { title: 'Payment', url: '/payment', icon: Wallet },
];

// Scanning happens in the check-in app; the web side gives staff the live
// picture of arrivals. Pointing them at /dashboard used to land them on the
// registrant page, which asked them to pay a fee and submit an abstract.
const staffNavItems: NavItem[] = [
    { title: 'Check-in', url: '/staff', icon: ScanLine },
    { title: 'Attendance', url: '/staff/attendance', icon: ClipboardList },
    { title: 'All registrants', url: '/staff/registrants', icon: Users },
];

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
    { title: 'SMS Manager', url: '/admin/sms', icon: MessageSquare },
    { title: 'Student Verification', url: '/admin/students', icon: GraduationCap },
    { title: 'Abstracts Review', url: '/admin/abstracts', icon: ScrollText },
    { title: 'Reviewer Assignments', url: '/admin/assignments', icon: Users },
    { title: 'Presentations', url: '/admin/presentations', icon: Presentation },
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
    { title: 'SMS Manager', url: '/admin/sms', icon: MessageSquare },
    { title: 'Abstracts Review', url: '/admin/abstracts', icon: ScrollText },
    { title: 'Reviewer Assignments', url: '/admin/assignments', icon: Users },
    { title: 'Presentations', url: '/admin/presentations', icon: Presentation },
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
    const homeUrl = isSuperAdmin
        ? '/admin/settings'
        : isAdmin || isReviewer
          ? '/admin'
          : isFinance
            ? '/admin/finance'
            : isStaff
              ? '/staff'
              : '/dashboard';
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
