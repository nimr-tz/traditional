import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AuthLayout from '@/layouts/auth-layout';

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

export default function Register({ salutations, participantTypes, feeCategories, eastAfricaCountries, institutions }: RegisterProps) {
    const { data, setData, post, processing, errors, reset, setError, clearErrors } = useForm<RegisterForm>({
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
        const text = typeof formData[field] === 'string' ? formData[field].trim() : '';

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

    const validateField = (field: RegisterField, formData: RegisterForm = data): boolean => {
        const message = validationMessage(field, formData);

        if (message) {
            setError(field, message);
        } else {
            clearErrors(field);
        }

        return !message;
    };

    const updateField = <K extends RegisterField>(field: K, value: RegisterForm[K]) => {
        const nextData = { ...data, [field]: value };
        setData(field, value);

        if (errors[field]) {
            validateField(field, nextData);
        }

        if (field === 'password' && (errors.password_confirmation || nextData.password_confirmation)) {
            validateField('password_confirmation', nextData);
        }
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

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title="Register for TMSC" description="Create your account to register and submit an abstract" contentClassName="max-w-4xl">
            <Head title="Register" />
            <form className="flex flex-col gap-6" onSubmit={submit} noValidate>
                <div className="grid gap-6">
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-[8rem_repeat(3,minmax(0,1fr))]">
                        <div className="grid gap-2">
                            <Label htmlFor="salutation">Title *</Label>
                            <Select value={data.salutation} onValueChange={(value) => updateField('salutation', value)}>
                                <SelectTrigger id="salutation" onBlur={() => validateField('salutation')} aria-invalid={Boolean(errors.salutation)}>
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

                        <div className="grid gap-2">
                            <Label htmlFor="first_name">First Name *</Label>
                            <Input
                                id="first_name"
                                type="text"
                                required
                                autoFocus
                                autoComplete="given-name"
                                value={data.first_name}
                                onChange={(e) => updateField('first_name', e.target.value)}
                                onBlur={() => validateField('first_name')}
                                aria-invalid={Boolean(errors.first_name)}
                                disabled={processing}
                                placeholder="First name"
                            />
                            <InputError message={errors.first_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="middle_name">Middle Name (optional)</Label>
                            <Input
                                id="middle_name"
                                type="text"
                                autoComplete="additional-name"
                                value={data.middle_name}
                                onChange={(e) => updateField('middle_name', e.target.value)}
                                onBlur={() => validateField('middle_name')}
                                aria-invalid={Boolean(errors.middle_name)}
                                disabled={processing}
                                placeholder="Middle name"
                            />
                            <InputError message={errors.middle_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="last_name">Family Name *</Label>
                            <Input
                                id="last_name"
                                type="text"
                                required
                                autoComplete="family-name"
                                value={data.last_name}
                                onChange={(e) => updateField('last_name', e.target.value)}
                                onBlur={() => validateField('last_name')}
                                aria-invalid={Boolean(errors.last_name)}
                                disabled={processing}
                                placeholder="Family name"
                            />
                            <InputError message={errors.last_name} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address *</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => updateField('email', e.target.value)}
                            onBlur={() => validateField('email')}
                            aria-invalid={Boolean(errors.email)}
                            disabled={processing}
                            placeholder="email@example.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
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
                            <SelectTrigger
                                id="institution_id"
                                onBlur={() => validateField('institution_id')}
                                aria-invalid={Boolean(errors.institution_id)}
                            >
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
                                type="text"
                                required
                                value={data.institution_other}
                                onChange={(e) => updateField('institution_other', e.target.value)}
                                onBlur={() => validateField('institution_other')}
                                aria-invalid={Boolean(errors.institution_other)}
                                disabled={processing}
                                placeholder="Enter your institution or organization"
                                className="mt-1"
                            />
                        )}
                        <InputError message={errors.institution_other} />
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="phone">Mobile number *</Label>
                            <Input
                                id="phone"
                                type="tel"
                                required
                                value={data.phone}
                                onChange={(e) => updateField('phone', e.target.value)}
                                onBlur={() => validateField('phone')}
                                aria-invalid={Boolean(errors.phone)}
                                disabled={processing}
                                placeholder="+255 7XX XXX XXX"
                            />
                            <InputError message={errors.phone} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="country">Country *</Label>
                            <Input
                                id="country"
                                type="text"
                                required
                                list="country-list"
                                value={data.country}
                                onChange={(e) => updateField('country', e.target.value)}
                                onBlur={() => validateField('country')}
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

                    <fieldset className="grid gap-2">
                        <legend className="text-sm font-medium">Type of Participant *</legend>
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {Object.entries(participantTypes).map(([key, label]) => (
                                <label
                                    key={key}
                                    className="border-input flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm"
                                >
                                    <input
                                        type="radio"
                                        name="participant_type"
                                        value={key}
                                        checked={data.participant_type === key}
                                        onChange={(e) => {
                                            setData({
                                                ...data,
                                                participant_type: e.target.value,
                                                fee_category: '',
                                                student_document: null,
                                            });
                                            clearErrors('participant_type', 'fee_category', 'student_document');
                                        }}
                                        onBlur={() => validateField('participant_type')}
                                        aria-invalid={Boolean(errors.participant_type)}
                                        disabled={processing}
                                        required
                                    />
                                    <span>{label}</span>
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.participant_type} />
                    </fieldset>

                    <div className="grid gap-2">
                        <Label htmlFor="fee_category">Registration category *</Label>
                        <Select
                            disabled={!data.participant_type}
                            value={data.fee_category}
                            onValueChange={(value) => {
                                const nextData = {
                                    ...data,
                                    fee_category: value,
                                    student_document: value.startsWith('student_') ? data.student_document : null,
                                };
                                setData(nextData);
                                clearErrors('fee_category');
                                if (!value.startsWith('student_')) clearErrors('student_document');
                            }}
                        >
                            <SelectTrigger id="fee_category" onBlur={() => validateField('fee_category')} aria-invalid={Boolean(errors.fee_category)}>
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
                        <p className="text-muted-foreground text-xs leading-5">
                            {data.participant_type
                                ? 'This selection determines the fee and billing category used for your control number.'
                                : 'Select your participant type first.'}
                        </p>
                        <InputError message={errors.fee_category} />
                    </div>

                    {isStudentCategory && (
                        <div className="grid gap-2 rounded-xl border border-[#67b52f]/25 bg-[#67b52f]/5 p-4">
                            <div>
                                <Label htmlFor="student_document">Student verification document *</Label>
                                <p className="text-muted-foreground mt-1 text-xs leading-5">
                                    Upload a valid student ID in PDF, JPG, JPEG, or PNG format. Maximum file size: 10 MB.
                                </p>
                            </div>
                            <Input
                                id="student_document"
                                name="student_document"
                                type="file"
                                required
                                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                onChange={(e) => {
                                    const nextData = { ...data, student_document: e.target.files?.[0] ?? null };
                                    setData('student_document', nextData.student_document);
                                    validateField('student_document', nextData);
                                }}
                                onBlur={() => validateField('student_document')}
                                aria-invalid={Boolean(errors.student_document)}
                                disabled={processing}
                            />
                            <InputError message={errors.student_document} />
                        </div>
                    )}

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Password *</Label>
                            <Input
                                id="password"
                                type="password"
                                required
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) => updateField('password', e.target.value)}
                                onBlur={() => validateField('password')}
                                aria-invalid={Boolean(errors.password)}
                                disabled={processing}
                                placeholder="Password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">Confirm password *</Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                required
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) => updateField('password_confirmation', e.target.value)}
                                onBlur={() => validateField('password_confirmation')}
                                aria-invalid={Boolean(errors.password_confirmation)}
                                disabled={processing}
                                placeholder="Confirm password"
                            />
                            <InputError message={errors.password_confirmation} />
                        </div>
                    </div>

                    <Button type="submit" className="mt-2 w-full md:mx-auto md:max-w-sm" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Create account
                    </Button>
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    Already have an account? <TextLink href={route('login')}>Log in</TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
