import { StatusPill, type PillTone } from '@/components/status-pill';
import { cn, formatPersonName } from '@/lib/utils';
import { Printer } from 'lucide-react';

/**
 * How a registrant appears to venue staff, shared by the desk and the full
 * directory so the two can never disagree about who may walk in.
 */
export interface RegistrantStanding {
    name: string;
    /** Absent for callers that never select it — falls back to the bare name. */
    salutation?: string | null;
    is_paid: boolean;
    fee_amount: string | null;
    currency: string | null;
    registration_code: string | null;
    /** Today's scan, if any — attendance is recorded once per person per day. */
    checked_in_at: string | null;
    days_attended: number;
    last_seen_at: string | null;
}

export function formatAmount(amount: string | null, currency: string | null): string | null {
    if (!amount || !currency) return null;

    const value = Number(amount);

    return `${currency} ${Number.isNaN(value) ? amount : value.toLocaleString()}`;
}

export function formatTime(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('en', { hour: '2-digit', minute: '2-digit' }).format(date);
}

export function formatDateTime(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(date);
}

/** The one line telling a door person what to do with whoever is in front of them. */
export function standingOf(person: RegistrantStanding): { label: string; tone: PillTone; detail: string } {
    if (person.checked_in_at) {
        const days = person.days_attended > 1 ? ` · ${person.days_attended} days attended` : '';

        return { label: 'In today', tone: 'positive', detail: `Scanned ${formatTime(person.checked_in_at)}${days}` };
    }

    if (person.is_paid) {
        // Attended before but not yet today — a returning attendee, and they can
        // be scanned again.
        const returning = person.days_attended > 0;

        return {
            label: returning ? 'Returning — not yet today' : 'Ready to scan',
            tone: 'neutral',
            detail: returning
                ? `Last seen ${person.last_seen_at ? formatDateTime(person.last_seen_at) : 'earlier'} · ${person.days_attended} day${person.days_attended === 1 ? '' : 's'}`
                : person.registration_code
                  ? `Badge ${person.registration_code}`
                  : 'Paid — badge not printed yet',
        };
    }

    const owed = formatAmount(person.fee_amount, person.currency);

    return {
        label: 'Unpaid — cannot enter',
        tone: 'attention',
        detail: owed ? `${owed} outstanding` : 'No payment recorded',
    };
}

export function StandingBadge({ person, className }: { person: RegistrantStanding; className?: string }) {
    const state = standingOf(person);

    return (
        <div className={cn('text-right', className)}>
            <StatusPill tone={state.tone}>{state.label}</StatusPill>
            <p className="text-muted-foreground mt-1.5 text-xs">{state.detail}</p>
        </div>
    );
}

/**
 * Reprints are allowed — people lose badges and a desk that cannot reissue just
 * creates a queue — but never silently. The count is shown, and a reprint has
 * to be confirmed.
 */
export function PrintBadgeButton({ person, printRoute }: { person: { id: number; name: string; badges_printed: number }; printRoute: string }) {
    const reprint = person.badges_printed > 0;

    const confirmReprint = (event: React.MouseEvent<HTMLAnchorElement>) => {
        if (!reprint) return;

        const already = `${person.badges_printed} badge${person.badges_printed === 1 ? ' has' : 's have'}`;

        if (
            !window.confirm(
                `${already} already been printed for ${formatPersonName(person)}. Printing another means more than one badge is in circulation for them. Print a replacement anyway?`,
            )
        ) {
            event.preventDefault();
        }
    };

    return (
        <a
            href={printRoute}
            target="_blank"
            rel="noreferrer"
            onClick={confirmReprint}
            className={cn(
                'inline-flex h-8 items-center gap-2 rounded-md px-3 text-xs font-bold transition',
                reprint
                    ? 'border border-amber-300 text-amber-800 hover:bg-amber-50 dark:text-amber-400'
                    : 'bg-[#135eeb] text-white hover:bg-[#0d4fca]',
            )}
        >
            <Printer className="size-4" />
            {reprint ? `Reprint (${person.badges_printed})` : 'Print badge'}
        </a>
    );
}
