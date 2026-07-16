import { DashboardCard } from '@/components/dashboard-card';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { LucideIcon, Palette, ShieldCheck, UserRound } from 'lucide-react';

const sidebarNavItems: { title: string; url: string; icon: LucideIcon }[] = [
    { title: 'Profile', url: '/settings/profile', icon: UserRound },
    { title: 'Password', url: '/settings/password', icon: ShieldCheck },
    { title: 'Appearance', url: '/settings/appearance', icon: Palette },
];

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
    const currentPath = window.location.pathname;

    return (
        <div className="mx-auto w-full max-w-5xl p-4 md:p-6">
            <div className="mb-8">
                <p className="text-xs font-semibold tracking-widest text-[#135eeb] uppercase">Account</p>
                <h1 className="mt-2 font-serif text-3xl font-semibold">Settings</h1>
                <p className="text-muted-foreground mt-2 text-sm">Manage your profile, password, and appearance.</p>
            </div>

            <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                <DashboardCard className="w-full p-3 lg:w-56 lg:shrink-0">
                    <nav className="flex gap-1 lg:flex-col">
                        {sidebarNavItems.map((item) => {
                            const isActive = currentPath === item.url;

                            return (
                                <Link
                                    key={item.url}
                                    href={item.url}
                                    prefetch
                                    className={cn(
                                        'flex flex-1 items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition-colors lg:flex-none',
                                        isActive
                                            ? 'bg-[#eef7e6] text-[#4c8a1f] dark:bg-[#67b52f]/15 dark:text-[#8fd45a]'
                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                    )}
                                >
                                    <item.icon className="size-4 shrink-0" />
                                    <span className="truncate">{item.title}</span>
                                </Link>
                            );
                        })}
                    </nav>
                </DashboardCard>

                <div className="min-w-0 flex-1 space-y-6">{children}</div>
            </div>
        </div>
    );
}
