import { AuthPanel } from '@/components/auth-panel';
import { applyTheme } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { useEffect } from 'react';
import nimrLogo from '../../../images/nimr-logo-128.webp';

interface AuthSplitPanelLayoutProps {
    children: React.ReactNode;
    contentClassName?: string;
}

export default function AuthSplitPanelLayout({ children, contentClassName }: AuthSplitPanelLayoutProps) {
    // This layout's warm, serif-accented look is a deliberate light-only brand
    // treatment for the public registration/auth flow, not the app's themed
    // dashboard — its colors are hardcoded to the light palette throughout.
    // Shared components (Input, Select, ...) read the shadcn CSS variables
    // though, which flip dark the moment `<html>` carries the app-wide `dark`
    // class, and Radix's Select dropdown portals straight to <body>, outside
    // this component's own DOM subtree — so overriding CSS variables locally
    // can't reach it. Keeping `dark` off `<html>` for as long as this layout
    // is mounted is the only thing that reaches both. A MutationObserver
    // guards against the system-theme-change listener in use-appearance.tsx
    // re-adding it while an auth page is open; unmounting restores whatever
    // the user's saved appearance actually is.
    useEffect(() => {
        const html = document.documentElement;
        html.classList.remove('dark');

        const observer = new MutationObserver(() => {
            if (html.classList.contains('dark')) html.classList.remove('dark');
        });
        observer.observe(html, { attributes: true, attributeFilter: ['class'] });

        return () => {
            observer.disconnect();
            applyTheme((localStorage.getItem('appearance') as 'light' | 'dark' | 'system') || 'system');
        };
    }, []);

    return (
        <div
            className="flex min-h-svh text-[hsl(20,12%,14%)]"
            style={{ backgroundColor: 'hsl(40,33%,97%)', fontFamily: "'Work Sans', ui-sans-serif, system-ui, sans-serif" }}
        >
            <div className="hidden shrink-0 lg:flex lg:w-[440px]">
                <AuthPanel />
            </div>
            <div className="flex flex-1 flex-col">
                <Link href={route('home')} className="flex items-center justify-center gap-2 py-6 text-[#135eeb] no-underline lg:hidden">
                    <img src={nimrLogo} alt="NIMR" className="size-8 rounded-full object-contain" />
                    <span className="font-serif text-base font-semibold">TMSC</span>
                </Link>
                <div className="flex flex-1 items-center justify-center px-6 pb-12 sm:px-8">
                    <div className={cn('flex w-full max-w-[400px] flex-col gap-7', contentClassName)}>{children}</div>
                </div>
            </div>
        </div>
    );
}
