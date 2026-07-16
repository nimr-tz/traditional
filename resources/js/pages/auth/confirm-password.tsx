import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Lock } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthSplitPanelLayout from '@/layouts/auth/auth-split-panel-layout';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthSplitPanelLayout>
            <Head title="Confirm password" />

            <div className="flex flex-col gap-2">
                <div className="mb-2 flex size-14 items-center justify-center rounded-full bg-[#eaf1ff] text-[#135eeb]">
                    <Lock className="size-6" />
                </div>
                <h1 className="font-serif text-[26px] font-semibold">Confirm your password</h1>
                <p className="text-sm leading-relaxed text-[hsl(30,8%,42%)]">
                    This is a secure area of the application. Please confirm your password before continuing.
                </p>
            </div>

            <form onSubmit={submit} className="flex flex-col gap-5">
                <div className="flex flex-col gap-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="current-password"
                        autoFocus
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="Password"
                    />
                    <InputError message={errors.password} />
                </div>

                <Button type="submit" className="h-11 bg-[#135eeb] font-semibold hover:bg-[#135eeb]/90" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Confirm password
                </Button>
            </form>
        </AuthSplitPanelLayout>
    );
}
