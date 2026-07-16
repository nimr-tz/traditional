import { AuthPanel } from '@/components/auth-panel';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import nimrLogo from '../../../images/nimr-logo-128.webp';

interface AuthSplitPanelLayoutProps {
    children: React.ReactNode;
    contentClassName?: string;
}

export default function AuthSplitPanelLayout({ children, contentClassName }: AuthSplitPanelLayoutProps) {
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
