import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthSplitPanelLayout from '@/layouts/auth/auth-split-panel-layout';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthSplitPanelLayout>
            <Head title="Log in" />

            <div className="flex flex-col gap-2">
                <h1 className="font-serif text-[26px] font-semibold">Welcome back</h1>
                <p className="text-sm leading-relaxed text-[hsl(30,8%,42%)]">Log in to manage your registration, abstract and payment.</p>
            </div>

            {status && (
                <div className="rounded-lg border border-[#67b52f]/35 bg-[#eef7e6] px-4 py-3 text-sm font-medium text-[#3f7317]">{status}</div>
            )}

            <form onSubmit={submit} className="flex flex-col gap-5">
                <div className="flex flex-col gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autoFocus
                        tabIndex={1}
                        autoComplete="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="email@example.com"
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="flex flex-col gap-2">
                    <div className="flex items-center justify-between">
                        <Label htmlFor="password">Password</Label>
                        {canResetPassword && (
                            <TextLink href={route('password.request')} className="text-sm" tabIndex={5}>
                                Forgot password?
                            </TextLink>
                        )}
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        tabIndex={2}
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="Password"
                    />
                    <InputError message={errors.password} />
                </div>

                <label className="flex w-fit cursor-pointer items-center gap-2.5 text-sm">
                    <Checkbox
                        id="remember"
                        name="remember"
                        tabIndex={3}
                        checked={data.remember}
                        onCheckedChange={(checked) => setData('remember', checked === true)}
                    />
                    <span>Remember me</span>
                </label>

                <Button type="submit" className="mt-1 h-11 bg-[#135eeb] font-semibold hover:bg-[#135eeb]/90" tabIndex={4} disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Log in
                </Button>
            </form>

            <p className="text-center text-sm text-[hsl(30,8%,42%)]">
                New to the conference?{' '}
                <TextLink href={route('register')} className="font-medium text-[#135eeb] decoration-transparent" tabIndex={5}>
                    Register to participate
                </TextLink>
            </p>
        </AuthSplitPanelLayout>
    );
}
