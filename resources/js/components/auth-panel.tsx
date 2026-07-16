import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import nimrLogo from '../../images/nimr-logo-128.webp';
import heroPhoto768 from '../../images/traditional-medicine-hero-768.webp';

function formatDate(value: string | null | undefined): string | null {
    if (!value) return null;

    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('en', { day: 'numeric', month: 'long', year: 'numeric' }).format(date);
}

function highlightTitle(name: string) {
    const parts = name.split(/(Traditional|Conference)/g);

    return parts.map((part, i) =>
        part === 'Traditional' || part === 'Conference' ? (
            <span key={i} className="text-[#67b52f]">
                {part}
            </span>
        ) : (
            <span key={i}>{part}</span>
        ),
    );
}

export function AuthPanel() {
    const { conference } = usePage<SharedData>().props;

    const editionLabel = [conference.edition_number, conference.conference_year].filter(Boolean).join(' ');
    const conferenceName = conference.conference_name ?? 'Traditional Medicine Scientific Conference and Exhibitions';
    const startDate = formatDate(conference.start_date);
    const abstractDeadline = formatDate(conference.submission_deadline);

    return (
        <div className="flex h-full min-h-full w-full flex-col gap-7 bg-[#135eeb] px-11 py-9 text-white">
            <Link href={route('home')} className="flex w-fit items-center gap-2.5 text-white no-underline">
                <img src={nimrLogo} alt="NIMR" className="size-9 rounded-full object-contain" />
                <span className="font-serif text-lg font-semibold">TMSC</span>
            </Link>

            <div className="flex max-w-[400px] flex-1 flex-col justify-center gap-[18px]">
                {editionLabel && (
                    <div className="flex">
                        <span className="inline-flex rounded-full bg-[#67b52f]/90 px-4 py-1.5 text-[11px] font-semibold tracking-wide uppercase">
                            {editionLabel}
                        </span>
                    </div>
                )}
                <h1 className="font-serif text-[30px] leading-[1.25] font-bold text-balance">{highlightTitle(conferenceName)}</h1>
                {conference.theme && <p className="font-serif text-[15px] leading-relaxed font-medium text-white/85 italic">{conference.theme}</p>}
                <div className="mt-1.5 size-[140px] overflow-hidden rounded-full border-[6px] border-white/90 shadow-[0_25px_50px_-12px_rgba(0,0,0,0.25)]">
                    <img src={heroPhoto768} alt="Traditional medicine herbs and mortar" className="size-full object-cover" />
                </div>
                <div className="mt-2 flex flex-col">
                    {startDate && (
                        <div className="border-t border-white/15 py-3.5">
                            <div className="text-[11px] font-semibold tracking-wide text-white/60 uppercase">Conference date</div>
                            <div className="mt-1 font-serif text-[17px] font-semibold">
                                <time dateTime={conference.start_date ?? undefined}>{startDate}</time>
                            </div>
                        </div>
                    )}
                    {conference.venue && (
                        <div className="border-t border-white/15 py-3.5">
                            <div className="text-[11px] font-semibold tracking-wide text-white/60 uppercase">Location</div>
                            <div className="mt-1 font-serif text-[17px] font-semibold">{conference.venue}</div>
                        </div>
                    )}
                    {abstractDeadline && (
                        <div className="border-y border-white/15 py-3.5">
                            <div className="text-[11px] font-semibold tracking-wide text-white/60 uppercase">Abstract deadline</div>
                            <div className="mt-1 font-serif text-[17px] font-semibold">
                                <time dateTime={conference.submission_deadline ?? undefined}>{abstractDeadline}</time>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <p className="text-xs leading-relaxed text-white/55">
                Hosted by the National Institute for Medical Research (NIMR), in partnership with the Ministry of Health.
            </p>
        </div>
    );
}
