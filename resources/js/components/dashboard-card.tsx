import { cn } from '@/lib/utils';

export function DashboardCard({ className, children }: { className?: string; children: React.ReactNode }) {
    return (
        <div className={cn('dark:bg-card rounded-2xl border border-[#135eeb]/10 bg-white p-7 shadow-[0_1px_2px_rgba(19,94,235,0.06)]', className)}>
            {children}
        </div>
    );
}

export function IconTile({ tone, children }: { tone: 'blue' | 'green'; children: React.ReactNode }) {
    return (
        <div
            className={cn(
                'flex h-[42px] w-[42px] items-center justify-center rounded-[11px]',
                tone === 'blue' && 'bg-[#eaf1ff] text-[#135eeb] dark:bg-[#135eeb]/15 dark:text-[#6ea1ff]',
                tone === 'green' && 'bg-[#eef7e6] text-[#67b52f] dark:bg-[#67b52f]/15 dark:text-[#8fd45a]',
            )}
        >
            {children}
        </div>
    );
}
