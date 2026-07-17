import { Head, useForm } from '@inertiajs/react';
import { Check, LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AuthSplitPanelLayout from '@/layouts/auth/auth-split-panel-layout';
import { cn } from '@/lib/utils';

interface RegisterForm {
    salutation: string;
    first_name: string;
    middle_name: string;
    last_name: string;
    email: string;
    institution_id: string;
    institution_other: string;
    phone: string;
    participant_type: string;
    country: string;
    fee_category: string;
    student_document: File | null;
    password: string;
    password_confirmation: string;
}

type RegisterField = keyof RegisterForm;

interface Institution {
    id: number;
    name: string;
}

interface FeeCategory {
    key: string;
    label: string;
    amount: string;
    currency: string;
}

interface RegisterProps {
    salutations: string[];
    participantTypes: Record<string, string>;
    feeCategories: FeeCategory[];
    eastAfricaCountries: string[];
    institutions: Institution[];
}

const STEPS = ['Personal', 'Contact', 'Category & fees', 'Password'] as const;

const STEP_FIELDS: Record<number, RegisterField[]> = {
    0: ['salutation', 'first_name', 'last_name', 'email'],
    1: ['institution_id', 'institution_other', 'phone', 'country'],
    2: ['participant_type', 'fee_category', 'student_document'],
    3: ['password', 'password_confirmation'],
};

export default function Register({ salutations, participantTypes, feeCategories, eastAfricaCountries, institutions }: RegisterProps) {
    const [step, setStep] = useState(0);
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm<RegisterForm>({
        salutation: '',
        first_name: '',
        middle_name: '',
        last_name: '',
        email: '',
        institution_id: '',
        institution_other: '',
        phone: '',
        participant_type: '',
        country: '',
        fee_category: '',
        student_document: null,
        password: '',
        password_confirmation: '',
    });

    const isStudentCategory = data.participant_type === 'student';
    const availableFeeCategories = data.participant_type
        ? feeCategories.filter((category) => category.key.startsWith(isStudentCategory ? 'student_' : 'participant_'))
        : [];

    const validationMessage = (field: RegisterField, formData: RegisterForm): string => {
        const text = typeof formData[field] === 'string' ? (formData[field] as string).trim() : '';

        switch (field) {
            case 'salutation':
                return text ? '' : 'Please select your title.';
            case 'first_name':
                return text ? '' : 'First name is required.';
            case 'middle_name':
                return text.length <= 255 ? '' : 'Middle name must not exceed 255 characters.';
            case 'last_name':
                return text ? '' : 'Family name is required.';
            case 'email':
                if (!text) return 'Email address is required.';
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text) ? '' : 'Enter a valid email address.';
            case 'institution_id':
                return text ? '' : 'Please select your institution.';
            case 'institution_other':
                return formData.institution_id !== 'other' || text ? '' : 'Institution name is required.';
            case 'phone':
                return text ? '' : 'Mobile number is required.';
            case 'country':
                return text ? '' : 'Country is required.';
            case 'participant_type':
                return text ? '' : 'Please select your participant type.';
            case 'fee_category':
                return text ? '' : 'Please select your registration category.';
            case 'student_document': {
                const file = formData.student_document;
                if (formData.participant_type === 'student' && !file) return 'Student verification document is required.';
                if (!file) return '';
                if (file.size > 10 * 1024 * 1024) return 'The document must not exceed 10 MB.';
                return ['application/pdf', 'image/jpeg', 'image/png'].includes(file.type) ? '' : 'Upload a PDF, JPG, JPEG, or PNG file.';
            }
            case 'password':
                if (!text) return 'Password is required.';
                return text.length >= 8 ? '' : 'Password must be at least 8 characters.';
            case 'password_confirmation':
                if (!text) return 'Please confirm your password.';
                return formData.password === formData.password_confirmation ? '' : 'The passwords do not match.';
        }
    };

    const stepFields = (index: number): RegisterField[] => {
        const fields = STEP_FIELDS[index];
        if (index === 1) return data.institution_id === 'other' ? fields : fields.filter((f) => f !== 'institution_other');
        if (index === 2) return isStudentCategory ? fields : fields.filter((f) => f !== 'student_document');
        return fields;
    };

    const validateField = (field: RegisterField, formData: RegisterForm = data): boolean => {
        const message = validationMessage(field, formData);
        if (message) setError(field, message);
        else clearErrors(field);
        return !message;
    };

    const validateStep = (index: number): boolean =>
        stepFields(index)
            .map((field) => validateField(field))
            .every(Boolean);

    const updateField = <K extends RegisterField>(field: K, value: RegisterForm[K]) => {
        const nextData = { ...data, [field]: value };
        setData(field, value);
        if (errors[field]) validateField(field, nextData);
        if (field === 'password' && (errors.password_confirmation || nextData.password_confirmation)) {
            validateField('password_confirmation', nextData);
        }
    };

    const goNext = () => {
        if (validateStep(step)) {
            clearErrors();
            setStep((s) => Math.min(STEPS.length - 1, s + 1));
        }
    };

    const goBack = () => {
        clearErrors();
        setStep((s) => Math.max(0, s - 1));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const fields: RegisterField[] = [
            'salutation',
            'first_name',
            'middle_name',
            'last_name',
            'email',
            'institution_id',
            'phone',
            'country',
            'participant_type',
            'fee_category',
            'password',
            'password_confirmation',
        ];
        if (data.institution_id === 'other') fields.push('institution_other');
        if (data.participant_type === 'student') fields.push('student_document');

        if (!fields.map((field) => validateField(field)).every(Boolean)) return;

        post(route('register'));
    };

    const summaryName = [data.salutation, data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ') || '—';
    const selectedFee = feeCategories.find((c) => c.key === data.fee_category);
    const summaryCategory = selectedFee
        ? `${selectedFee.label} — ${selectedFee.currency} ${Number(selectedFee.amount).toLocaleString()}`
        : 'Registration category not selected yet';

    return (
        <AuthSplitPanelLayout contentClassName="max-w-[560px]">
            <Head title="Register" />

            <div className="flex flex-col gap-2">
                <h1 className="font-serif text-[26px] font-semibold">Register to participate</h1>
                <p className="text-sm leading-relaxed text-[hsl(30,8%,42%)]">
                    Create your account to register, submit an abstract and receive your payment control number.
                </p>
            </div>

            <div className="flex items-start">
                {STEPS.map((label, i) => {
                    const done = i < step;
                    const active = i === step;

                    return (
                        <div key={label} className="relative flex flex-1 flex-col items-center gap-1.5">
                            <div
                                className={cn(
                                    'z-10 flex size-8 items-center justify-center rounded-full font-serif text-sm font-bold',
                                    done && 'bg-[#67b52f] text-white',
                                    active && 'bg-[#135eeb] text-white',
                                    !done && !active && 'bg-[#eaf1ff] text-[#135eeb]',
                                )}
                            >
                                {done ? <Check className="size-4" /> : i + 1}
                            </div>
                            <div
                                className={cn(
                                    'text-center text-[11px] font-semibold tracking-wide uppercase',
                                    active ? 'text-[#135eeb]' : done ? 'text-[#67b52f]' : 'text-[hsl(30,8%,55%)]',
                                )}
                            >
                                {label}
                            </div>
                            {i < STEPS.length - 1 && (
                                <div
                                    className={cn(
                                        'absolute top-4 left-[calc(50%+22px)] h-0.5 w-[calc(100%-44px)]',
                                        done ? 'bg-[#67b52f]' : 'bg-[hsl(35,18%,87%)]',
                                    )}
                                />
                            )}
                        </div>
                    );
                })}
            </div>

            <form
                onSubmit={submit}
                noValidate
                className="flex flex-col gap-5 rounded-2xl border border-[hsl(35,18%,87%)] bg-[hsl(40,30%,99%)] p-7 shadow-[0_1px_2px_rgba(0,0,0,0.05)]"
            >
                {step === 0 && (
                    <div className="flex flex-col gap-5">
                        <div className="grid grid-cols-[8rem_1fr] gap-4">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="salutation">Title *</Label>
                                <Select value={data.salutation} onValueChange={(value) => updateField('salutation', value)}>
                                    <SelectTrigger id="salutation" aria-invalid={Boolean(errors.salutation)}>
                                        <SelectValue placeholder="Select" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {salutations.map((s) => (
                                            <SelectItem key={s} value={s}>
                                                {s}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.salutation} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="first_name">First name *</Label>
                                <Input
                                    id="first_name"
                                    autoFocus
                                    autoComplete="given-name"
                                    value={data.first_name}
                                    onChange={(e) => updateField('first_name', e.target.value)}
                                    aria-invalid={Boolean(errors.first_name)}
                                    disabled={processing}
                                    placeholder="First name"
                                />
                                <InputError message={errors.first_name} />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="middle_name">Middle name (optional)</Label>
                                <Input
                                    id="middle_name"
                                    autoComplete="additional-name"
                                    value={data.middle_name}
                                    onChange={(e) => updateField('middle_name', e.target.value)}
                                    aria-invalid={Boolean(errors.middle_name)}
                                    disabled={processing}
                                    placeholder="Middle name"
                                />
                                <InputError message={errors.middle_name} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="last_name">Family name *</Label>
                                <Input
                                    id="last_name"
                                    autoComplete="family-name"
                                    value={data.last_name}
                                    onChange={(e) => updateField('last_name', e.target.value)}
                                    aria-invalid={Boolean(errors.last_name)}
                                    disabled={processing}
                                    placeholder="Family name"
                                />
                                <InputError message={errors.last_name} />
                            </div>
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="email">Email address *</Label>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="email"
                                value={data.email}
                                onChange={(e) => updateField('email', e.target.value)}
                                aria-invalid={Boolean(errors.email)}
                                disabled={processing}
                                placeholder="email@example.com"
                            />
                            <p className="text-xs leading-relaxed text-[hsl(30,8%,42%)]">
                                We will send your verification link and control number to this address.
                            </p>
                            <InputError message={errors.email} />
                        </div>
                    </div>
                )}

                {step === 1 && (
                    <div className="flex flex-col gap-5">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="institution_id">Institution *</Label>
                            <Select
                                value={data.institution_id}
                                onValueChange={(value) => {
                                    const nextData = {
                                        ...data,
                                        institution_id: value,
                                        institution_other: value === 'other' ? data.institution_other : '',
                                    };
                                    setData(nextData);
                                    clearErrors('institution_id');
                                    if (value !== 'other') clearErrors('institution_other');
                                }}
                            >
                                <SelectTrigger id="institution_id" aria-invalid={Boolean(errors.institution_id)}>
                                    <SelectValue placeholder="Select your institution" />
                                </SelectTrigger>
                                <SelectContent>
                                    {institutions.map((institution) => (
                                        <SelectItem key={institution.id} value={String(institution.id)}>
                                            {institution.name}
                                        </SelectItem>
                                    ))}
                                    <SelectItem value="other">Other (not listed)</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.institution_id} />
                            {data.institution_id === 'other' && (
                                <Input
                                    id="institution_other"
                                    value={data.institution_other}
                                    onChange={(e) => updateField('institution_other', e.target.value)}
                                    aria-invalid={Boolean(errors.institution_other)}
                                    disabled={processing}
                                    placeholder="Enter your institution or organization"
                                />
                            )}
                            <InputError message={errors.institution_other} />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="phone">Mobile number *</Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    value={data.phone}
                                    onChange={(e) => updateField('phone', e.target.value)}
                                    aria-invalid={Boolean(errors.phone)}
                                    disabled={processing}
                                    placeholder="+255 7XX XXX XXX"
                                />
                                <InputError message={errors.phone} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="country">Country *</Label>
                                <Input
                                    id="country"
                                    list="country-list"
                                    value={data.country}
                                    onChange={(e) => updateField('country', e.target.value)}
                                    aria-invalid={Boolean(errors.country)}
                                    disabled={processing}
                                    placeholder="e.g. Tanzania"
                                />
                                <datalist id="country-list">
                                    {eastAfricaCountries.map((c) => (
                                        <option key={c} value={c} />
                                    ))}
                                </datalist>
                                <InputError message={errors.country} />
                            </div>
                        </div>
                        <p className="text-xs leading-relaxed text-[hsl(30,8%,42%)]">
                            Participants from East African Community countries qualify for the East African fee tier.
                        </p>
                    </div>
                )}

                {step === 2 && (
                    <div className="flex flex-col gap-6">
                        <fieldset className="flex flex-col gap-2.5">
                            <legend className="mb-2.5 text-sm font-medium">Type of participant *</legend>
                            <div className="grid grid-cols-2 gap-2">
                                {Object.entries(participantTypes).map(([key, label]) => {
                                    const selected = data.participant_type === key;
                                    return (
                                        <button
                                            key={key}
                                            type="button"
                                            onClick={() => {
                                                setData({ ...data, participant_type: key, fee_category: '', student_document: null });
                                                clearErrors('participant_type', 'fee_category', 'student_document');
                                            }}
                                            className={cn(
                                                'flex min-h-11 items-center gap-2.5 rounded-lg border px-3 py-2 text-left text-sm',
                                                selected ? 'border-2 border-[#135eeb] bg-[#eaf1ff]' : 'border-[hsl(35,18%,85%)] bg-[hsl(40,30%,99%)]',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'size-4 shrink-0 rounded-full border-2 bg-white',
                                                    selected ? 'border-[5px] border-[#135eeb]' : 'border-[hsl(35,18%,75%)]',
                                                )}
                                            />
                                            <span>{label}</span>
                                        </button>
                                    );
                                })}
                            </div>
                            <InputError message={errors.participant_type} />
                        </fieldset>

                        {data.participant_type && (
                            <div className="flex flex-col gap-2.5">
                                <div className="text-sm font-medium">Registration category *</div>
                                <div className="grid grid-cols-2 gap-3">
                                    {availableFeeCategories.map((category) => {
                                        const selected = data.fee_category === category.key;
                                        const accent = isStudentCategory ? '#67b52f' : '#135eeb';
                                        return (
                                            <button
                                                key={category.key}
                                                type="button"
                                                onClick={() => {
                                                    setData({ ...data, fee_category: category.key });
                                                    clearErrors('fee_category');
                                                }}
                                                style={{ borderColor: selected ? accent : undefined }}
                                                className={cn(
                                                    'flex flex-col items-start gap-2.5 rounded-xl border p-4.5 text-left',
                                                    selected ? 'border-2' : 'border-[hsl(35,18%,85%)]',
                                                    selected ? (isStudentCategory ? 'bg-[#67b52f]/[0.08]' : 'bg-[#eaf1ff]') : 'bg-[hsl(40,30%,99%)]',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'inline-flex rounded-full px-3 py-1 text-[11px] font-semibold tracking-wide uppercase',
                                                        isStudentCategory ? 'bg-[#67b52f]/10 text-[#67b52f]' : 'bg-[#eaf1ff] text-[#135eeb]',
                                                    )}
                                                >
                                                    {isStudentCategory ? 'Student' : 'Participant'}
                                                </span>
                                                <span className="text-sm leading-snug font-semibold">{category.label}</span>
                                                <span className="font-serif text-[22px] font-bold" style={{ color: accent }}>
                                                    {category.currency} {Number(category.amount).toLocaleString()}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                                <p className="text-xs leading-relaxed text-[hsl(30,8%,42%)]">
                                    This selection determines the fee and billing category used for your control number.
                                </p>
                                <InputError message={errors.fee_category} />
                            </div>
                        )}

                        {isStudentCategory && (
                            <div className="flex flex-col gap-2.5 rounded-xl border border-[#67b52f]/25 bg-[#67b52f]/5 p-4">
                                <div>
                                    <Label htmlFor="student_document">Student verification document *</Label>
                                    <p className="mt-1 text-xs leading-relaxed text-[hsl(30,8%,42%)]">
                                        Upload a valid student ID in PDF, JPG, JPEG, or PNG format. Maximum file size: 10 MB.
                                    </p>
                                </div>
                                <Input
                                    id="student_document"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    onChange={(e) => {
                                        const nextData = { ...data, student_document: e.target.files?.[0] ?? null };
                                        setData('student_document', nextData.student_document);
                                        validateField('student_document', nextData);
                                    }}
                                    aria-invalid={Boolean(errors.student_document)}
                                    disabled={processing}
                                />
                                <InputError message={errors.student_document} />
                            </div>
                        )}
                    </div>
                )}

                {step === 3 && (
                    <div className="flex flex-col gap-5">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="password">Password *</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={data.password}
                                    onChange={(e) => updateField('password', e.target.value)}
                                    aria-invalid={Boolean(errors.password)}
                                    disabled={processing}
                                    placeholder="Password"
                                />
                                <p className="text-xs text-[hsl(30,8%,42%)]">At least 8 characters.</p>
                                <InputError message={errors.password} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="password_confirmation">Confirm password *</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    value={data.password_confirmation}
                                    onChange={(e) => updateField('password_confirmation', e.target.value)}
                                    aria-invalid={Boolean(errors.password_confirmation)}
                                    disabled={processing}
                                    placeholder="Confirm password"
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>
                        </div>
                        <div className="flex flex-col gap-2 rounded-xl bg-[#eaf1ff] px-4.5 py-4">
                            <div className="text-[11px] font-semibold tracking-wide text-[#135eeb] uppercase">Your registration summary</div>
                            <div className="text-sm leading-relaxed text-[#0f2350]">{summaryName}</div>
                            <div className="text-[13px] leading-relaxed text-[#0f2350]">{summaryCategory}</div>
                            <div className="text-xs leading-relaxed text-[#0f2350]/70">
                                After creating your account, you will need to <strong>confirm your email</strong> to continue.
                            </div>
                        </div>
                    </div>
                )}

                <div className="flex items-center justify-between gap-3 border-t border-[hsl(35,18%,87%)] pt-5">
                    {step > 0 ? (
                        <Button type="button" variant="outline" onClick={goBack} className="h-10">
                            Back
                        </Button>
                    ) : (
                        <span />
                    )}
                    {step < STEPS.length - 1 ? (
                        <Button type="button" onClick={goNext} className="h-11 bg-[#135eeb] px-7 font-semibold hover:bg-[#135eeb]/90">
                            Continue
                        </Button>
                    ) : (
                        <Button type="submit" disabled={processing} className="h-11 bg-[#67b52f] px-7 font-semibold hover:bg-[#67b52f]/90">
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Create account
                        </Button>
                    )}
                </div>
            </form>

            <p className="text-center text-sm text-[hsl(30,8%,42%)]">
                Already have an account?{' '}
                <TextLink href={route('login')} className="font-medium text-[#135eeb] decoration-transparent">
                    Log in
                </TextLink>
            </p>
        </AuthSplitPanelLayout>
    );
}
