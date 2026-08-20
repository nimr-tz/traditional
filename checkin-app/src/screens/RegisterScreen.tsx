import React, { useEffect, useMemo, useState } from 'react';
import {
    ActivityIndicator,
    KeyboardAvoidingView,
    Platform,
    Pressable,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    View,
} from 'react-native';
import { DeskOptions, RegistrationResult, apiErrorMessage, fetchDeskOptions, registerAttendee } from '../api/client';

const EMPTY_FORM = {
    name: '',
    email: '',
    phone: '',
    institution_id: '',
    institution_other: '',
    participant_type: '',
    country: '',
    fee_category: '',
};

function formatAmount(amount: string, currency: string) {
    const value = Number(amount);
    return `${currency} ${Number.isNaN(value) ? amount : value.toLocaleString()}`;
}

export default function RegisterScreen() {
    const [form, setForm] = useState(EMPTY_FORM);
    const [options, setOptions] = useState<DeskOptions | null>(null);
    const [countryQuery, setCountryQuery] = useState('');
    const [institutionQuery, setInstitutionQuery] = useState('');
    const [registered, setRegistered] = useState<RegistrationResult | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        fetchDeskOptions()
            .then(setOptions)
            .catch(() => setError('Could not load the fee categories. Pull the app back to the desk network and reopen this tab.'));
    }, []);

    const update = (field: keyof typeof form, value: string) => {
        setForm((current) => ({ ...current, [field]: value }));
    };

    // The server runs FeeTier::guard on every registration, so a mismatched
    // country and category is rejected there regardless. Narrowing the list
    // here just keeps the desk from picking one that will bounce.
    const selectableCategories = useMemo(() => {
        if (!options) return [];

        const isEastAfrican = options.east_africa_countries.includes(form.country);
        const wantsStudent = form.participant_type === 'student';

        return options.fee_categories.filter((category) => {
            const isStudent = category.key.startsWith('student_');
            if (isStudent !== wantsStudent) return false;
            if (!form.country) return true;
            return isEastAfrican ? category.key.endsWith('_east_africa') : category.key.endsWith('_non_east_africa');
        });
    }, [options, form.country, form.participant_type]);

    const countryMatches = useMemo(() => {
        if (!options || countryQuery.trim().length < 2) return [];
        const query = countryQuery.trim().toLowerCase();
        return options.countries.filter((country) => country.toLowerCase().includes(query)).slice(0, 6);
    }, [options, countryQuery]);

    // Same pattern as country: type to filter rather than scroll a long list,
    // with an explicit "Other" row always reachable regardless of what's typed
    // — the institution list is admin-curated and won't have everyone in it.
    const institutionMatches = useMemo(() => {
        if (!options) return [];
        if (institutionQuery.trim().length < 2) return [];
        const query = institutionQuery.trim().toLowerCase();
        return options.institutions.filter((institution) => institution.name.toLowerCase().includes(query)).slice(0, 6);
    }, [options, institutionQuery]);

    const selectedInstitutionName =
        form.institution_id === 'other' ? 'Other (not listed)' : (options?.institutions.find((i) => String(i.id) === form.institution_id)?.name ?? '');

    const submit = async () => {
        if (!form.name.trim() || !form.email.trim() || !form.phone.trim() || !form.participant_type) {
            setError('Name, email, phone and participant type are required.');
            return;
        }

        if (!form.institution_id) {
            setError('Select an institution.');
            return;
        }

        if (form.institution_id === 'other' && !form.institution_other.trim()) {
            setError('Enter their institution or organization.');
            return;
        }

        if (!form.country) {
            setError('Select a country — it decides which registration fee applies.');
            return;
        }

        if (!form.fee_category) {
            setError('Select a registration category.');
            return;
        }

        setLoading(true);
        setError(null);
        try {
            setRegistered(
                await registerAttendee({
                    ...form,
                    name: form.name.trim(),
                    email: form.email.trim().toLowerCase(),
                    phone: form.phone.trim(),
                    institution_other: form.institution_id === 'other' ? form.institution_other.trim() : undefined,
                }),
            );
        } catch (requestError) {
            setError(apiErrorMessage(requestError, 'Registration failed. Check the connection.'));
        } finally {
            setLoading(false);
        }
    };

    const reset = () => {
        setForm(EMPTY_FORM);
        setCountryQuery('');
        setInstitutionQuery('');
        setRegistered(null);
        setError(null);
    };

    if (registered) {
        const { user, billing } = registered;
        const controlNumberReady = billing.status === 'ready' && billing.control_number;

        return (
            <ScrollView style={registerStyles.screen} contentContainerStyle={registerStyles.successScreen}>
                <View
                    style={[registerStyles.successMark, !controlNumberReady && registerStyles.successMarkPending]}
                    accessibilityLabel="Registration recorded"
                >
                    <Text style={registerStyles.successMarkText}>{controlNumberReady ? '✓' : '…'}</Text>
                </View>
                <Text style={registerStyles.successTitle}>Registered — payment outstanding</Text>
                <Text style={registerStyles.successName}>{user.name}</Text>
                <Text style={registerStyles.successDetail}>{user.email}</Text>
                {user.fee_amount && user.currency ? (
                    <Text style={registerStyles.amountDue}>{formatAmount(user.fee_amount, user.currency)} due</Text>
                ) : null}

                <View style={registerStyles.codeBlock}>
                    <Text style={registerStyles.codeLabel}>CONTROL NUMBER</Text>
                    {controlNumberReady ? (
                        <Text selectable style={registerStyles.code}>{billing.control_number}</Text>
                    ) : (
                        <Text style={registerStyles.codePending}>Not issued yet</Text>
                    )}
                </View>

                <Text style={[registerStyles.successHint, billing.status === 'blocked' && registerStyles.blockedHint]}>
                    {billing.message}
                </Text>
                <Text style={registerStyles.successHint}>
                    No badge is issued until the payment clears. Once they have paid, find them under Attendance to check them in.
                </Text>

                <Pressable style={registerStyles.primaryButton} onPress={reset} accessibilityRole="button">
                    <Text style={registerStyles.primaryButtonText}>Register another attendee</Text>
                </Pressable>
            </ScrollView>
        );
    }

    return (
        <KeyboardAvoidingView style={registerStyles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <ScrollView
                style={registerStyles.screen}
                contentContainerStyle={registerStyles.content}
                keyboardShouldPersistTaps="handled"
            >
                <Text style={registerStyles.heading}>Register attendee</Text>
                <Text style={registerStyles.intro}>
                    Registers a walk-in and issues a control number to pay with. The badge follows once the payment clears — this does not
                    check anyone in.
                </Text>

                <Field label="Full name *">
                    <TextInput
                        style={registerStyles.input}
                        value={form.name}
                        onChangeText={(value) => update('name', value)}
                        autoCapitalize="words"
                        autoComplete="name"
                        returnKeyType="next"
                    />
                </Field>
                <Field label="Email *">
                    <TextInput
                        style={registerStyles.input}
                        value={form.email}
                        onChangeText={(value) => update('email', value)}
                        autoCapitalize="none"
                        autoComplete="email"
                        keyboardType="email-address"
                        returnKeyType="next"
                    />
                </Field>
                <Field label="Phone *">
                    <TextInput
                        style={registerStyles.input}
                        value={form.phone}
                        onChangeText={(value) => update('phone', value)}
                        autoComplete="tel"
                        keyboardType="phone-pad"
                        returnKeyType="next"
                    />
                </Field>
                <Text style={registerStyles.label}>Institution *</Text>
                {form.institution_id ? (
                    <Pressable
                        style={registerStyles.selectedCountry}
                        onPress={() => {
                            update('institution_id', '');
                            update('institution_other', '');
                            setInstitutionQuery('');
                        }}
                        accessibilityRole="button"
                        accessibilityHint="Clears the selected institution"
                    >
                        <Text style={registerStyles.selectedCountryText}>{selectedInstitutionName}</Text>
                        <Text style={registerStyles.selectedCountryChange}>Change</Text>
                    </Pressable>
                ) : (
                    <View style={registerStyles.countryPicker}>
                        <TextInput
                            style={registerStyles.input}
                            value={institutionQuery}
                            onChangeText={setInstitutionQuery}
                            placeholder="Start typing an institution"
                            autoCapitalize="words"
                        />
                        {institutionMatches.map((institution) => (
                            <Pressable
                                key={institution.id}
                                style={registerStyles.countryMatch}
                                onPress={() => {
                                    update('institution_id', String(institution.id));
                                    setInstitutionQuery('');
                                }}
                                accessibilityRole="button"
                            >
                                <Text style={registerStyles.countryMatchText}>{institution.name}</Text>
                            </Pressable>
                        ))}
                        <Pressable
                            style={registerStyles.countryMatch}
                            onPress={() => {
                                update('institution_id', 'other');
                                setInstitutionQuery('');
                            }}
                            accessibilityRole="button"
                        >
                            <Text style={registerStyles.countryMatchText}>Other (not listed)</Text>
                        </Pressable>
                    </View>
                )}
                {form.institution_id === 'other' && (
                    <Field label="Their institution or organization *">
                        <TextInput
                            style={registerStyles.input}
                            value={form.institution_other}
                            onChangeText={(value) => update('institution_other', value)}
                            autoCapitalize="words"
                            returnKeyType="done"
                        />
                    </Field>
                )}

                <Text style={registerStyles.label}>Participant type *</Text>
                <View style={registerStyles.typeOptions}>
                    {Object.entries(options?.participant_types ?? {}).map(([value, label]) => {
                        const selected = form.participant_type === value;
                        return (
                            <Pressable
                                key={value}
                                style={[registerStyles.typeOption, selected && registerStyles.typeOptionSelected]}
                                onPress={() => {
                                    update('participant_type', value);
                                    // Student and non-student draw from different
                                    // price lists, so a held-over choice would be
                                    // wrong the moment this changes.
                                    update('fee_category', '');
                                }}
                                accessibilityRole="radio"
                                accessibilityState={{ checked: selected }}
                            >
                                <Text style={[registerStyles.typeText, selected && registerStyles.typeTextSelected]}>{label}</Text>
                            </Pressable>
                        );
                    })}
                </View>

                <Text style={registerStyles.label}>Country *</Text>
                {form.country ? (
                    <Pressable
                        style={registerStyles.selectedCountry}
                        onPress={() => {
                            update('country', '');
                            update('fee_category', '');
                            setCountryQuery('');
                        }}
                        accessibilityRole="button"
                        accessibilityHint="Clears the selected country"
                    >
                        <Text style={registerStyles.selectedCountryText}>{form.country}</Text>
                        <Text style={registerStyles.selectedCountryChange}>Change</Text>
                    </Pressable>
                ) : (
                    <View style={registerStyles.countryPicker}>
                        <TextInput
                            style={registerStyles.input}
                            value={countryQuery}
                            onChangeText={setCountryQuery}
                            placeholder="Start typing a country"
                            autoCapitalize="words"
                        />
                        {countryMatches.map((country) => (
                            <Pressable
                                key={country}
                                style={registerStyles.countryMatch}
                                onPress={() => {
                                    update('country', country);
                                    update('fee_category', '');
                                    setCountryQuery('');
                                }}
                                accessibilityRole="button"
                            >
                                <Text style={registerStyles.countryMatchText}>{country}</Text>
                            </Pressable>
                        ))}
                    </View>
                )}
                <Text style={registerStyles.hint}>The country decides whether the East African or international rate applies.</Text>

                <Text style={registerStyles.label}>Registration category *</Text>
                {!form.country || !form.participant_type ? (
                    <Text style={registerStyles.hint}>Pick a participant type and country first.</Text>
                ) : selectableCategories.length === 0 ? (
                    <Text style={registerStyles.hint}>No active fee category matches that combination. Check Conference Settings.</Text>
                ) : (
                    <View style={registerStyles.categoryOptions}>
                        {selectableCategories.map((category) => {
                            const selected = form.fee_category === category.key;
                            return (
                                <Pressable
                                    key={category.key}
                                    style={[registerStyles.categoryOption, selected && registerStyles.categoryOptionSelected]}
                                    onPress={() => update('fee_category', category.key)}
                                    accessibilityRole="radio"
                                    accessibilityState={{ checked: selected }}
                                >
                                    <Text style={[registerStyles.categoryLabel, selected && registerStyles.categoryLabelSelected]}>
                                        {category.label}
                                    </Text>
                                    <Text style={[registerStyles.categoryAmount, selected && registerStyles.categoryLabelSelected]}>
                                        {formatAmount(category.amount, category.currency)}
                                    </Text>
                                </Pressable>
                            );
                        })}
                    </View>
                )}

                {form.participant_type === 'student' ? (
                    <Text style={registerStyles.warning}>
                        Student rates need a verified student ID before a control number can be issued. Registering here is fine, but they
                        will have to upload the document online before they can pay.
                    </Text>
                ) : null}

                {error ? <Text style={registerStyles.error}>{error}</Text> : null}

                <Pressable
                    style={[registerStyles.primaryButton, loading && registerStyles.buttonDisabled]}
                    onPress={submit}
                    disabled={loading}
                    accessibilityRole="button"
                >
                    {loading ? <ActivityIndicator color="#ffffff" /> : <Text style={registerStyles.primaryButtonText}>Register attendee</Text>}
                </Pressable>
            </ScrollView>
        </KeyboardAvoidingView>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <View style={registerStyles.field}>
            <Text style={registerStyles.label}>{label}</Text>
            {children}
        </View>
    );
}

const registerStyles = StyleSheet.create({
    flex: { flex: 1 },
    screen: { flex: 1, backgroundColor: '#f7f5ef' },
    content: { padding: 20, paddingBottom: 40 },
    heading: { color: '#173f2a', fontSize: 28, fontWeight: '700', marginBottom: 6 },
    intro: { color: '#536159', fontSize: 15, lineHeight: 22, marginBottom: 24 },
    field: { marginBottom: 16 },
    label: { color: '#24372c', fontSize: 14, fontWeight: '600', marginBottom: 7 },
    input: {
        minHeight: 50,
        backgroundColor: '#ffffff',
        borderColor: '#c8cec9',
        borderWidth: 1,
        borderRadius: 6,
        color: '#172019',
        fontSize: 16,
        paddingHorizontal: 14,
    },
    hint: { color: '#647168', fontSize: 13, lineHeight: 19, marginBottom: 18 },
    warning: {
        color: '#7a5216',
        backgroundColor: '#fbf1dc',
        padding: 12,
        borderRadius: 6,
        marginBottom: 16,
        fontSize: 13,
        lineHeight: 19,
    },
    countryPicker: { marginBottom: 8 },
    countryMatch: {
        minHeight: 46,
        justifyContent: 'center',
        paddingHorizontal: 14,
        borderBottomWidth: 1,
        borderColor: '#e1e5e2',
        backgroundColor: '#ffffff',
    },
    countryMatchText: { color: '#24372c', fontSize: 15 },
    selectedCountry: {
        minHeight: 50,
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingHorizontal: 14,
        borderWidth: 1,
        borderColor: '#173f2a',
        borderRadius: 6,
        backgroundColor: '#ffffff',
        marginBottom: 8,
    },
    selectedCountryText: { color: '#172019', fontSize: 16, fontWeight: '600' },
    selectedCountryChange: { color: '#1d6b41', fontSize: 14, fontWeight: '700' },
    categoryOptions: { gap: 8, marginBottom: 18 },
    categoryOption: {
        minHeight: 56,
        justifyContent: 'center',
        paddingHorizontal: 14,
        paddingVertical: 10,
        borderWidth: 1,
        borderColor: '#aeb8b1',
        borderRadius: 8,
        backgroundColor: '#ffffff',
    },
    categoryOptionSelected: { backgroundColor: '#173f2a', borderColor: '#173f2a' },
    categoryLabel: { color: '#24372c', fontSize: 15, fontWeight: '600' },
    categoryLabelSelected: { color: '#ffffff' },
    categoryAmount: { color: '#647168', fontSize: 14, marginTop: 3 },
    amountDue: { color: '#173f2a', fontSize: 17, fontWeight: '700', marginTop: 10 },
    codePending: { color: '#8a7550', fontSize: 16, fontWeight: '600', textAlign: 'center' },
    blockedHint: { color: '#7a5216', backgroundColor: '#fbf1dc', padding: 12, borderRadius: 6 },
    successMarkPending: { backgroundColor: '#b8863b' },
    typeOptions: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 20 },
    typeOption: {
        minHeight: 44,
        justifyContent: 'center',
        paddingHorizontal: 14,
        borderWidth: 1,
        borderColor: '#aeb8b1',
        borderRadius: 22,
        backgroundColor: '#ffffff',
    },
    typeOptionSelected: { backgroundColor: '#173f2a', borderColor: '#173f2a' },
    typeText: { color: '#354b3e', fontSize: 14, fontWeight: '600' },
    typeTextSelected: { color: '#ffffff' },
    primaryButton: {
        minHeight: 52,
        backgroundColor: '#173f2a',
        borderRadius: 6,
        alignItems: 'center',
        justifyContent: 'center',
        paddingHorizontal: 20,
        marginTop: 4,
    },
    primaryButtonText: { color: '#ffffff', fontSize: 16, fontWeight: '700' },
    buttonDisabled: { opacity: 0.65 },
    error: { color: '#a73526', backgroundColor: '#f9e7e3', padding: 12, borderRadius: 6, marginBottom: 12, lineHeight: 20 },
    successScreen: { flexGrow: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
    successMark: { width: 64, height: 64, borderRadius: 32, backgroundColor: '#173f2a', alignItems: 'center', justifyContent: 'center' },
    successMarkText: { color: '#ffffff', fontSize: 36, fontWeight: '700', lineHeight: 42 },
    successTitle: { color: '#173f2a', fontSize: 24, fontWeight: '700', marginTop: 20, marginBottom: 12 },
    successName: { color: '#172019', fontSize: 19, fontWeight: '600', textAlign: 'center' },
    successDetail: { color: '#647168', fontSize: 15, marginTop: 4 },
    codeBlock: { alignSelf: 'stretch', borderTopWidth: 1, borderBottomWidth: 1, borderColor: '#c8cec9', paddingVertical: 16, marginVertical: 24 },
    codeLabel: { color: '#69766e', fontSize: 11, letterSpacing: 1.2, textAlign: 'center', marginBottom: 6 },
    code: { color: '#173f2a', fontSize: 20, fontWeight: '700', letterSpacing: 1.4, textAlign: 'center' },
    successHint: { color: '#536159', fontSize: 15, lineHeight: 22, textAlign: 'center', marginBottom: 18 },
});
