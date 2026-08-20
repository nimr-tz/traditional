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
import { fixTypography, hasFixableTypography, smsCost } from '@/lib/sms';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, LoaderCircle, MessageSquare, Send, UserRound, Users, WandSparkles } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'SMS Manager', href: '/admin/sms' },
];

const MESSAGE_MAX = 480;
const NAME_PLACEHOLDER = ':name';

/** Stand-in used for pricing and preview before an audience has been chosen. */
const SAMPLE_NAME = 'Asha';

type CampaignStatus = 'queued' | 'sending' | 'sent';

interface Campaign {
    id: number;
    message: string;
    audience_label: string;
    recipient_count: number;
    sent_count: number;
    failed_count: number;
    status: CampaignStatus;
    created_by_name: string;
    created_at: string;
}

interface SmsIndexProps {
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

export default function SmsIndex({ segments, parameterisedSegments, audienceOptions, campaigns }: SmsIndexProps) {
    const form = useForm({ audience: 'all', audience_value: '', message: '' });
    const [recipientCount, setRecipientCount] = useState<number | null>(null);
    const [countLoading, setCountLoading] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [testing, setTesting] = useState(false);
    const [testPhone, setTestPhone] = useState('');
    const [previewing, setPreviewing] = useState(false);
    const [longestName, setLongestName] = useState('');

    const needsValue = parameterisedSegments.includes(form.data.audience);
    const valueOptions = form.data.audience === 'by_country' ? audienceOptions.countries : audienceOptions.institutions;

    const usesName = form.data.message.includes(NAME_PLACEHOLDER);
    // Priced against the longest name in the audience, not a typical one: every
    // recipient is billed, so the message has to fit for the worst case.
    const pricingName = longestName || SAMPLE_NAME;
    const pricedMessage = form.data.message.replaceAll(NAME_PLACEHOLDER, pricingName);
    const previewMessage = form.data.message.replaceAll(NAME_PLACEHOLDER, SAMPLE_NAME);
    const cost = smsCost(pricedMessage);
    const parts = cost.parts;
    const canFixTypography = hasFixableTypography(form.data.message);

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

        fetch(route('admin.sms.count', params), { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((d) => {
                setRecipientCount(d.count);
                setLongestName(d.longest_name ?? '');
            })
            .catch(() => setRecipientCount(null))
            .finally(() => setCountLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.audience, form.data.audience_value]);

    const canSend = Boolean(form.data.message.trim() && recipientCount && recipientCount > 0);

    const send: FormEventHandler = (event) => {
        event.preventDefault();
        setConfirming(true);
    };

    const confirmSend = () => {
        form.post(route('admin.sms.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('message');
                setConfirming(false);
            },
            onError: () => setConfirming(false),
        });
    };

    const sendTest = () => {
        setTesting(true);
        router.post(
            route('admin.sms.test'),
            { message: form.data.message, phone: testPhone || undefined },
            { preserveScroll: true, onFinish: () => setTesting(false) },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SMS Manager" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 pb-10 md:p-6">
                <header className="flex items-start gap-4 border-b pb-5">
                    <IconTile tone="amber">
                        <MessageSquare className="size-5" />
                    </IconTile>
                    <div>
                        <p className="text-xs font-bold tracking-[0.18em] text-[#4c8a1f] uppercase">Conference operations</p>
                        <h1 className="mt-2 font-serif text-3xl font-semibold">SMS Manager</h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Send a text announcement to a group of registrants. Automatic texts — registration, control numbers, payment, abstract
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
                                    {recipientCount === 1 ? '' : 's'} with a usable Tanzanian mobile number.
                                </span>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between gap-3">
                                <Label htmlFor="message">Message</Label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 text-xs"
                                    onClick={() => form.setData('message', `${form.data.message}${NAME_PLACEHOLDER}`)}
                                >
                                    <UserRound className="size-3.5" />
                                    Insert recipient name
                                </Button>
                            </div>
                            <Textarea
                                id="message"
                                rows={6}
                                maxLength={MESSAGE_MAX}
                                value={form.data.message}
                                onChange={(e) => form.setData('message', e.target.value)}
                                placeholder="Write your text message in plain text. Type :name to insert each recipient's first name."
                            />
                            <div className="text-muted-foreground flex flex-wrap justify-between gap-x-4 gap-y-1 text-xs">
                                <span>
                                    {usesName ? (
                                        <>
                                            <code className="bg-muted rounded px-1 py-0.5">:name</code> becomes each recipient's first name.
                                        </>
                                    ) : (
                                        'Plain text only, sent exactly as written.'
                                    )}
                                </span>
                                <span>
                                    {form.data.message.length}/{MESSAGE_MAX} characters
                                    {parts > 0 && ` · ${parts} SMS part${parts === 1 ? '' : 's'}`}
                                    {parts > 0 && cost.encoding === 'GSM-7' && ` · ${cost.remaining} left in this part`}
                                </span>
                            </div>

                            {usesName && longestName && (
                                <p className="text-muted-foreground text-xs">
                                    Priced with the longest name in this audience ({longestName}), so the count holds for everyone.
                                </p>
                            )}

                            {cost.encoding === 'UCS-2' && (
                                <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                                    <p className="font-semibold">
                                        This message costs {parts} part{parts === 1 ? '' : 's'} instead of{' '}
                                        {smsCost(fixTypography(pricedMessage)).parts}.
                                    </p>
                                    <p className="mt-1">
                                        Text messages fit 160 plain characters per part, but only 70 once they contain a character outside the basic
                                        set — here {cost.offenders.map((c) => `"${c}"`).join(', ')}. Every recipient is billed for the extra parts.
                                    </p>
                                    {canFixTypography && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="mt-2 h-7 border-amber-300 bg-white text-xs hover:bg-amber-100"
                                            onClick={() => form.setData('message', fixTypography(form.data.message))}
                                        >
                                            <WandSparkles className="size-3.5" />
                                            Replace with plain equivalents
                                        </Button>
                                    )}
                                </div>
                            )}

                            <InputError message={form.errors.message} />
                        </div>

                        <div className="flex flex-wrap items-end gap-3 border-t pt-4">
                            <Button type="submit" disabled={!canSend || form.processing}>
                                <Send className="size-4" />
                                Send to {recipientCount?.toLocaleString() ?? '—'} recipients
                            </Button>
                            <Button type="button" variant="outline" disabled={!form.data.message.trim()} onClick={() => setPreviewing(true)}>
                                <Eye className="size-4" />
                                Preview
                            </Button>
                            <div className="flex flex-wrap items-end gap-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="test_phone" className="text-muted-foreground text-xs font-normal">
                                        Test recipient (optional)
                                    </Label>
                                    <Input
                                        id="test_phone"
                                        type="tel"
                                        value={testPhone}
                                        onChange={(e) => setTestPhone(e.target.value)}
                                        placeholder="0712345678"
                                        className="w-full sm:w-44"
                                    />
                                </div>
                                <Button type="button" variant="outline" disabled={!form.data.message.trim() || testing} onClick={sendTest}>
                                    {testing && <LoaderCircle className="size-4 animate-spin" />}
                                    Send test
                                </Button>
                            </div>
                        </div>
                        <p className="text-muted-foreground -mt-2 text-xs">
                            Leave the test recipient blank to send it to your own number, if one is on file.
                        </p>
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
                                        <th className="p-4">Message</th>
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
                                            <td className="max-w-xs p-4">
                                                <Link
                                                    href={route('admin.sms.show', campaign.id)}
                                                    className="line-clamp-1 font-semibold hover:underline"
                                                >
                                                    {campaign.message}
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
                            <DialogTitle>Send this SMS?</DialogTitle>
                            <DialogDescription>
                                This message will be sent to <strong>{recipientCount?.toLocaleString()}</strong> recipient
                                {recipientCount === 1 ? '' : 's'} ({segments[form.data.audience]}
                                {form.data.audience_value ? `: ${form.data.audience_value}` : ''}) as {parts} SMS part
                                {parts === 1 ? '' : 's'} each. This cannot be undone or recalled.
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

                <Dialog open={previewing} onOpenChange={setPreviewing}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Preview</DialogTitle>
                            <DialogDescription>
                                {usesName
                                    ? `Shown for a recipient named ${SAMPLE_NAME} — ${parts} SMS part${parts === 1 ? '' : 's'}.`
                                    : `Sent exactly as written, with no greeting or signature added — ${parts} SMS part${parts === 1 ? '' : 's'}.`}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="bg-muted/40 rounded-2xl p-4">
                            <div className="ml-auto max-w-[80%] rounded-2xl rounded-tr-sm bg-[#135eeb] px-4 py-2.5 text-sm whitespace-pre-wrap text-white">
                                {previewMessage}
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
