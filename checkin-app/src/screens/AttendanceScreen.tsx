import { CameraView, useCameraPermissions } from 'expo-camera';
import React, { useState } from 'react';
import {
    ActivityIndicator,
    FlatList,
    Pressable,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    View,
} from 'react-native';
import {
    apiErrorMessage,
    checkInById,
    lookupAttendee,
    Registrant,
    requestControlNumber,
    scanCode,
    ScanResult,
    unpaidRegistrantFrom,
    verifyPayment,
    waivePayment,
} from '../api/client';
import { useAuth } from '../context/AuthContext';
import { colors } from '../theme';

type AttendanceMode = 'scan' | 'search';

function formatAmount(amount: string | null, currency: string | null) {
    if (!amount || !currency) return null;
    const value = Number(amount);
    return `${currency} ${Number.isNaN(value) ? amount : value.toLocaleString()}`;
}

export default function AttendanceScreen() {
    const { canManageFinance } = useAuth();
    const [permission, requestPermission] = useCameraPermissions();
    const [mode, setMode] = useState<AttendanceMode>('scan');
    const [locked, setLocked] = useState(false);
    const [result, setResult] = useState<ScanResult | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [query, setQuery] = useState('');
    const [matches, setMatches] = useState<Registrant[]>([]);
    const [loading, setLoading] = useState(false);
    const [searched, setSearched] = useState(false);
    /** The unpaid registrant the desk is currently dealing with, if any. */
    const [owing, setOwing] = useState<Registrant | null>(null);
    const [notes, setNotes] = useState('');
    const [notice, setNotice] = useState<string | null>(null);

    const handleScan = async ({ data }: { data: string }) => {
        if (locked) return;
        setLocked(true);
        setError(null);
        try {
            setResult(await scanCode(data));
        } catch (requestError) {
            // An unpaid registrant comes back with the 422 so the desk gets a
            // way forward instead of just a refusal.
            const unpaid = unpaidRegistrantFrom(requestError);
            if (unpaid) {
                setOwing(unpaid);
            }
            setError(apiErrorMessage(requestError, 'Could not mark this attendee present.'));
        }
    };

    const settle = async (action: 'verify' | 'waive') => {
        if (!owing) return;

        if (action === 'waive' && !notes.trim()) {
            setError('A reason is required to waive a fee.');
            return;
        }

        setLoading(true);
        setError(null);
        try {
            const { message, user } = await (action === 'verify'
                ? verifyPayment(owing.id, notes.trim())
                : waivePayment(owing.id, notes.trim()));
            setOwing(null);
            setNotes('');
            setNotice(`${message} Their badge code is ${user.registration_code}.`);
            setMatches((current) => current.map((match) => (match.id === user.id ? user : match)));
        } catch (requestError) {
            setError(apiErrorMessage(requestError, 'Could not settle this payment.'));
        } finally {
            setLoading(false);
        }
    };

    const issueControlNumber = async () => {
        if (!owing) return;

        setLoading(true);
        setError(null);
        try {
            const { billing } = await requestControlNumber(owing.id);
            setNotice(billing.control_number ? `Control number ${billing.control_number} — ${billing.message}` : billing.message);
        } catch (requestError) {
            setError(apiErrorMessage(requestError, 'Could not request a control number.'));
        } finally {
            setLoading(false);
        }
    };

    const runSearch = async () => {
        const trimmedQuery = query.trim();
        if (trimmedQuery.length < 2) {
            setError('Enter at least two letters to search.');
            return;
        }

        setLoading(true);
        setError(null);
        setSearched(false);
        try {
            setMatches(await lookupAttendee(trimmedQuery));
            setSearched(true);
        } catch {
            setError('Search failed. Check the connection and try again.');
        } finally {
            setLoading(false);
        }
    };

    const markPresent = async (registrant: Registrant) => {
        if (!registrant.is_paid) {
            setOwing(registrant);
            setNotes('');
            setError(null);
            setNotice(null);
            return;
        }

        setLoading(true);
        setError(null);
        try {
            setResult(await checkInById(registrant.id));
            setMatches([]);
        } catch (requestError) {
            setError(apiErrorMessage(requestError, 'Could not mark this attendee present.'));
        } finally {
            setLoading(false);
        }
    };

    const reset = () => {
        setResult(null);
        setError(null);
        setLocked(false);
        setMatches([]);
        setQuery('');
        setSearched(false);
        setOwing(null);
        setNotes('');
        setNotice(null);
    };

    const changeMode = (nextMode: AttendanceMode) => {
        setMode(nextMode);
        reset();
    };

    if (result) {
        const alreadyPresent = result.already_checked_in;
        return (
            <View style={attendanceStyles.resultScreen}>
                <View style={[attendanceStyles.resultMark, alreadyPresent && attendanceStyles.resultMarkWarning]}>
                    <Text style={attendanceStyles.resultMarkText}>{alreadyPresent ? '!' : '✓'}</Text>
                </View>
                {/* Attendance is per day, so a repeat scan means "already today",
                    not "already ever" — a returning attendee is recorded afresh
                    each morning. */}
                <Text style={attendanceStyles.resultTitle}>{alreadyPresent ? 'Already recorded today' : 'Attendance recorded'}</Text>
                <Text style={attendanceStyles.resultName}>{result.user.name}</Text>
                {result.user.institution ? <Text style={attendanceStyles.resultDetail}>{result.user.institution}</Text> : null}
                {result.user.participant_type ? (
                    <Text style={attendanceStyles.resultDetail}>{formatParticipantType(result.user.participant_type)}</Text>
                ) : null}
                <Text style={attendanceStyles.resultTime}>
                    {alreadyPresent ? 'Scanned today at' : 'Recorded at'}{' '}
                    {new Date(result.checked_in_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                </Text>
                {result.days_attended > 1 ? (
                    <Text style={attendanceStyles.resultDetail}>{result.days_attended} days attended so far</Text>
                ) : null}
                <Pressable style={attendanceStyles.primaryButton} onPress={reset} accessibilityRole="button">
                    <Text style={attendanceStyles.primaryButtonText}>Take next attendance</Text>
                </Pressable>
            </View>
        );
    }

    if (owing) {
        const amount = formatAmount(owing.fee_amount, owing.currency);

        return (
            <ScrollView style={attendanceStyles.screen} contentContainerStyle={attendanceStyles.owingScreen}>
                <View style={attendanceStyles.owingMark}>
                    <Text style={attendanceStyles.owingMarkText}>!</Text>
                </View>
                <Text style={attendanceStyles.resultTitle}>Payment outstanding</Text>
                <Text style={attendanceStyles.resultName}>{owing.name}</Text>
                <Text style={attendanceStyles.resultDetail}>{owing.email}</Text>
                {amount ? <Text style={attendanceStyles.owingAmount}>{amount} due</Text> : null}
                <Text style={attendanceStyles.resultDetail}>
                    Status: {owing.payment_status ?? 'not started'}
                    {owing.control_number ? ` · Control number ${owing.control_number}` : ''}
                </Text>
                <Text style={attendanceStyles.owingNote}>No badge is issued until this is paid or waived.</Text>

                {error ? <Text style={attendanceStyles.error}>{error}</Text> : null}
                {notice ? <Text style={attendanceStyles.notice}>{notice}</Text> : null}

                {canManageFinance ? (
                    <View style={attendanceStyles.owingActions}>
                        <TextInput
                            style={attendanceStyles.notesInput}
                            value={notes}
                            onChangeText={setNotes}
                            placeholder="Notes — required to waive"
                            placeholderTextColor={colors.textMuted}
                            multiline
                        />
                        <Pressable
                            style={[attendanceStyles.primaryButton, loading && attendanceStyles.buttonDisabled]}
                            onPress={() => settle('verify')}
                            disabled={loading}
                            accessibilityRole="button"
                        >
                            <Text style={attendanceStyles.primaryButtonText}>Confirm payment received</Text>
                        </Pressable>
                        <Pressable
                            style={[attendanceStyles.waiveButton, loading && attendanceStyles.buttonDisabled]}
                            onPress={() => settle('waive')}
                            disabled={loading}
                            accessibilityRole="button"
                        >
                            <Text style={attendanceStyles.waiveButtonText}>Waive the fee</Text>
                        </Pressable>
                    </View>
                ) : (
                    <View style={attendanceStyles.owingActions}>
                        <Text style={attendanceStyles.owingNote}>
                            Only finance can settle a payment. Give the attendee their control number, or send them to the finance desk.
                        </Text>
                        <Pressable
                            style={[attendanceStyles.primaryButton, loading && attendanceStyles.buttonDisabled]}
                            onPress={issueControlNumber}
                            disabled={loading}
                            accessibilityRole="button"
                        >
                            <Text style={attendanceStyles.primaryButtonText}>
                                {owing.control_number ? 'Show control number again' : 'Issue a control number'}
                            </Text>
                        </Pressable>
                    </View>
                )}

                <Pressable style={attendanceStyles.retryButton} onPress={reset} accessibilityRole="button">
                    <Text style={attendanceStyles.retryButtonText}>Back to attendance</Text>
                </Pressable>
            </ScrollView>
        );
    }

    return (
        <View style={attendanceStyles.screen}>
            <View style={attendanceStyles.modeBar}>
                <ModeButton label="Scan QR" selected={mode === 'scan'} onPress={() => changeMode('scan')} />
                <ModeButton label="Search attendee" selected={mode === 'search'} onPress={() => changeMode('search')} />
            </View>

            {notice ? <Text style={attendanceStyles.notice}>{notice}</Text> : null}

            {mode === 'scan' ? (
                <ScanPanel
                    permission={permission}
                    requestPermission={requestPermission}
                    locked={locked}
                    onScan={handleScan}
                />
            ) : (
                <View style={attendanceStyles.searchPanel}>
                    <Text style={attendanceStyles.heading}>Find attendee</Text>
                    <Text style={attendanceStyles.intro}>
                        Search by name or email. Everyone is findable, paid or not — the row tells you which.
                    </Text>
                    <View style={attendanceStyles.searchRow}>
                        <TextInput
                            style={attendanceStyles.searchInput}
                            placeholder="Name or email"
                            placeholderTextColor={colors.textMuted}
                            value={query}
                            onChangeText={(value) => {
                                setQuery(value);
                                setSearched(false);
                            }}
                            onSubmitEditing={runSearch}
                            autoCapitalize="none"
                            returnKeyType="search"
                        />
                        <Pressable style={attendanceStyles.searchButton} onPress={runSearch} disabled={loading} accessibilityRole="button">
                            {loading ? <ActivityIndicator color={colors.onPrimary} /> : <Text style={attendanceStyles.searchButtonText}>Search</Text>}
                        </Pressable>
                    </View>

                    {error ? <Text style={attendanceStyles.error}>{error}</Text> : null}

                    <FlatList
                        data={matches}
                        keyExtractor={(item) => String(item.id)}
                        keyboardShouldPersistTaps="handled"
                        contentContainerStyle={attendanceStyles.resultsList}
                        ListEmptyComponent={searched && !loading && !error ? <Text style={attendanceStyles.empty}>No attendees found.</Text> : null}
                        renderItem={({ item }) => (
                            <View style={attendanceStyles.matchRow}>
                                <View style={attendanceStyles.matchText}>
                                    <Text style={attendanceStyles.matchName}>{item.name}</Text>
                                    <Text style={attendanceStyles.matchDetail}>{item.email}</Text>
                                    {item.institution ? <Text style={attendanceStyles.matchDetail}>{item.institution}</Text> : null}
                                    <Text style={item.is_paid ? attendanceStyles.paidTag : attendanceStyles.unpaidTag}>
                                        {item.is_paid
                                            ? item.is_checked_in_today
                                                ? 'Paid · in today'
                                                : item.days_attended > 0
                                                  ? `Paid · returning (${item.days_attended} day${item.days_attended === 1 ? '' : 's'})`
                                                  : 'Paid'
                                            : `Unpaid${formatAmount(item.fee_amount, item.currency) ? ` · ${formatAmount(item.fee_amount, item.currency)} due` : ''}`}
                                    </Text>
                                </View>
                                <Pressable
                                    style={[attendanceStyles.presentButton, !item.is_paid && attendanceStyles.resolveButton]}
                                    onPress={() => markPresent(item)}
                                    disabled={loading}
                                    accessibilityRole="button"
                                    accessibilityLabel={item.is_paid ? `Mark ${item.name} present` : `Resolve payment for ${item.name}`}
                                >
                                    <Text style={attendanceStyles.presentButtonText}>{item.is_paid ? 'Mark present' : 'Resolve'}</Text>
                                </Pressable>
                            </View>
                        )}
                    />
                </View>
            )}

            {mode === 'scan' && error ? (
                <View style={attendanceStyles.scanError}>
                    <Text style={attendanceStyles.errorText}>{error}</Text>
                    <Pressable style={attendanceStyles.retryButton} onPress={reset} accessibilityRole="button">
                        <Text style={attendanceStyles.retryButtonText}>Try again</Text>
                    </Pressable>
                </View>
            ) : null}
        </View>
    );
}

function ScanPanel({
    permission,
    requestPermission,
    locked,
    onScan,
}: {
    permission: ReturnType<typeof useCameraPermissions>[0];
    requestPermission: ReturnType<typeof useCameraPermissions>[1];
    locked: boolean;
    onScan: ({ data }: { data: string }) => void;
}) {
    if (!permission) {
        return <ActivityIndicator style={attendanceStyles.loadingScreen} color={colors.primary} />;
    }

    if (!permission.granted) {
        return (
            <View style={attendanceStyles.permissionScreen}>
                <Text style={attendanceStyles.heading}>Camera access required</Text>
                <Text style={attendanceStyles.permissionText}>Allow camera access to scan attendee QR codes.</Text>
                <Pressable style={attendanceStyles.primaryButton} onPress={requestPermission} accessibilityRole="button">
                    <Text style={attendanceStyles.primaryButtonText}>Allow camera</Text>
                </Pressable>
            </View>
        );
    }

    return (
        <View style={attendanceStyles.cameraPanel}>
            <CameraView
                style={StyleSheet.absoluteFill}
                facing="back"
                barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
                onBarcodeScanned={locked ? undefined : onScan}
            />
            <View style={attendanceStyles.cameraShadeTop} />
            <View style={attendanceStyles.cameraMiddle}>
                <View style={attendanceStyles.cameraShadeSide} />
                <View style={attendanceStyles.scanFrame} />
                <View style={attendanceStyles.cameraShadeSide} />
            </View>
            <View style={attendanceStyles.cameraShadeBottom}>
                <Text style={attendanceStyles.cameraInstruction}>Hold the QR code inside the frame</Text>
                {locked ? <ActivityIndicator color={colors.onPrimary} style={attendanceStyles.scanProgress} /> : null}
            </View>
        </View>
    );
}

function ModeButton({ label, selected, onPress }: { label: string; selected: boolean; onPress: () => void }) {
    return (
        <Pressable
            style={[attendanceStyles.modeButton, selected && attendanceStyles.modeButtonSelected]}
            onPress={onPress}
            accessibilityRole="tab"
            accessibilityState={{ selected }}
        >
            <Text style={[attendanceStyles.modeButtonText, selected && attendanceStyles.modeButtonTextSelected]}>{label}</Text>
        </Pressable>
    );
}

function formatParticipantType(value: string) {
    return value
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

const attendanceStyles = StyleSheet.create({
    screen: { flex: 1, backgroundColor: colors.background },
    modeBar: {
        flexDirection: 'row',
        padding: 12,
        gap: 8,
        backgroundColor: colors.surface,
        borderBottomWidth: 1,
        borderBottomColor: colors.headerDivider,
    },
    modeButton: { flex: 1, minHeight: 46, alignItems: 'center', justifyContent: 'center', borderRadius: 6 },
    modeButtonSelected: { backgroundColor: colors.primaryDark },
    modeButtonText: { color: '#43564A', fontSize: 15, fontWeight: '700' },
    modeButtonTextSelected: { color: colors.onPrimary },
    cameraPanel: { flex: 1, backgroundColor: '#111111' },
    cameraShadeTop: { flex: 1, backgroundColor: colors.scanShade },
    cameraMiddle: { height: 240, flexDirection: 'row' },
    cameraShadeSide: { flex: 1, backgroundColor: colors.scanShade },
    // Yellow, not white: the frame has to stay findable against a dark room and
    // against a badge held up in daylight.
    scanFrame: { width: 240, borderWidth: 3, borderColor: colors.scanFrame, borderRadius: 10 },
    cameraShadeBottom: { flex: 1.2, backgroundColor: colors.scanShade, alignItems: 'center', paddingTop: 26 },
    cameraInstruction: { color: colors.onPrimary, fontSize: 15, fontWeight: '600', textAlign: 'center', paddingHorizontal: 40 },
    scanProgress: { marginTop: 16 },
    searchPanel: { flex: 1, paddingTop: 18 },
    heading: { color: colors.primaryDark, fontSize: 22, fontWeight: '700', marginHorizontal: 20 },
    intro: { color: colors.textMuted, fontSize: 14, lineHeight: 20, marginHorizontal: 20, marginTop: 6, marginBottom: 16 },
    searchRow: { flexDirection: 'row', gap: 8, marginHorizontal: 20 },
    searchInput: {
        flex: 1,
        minHeight: 48,
        backgroundColor: colors.surface,
        borderColor: colors.borderStrong,
        borderWidth: 1,
        borderRadius: 6,
        paddingHorizontal: 14,
        fontSize: 16,
        color: colors.text,
    },
    searchButton: {
        minWidth: 84,
        minHeight: 48,
        backgroundColor: colors.primary,
        borderRadius: 6,
        alignItems: 'center',
        justifyContent: 'center',
        paddingHorizontal: 14,
    },
    searchButtonText: { color: colors.onPrimary, fontSize: 15, fontWeight: '700' },
    resultsList: { padding: 20, paddingBottom: 40 },
    matchRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 15, borderBottomWidth: 1, borderBottomColor: colors.divider },
    matchText: { flex: 1, paddingRight: 12 },
    matchName: { color: colors.text, fontSize: 16, fontWeight: '700', marginBottom: 3 },
    matchDetail: { color: colors.textFaint, fontSize: 13, lineHeight: 19 },
    presentButton: {
        minHeight: 44,
        justifyContent: 'center',
        paddingHorizontal: 13,
        borderWidth: 1,
        borderColor: colors.primary,
        borderRadius: 6,
    },
    presentButtonText: { color: colors.primary, fontSize: 13, fontWeight: '700' },
    empty: { color: colors.textMuted, fontSize: 15, textAlign: 'center', marginTop: 30 },
    error: {
        color: colors.danger,
        backgroundColor: colors.dangerSurface,
        padding: 12,
        borderRadius: 8,
        marginHorizontal: 20,
        marginTop: 14,
        lineHeight: 20,
    },
    scanError: { position: 'absolute', left: 20, right: 20, bottom: 24, backgroundColor: colors.surface, borderRadius: 10, padding: 16 },
    errorText: { color: '#912F22', fontSize: 15, lineHeight: 21, textAlign: 'center' },
    retryButton: { minHeight: 44, alignItems: 'center', justifyContent: 'center', marginTop: 8 },
    retryButtonText: { color: colors.primary, fontSize: 14, fontWeight: '700' },
    loadingScreen: { flex: 1 },
    permissionScreen: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24, backgroundColor: colors.background },
    permissionText: { color: colors.textSubtle, fontSize: 15, lineHeight: 22, textAlign: 'center', marginTop: 10, marginBottom: 20 },
    resultScreen: { flex: 1, backgroundColor: colors.background, alignItems: 'center', justifyContent: 'center', padding: 24 },
    resultMark: { width: 64, height: 64, borderRadius: 32, backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center' },
    // A repeat scan is a warning, not a failure - brown, never red.
    resultMarkWarning: { backgroundColor: colors.duplicate },
    resultMarkText: { color: colors.onPrimary, fontSize: 32, fontWeight: '700', lineHeight: 38 },
    resultTitle: { color: colors.primaryDark, fontSize: 22, fontWeight: '700', textAlign: 'center', marginTop: 18, marginBottom: 16 },
    resultName: { color: colors.text, fontSize: 18, fontWeight: '700', textAlign: 'center' },
    resultDetail: { color: colors.textMuted, fontSize: 14, marginTop: 4, textAlign: 'center' },
    resultTime: { color: colors.textSubtle, fontSize: 13, marginTop: 16, marginBottom: 20 },
    primaryButton: {
        minHeight: 52,
        backgroundColor: colors.primary,
        borderRadius: 8,
        alignItems: 'center',
        justifyContent: 'center',
        paddingHorizontal: 22,
        marginTop: 4,
    },
    primaryButtonText: { color: colors.onPrimary, fontSize: 15, fontWeight: '700' },
    buttonDisabled: { opacity: 0.65 },
    paidTag: { color: colors.paid, fontSize: 12, fontWeight: '700', marginTop: 5 },
    unpaidTag: { color: colors.owing, fontSize: 12, fontWeight: '700', marginTop: 5 },
    resolveButton: { borderColor: colors.owing, backgroundColor: colors.owingSurface },
    notice: {
        color: colors.noticeText,
        backgroundColor: colors.noticeSurface,
        padding: 12,
        borderRadius: 8,
        marginHorizontal: 20,
        marginTop: 14,
        lineHeight: 20,
    },
    owingScreen: { flexGrow: 1, alignItems: 'center', justifyContent: 'center', padding: 26 },
    owingMark: { width: 64, height: 64, borderRadius: 32, backgroundColor: colors.owingMark, alignItems: 'center', justifyContent: 'center' },
    owingMarkText: { color: colors.onPrimary, fontSize: 32, fontWeight: '700', lineHeight: 38 },
    owingAmount: { color: colors.primaryDark, fontSize: 17, fontWeight: '700', marginTop: 12 },
    owingNote: { color: colors.textSubtle, fontSize: 13, lineHeight: 20, textAlign: 'center', marginTop: 14 },
    // A dashed rule separates what they owe from what you may do about it.
    owingActions: {
        alignSelf: 'stretch',
        gap: 10,
        marginTop: 22,
        paddingTop: 18,
        borderTopWidth: 1,
        borderTopColor: colors.border,
        borderStyle: 'dashed',
    },
    notesInput: {
        minHeight: 76,
        borderWidth: 1,
        borderColor: colors.border,
        borderRadius: 8,
        backgroundColor: colors.surface,
        color: colors.text,
        fontSize: 15,
        padding: 12,
        textAlignVertical: 'top',
    },
    waiveButton: {
        minHeight: 52,
        borderWidth: 1,
        borderColor: colors.owing,
        borderRadius: 8,
        alignItems: 'center',
        justifyContent: 'center',
        paddingHorizontal: 22,
    },
    waiveButtonText: { color: colors.owing, fontSize: 15, fontWeight: '700' },
});
