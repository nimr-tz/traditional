import { Head } from '@inertiajs/react';
import { CalendarX2 } from 'lucide-react';

import TextLink from '@/components/text-link';
import AuthSplitPanelLayout from '@/layouts/auth/auth-split-panel-layout';

interface RegistrationClosedProps {
    closedMessage: string;
    contactEmail: string | null;
}

export default function RegistrationClosed({ closedMessage, contactEmail }: RegistrationClosedProps) {
    return (
        <AuthSplitPanelLayout>
            <Head title="Registration closed" />

            <div className="flex flex-col items-center gap-6 text-center">
                <div className="mx-auto flex size-16 items-center justify-center rounded-full bg-[#fdf1e7] text-[#b5651d]">
                    <CalendarX2 className="size-7" />
                </div>

                <div className="flex flex-col gap-2">
                    <h1 className="font-serif text-[26px] font-semibold">Registration is closed</h1>
                    <p className="text-sm leading-relaxed text-[hsl(30,8%,42%)]">{closedMessage}</p>
                </div>

                <p className="text-sm leading-relaxed text-[hsl(30,8%,42%)]">
                    If you already have an account you can still{' '}
                    <TextLink href={route('login')} className="font-medium text-[#135eeb] decoration-transparent">
                        log in
                    </TextLink>{' '}
                    to complete your payment, manage your abstract, and download your badge.
                </p>

                {contactEmail && (
                    <p className="text-xs leading-relaxed text-[hsl(30,8%,55%)]">
                        Need to register late? Contact the organizers at{' '}
                        <a href={`mailto:${contactEmail}`} className="font-medium text-[#135eeb]">
                            {contactEmail}
                        </a>
                        .
                    </p>
                )}
            </div>
        </AuthSplitPanelLayout>
    );
}
