import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { BadgeCheck, Clock3, FileCheck2, GraduationCap, LoaderCircle, ShieldAlert, UserRound } from 'lucide-react';
import { FormEventHandler } from 'react';

import { DashboardCard } from '@/components/dashboard-card';
import InputError from '@/components/input-error';
import SettingsSectionHeader from '@/components/settings-section-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

interface FeeCategory {
    key: string;
    label: string;
    amount: string;
    currency: string;
}

/**
 * Mirrors App\Support\FeeTier::regionOf(). The fee differs by regional tier, so
 * the form only offers the tier the registrant's country entitles them to.
 */
const categoryRegion = (key: string): 'east_africa' | 'international' | null => {
    if (key.endsWith('_non_east_africa')) return 'international';
    if (key.endsWith('_east_africa')) return 'east_africa';
    return null;
};

interface RegistrationInfo {
    participant_type: string | null;
    fee_category: string | null;
    country: string | null;
    region: 'east_africa' | 'international';
    payment_status: 'pending' | 'submitted' | 'verified' | 'rejected' | 'waived';
    requires_student_verification: boolean;
    student_verification_status: 'pending' | 'verified' | 'rejected' | null;
    student_verification_notes: string | null;
    has_student_document: boolean;
}

interface ProfileProps {
    mustVerifyEmail: boolean;
    status?: string;
    participantTypes: Record<string, string>;
    feeCategories: FeeCategory[];
    registration: RegistrationInfo;
}

export default function Profile({ mustVerifyEmail, status, participantTypes, feeCategories, registration }: ProfileProps) {
    const { auth } = usePage<SharedData>().props;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: auth.user.name,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    const canChangeCategory = registration.payment_status === 'pending';

    const categoryForm = useForm<{ participant_type: string; fee_category: string; student_document: File | null }>({
        participant_type: registration.participant_type ?? '',
        fee_category: registration.fee_category ?? '',
        student_document: null,
    });

    const isStudentSelection = categoryForm.data.participant_type === 'student';
    const availableFeeCategories = categoryForm.data.participant_type
        ? feeCategories.filter((category) => {
              if (!category.key.startsWith(isStudentSelection ? 'student_' : 'participant_')) return false;
              const region = categoryRegion(category.key);
              return region === null || region === registration.region;
          })
        : [];

    // Switching into a student rate needs proof, same as registering into one.
    const switchingToStudentWithoutDocument = categoryForm.data.fee_category.startsWith('student_') && !registration.has_student_document;

    const submitCategory: FormEventHandler = (e) => {
        e.preventDefault();

        // Inertia can't send a file over PATCH, so spoof the method on a POST.
        categoryForm.transform((data) => ({ ...data, _method: 'patch' }));
        categoryForm.post(route('registration.update'), {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    const documentForm = useForm<{ student_document: File | null }>({ student_document: null });

    const submitDocument = () => {
        documentForm.post(route('student-verification.document'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => documentForm.reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <SettingsLayout>
                <DashboardCard>
                    <SettingsSectionHeader
                        icon={UserRound}
                        tone="green"
                        title="Profile information"
                        description="Your name and verified email address."
                    />

                    <form onSubmit={submit} className="mt-6 space-y-5">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>

                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                autoComplete="name"
                                placeholder="Full name"
                            />

                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>

                            <div className="flex items-center gap-2">
                                <Input id="email" value={auth.user.email} disabled className="text-muted-foreground" />
                                {auth.user.email_verified_at ? (
                                    <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-[#eef7e6] px-2.5 py-1 text-xs font-bold text-[#4c8a1f] dark:bg-[#67b52f]/15 dark:text-[#8fd45a]">
                                        <BadgeCheck className="size-3.5" /> Verified
                                    </span>
                                ) : (
                                    <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-[#fdf1e7] px-2.5 py-1 text-xs font-bold text-[#b5651d] dark:bg-[#b5651d]/15 dark:text-[#e0a06a]">
                                        <ShieldAlert className="size-3.5" /> Unverified
                                    </span>
                                )}
                            </div>
                            <p className="text-muted-foreground text-xs leading-5">
                                Your email is verified and used for your badge, certificate, and all conference notifications, so it can't be changed
                                here. Contact the organizers if it's wrong.
                            </p>
                        </div>

                        {mustVerifyEmail && auth.user.email_verified_at === null && (
                            <Alert className="border-[#135eeb]/25 bg-[#135eeb]/5">
                                <AlertTitle>Your email address is unverified</AlertTitle>
                                <AlertDescription>
                                    <Link
                                        href={route('verification.send')}
                                        method="post"
                                        as="button"
                                        className="font-semibold text-[#135eeb] underline underline-offset-2 hover:text-[#0e4bc2]"
                                    >
                                        Click here to re-send the verification email.
                                    </Link>

                                    {status === 'verification-link-sent' && (
                                        <p className="mt-2 flex items-center gap-1.5 font-semibold text-[#4c8a1f]">
                                            <BadgeCheck className="size-4" />A new verification link has been sent to your email address.
                                        </p>
                                    )}
                                </AlertDescription>
                            </Alert>
                        )}

                        <div className="flex items-center gap-4 pt-1">
                            <Button disabled={processing} className="bg-[#4c8a1f] font-bold hover:bg-[#3f751a]">
                                Save changes
                            </Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm font-semibold text-[#4c8a1f]">Saved</p>
                            </Transition>
                        </div>
                    </form>
                </DashboardCard>

                <DashboardCard>
                    <SettingsSectionHeader
                        icon={GraduationCap}
                        tone="blue"
                        title="Registration category"
                        description="Fix your participant type or region if it was selected incorrectly."
                    />

                    {canChangeCategory ? (
                        <form onSubmit={submitCategory} className="mt-6 space-y-5">
                            <div className="grid gap-2">
                                <Label htmlFor="participant_type">Participant type</Label>
                                <Select
                                    value={categoryForm.data.participant_type}
                                    onValueChange={(value) => {
                                        categoryForm.setData({ ...categoryForm.data, participant_type: value, fee_category: '' });
                                        categoryForm.clearErrors();
                                    }}
                                >
                                    <SelectTrigger id="participant_type">
                                        <SelectValue placeholder="Select participant type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(participantTypes).map(([key, label]) => (
                                            <SelectItem key={key} value={key}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={categoryForm.errors.participant_type} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="fee_category">Registration category</Label>
                                <Select
                                    disabled={!categoryForm.data.participant_type}
                                    value={categoryForm.data.fee_category}
                                    onValueChange={(value) => categoryForm.setData('fee_category', value)}
                                >
                                    <SelectTrigger id="fee_category">
                                        <SelectValue placeholder="Select your registration category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableFeeCategories.map((category) => (
                                            <SelectItem key={category.key} value={category.key}>
                                                {category.label} — {category.currency} {Number(category.amount).toLocaleString()}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={categoryForm.errors.fee_category} />
                                <p className="text-muted-foreground text-xs leading-5">
                                    Your regional tier is set by your country
                                    {registration.country ? ` (${registration.country})` : ''} and can't be changed here. Contact the organizers if
                                    your country is recorded incorrectly.
                                </p>
                            </div>

                            {switchingToStudentWithoutDocument && (
                                <div className="grid gap-2 rounded-xl border border-[#67b52f]/25 bg-[#67b52f]/5 p-4">
                                    <Label htmlFor="category_student_document">Student verification document</Label>
                                    <p className="text-muted-foreground text-xs leading-5">
                                        A student rate needs proof of student status. Upload a valid student ID (PDF, JPG, JPEG, or PNG, max 10 MB) to
                                        switch to this category.
                                    </p>
                                    <Input
                                        id="category_student_document"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                        onChange={(event) => categoryForm.setData('student_document', event.target.files?.[0] ?? null)}
                                    />
                                    <InputError message={categoryForm.errors.student_document} />
                                </div>
                            )}

                            <div className="flex items-center gap-4 pt-1">
                                <Button disabled={categoryForm.processing} className="bg-[#135eeb] font-bold hover:bg-[#0e4bc2]">
                                    Update category
                                </Button>

                                <Transition
                                    show={categoryForm.recentlySuccessful}
                                    enter="transition ease-in-out"
                                    enterFrom="opacity-0"
                                    leave="transition ease-in-out"
                                    leaveTo="opacity-0"
                                >
                                    <p className="text-sm font-semibold text-[#4c8a1f]">Saved</p>
                                </Transition>
                            </div>
                        </form>
                    ) : (
                        <p className="text-muted-foreground mt-6 text-sm leading-6">
                            A control number has already been requested for your registration, so your category is locked in. Contact the organizers
                            if it needs to change.
                        </p>
                    )}
                </DashboardCard>

                {registration.requires_student_verification && (
                    <DashboardCard>
                        <SettingsSectionHeader
                            icon={FileCheck2}
                            tone="green"
                            title="Student verification"
                            description="Your student registration rate requires a verified student document."
                        />

                        <div className="mt-6 space-y-4">
                            {registration.student_verification_status === 'verified' && (
                                <Alert className="border-[#67b52f]/25 bg-[#67b52f]/5">
                                    <BadgeCheck className="h-4 w-4" />
                                    <AlertTitle>Student status approved</AlertTitle>
                                    <AlertDescription>Your student registration rate is confirmed.</AlertDescription>
                                </Alert>
                            )}

                            {registration.student_verification_status === 'pending' && registration.has_student_document && (
                                <Alert>
                                    <Clock3 className="h-4 w-4" />
                                    <AlertTitle>Your student document is being reviewed</AlertTitle>
                                    <AlertDescription>
                                        You'll be able to request a control number as soon as your student status is approved.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {registration.student_verification_status === 'rejected' && registration.student_verification_notes && (
                                <Alert variant="destructive">
                                    <ShieldAlert className="h-4 w-4" />
                                    <AlertTitle>Your document was not approved</AlertTitle>
                                    <AlertDescription>{registration.student_verification_notes}</AlertDescription>
                                </Alert>
                            )}

                            {registration.student_verification_status !== 'verified' && (
                                <div className="grid max-w-xl gap-2">
                                    <Label htmlFor="student_document">
                                        {registration.has_student_document ? 'Upload a replacement document' : 'Upload your student document'}
                                    </Label>
                                    <Input
                                        id="student_document"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                        onChange={(event) => documentForm.setData('student_document', event.target.files?.[0] ?? null)}
                                    />
                                    <InputError message={documentForm.errors.student_document} />
                                    <Button
                                        type="button"
                                        className="mt-1 w-fit bg-[#4c8a1f] font-bold hover:bg-[#3f751a]"
                                        disabled={!documentForm.data.student_document || documentForm.processing}
                                        onClick={submitDocument}
                                    >
                                        {documentForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                        Submit document
                                    </Button>
                                </div>
                            )}
                        </div>
                    </DashboardCard>
                )}
            </SettingsLayout>
        </AppLayout>
    );
}
