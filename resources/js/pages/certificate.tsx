import { DashboardCard, IconTile } from '@/components/dashboard-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Award, Clock3, Download, Info, ShieldCheck } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Certificate', href: '/certificate' }];

interface CertificateProps {
    window: { is_open: boolean; release_at: string | null; pending_message: string | null };
    /** Why they cannot download yet, or null when they can. */
    blockedReason: string | null;
    daysAttended: number;
    isPaid: boolean;
    certificateCode: string | null;
    conferenceName: string | null;
    conferenceYear: string | null;
}

function formatReleaseAt(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('en', { dateStyle: 'full', timeStyle: 'short' }).format(date);
}

export default function CertificatePage({
    window: releaseWindow,
    blockedReason,
    daysAttended,
    isPaid,
    certificateCode,
    conferenceName,
    conferenceYear,
}: CertificateProps) {
    const { flash } = usePage<SharedData & { flash: { error?: string } }>().props;
    const available = blockedReason === null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Certificate" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4 pb-10 md:p-6 md:pb-12">
                <section className="relative overflow-hidden rounded-[24px] bg-[#0d3fa8] px-6 py-9 text-white md:px-10">
                    <div className="absolute -top-24 -right-16 size-64 rounded-full bg-white/5" />
                    <div className="relative">
                        <p className="text-xs font-black tracking-[0.24em] text-[#8fd45a] uppercase">
                            {conferenceName}
                            {conferenceYear ? ` · ${conferenceYear}` : ''}
                        </p>
                        <h1 className="mt-3 font-serif text-3xl font-semibold">Certificate of participation</h1>
                        <p className="mt-2 max-w-xl text-sm leading-6 text-white/75">Issued to attendees whose arrival was recorded at the venue.</p>
                    </div>
                </section>

                {flash?.error && (
                    <div className="border-destructive/30 bg-destructive/5 text-destructive rounded-xl border p-4 text-sm font-semibold">
                        {flash.error}
                    </div>
                )}

                <DashboardCard>
                    <div className="flex items-start gap-4">
                        <IconTile tone={available ? 'green' : 'amber'}>
                            {available ? <Award className="size-5" /> : <Clock3 className="size-5" />}
                        </IconTile>
                        <div className="min-w-0 flex-1">
                            <h2 className="text-lg font-semibold">{available ? 'Your certificate is ready' : 'Not available yet'}</h2>

                            {available ? (
                                <>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        Attended {daysAttended} {daysAttended === 1 ? 'day' : 'days'} of the conference.
                                    </p>
                                    <Button asChild className="mt-5 h-11 bg-[#4c8a1f] px-6 font-bold hover:bg-[#3f751a]">
                                        <a href={route('certificate.download')}>
                                            <Download className="size-4" />
                                            Download certificate
                                        </a>
                                    </Button>
                                    {certificateCode && (
                                        <p className="text-muted-foreground mt-3 font-mono text-xs">Certificate No. {certificateCode}</p>
                                    )}
                                </>
                            ) : (
                                <p className="text-muted-foreground mt-1 text-sm leading-6">{blockedReason}</p>
                            )}
                        </div>
                    </div>
                </DashboardCard>

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatusTile label="Registration" value={isPaid ? 'Settled' : 'Not settled'} ok={isPaid} />
                    <StatusTile
                        label="Attendance"
                        value={daysAttended > 0 ? `${daysAttended} ${daysAttended === 1 ? 'day' : 'days'}` : 'Not recorded'}
                        ok={daysAttended > 0}
                    />
                    <StatusTile
                        label="Released"
                        value={releaseWindow.is_open ? 'Yes' : releaseWindow.release_at ? formatReleaseAt(releaseWindow.release_at) : 'Not set'}
                        ok={releaseWindow.is_open}
                    />
                </div>

                <div className="text-muted-foreground flex items-start gap-2.5 px-1 text-xs leading-5">
                    <ShieldCheck className="mt-0.5 size-3.5 shrink-0" />
                    <span>
                        Each certificate carries a QR code and a number that anyone can check against this site, so it can be verified without
                        contacting the organisers.
                    </span>
                </div>

                <div className="text-muted-foreground flex items-start gap-2.5 px-1 text-xs leading-5">
                    <Info className="mt-0.5 size-3.5 shrink-0" />
                    <span>
                        Attended but registered at the venue desk without an account? Certificates can also be claimed at{' '}
                        <a href={route('certificate.claim')} className="font-semibold underline underline-offset-2">
                            {route('certificate.claim')}
                        </a>
                        .
                    </span>
                </div>
            </div>
        </AppLayout>
    );
}

function StatusTile({ label, value, ok }: { label: string; value: string; ok: boolean }) {
    return (
        <div className="bg-card rounded-2xl border p-4">
            <div className="text-muted-foreground text-xs font-semibold">{label}</div>
            <div className={`mt-1 text-sm font-bold ${ok ? 'text-[#4c8a1f]' : 'text-muted-foreground'}`}>{value}</div>
        </div>
    );
}
