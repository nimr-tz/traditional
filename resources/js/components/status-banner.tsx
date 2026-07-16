import { cn } from '@/lib/utils';
import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

export type BannerTone = 'info' | 'success' | 'warning' | 'negative' | 'neutral';

const toneClasses: Record<BannerTone, { wrap: string; iconTile: string; noteBlock: string }> = {
    info: {
        wrap: 'border-[#135eeb]/15 bg-[linear-gradient(135deg,#eaf1ff_0%,#ffffff_75%)] dark:border-[#135eeb]/20 dark:bg-[linear-gradient(135deg,rgba(19,94,235,0.14)_0%,rgba(19,94,235,0.03)_100%)]',
        iconTile: 'bg-[#135eeb] text-white',
        noteBlock: 'border-[#135eeb]/30 bg-white/80 dark:bg-black/15',
    },
    success: {
        wrap: 'border-[#67b52f]/20 bg-[linear-gradient(135deg,#f3f9ee_0%,#ffffff_75%)] dark:border-[#67b52f]/25 dark:bg-[linear-gradient(135deg,rgba(103,181,47,0.14)_0%,rgba(103,181,47,0.03)_100%)]',
        iconTile: 'bg-[#4c8a1f] text-white',
        noteBlock: 'border-[#67b52f]/30 bg-white/80 dark:bg-black/15',
    },
    warning: {
        wrap: 'border-amber-300/40 bg-[linear-gradient(135deg,#fef6e6_0%,#ffffff_75%)] dark:border-amber-400/25 dark:bg-[linear-gradient(135deg,rgba(217,158,10,0.16)_0%,rgba(217,158,10,0.03)_100%)]',
        iconTile: 'bg-amber-500 text-white',
        noteBlock: 'border-amber-400/40 bg-white/80 dark:bg-black/15',
    },
    negative: {
        wrap: 'border-red-300/40 bg-[linear-gradient(135deg,#fdf0f0_0%,#ffffff_75%)] dark:border-red-400/25 dark:bg-[linear-gradient(135deg,rgba(220,38,38,0.14)_0%,rgba(220,38,38,0.03)_100%)]',
        iconTile: 'bg-red-600 text-white',
        noteBlock: 'border-red-300/40 bg-white/80 dark:bg-black/15',
    },
    neutral: {
        wrap: 'border-slate-200 bg-[linear-gradient(135deg,#f8fafc_0%,#ffffff_75%)] dark:border-slate-700 dark:bg-slate-900/40',
        iconTile: 'bg-slate-500 text-white dark:bg-slate-600',
        noteBlock: 'border-slate-300/60 bg-white/80 dark:bg-black/15',
    },
};

interface StatusBannerProps {
    tone: BannerTone;
    icon: LucideIcon;
    title: string;
    description?: ReactNode;
    note?: string | null;
    noteLabel?: string;
    action?: ReactNode;
    className?: string;
}

export function StatusBanner({ tone, icon: Icon, title, description, note, noteLabel, action, className }: StatusBannerProps) {
    const classes = toneClasses[tone];

    return (
        <div className={cn('overflow-hidden rounded-2xl border p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)] md:p-6', classes.wrap, className)}>
            <div className="flex items-start gap-4">
                <span className={cn('flex size-11 shrink-0 items-center justify-center rounded-xl shadow-sm', classes.iconTile)}>
                    <Icon className="size-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <h3 className="text-base leading-6 font-bold md:text-lg">{title}</h3>
                    {description && <div className="text-muted-foreground mt-1.5 text-sm leading-6">{description}</div>}
                    {note && (
                        <div className={cn('mt-4 rounded-xl border-l-4 p-4 text-sm leading-6', classes.noteBlock)}>
                            {noteLabel && <p className="mb-1.5 text-xs font-bold tracking-wide uppercase opacity-60">{noteLabel}</p>}
                            <p className="font-medium whitespace-pre-wrap">{note}</p>
                        </div>
                    )}
                    {action && <div className="mt-4">{action}</div>}
                </div>
            </div>
        </div>
    );
}
