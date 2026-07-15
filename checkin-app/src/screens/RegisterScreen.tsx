import React, { useState } from 'react';
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
import { RegisteredAttendee, registerAttendee } from '../api/client';

const PARTICIPANT_TYPES = [
    { value: 'researcher', label: 'Researcher' },
    { value: 'practitioner', label: 'Practitioner' },
    { value: 'academic', label: 'Academic' },
    { value: 'policy_maker', label: 'Policy maker' },
    { value: 'decision_maker', label: 'Decision maker' },
    { value: 'student', label: 'Student' },
    { value: 'media', label: 'Media' },
] as const;

const EMPTY_FORM = {
    name: '',
    email: '',
    phone: '',
    institution: '',
    participant_type: '',
};

export default function RegisterScreen() {
    const [form, setForm] = useState(EMPTY_FORM);
    const [registered, setRegistered] = useState<RegisteredAttendee | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    const update = (field: keyof typeof form, value: string) => {
        setForm((current) => ({ ...current, [field]: value }));
    };

    const submit = async () => {
        if (!form.name.trim() || !form.email.trim() || !form.phone.trim() || !form.participant_type) {
            setError('Name, email, phone and participant type are required.');
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
                    institution: form.institution.trim(),
                }),
            );
        } catch (requestError: any) {
            const validationErrors = requestError?.response?.data?.errors;
            const firstValidationError = validationErrors ? Object.values(validationErrors).flat()[0] : null;
            setError(String(firstValidationError ?? requestError?.response?.data?.message ?? 'Registration failed. Check the connection.'));
        } finally {
            setLoading(false);
        }
    };

    const reset = () => {
        setForm(EMPTY_FORM);
        setRegistered(null);
        setError(null);
    };

    if (registered) {
        return (
            <View style={registerStyles.successScreen}>
                <View style={registerStyles.successMark} accessibilityLabel="Registration successful">
                    <Text style={registerStyles.successMarkText}>✓</Text>
                </View>
                <Text style={registerStyles.successTitle}>Registration complete</Text>
                <Text style={registerStyles.successName}>{registered.name}</Text>
                <Text style={registerStyles.successDetail}>{registered.email}</Text>
                <View style={registerStyles.codeBlock}>
                    <Text style={registerStyles.codeLabel}>REGISTRATION CODE</Text>
                    <Text selectable style={registerStyles.code}>{registered.registration_code}</Text>
                </View>
                <Text style={registerStyles.successHint}>This attendee can now be marked present from Attendance.</Text>
                <Pressable style={registerStyles.primaryButton} onPress={reset} accessibilityRole="button">
                    <Text style={registerStyles.primaryButtonText}>Register another attendee</Text>
                </Pressable>
            </View>
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
                <Text style={registerStyles.intro}>Enter the attendee's details. Required fields are marked with *.</Text>

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
                <Field label="Institution">
                    <TextInput
                        style={registerStyles.input}
                        value={form.institution}
                        onChangeText={(value) => update('institution', value)}
                        autoCapitalize="words"
                        returnKeyType="done"
                    />
                </Field>

                <Text style={registerStyles.label}>Participant type *</Text>
                <View style={registerStyles.typeOptions}>
                    {PARTICIPANT_TYPES.map((type) => {
                        const selected = form.participant_type === type.value;
                        return (
                            <Pressable
                                key={type.value}
                                style={[registerStyles.typeOption, selected && registerStyles.typeOptionSelected]}
                                onPress={() => update('participant_type', type.value)}
                                accessibilityRole="radio"
                                accessibilityState={{ checked: selected }}
                            >
                                <Text style={[registerStyles.typeText, selected && registerStyles.typeTextSelected]}>{type.label}</Text>
                            </Pressable>
                        );
                    })}
                </View>

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
    successScreen: { flex: 1, backgroundColor: '#f7f5ef', alignItems: 'center', justifyContent: 'center', padding: 24 },
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
