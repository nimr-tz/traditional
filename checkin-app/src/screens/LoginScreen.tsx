import React, { useState } from 'react';
import { ActivityIndicator, Image, KeyboardAvoidingView, Platform, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { apiErrorMessage } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { colors } from '../theme';

export default function LoginScreen() {
    const { signIn } = useAuth();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    const submit = async () => {
        setError(null);
        setLoading(true);
        try {
            await signIn(email.trim(), password);
        } catch (e) {
            setError(apiErrorMessage(e, 'Login failed. Check your credentials and connection.'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <KeyboardAvoidingView style={styles.container} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            {/* The mark sits on green so staff can tell at a glance this is the
                NIMR app and not a look-alike asking for their password. */}
            <View style={styles.logoRing}>
                <Image source={require('../../assets/nimr-logo.png')} style={styles.logo} accessibilityIgnoresInvertColors />
            </View>

            <Text style={styles.title}>TMSC Attendance</Text>
            <Text style={styles.subtitle}>Staff access for registration and attendance</Text>

            <TextInput
                style={styles.input}
                placeholder="Email"
                placeholderTextColor={colors.textMuted}
                autoCapitalize="none"
                keyboardType="email-address"
                value={email}
                onChangeText={setEmail}
            />
            <TextInput
                style={styles.input}
                placeholder="Password"
                placeholderTextColor={colors.textMuted}
                secureTextEntry
                value={password}
                onChangeText={setPassword}
            />

            {error && <Text style={styles.error}>{error}</Text>}

            <TouchableOpacity style={[styles.button, loading && styles.buttonDisabled]} onPress={submit} disabled={loading}>
                {loading ? <ActivityIndicator color={colors.onPrimary} /> : <Text style={styles.buttonText}>Sign in</Text>}
            </TouchableOpacity>
        </KeyboardAvoidingView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, justifyContent: 'center', padding: 32, backgroundColor: colors.background },
    logoRing: {
        width: 68,
        height: 68,
        borderRadius: 34,
        backgroundColor: colors.primary,
        alignItems: 'center',
        justifyContent: 'center',
        alignSelf: 'center',
        marginBottom: 20,
    },
    logo: { width: 52, height: 52, borderRadius: 26, backgroundColor: colors.surface },
    title: { fontSize: 26, fontWeight: '700', color: colors.primaryDark, textAlign: 'center' },
    subtitle: { fontSize: 14, lineHeight: 20, color: colors.textMuted, textAlign: 'center', marginTop: 6, marginBottom: 30 },
    input: {
        minHeight: 50,
        backgroundColor: colors.surface,
        borderRadius: 8,
        paddingHorizontal: 14,
        marginBottom: 12,
        borderWidth: 1,
        borderColor: colors.border,
        fontSize: 16,
        color: colors.text,
    },
    button: {
        minHeight: 52,
        backgroundColor: colors.primary,
        borderRadius: 8,
        padding: 14,
        alignItems: 'center',
        justifyContent: 'center',
        marginTop: 18,
    },
    buttonDisabled: { opacity: 0.65 },
    buttonText: { color: colors.onPrimary, fontWeight: '700', fontSize: 16 },
    error: { color: colors.danger, marginBottom: 8, textAlign: 'center' },
});
