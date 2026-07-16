import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthSplitPanelLayout from '@/layouts/auth/auth-split-panel-layout';

export default function ForgotPassword({ status }: { status?: string }) {
    const [dismissed, setDismissed] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ email: '' });
    const sent = Boolean(status) && !dismissed;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    const tryAgain = () => {
        setDismissed(true);
        reset('email');
    };

    return (
        <AuthSplitPanelLayout>
            <Head title="Forgot password" />

            {sent ? (
                <div className="flex flex-col items-center gap-5 text-center">
                    <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-[#67b52f]/12 text-2xl text-[#67b52f]">✓</div>
                    <div className="flex flex-col gap-2">
                        <h1 className="font-serif text-[26px] font-semibold">Check your email</h1>
                        <p className="text-sm leading-relaxed text-[hsl(30,8%,42%)]">Follow the link in the email to choose a new password.</p>
                    </div>
                    <p className="text-sm font-medium text-[#3f7317]">{status}</p>
                    <p className="text-sm text-[hsl(30,8%,42%)]">If you do not see it, check your spam or junk folder.</p>
                    <Button asChild className="h-11 w-full bg-[#135eeb] font-semibold hover:bg-[#135eeb]/90">
                        <Link href={route('login')}>Back to log in</Link>
                    </Button>
                    <button
                        type="button"
                        onClick={tryAgain}
                        className="mx-auto w-fit border-none bg-transparent text-sm text-[hsl(20,12%,14%)] underline decoration-neutral-300 underline-offset-4 hover:decoration-current"
                    >
                        Try a different email address
                    </button>
                </div>
            ) : (
                <div className="flex flex-col gap-7">
                    <div className="flex flex-col gap-2">
                        <h1 className="font-serif text-[26px] font-semibold">Forgot password</h1>
                        <p className="text-sm leading-relaxed text-[hsl(30,8%,42%)]">Enter your email and we will send you a password reset link.</p>
                    </div>
                    <form onSubmit={submit} className="flex flex-col gap-5">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="email"
                                autoFocus
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="email@example.com"
                            />
                            <InputError message={errors.email} />
                        </div>
                        <Button type="submit" className="h-11 bg-[#135eeb] font-semibold hover:bg-[#135eeb]/90" disabled={processing}>
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Email password reset link
                        </Button>
                    </form>
                    <p className="text-center text-sm text-[hsl(30,8%,42%)]">
                        Or, return to{' '}
                        <TextLink href={route('login')} className="font-medium text-[#135eeb] decoration-transparent">
                            log in
                        </TextLink>
                    </p>
                </div>
            )}
        </AuthSplitPanelLayout>
    );
}
