import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';
import { API_BASE_URL } from '../config';

export const TOKEN_KEY = 'tmsc_checkin_token';

export const apiClient = axios.create({
    baseURL: API_BASE_URL,
    headers: { Accept: 'application/json' },
});

apiClient.interceptors.request.use(async (config) => {
    const token = await AsyncStorage.getItem(TOKEN_KEY);
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

export interface CheckedInUser {
    id: number;
    name: string;
    institution: string | null;
    participant_type: string | null;
}

export interface ScanResult {
    already_checked_in: boolean;
    checked_in_at: string;
    user: CheckedInUser;
}

export interface LookupResult {
    id: number;
    name: string;
    email: string;
    institution: string | null;
    participant_type: string | null;
    registration_code: string | null;
}

export interface RegistrationInput {
    name: string;
    email: string;
    phone: string;
    institution: string;
    participant_type: string;
}

export interface RegisteredAttendee {
    id: number;
    name: string;
    email: string;
    phone: string;
    institution: string | null;
    participant_type: string;
    registration_code: string;
}

export async function login(email: string, password: string) {
    const { data } = await apiClient.post('/checkin/login', { email, password });
    return data as { token: string; user: { id: number; name: string; email: string } };
}

export async function logout() {
    await apiClient.post('/checkin/logout');
}

export async function scanCode(code: string) {
    const { data } = await apiClient.post('/checkin/scan', { code });
    return data as ScanResult;
}

export async function lookupAttendee(q: string) {
    const { data } = await apiClient.get('/checkin/lookup', { params: { q } });
    return data.results as LookupResult[];
}

export async function checkInById(userId: number) {
    const { data } = await apiClient.post(`/checkin/users/${userId}/check-in`);
    return data as ScanResult;
}

export async function registerAttendee(input: RegistrationInput) {
    const { data } = await apiClient.post('/checkin/register', input);
    return data.user as RegisteredAttendee;
}

export interface RecentAttendance {
    id: number;
    checked_in_at: string;
    user: { id: number; name: string; institution: string | null } | null;
    staff: { id: number; name: string } | null;
}

export async function fetchRecent() {
    const { data } = await apiClient.get('/checkin/recent');
    return data.attendances as RecentAttendance[];
}
