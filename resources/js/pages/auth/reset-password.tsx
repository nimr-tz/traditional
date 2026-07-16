import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthSplitPanelLayout from '@/layouts/auth/auth-split-panel-layout';

interface ResetPasswordProps {
    token: string;
    email: string;
}

interface ResetPasswordForm {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { data, setData, post, processing, errors, reset } = useForm<ResetPasswordForm>({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthSplitPanelLayout>
            <Head title="Reset password" />

            <div className="flex flex-col gap-2">
                <h1 className="font-serif text-[26px] font-semibold">Reset password</h1>
                <p className="text-sm leading-relaxed text-[hsl(30,8%,42%)]">Please enter your new password below.</p>
            </div>

            <form onSubmit={submit} className="flex flex-col gap-5">
                <div className="flex flex-col gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" type="email" value={data.email} readOnly className="bg-[hsl(40,20%,93%)] text-[hsl(30,8%,42%)]" />
                    <InputError message={errors.email} />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        autoFocus
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="Password"
                    />
                    <p className="text-xs text-[hsl(30,8%,42%)]">At least 8 characters.</p>
                    <InputError message={errors.password} />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        placeholder="Confirm password"
                    />
                    <InputError message={errors.password_confirmation} />
                </div>

                <Button type="submit" className="mt-1 h-11 bg-[#135eeb] font-semibold hover:bg-[#135eeb]/90" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Reset password
                </Button>
            </form>
        </AuthSplitPanelLayout>
    );
}
