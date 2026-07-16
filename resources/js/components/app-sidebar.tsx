import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BadgeCheck, ClipboardList, GraduationCap, LayoutGrid, ScrollText, Settings, Wallet } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
    { title: 'Payment', url: '/payment', icon: Wallet },
    { title: 'My Abstracts', url: '/abstracts', icon: ScrollText },
];

const adminNavItems: NavItem[] = [
    { title: 'Admin Dashboard', url: '/admin', icon: BadgeCheck },
    { title: 'Registrations', url: '/admin/registrations', icon: ClipboardList },
    { title: 'Student Verification', url: '/admin/students', icon: GraduationCap },
    { title: 'Abstracts Review', url: '/admin/abstracts', icon: ScrollText },
    { title: 'Conference Settings', url: '/admin/settings', icon: Settings },
];

const reviewerNavItems: NavItem[] = [
    { title: 'Admin Dashboard', url: '/admin', icon: BadgeCheck },
    { title: 'Abstracts Review', url: '/admin/abstracts', icon: ScrollText },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const role = auth.user.role;
    const isAdmin = role === 'admin' || role === 'super_admin';
    const isReviewer = role === 'reviewer';
    const homeUrl = isAdmin || isReviewer ? '/admin' : '/dashboard';
    const navItems = isAdmin ? adminNavItems : isReviewer ? reviewerNavItems : mainNavItems;

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
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
