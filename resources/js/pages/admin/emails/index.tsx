import { IconTile } from '@/components/dashboard-card';
import InputError from '@/components/input-error';
import { StatusPill, type PillTone } from '@/components/status-pill';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Mail, Send, Users } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Email Manager', href: '/admin/emails' },
];

type CampaignStatus = 'queued' | 'sending' | 'sent';

interface Campaign {
    id: number;
    subject: string;
    audience_label: string;
    recipient_count: number;
    sent_count: number;
    failed_count: number;
    status: CampaignStatus;
    created_by_name: string;
    created_at: string;
}

interface EmailsIndexProps {
    segments: Record<string, string>;
    parameterisedSegments: string[];
    audienceOptions: { countries: string[]; institutions: string[] };
    campaigns: Campaign[];
}

const STATUS_TONE: Record<CampaignStatus, PillTone> = {
    queued: 'neutral',
    sending: 'attention',
    sent: 'positive',
};

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

export default function EmailsIndex({ segments, parameterisedSegments, audienceOptions, campaigns }: EmailsIndexProps) {
    const form = useForm({ audience: 'all', audience_value: '', subject: '', body: '' });
    const [recipientCount, setRecipientCount] = useState<number | null>(null);
    const [countLoading, setCountLoading] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [testing, setTesting] = useState(false);

    const needsValue = parameterisedSegments.includes(form.data.audience);
    const valueOptions = form.data.audience === 'by_country' ? audienceOptions.countries : audienceOptions.institutions;

    // The count is the only thing standing between a mistyped segment and a
    // real send, so it refreshes on every audience change before sending.
    useEffect(() => {
        if (needsValue && !form.data.audience_value) {
            setRecipientCount(null);
            return;
        }

        setCountLoading(true);
        const params: Record<string, string> = { audience: form.data.audience };
        if (form.data.audience_value) params.audience_value = form.data.audience_value;

        fetch(route('admin.emails.count', params), { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((d) => setRecipientCount(d.count))
            .catch(() => setRecipientCount(null))
            .finally(() => setCountLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.audience, form.data.audience_value]);

    const canSend = Boolean(form.data.subject.trim() && form.data.body.trim() && recipientCount && recipientCount > 0);

    const send: FormEventHandler = (event) => {
        event.preventDefault();
        setConfirming(true);
    };

    const confirmSend = () => {
        form.post(route('admin.emails.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('subject', 'body');
                setConfirming(false);
            },
            onError: () => setConfirming(false),
        });
    };

    const sendTest = () => {
        setTesting(true);
        router.post(
            route('admin.emails.test'),
            { subject: form.data.subject, body: form.data.body },
            { preserveScroll: true, onFinish: () => setTesting(false) },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Email Manager" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex items-start gap-4 border-b pb-5">
                    <IconTile tone="blue">
                        <Mail className="size-5" />
                    </IconTile>
                    <div>
                        <p className="text-xs font-bold tracking-[0.18em] text-[#4c8a1f] uppercase">Conference operations</p>
                        <h1 className="mt-2 font-serif text-3xl font-semibold">Email Manager</h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Send an announcement to a group of registrants. Automatic emails — registration, control numbers, payment, abstract
                            decisions — keep sending on their own and are not affected by anything here.
                        </p>
                    </div>
                </header>

                <form onSubmit={send} className="bg-card rounded-2xl border p-5">
                    <h2 className="font-serif text-lg font-semibold">Compose</h2>

                    <div className="mt-5 grid gap-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="audience">Audience</Label>
                                <Select
                                    value={form.data.audience}
                                    onValueChange={(value) => form.setData({ ...form.data, audience: value, audience_value: '' })}
                                >
                                    <SelectTrigger id="audience">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(segments).map(([key, label]) => (
                                            <SelectItem key={key} value={key}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.audience} />
                            </div>

                            {needsValue && (
                                <div className="grid gap-2">
                                    <Label htmlFor="audience_value">{form.data.audience === 'by_country' ? 'Country' : 'Institution'}</Label>
                                    <Select value={form.data.audience_value} onValueChange={(value) => form.setData('audience_value', value)}>
                                        <SelectTrigger id="audience_value">
                                            <SelectValue placeholder="Select one" />
                                        </SelectTrigger>
                                        <SelectContent className="max-h-72">
                                            {valueOptions.map((option) => (
                                                <SelectItem key={option} value={option}>
                                                    {option}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={form.errors.audience_value} />
                                </div>
                            )}
                        </div>

                        <div className="flex items-center gap-2 rounded-xl bg-[#eaf1ff] px-4 py-3 text-sm text-[#0f2350] dark:bg-[#135eeb]/15 dark:text-[#a9c6ff]">
                            <Users className="size-4 shrink-0" />
                            {countLoading ? (
                                <span>Counting recipients…</span>
                            ) : recipientCount === null ? (
                                <span>Choose an audience to see how many people it reaches.</span>
                            ) : (
                                <span>
                                    This will reach <strong>{recipientCount.toLocaleString()}</strong> recipient
                                    {recipientCount === 1 ? '' : 's'}.
                                </span>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="subject">Subject</Label>
                            <Input
                                id="subject"
                                value={form.data.subject}
                                onChange={(e) => form.setData('subject', e.target.value)}
                                placeholder="e.g. Payment deadline reminder"
                            />
                            <InputError message={form.errors.subject} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="body">Message</Label>
                            <Textarea
                                id="body"
                                rows={10}
                                value={form.data.body}
                                onChange={(e) => form.setData('body', e.target.value)}
                                placeholder={
                                    'Write your message in plain text.\n\nLeave a blank line between paragraphs — each becomes its own paragraph in the email.'
                                }
                            />
                            <p className="text-muted-foreground text-xs leading-5">
                                Plain text only. Each recipient is greeted by name, and the conference header and sign-off are added automatically.
                            </p>
                            <InputError message={form.errors.body} />
                        </div>

                        <div className="flex flex-wrap items-center gap-3 border-t pt-4">
                            <Button type="submit" disabled={!canSend || form.processing}>
                                <Send className="size-4" />
                                Send to {recipientCount?.toLocaleString() ?? '—'} recipients
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={!form.data.subject.trim() || !form.data.body.trim() || testing}
                                onClick={sendTest}
                            >
                                {testing && <LoaderCircle className="size-4 animate-spin" />}
                                Send test to myself
                            </Button>
                        </div>
                    </div>
                </form>

                <section className="bg-card overflow-hidden rounded-2xl border">
                    <div className="border-b p-5">
                        <h2 className="font-serif text-lg font-semibold">Sent campaigns</h2>
                        <p className="text-muted-foreground mt-1 text-sm">Every send is recorded, including who it went to and what failed.</p>
                    </div>

                    {campaigns.length === 0 ? (
                        <p className="text-muted-foreground p-8 text-center text-sm">No campaigns have been sent yet.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[760px] text-sm">
                                <thead className="bg-muted/40 text-left text-xs tracking-wide uppercase">
                                    <tr>
                                        <th className="p-4">Subject</th>
                                        <th className="p-4">Audience</th>
                                        <th className="p-4 text-right">Recipients</th>
                                        <th className="p-4 text-right">Sent</th>
                                        <th className="p-4 text-right">Failed</th>
                                        <th className="p-4">Status</th>
                                        <th className="p-4">Sent by</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {campaigns.map((campaign) => (
                                        <tr key={campaign.id} className="hover:bg-muted/30">
                                            <td className="p-4">
                                                <Link href={route('admin.emails.show', campaign.id)} className="font-semibold hover:underline">
                                                    {campaign.subject}
                                                </Link>
                                                <div className="text-muted-foreground mt-0.5 text-xs">{formatDate(campaign.created_at)}</div>
                                            </td>
                                            <td className="text-muted-foreground p-4 text-xs">{campaign.audience_label}</td>
                                            <td className="p-4 text-right tabular-nums">{campaign.recipient_count}</td>
                                            <td className="p-4 text-right tabular-nums">{campaign.sent_count}</td>
                                            <td className="p-4 text-right tabular-nums">
                                                {campaign.failed_count > 0 ? (
                                                    <span className="font-semibold text-red-700">{campaign.failed_count}</span>
                                                ) : (
                                                    <span className="text-muted-foreground">0</span>
                                                )}
                                            </td>
                                            <td className="p-4">
                                                <StatusPill tone={STATUS_TONE[campaign.status]}>{campaign.status}</StatusPill>
                                            </td>
                                            <td className="text-muted-foreground p-4 text-xs">{campaign.created_by_name}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <Dialog open={confirming} onOpenChange={setConfirming}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Send this email?</DialogTitle>
                            <DialogDescription>
                                &ldquo;{form.data.subject}&rdquo; will be sent to <strong>{recipientCount?.toLocaleString()}</strong> recipient
                                {recipientCount === 1 ? '' : 's'} ({segments[form.data.audience]}
                                {form.data.audience_value ? `: ${form.data.audience_value}` : ''}). This cannot be undone or recalled.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="mt-4">
                            <Button type="button" variant="outline" onClick={() => setConfirming(false)}>
                                Cancel
                            </Button>
                            <Button type="button" onClick={confirmSend} disabled={form.processing}>
                                {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                                Send now
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
