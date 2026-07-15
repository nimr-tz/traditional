import { CameraView, useCameraPermissions } from 'expo-camera';
import React, { useState } from 'react';
import {
    ActivityIndicator,
    FlatList,
    Pressable,
    StyleSheet,
    Text,
    TextInput,
    View,
} from 'react-native';
import { checkInById, lookupAttendee, LookupResult, scanCode, ScanResult } from '../api/client';

type AttendanceMode = 'scan' | 'search';

export default function AttendanceScreen() {
    const [permission, requestPermission] = useCameraPermissions();
    const [mode, setMode] = useState<AttendanceMode>('scan');
    const [locked, setLocked] = useState(false);
    const [result, setResult] = useState<ScanResult | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [query, setQuery] = useState('');
    const [matches, setMatches] = useState<LookupResult[]>([]);
    const [loading, setLoading] = useState(false);
    const [searched, setSearched] = useState(false);

    const handleScan = async ({ data }: { data: string }) => {
        if (locked) return;
        setLocked(true);
        setError(null);
        try {
            setResult(await scanCode(data));
        } catch (requestError: any) {
            setError(requestError?.response?.data?.message ?? 'Could not mark this attendee present.');
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

    const markPresent = async (userId: number) => {
        setLoading(true);
        setError(null);
        try {
            setResult(await checkInById(userId));
            setMatches([]);
        } catch (requestError: any) {
            setError(requestError?.response?.data?.message ?? 'Could not mark this attendee present.');
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
                <Text style={attendanceStyles.resultTitle}>{alreadyPresent ? 'Already marked present' : 'Attendance recorded'}</Text>
                <Text style={attendanceStyles.resultName}>{result.user.name}</Text>
                {result.user.institution ? <Text style={attendanceStyles.resultDetail}>{result.user.institution}</Text> : null}
                {result.user.participant_type ? (
                    <Text style={attendanceStyles.resultDetail}>{formatParticipantType(result.user.participant_type)}</Text>
                ) : null}
                <Text style={attendanceStyles.resultTime}>
                    {alreadyPresent ? 'First recorded' : 'Recorded'} at {new Date(result.checked_in_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                </Text>
                <Pressable style={attendanceStyles.primaryButton} onPress={reset} accessibilityRole="button">
                    <Text style={attendanceStyles.primaryButtonText}>Take next attendance</Text>
                </Pressable>
            </View>
        );
    }

    return (
        <View style={attendanceStyles.screen}>
            <View style={attendanceStyles.modeBar}>
                <ModeButton label="Scan QR" selected={mode === 'scan'} onPress={() => changeMode('scan')} />
                <ModeButton label="Search attendee" selected={mode === 'search'} onPress={() => changeMode('search')} />
            </View>

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
                    <Text style={attendanceStyles.intro}>Search by name or email, then mark the attendee present.</Text>
                    <View style={attendanceStyles.searchRow}>
                        <TextInput
                            style={attendanceStyles.searchInput}
                            placeholder="Name or email"
                            placeholderTextColor="#77827b"
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
                            {loading ? <ActivityIndicator color="#ffffff" /> : <Text style={attendanceStyles.searchButtonText}>Search</Text>}
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
                                </View>
                                <Pressable
                                    style={attendanceStyles.presentButton}
                                    onPress={() => markPresent(item.id)}
                                    disabled={loading}
                                    accessibilityRole="button"
                                    accessibilityLabel={`Mark ${item.name} present`}
                                >
                                    <Text style={attendanceStyles.presentButtonText}>Mark present</Text>
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
        return <ActivityIndicator style={attendanceStyles.loadingScreen} color="#173f2a" />;
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
                {locked ? <ActivityIndicator color="#ffffff" style={attendanceStyles.scanProgress} /> : null}
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
    screen: { flex: 1, backgroundColor: '#f7f5ef' },
    modeBar: { flexDirection: 'row', padding: 12, gap: 8, backgroundColor: '#ffffff', borderBottomWidth: 1, borderBottomColor: '#d7dcd8' },
    modeButton: { flex: 1, minHeight: 46, alignItems: 'center', justifyContent: 'center', borderRadius: 5 },
    modeButtonSelected: { backgroundColor: '#173f2a' },
    modeButtonText: { color: '#43564a', fontSize: 15, fontWeight: '700' },
    modeButtonTextSelected: { color: '#ffffff' },
    cameraPanel: { flex: 1, backgroundColor: '#000000' },
    cameraShadeTop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.55)' },
    cameraMiddle: { height: 250, flexDirection: 'row' },
    cameraShadeSide: { flex: 1, backgroundColor: 'rgba(0,0,0,0.55)' },
    scanFrame: { width: 250, borderWidth: 3, borderColor: '#ffffff', borderRadius: 8 },
    cameraShadeBottom: { flex: 1.2, backgroundColor: 'rgba(0,0,0,0.55)', alignItems: 'center', paddingTop: 28 },
    cameraInstruction: { color: '#ffffff', fontSize: 16, fontWeight: '600', textAlign: 'center' },
    scanProgress: { marginTop: 16 },
    searchPanel: { flex: 1, paddingTop: 22 },
    heading: { color: '#173f2a', fontSize: 26, fontWeight: '700', marginHorizontal: 20 },
    intro: { color: '#536159', fontSize: 15, lineHeight: 22, marginHorizontal: 20, marginTop: 6, marginBottom: 18 },
    searchRow: { flexDirection: 'row', gap: 8, marginHorizontal: 20 },
    searchInput: { flex: 1, minHeight: 50, backgroundColor: '#ffffff', borderColor: '#bfc7c1', borderWidth: 1, borderRadius: 6, paddingHorizontal: 14, fontSize: 16, color: '#172019' },
    searchButton: { minWidth: 88, minHeight: 50, backgroundColor: '#173f2a', borderRadius: 6, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 14 },
    searchButtonText: { color: '#ffffff', fontSize: 15, fontWeight: '700' },
    resultsList: { padding: 20, paddingBottom: 40 },
    matchRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 16, borderBottomWidth: 1, borderBottomColor: '#d3d9d4' },
    matchText: { flex: 1, paddingRight: 12 },
    matchName: { color: '#172019', fontSize: 17, fontWeight: '700', marginBottom: 3 },
    matchDetail: { color: '#617067', fontSize: 13, lineHeight: 19 },
    presentButton: { minHeight: 44, justifyContent: 'center', paddingHorizontal: 13, borderWidth: 1, borderColor: '#173f2a', borderRadius: 5 },
    presentButtonText: { color: '#173f2a', fontSize: 13, fontWeight: '700' },
    empty: { color: '#6a766f', fontSize: 15, textAlign: 'center', marginTop: 30 },
    error: { color: '#a73526', backgroundColor: '#f9e7e3', padding: 12, borderRadius: 6, marginHorizontal: 20, marginTop: 14, lineHeight: 20 },
    scanError: { position: 'absolute', left: 20, right: 20, bottom: 24, backgroundColor: '#ffffff', borderRadius: 8, padding: 16 },
    errorText: { color: '#912f22', fontSize: 15, lineHeight: 21, textAlign: 'center' },
    retryButton: { minHeight: 44, alignItems: 'center', justifyContent: 'center', marginTop: 8 },
    retryButtonText: { color: '#173f2a', fontSize: 15, fontWeight: '700' },
    loadingScreen: { flex: 1 },
    permissionScreen: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24 },
    permissionText: { color: '#536159', fontSize: 16, lineHeight: 24, textAlign: 'center', marginTop: 10, marginBottom: 20 },
    resultScreen: { flex: 1, backgroundColor: '#f7f5ef', alignItems: 'center', justifyContent: 'center', padding: 24 },
    resultMark: { width: 64, height: 64, borderRadius: 32, backgroundColor: '#173f2a', alignItems: 'center', justifyContent: 'center' },
    resultMarkWarning: { backgroundColor: '#996817' },
    resultMarkText: { color: '#ffffff', fontSize: 36, fontWeight: '700', lineHeight: 42 },
    resultTitle: { color: '#173f2a', fontSize: 24, fontWeight: '700', textAlign: 'center', marginTop: 18, marginBottom: 18 },
    resultName: { color: '#172019', fontSize: 20, fontWeight: '700', textAlign: 'center' },
    resultDetail: { color: '#647168', fontSize: 15, marginTop: 4, textAlign: 'center' },
    resultTime: { color: '#536159', fontSize: 14, marginTop: 18, marginBottom: 20 },
    primaryButton: { minHeight: 52, backgroundColor: '#173f2a', borderRadius: 6, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 22, marginTop: 4 },
    primaryButtonText: { color: '#ffffff', fontSize: 16, fontWeight: '700' },
});
