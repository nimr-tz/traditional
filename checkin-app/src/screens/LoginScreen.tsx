import React, { useState } from 'react';
import { ActivityIndicator, KeyboardAvoidingView, Platform, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { useAuth } from '../context/AuthContext';

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
        } catch (e: any) {
            setError(e?.response?.data?.message ?? 'Login failed. Check your credentials and connection.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <KeyboardAvoidingView style={styles.container} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <Text style={styles.title}>TMSC Attendance</Text>
            <Text style={styles.subtitle}>Staff access for registration and attendance</Text>

            <TextInput
                style={styles.input}
                placeholder="Email"
                autoCapitalize="none"
                keyboardType="email-address"
                value={email}
                onChangeText={setEmail}
            />
            <TextInput style={styles.input} placeholder="Password" secureTextEntry value={password} onChangeText={setPassword} />

            {error && <Text style={styles.error}>{error}</Text>}

            <TouchableOpacity style={styles.button} onPress={submit} disabled={loading}>
                {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Sign in</Text>}
            </TouchableOpacity>
        </KeyboardAvoidingView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, justifyContent: 'center', padding: 24, backgroundColor: '#f7f5ef' },
    title: { fontSize: 28, fontWeight: 'bold', color: '#173f2a', textAlign: 'center' },
    subtitle: { fontSize: 15, lineHeight: 22, color: '#536159', textAlign: 'center', marginTop: 6, marginBottom: 28 },
    input: { minHeight: 50, backgroundColor: '#fff', borderRadius: 6, paddingHorizontal: 14, marginBottom: 12, borderWidth: 1, borderColor: '#c8cec9', fontSize: 16 },
    button: { minHeight: 52, backgroundColor: '#173f2a', borderRadius: 6, padding: 14, alignItems: 'center', justifyContent: 'center', marginTop: 8 },
    buttonText: { color: '#fff', fontWeight: '600', fontSize: 16 },
    error: { color: '#b3402a', marginBottom: 8, textAlign: 'center' },
});
