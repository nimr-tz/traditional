import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Mail } from 'lucide-react';
import { FormEventHandler } from 'react';

import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import AuthSplitPanelLayout from '@/layouts/auth/auth-split-panel-layout';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <AuthSplitPanelLayout>
            <Head title="Email verification" />

            <div className="flex flex-col items-center gap-6 text-center">
                <div className="mx-auto flex size-16 items-center justify-center rounded-full bg-[#eaf1ff] text-[#135eeb]">
                    <Mail className="size-7" />
                </div>
                <div className="flex flex-col gap-2">
                    <h1 className="font-serif text-[26px] font-semibold">Check your email</h1>
                    <p className="text-sm leading-relaxed text-[hsl(30,8%,42%)]">
                        Open the verification link in your email to activate your account and continue your registration for the conference.
                    </p>
                </div>

                {status === 'verification-link-sent' && (
                    <div className="rounded-lg border border-[#67b52f]/35 bg-[#eef7e6] px-4 py-3 text-sm font-medium text-[#3f7317]">
                        We have sent you a verification link. Please check your inbox to continue.
                    </div>
                )}

                <form onSubmit={submit} className="flex flex-col items-center gap-4">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="h-11 bg-[hsl(140,16%,92%)] px-7 font-semibold text-[hsl(140,32%,18%)] hover:bg-[hsl(140,16%,86%)]"
                    >
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Resend verification email
                    </Button>
                    <TextLink href={route('logout')} method="post" className="mx-auto text-sm decoration-neutral-300">
                        Log out
                    </TextLink>
                </form>

                <p className="text-xs leading-relaxed text-[hsl(30,8%,55%)]">If you do not see it, check your spam or junk folder.</p>
            </div>
        </AuthSplitPanelLayout>
    );
}
