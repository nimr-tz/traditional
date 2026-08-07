import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useState } from 'react';
import nimrLogo from '../../images/nimr-logo-128.webp';
import heroPhoto1200 from '../../images/traditional-medicine-hero-1200.webp';
import heroPhoto480 from '../../images/traditional-medicine-hero-480.webp';
import heroPhotoFallback from '../../images/traditional-medicine-hero-768.jpg';
import heroPhoto768 from '../../images/traditional-medicine-hero-768.webp';

interface Subtheme {
    title: string;
    description: string | null;
}

interface FeeCategory {
    label: string;
    amount: string;
    currency: string;
}

interface RegistrationWindow {
    is_open: boolean;
    deadline: string | null;
    closed_message: string | null;
}

interface WelcomeProps {
    conference: Record<string, string | null>;
    subthemes: Subtheme[];
    feeCategories: FeeCategory[];
    registrationWindow: RegistrationWindow;
    seo?: {
        title: string;
        description: string;
        canonicalUrl: string;
        imageUrl: string;
        structuredData: Record<string, unknown>;
    };
}

const MONTHS: Record<string, string> = {
    january: '01',
    february: '02',
    march: '03',
    april: '04',
    may: '05',
    june: '06',
    july: '07',
    august: '08',
    september: '09',
    october: '10',
    november: '11',
    december: '12',
};

function toIsoDate(value: string | null | undefined): string | null {
    if (!value) return null;

    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;

    const match = value.trim().match(/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/);
    if (!match) return null;

    const month = MONTHS[match[2].toLowerCase()];
    if (!month) return null;

    return `${match[3]}-${month}-${match[1].padStart(2, '0')}`;
}

function formatConferenceDate(value: string | null | undefined): string {
    const isoDate = toIsoDate(value);
    if (!isoDate) return value ?? '';

    const [year, month, day] = isoDate.split('-').map(Number);
    return new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, month - 1, day)));
}

const FORMAT_FACTS = [
    { k: 'Word limit', v: '300 words + 5 keywords' },
    { k: 'Title', v: 'Bold capital letters' },
    { k: 'Structure', v: 'Background, Objective, Methods, Results, Conclusion' },
];

const INSTRUCTIONS = [
    'Abstracts must not exceed 300 words, including five keywords.',
    'The title should be in bold capital letters, followed by a blank line.',
    "The presenting author's name must be bolded and underlined.",
    'Author names should be written as initials followed by surname, e.g. H Malinye; JJ Kayumba.',
    'Affiliation and email address of the presenting author should follow the list of authors.',
    'Abstracts should include: Background, Objective, Methods, Results, and Conclusion.',
    'Please indicate whether your presentation is Oral or Poster.',
];

export default function Welcome({ conference, subthemes, feeCategories, registrationWindow, seo }: WelcomeProps) {
    const { auth, submissionWindow } = usePage<SharedData>().props;
    const [mobileNavOpen, setMobileNavOpen] = useState(false);

    const conferenceName = conference.conference_name || 'Traditional Medicine Scientific Conference';
    const startDateIso = toIsoDate(conference.start_date);
    const seoData =
        seo ??
        ({
            title: conferenceName,
            description: conference.theme ?? '',
            canonicalUrl: route('home'),
            imageUrl: heroPhoto1200,
            structuredData: {},
        } satisfies NonNullable<WelcomeProps['seo']>);

    const eyebrow = conference.edition_number ? `${conference.edition_number} Edition` : '';

    const factStrip = [
        { label: 'Conference date', value: formatConferenceDate(conference.start_date), dateTime: startDateIso },
        { label: 'Location', value: conference.venue, dateTime: null },
        {
            label: submissionWindow.is_open ? 'Abstract deadline' : 'Abstracts closed',
            value: formatConferenceDate(conference.submission_deadline),
            dateTime: toIsoDate(conference.submission_deadline),
        },
        registrationWindow.is_open && registrationWindow.deadline
            ? {
                  label: 'Registration deadline',
                  value: formatConferenceDate(registrationWindow.deadline),
                  dateTime: registrationWindow.deadline,
              }
            : { label: '', value: '', dateTime: null },
    ].filter((fact) => fact.value);

    const factGridClass =
        factStrip.length === 1
            ? 'sm:grid-cols-1'
            : factStrip.length === 2
              ? 'sm:grid-cols-2'
              : factStrip.length >= 4
                ? 'sm:grid-cols-2 lg:grid-cols-4'
                : 'sm:grid-cols-3';

    const footerContacts = [
        conference.contact_phone && { label: 'Call', value: conference.contact_phone },
        conference.contact_email && { label: 'Email', value: conference.contact_email },
        conference.website && { label: 'Web', value: conference.website },
    ].filter(Boolean) as { label: string; value: string }[];

    return (
        <>
            <Head title={seoData.title || 'TMSC'}>
                {seoData.description && <meta head-key="description" name="description" content={seoData.description} />}
                <meta head-key="robots" name="robots" content="index, follow" />
                <link head-key="canonical" rel="canonical" href={seoData.canonicalUrl} />
                <link head-key="sitemap" rel="sitemap" type="application/xml" href={route('sitemap')} />
                <meta head-key="og:type" property="og:type" content="website" />
                <meta head-key="og:site_name" property="og:site_name" content="TMSC" />
                <meta head-key="og:title" property="og:title" content={seoData.title || conferenceName} />
                {seoData.description && <meta head-key="og:description" property="og:description" content={seoData.description} />}
                <meta head-key="og:url" property="og:url" content={seoData.canonicalUrl} />
                <meta head-key="og:image" property="og:image" content={seoData.imageUrl} />
                <meta head-key="og:image:alt" property="og:image:alt" content="Traditional medicine herbs and mortar" />
                <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
                <meta head-key="twitter:title" name="twitter:title" content={seoData.title || conferenceName} />
                {seoData.description && <meta head-key="twitter:description" name="twitter:description" content={seoData.description} />}
                <meta head-key="twitter:image" name="twitter:image" content={seoData.imageUrl} />
                <script head-key="event-json-ld" type="application/ld+json">
                    {JSON.stringify(seoData.structuredData)}
                </script>
            </Head>

            <div className="bg-background text-foreground min-h-screen">
                <header className="bg-[#135eeb] text-white">
                    <div className="mx-auto flex max-w-5xl items-center justify-between p-4">
                        <div className="flex items-center gap-2">
                            <img src={nimrLogo} alt="NIMR" className="size-9 rounded-full object-contain" />
                            <span className="font-serif text-lg font-semibold">TMSC</span>
                        </div>
                        <nav className="hidden items-center gap-6 text-sm font-medium text-white/80 sm:flex">
                            <a href="#subthemes" className="hover:text-white">
                                Sub-themes
                            </a>
                            <a href="#authors" className="hover:text-white">
                                Call for abstracts
                            </a>
                            <a href="#registration" className="hover:text-white">
                                Registration
                            </a>
                        </nav>
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                className="inline-flex size-10 items-center justify-center rounded-md text-white hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:hidden"
                                aria-label={mobileNavOpen ? 'Close conference navigation' : 'Open conference navigation'}
                                aria-expanded={mobileNavOpen}
                                aria-controls="mobile-conference-nav"
                                onClick={() => setMobileNavOpen((open) => !open)}
                            >
                                {mobileNavOpen ? <X className="size-5" aria-hidden="true" /> : <Menu className="size-5" aria-hidden="true" />}
                            </button>
                            {auth.user ? (
                                <Button asChild className="bg-[#67b52f] text-white hover:bg-[#67b52f]/90">
                                    <Link href={route('dashboard')}>Dashboard</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild variant="ghost" className="text-white hover:bg-white/10 hover:text-white">
                                        <Link href={route('login')}>Log in</Link>
                                    </Button>
                                    <Button asChild className="bg-[#67b52f] text-white hover:bg-[#67b52f]/90">
                                        <Link href={route('register')}>Register</Link>
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>
                    {mobileNavOpen && (
                        <nav id="mobile-conference-nav" aria-label="Conference sections" className="border-t border-white/15 px-4 py-3 sm:hidden">
                            <div className="mx-auto flex max-w-5xl flex-col text-sm font-medium">
                                <a
                                    href="#subthemes"
                                    className="rounded-md px-3 py-3 text-white/85 hover:bg-white/10 hover:text-white"
                                    onClick={() => setMobileNavOpen(false)}
                                >
                                    Sub-themes
                                </a>
                                <a
                                    href="#authors"
                                    className="rounded-md px-3 py-3 text-white/85 hover:bg-white/10 hover:text-white"
                                    onClick={() => setMobileNavOpen(false)}
                                >
                                    Call for abstracts
                                </a>
                                <a
                                    href="#registration"
                                    className="rounded-md px-3 py-3 text-white/85 hover:bg-white/10 hover:text-white"
                                    onClick={() => setMobileNavOpen(false)}
                                >
                                    Registration
                                </a>
                            </div>
                        </nav>
                    )}
                </header>

                {!registrationWindow.is_open && (
                    <div className="bg-[#fdf1e7] px-4 py-3 text-center text-sm font-semibold text-[#8a4d16]">
                        {registrationWindow.closed_message} Already registered?{' '}
                        <Link href={route('login')} className="underline underline-offset-2">
                            Log in
                        </Link>{' '}
                        to complete your payment and download your badge.
                    </div>
                )}

                <section className="bg-[#135eeb] text-white">
                    <div className="mx-auto max-w-7xl px-4 pt-16 sm:px-8">
                        <div className="grid items-center gap-10 md:grid-cols-[1.3fr_1fr]">
                            <div>
                                {eyebrow && (
                                    <div className="inline-flex rounded-full bg-[#67b52f]/90 px-4 py-1.5 text-xs font-semibold tracking-wide text-white uppercase">
                                        {eyebrow}
                                    </div>
                                )}
                                <h1 className="mt-4 font-serif text-4xl leading-tight font-bold sm:text-5xl">
                                    {(() => {
                                        const name = conferenceName;
                                        const highlighted = ['Traditional', 'Conference'];
                                        const parts = name.split(new RegExp(`(${highlighted.join('|')})`, 'g'));
                                        return parts.map((part, i) =>
                                            highlighted.includes(part) ? (
                                                <span key={i} className="text-[#67b52f]">
                                                    {part}
                                                </span>
                                            ) : (
                                                <span key={i}>{part}</span>
                                            ),
                                        );
                                    })()}
                                </h1>
                                {conference.theme && (
                                    <p className="mt-4 max-w-xl font-serif text-lg font-semibold text-white italic sm:text-xl">{conference.theme}</p>
                                )}
                                <div className="mt-4 inline-flex rounded-full bg-[#67b52f]/90 px-4 py-1.5 text-xs font-semibold tracking-wide text-white uppercase">
                                    Hosted by the National Institute for Medical Research
                                </div>
                                <div className="mt-7">
                                    <Button size="lg" asChild className="bg-white text-[#135eeb] hover:bg-white/90">
                                        <Link href={route('register')}>Register to participate</Link>
                                    </Button>
                                </div>
                            </div>
                            <div className="flex justify-center">
                                <div className="h-[260px] w-[260px] overflow-hidden rounded-full border-8 border-white/90 shadow-2xl sm:h-[300px] sm:w-[300px]">
                                    <picture>
                                        <source
                                            type="image/webp"
                                            srcSet={`${heroPhoto480} 480w, ${heroPhoto768} 768w, ${heroPhoto1200} 1200w`}
                                            sizes="(min-width: 640px) 300px, 260px"
                                        />
                                        <img
                                            src={heroPhotoFallback}
                                            alt="Traditional medicine herbs and mortar"
                                            width="768"
                                            height="576"
                                            fetchPriority="high"
                                            decoding="async"
                                            className="h-full w-full object-cover"
                                        />
                                    </picture>
                                </div>
                            </div>
                        </div>

                        {factStrip.length > 0 && (
                            <div
                                className={`bg-card text-card-foreground mt-14 grid grid-cols-1 overflow-hidden rounded-t-2xl shadow-xl ${factGridClass}`}
                            >
                                {factStrip.map((fact, i) => (
                                    <div
                                        key={fact.label}
                                        className={`px-5 py-5 text-center sm:py-6 ${i > 0 ? 'border-t sm:border-t-0 sm:border-l' : ''}`}
                                    >
                                        <div className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">{fact.label}</div>
                                        <div className="mt-2 font-serif text-lg font-semibold">
                                            {fact.dateTime ? <time dateTime={fact.dateTime}>{fact.value}</time> : fact.value}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </section>

                <main>
                    <section id="subthemes" className="bg-muted/30 py-20">
                        <div className="mx-auto max-w-7xl px-4 sm:px-8">
                            <div className="mb-10 flex flex-col items-end justify-between gap-4 sm:flex-row">
                                <div>
                                    <p className="text-xs font-semibold tracking-widest text-[#135eeb] uppercase">Programme</p>
                                    <h2 className="mt-2 font-serif text-3xl font-semibold">Five conversations shaping traditional medicine</h2>
                                </div>
                                {submissionWindow.is_open && (
                                    <Button
                                        asChild
                                        size="lg"
                                        className="h-auto max-w-xs bg-[#135eeb] px-6 py-3 text-right leading-snug whitespace-normal text-white hover:bg-[#135eeb]/90"
                                    >
                                        <Link href={route('register')}>Submit your abstract under any of the five conference sub-themes.</Link>
                                    </Button>
                                )}
                            </div>

                            <div className="flex flex-wrap justify-center gap-4">
                                {subthemes.map((subtheme, i) => {
                                    const featured = i === 0 ? 'blue' : i === subthemes.length - 1 ? 'green' : null;
                                    const wrapperClass = featured
                                        ? featured === 'blue'
                                            ? 'bg-[#135eeb] text-white shadow-xl'
                                            : 'bg-[#67b52f] text-white shadow-xl'
                                        : 'bg-card border shadow-sm';
                                    const badgeClass = featured ? 'bg-white/15 text-white' : 'bg-[#eaf1ff] text-[#135eeb]';
                                    const numClass = featured
                                        ? featured === 'blue'
                                            ? 'bg-white text-[#135eeb]'
                                            : 'bg-white text-[#67b52f]'
                                        : 'bg-[#67b52f] text-white';

                                    return (
                                        <div
                                            key={subtheme.title}
                                            className={`w-full rounded-2xl p-7 transition-transform hover:-translate-y-1 sm:w-[calc(50%-0.5rem)] lg:w-[calc(33.333%-0.667rem)] ${wrapperClass}`}
                                        >
                                            <div className="flex items-center justify-between">
                                                <div
                                                    className={`flex h-10 w-10 items-center justify-center rounded-full font-serif text-base font-bold ${numClass}`}
                                                >
                                                    {String(i + 1).padStart(2, '0')}
                                                </div>
                                                <span
                                                    className={`rounded-full px-3 py-1 text-[11px] font-semibold tracking-wide uppercase ${badgeClass}`}
                                                >
                                                    Sub-theme
                                                </span>
                                            </div>
                                            <p className="mt-4 font-serif text-lg leading-snug font-semibold">{subtheme.title}</p>
                                            {subtheme.description && (
                                                <ul className="mt-3 flex flex-col gap-1.5 text-sm">
                                                    {subtheme.description.split('\n').map((line) => (
                                                        <li key={line} className="flex gap-2">
                                                            <span className={featured ? 'text-white/70' : 'text-[#67b52f]'}>–</span>
                                                            <span className={featured ? 'text-white/90' : 'text-muted-foreground'}>{line}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    <section id="authors" className="py-20">
                        <div className="mx-auto max-w-5xl px-4">
                            <div className="mb-9 flex flex-col items-end justify-between gap-4 sm:flex-row">
                                <div>
                                    <p className="text-xs font-semibold tracking-widest text-[#135eeb] uppercase">Call for abstracts</p>
                                    <h2 className="mt-2 font-serif text-3xl font-semibold">Instructions to authors</h2>
                                </div>
                                <p className="text-muted-foreground max-w-xs text-sm sm:text-right">
                                    Follow the format below — abstracts that don't meet it may not reach review.
                                </p>
                            </div>

                            <div className="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {FORMAT_FACTS.map((fact, i) => {
                                    const pale = i % 2 === 0 ? 'bg-[#eaf1ff]' : 'bg-[#eef7e6]';
                                    const label = i % 2 === 0 ? 'text-[#135eeb]' : 'text-[#67b52f]';

                                    return (
                                        <div key={fact.k} className={`rounded-xl p-5 ${pale}`}>
                                            <div className={`text-[11px] font-semibold tracking-wide uppercase ${label}`}>{fact.k}</div>
                                            <p className="mt-2 font-serif text-sm leading-snug font-semibold">{fact.v}</p>
                                        </div>
                                    );
                                })}
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                {INSTRUCTIONS.map((text, i) => (
                                    <div key={text} className="bg-card flex items-start gap-4 rounded-xl border p-4">
                                        <div className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-[#eaf1ff] text-xs font-bold text-[#135eeb]">
                                            {i + 1}
                                        </div>
                                        <p className="text-muted-foreground pt-0.5 text-sm leading-relaxed">{text}</p>
                                    </div>
                                ))}
                            </div>

                            {(conference.submission_deadline || conference.abstract_notification_date || conference.start_date) && (
                                <div className="mt-7 flex flex-col items-center justify-between gap-6 rounded-2xl bg-[#67b52f] p-8 text-white sm:flex-row">
                                    <div className="flex flex-wrap gap-10">
                                        {conference.submission_deadline && (
                                            <div>
                                                <div className="text-[11px] font-semibold tracking-wide text-[#135eeb] uppercase">
                                                    {submissionWindow.is_open ? 'Abstract deadline' : 'Abstracts closed'}
                                                </div>
                                                <time
                                                    dateTime={toIsoDate(conference.submission_deadline) ?? undefined}
                                                    className="mt-1.5 block font-serif text-xl font-semibold"
                                                >
                                                    {formatConferenceDate(conference.submission_deadline)}
                                                </time>
                                            </div>
                                        )}
                                        {conference.abstract_notification_date && (
                                            <div>
                                                <div className="text-[11px] font-semibold tracking-wide text-[#135eeb] uppercase">Notification</div>
                                                <time
                                                    dateTime={toIsoDate(conference.abstract_notification_date) ?? undefined}
                                                    className="mt-1.5 block font-serif text-xl font-semibold"
                                                >
                                                    {formatConferenceDate(conference.abstract_notification_date)}
                                                </time>
                                            </div>
                                        )}
                                        {conference.start_date && (
                                            <div>
                                                <div className="text-[11px] font-semibold tracking-wide text-[#135eeb] uppercase">Conference</div>
                                                <time dateTime={startDateIso ?? undefined} className="mt-1.5 block font-serif text-xl font-semibold">
                                                    {formatConferenceDate(conference.start_date)}
                                                </time>
                                            </div>
                                        )}
                                    </div>
                                    {submissionWindow.is_open ? (
                                        <Button variant="secondary" asChild>
                                            <Link href={route('register')}>Create an account to submit</Link>
                                        </Button>
                                    ) : (
                                        <p className="max-w-xs rounded-xl bg-white/15 px-4 py-3 text-sm leading-6 font-semibold">
                                            {submissionWindow.closed_message} Authors already under review can still sign in to answer reviewer
                                            comments.
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    </section>

                    <section id="registration" className="bg-muted/30 py-20">
                        <div className="mx-auto max-w-5xl px-4">
                            <div className="mx-auto mb-11 max-w-xl text-center">
                                <p className="text-xs font-semibold tracking-widest text-[#135eeb] uppercase">Registration</p>
                                <h2 className="mt-2 font-serif text-3xl font-semibold">Registration &amp; payments</h2>
                            </div>

                            {feeCategories.length > 0 && (
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    {feeCategories.map((fee) => {
                                        const isStudent = fee.label.includes('Student');
                                        return (
                                            <div
                                                key={fee.label}
                                                className="bg-card rounded-2xl border p-6 shadow-sm transition-transform hover:-translate-y-1"
                                            >
                                                <span
                                                    className={`inline-flex rounded-full px-3 py-1 text-[11px] font-semibold tracking-wide uppercase ${
                                                        isStudent ? 'bg-[#67b52f]/10 text-[#67b52f]' : 'bg-[#eaf1ff] text-[#135eeb]'
                                                    }`}
                                                >
                                                    {isStudent ? 'Student' : 'Participant'}
                                                </span>
                                                <p className="text-foreground mt-4 text-sm leading-snug font-semibold">{fee.label}</p>
                                                <p
                                                    className={`mt-3.5 font-serif text-2xl font-bold ${isStudent ? 'text-[#67b52f]' : 'text-[#135eeb]'}`}
                                                >
                                                    {fee.currency} {Number(fee.amount).toLocaleString()}
                                                </p>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            <div className="mt-6 flex flex-col items-center justify-between gap-5 rounded-2xl bg-[#eaf1ff] p-6 sm:flex-row">
                                <p className="text-sm text-[#0f2350]">
                                    All payments are directed to the <strong>control number</strong> issued to you upon registration.
                                </p>
                                <Button asChild className="bg-[#67b52f] text-white hover:bg-[#67b52f]/90">
                                    <Link href={route('register')}>Register to get your control number</Link>
                                </Button>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="bg-[#0f1b2d] text-white">
                    <div className="mx-auto max-w-7xl px-4 py-14 sm:px-8">
                        <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <div className="flex items-center gap-3">
                                    <img src={nimrLogo} alt="NIMR" className="size-9 rounded-full object-contain" />
                                    <span className="font-serif text-lg font-semibold">
                                        {conference.conference_name || 'Traditional Medicine Scientific Conference'}
                                    </span>
                                </div>
                                {conference.tagline && <p className="mt-4 font-serif text-sm text-white/70 italic">{conference.tagline}</p>}
                                <p className="mt-4 text-xs leading-relaxed text-white/50">
                                    Organized by the National Institute for Medical Research (NIMR), in partnership with the Ministry of Health.
                                </p>
                            </div>

                            <div>
                                <h3 className="text-xs font-semibold tracking-widest text-[#67b52f] uppercase">Explore</h3>
                                <ul className="mt-4 space-y-2.5 text-sm text-white/70">
                                    <li>
                                        <a href="#subthemes" className="transition-colors hover:text-white">
                                            Programme
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#authors" className="transition-colors hover:text-white">
                                            Call for abstracts
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#registration" className="transition-colors hover:text-white">
                                            Registration
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <div>
                                <h3 className="text-xs font-semibold tracking-widest text-[#67b52f] uppercase">Account</h3>
                                <ul className="mt-4 space-y-2.5 text-sm text-white/70">
                                    {auth.user ? (
                                        <li>
                                            <Link href={route('dashboard')} className="transition-colors hover:text-white">
                                                Dashboard
                                            </Link>
                                        </li>
                                    ) : (
                                        <>
                                            <li>
                                                <Link href={route('register')} className="transition-colors hover:text-white">
                                                    Register to participate
                                                </Link>
                                            </li>
                                            <li>
                                                <Link href={route('login')} className="transition-colors hover:text-white">
                                                    Log in
                                                </Link>
                                            </li>
                                        </>
                                    )}
                                </ul>
                            </div>

                            {footerContacts.length > 0 && (
                                <div>
                                    <h3 className="text-xs font-semibold tracking-widest text-[#67b52f] uppercase">Contact</h3>
                                    <ul className="mt-4 space-y-2.5 text-sm text-white/70">
                                        {footerContacts.map((c) => (
                                            <li key={c.label}>
                                                <span className="text-white/45">{c.label}:</span> {c.value}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>

                        <div className="mt-12 flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-6 text-xs text-white/45 sm:flex-row">
                            <p>&copy; {new Date().getFullYear()} National Institute for Medical Research. All rights reserved.</p>
                            <p>Open to researchers, academicians, TCIM practitioners, public health professionals and students.</p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
