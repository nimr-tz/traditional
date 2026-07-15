import { cn } from '@/lib/utils';

export type PillTone = 'positive' | 'neutral' | 'attention' | 'negative';

const pillToneClasses: Record<PillTone, string> = {
    positive: 'bg-[#eef7e6] text-[#4c8a1f] dark:bg-[#67b52f]/15 dark:text-[#8fd45a]',
    neutral: 'bg-muted text-muted-foreground',
    attention: 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
    negative: 'bg-destructive/10 text-destructive',
};

export function StatusPill({ tone, className, children }: { tone: PillTone; className?: string; children: React.ReactNode }) {
    return (
        <span className={cn('inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold', pillToneClasses[tone], className)}>
            {children}
        </span>
    );
}
