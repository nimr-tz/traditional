import { IconTile } from '@/components/dashboard-card';
import { StatusPill, type PillTone } from '@/components/status-pill';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';

type RecipientStatus = 'pending' | 'sent' | 'failed';

interface Campaign {
    id: number;
    message: string;
    audience_label: string;
    recipient_count: number;
    sent_count: number;
    failed_count: number;
    status: string;
    created_by_name: string;
    created_at: string;
    completed_at: string | null;
}

interface Recipient {
    id: number;
    name: string;
    phone: string;
    status: RecipientStatus;
    error: string | null;
    sent_at: string | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

const TONE: Record<RecipientStatus, PillTone> = {
    pending: 'neutral',
    sent: 'positive',
    failed: 'negative',
};

function formatDate(value: string | null): string {
    return value ? new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
}

export default function SmsCampaignShow({ campaign, recipients }: { campaign: Campaign; recipients: Paginated<Recipient> }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin' },
        { title: 'SMS Manager', href: '/admin/sms' },
        { title: campaign.message.slice(0, 40), href: route('admin.sms.show', campaign.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SMS campaign" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex items-start gap-4 border-b pb-5">
                    <IconTile tone="amber">
                        <MessageSquare className="size-5" />
                    </IconTile>
                    <div className="min-w-0">
                        <p className="text-xs font-bold tracking-[0.18em] text-[#4c8a1f] uppercase">{campaign.audience_label}</p>
                        <h1 className="mt-2 font-serif text-2xl font-semibold">SMS campaign</h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Sent by {campaign.created_by_name} · {formatDate(campaign.created_at)}
                            {campaign.completed_at && ` · completed ${formatDate(campaign.completed_at)}`}
                        </p>
                    </div>
                </header>

                <section className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    {[
                        { label: 'Recipients', value: campaign.recipient_count },
                        { label: 'Delivered', value: campaign.sent_count },
                        { label: 'Failed', value: campaign.failed_count },
                    ].map((tile) => (
                        <div key={tile.label} className="bg-card rounded-2xl border p-4">
                            <div className="text-muted-foreground text-xs font-semibold">{tile.label}</div>
                            <div className={cn('mt-2 text-3xl font-bold tabular-nums', tile.label === 'Failed' && tile.value > 0 && 'text-red-700')}>
                                {tile.value.toLocaleString()}
                            </div>
                        </div>
                    ))}
                </section>

                <section className="bg-card rounded-2xl border p-5">
                    <h2 className="font-serif text-lg font-semibold">Message</h2>
                    <div className="mt-3 text-[15px] leading-8 whitespace-pre-wrap text-slate-800 dark:text-slate-200">{campaign.message}</div>
                </section>

                <section className="bg-card overflow-hidden rounded-2xl border">
                    <div className="border-b p-5">
                        <h2 className="font-serif text-lg font-semibold">Recipients</h2>
                        <p className="text-muted-foreground mt-1 text-sm">Failures are listed first, with the reason delivery was refused.</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[560px] text-sm">
                            <thead className="bg-muted/40 text-left text-xs tracking-wide uppercase">
                                <tr>
                                    <th className="p-4">Name</th>
                                    <th className="p-4">Phone</th>
                                    <th className="p-4">Status</th>
                                    <th className="p-4">Sent at</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recipients.data.map((recipient) => (
                                    <tr key={recipient.id} className="hover:bg-muted/30">
                                        <td className="p-4 font-medium">{recipient.name}</td>
                                        <td className="text-muted-foreground p-4">{recipient.phone}</td>
                                        <td className="p-4">
                                            <StatusPill tone={TONE[recipient.status]}>{recipient.status}</StatusPill>
                                            {recipient.error && <div className="mt-1 max-w-md text-xs text-red-700">{recipient.error}</div>}
                                        </td>
                                        <td className="text-muted-foreground p-4 text-xs">{formatDate(recipient.sent_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                {recipients.links.length > 3 && (
                    <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                        {recipients.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={cn(
                                    'rounded-lg px-3 py-1.5 text-sm',
                                    link.active ? 'bg-[#4c8a1f] text-white' : 'text-muted-foreground hover:bg-muted',
                                    !link.url && 'pointer-events-none opacity-40',
                                )}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
