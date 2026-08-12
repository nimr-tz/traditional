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

export type PaymentStatus = 'pending' | 'submitted' | 'verified' | 'rejected' | 'waived';

/**
 * The one shape the API returns a registrant in, whether they came from a scan,
 * a search, or a fresh walk-in registration. `is_paid` is the only thing that
 * decides whether they can be checked in — a registration_code only exists once
 * payment is verified or waived.
 */
export interface Registrant {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    institution: string | null;
    participant_type: string | null;
    registration_code: string | null;
    fee_category: string | null;
    fee_amount: string | null;
    currency: string | null;
    payment_status: PaymentStatus | null;
    control_number: string | null;
    student_verification_status: 'pending' | 'verified' | 'rejected' | null;
    is_paid: boolean;
    /** Attended on any day of the conference. */
    is_checked_in: boolean;
    /** Attended today — the one that decides whether a scan is a duplicate. */
    is_checked_in_today: boolean;
    days_attended: number;
}

/**
 * Attendance is recorded once per person per day, so `already_checked_in` means
 * "already recorded today", not "already recorded ever" — a returning attendee
 * scans in again each morning.
 */
export interface ScanResult {
    already_checked_in: boolean;
    checked_in_at: string;
    days_attended: number;
    user: Registrant;
}

/** What the desk can tell the attendee about paying, right now. */
export interface BillingOutcome {
    status: 'ready' | 'pending' | 'blocked' | 'unavailable' | 'paid';
    control_number: string | null;
    message: string;
}

export interface RegistrationInput {
    name: string;
    email: string;
    phone: string;
    institution: string;
    participant_type: string;
    country: string;
    fee_category: string;
}

export interface RegistrationResult {
    message: string;
    user: Registrant;
    billing: BillingOutcome;
}

export interface FeeCategory {
    key: string;
    label: string;
    amount: string;
    currency: string;
}

export interface DeskOptions {
    fee_categories: FeeCategory[];
    participant_types: Record<string, string>;
    countries: string[];
    east_africa_countries: string[];
}

export interface AuthenticatedUser {
    id: number;
    name: string;
    email: string;
    can_manage_finance: boolean;
}

export async function login(email: string, password: string) {
    const { data } = await apiClient.post('/checkin/login', { email, password });
    return data as { token: string; user: AuthenticatedUser };
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
    return data.results as Registrant[];
}

export async function checkInById(userId: number) {
    const { data } = await apiClient.post(`/checkin/users/${userId}/check-in`);
    return data as ScanResult;
}

export async function registerAttendee(input: RegistrationInput) {
    const { data } = await apiClient.post('/checkin/register', input);
    return data as RegistrationResult;
}

export async function fetchDeskOptions() {
    const { data } = await apiClient.get('/checkin/fee-categories');
    return data as DeskOptions;
}

/** Re-request (or first-request) a control number for someone who still owes. */
export async function requestControlNumber(userId: number) {
    const { data } = await apiClient.post(`/checkin/users/${userId}/control-number`);
    return data as { user: Registrant; billing: BillingOutcome };
}

/** Finance only — the API returns 403 for staff. */
export async function verifyPayment(userId: number, notes: string) {
    const { data } = await apiClient.post(`/checkin/users/${userId}/verify-payment`, { notes });
    return data as { message: string; user: Registrant };
}

/** Finance only — notes are required, they are the audit trail for the waiver. */
export async function waivePayment(userId: number, notes: string) {
    const { data } = await apiClient.post(`/checkin/users/${userId}/waive`, { notes });
    return data as { message: string; user: Registrant };
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

interface ApiErrorBody {
    message?: string;
    errors?: Record<string, string[]>;
    user?: Registrant;
}

function errorBody(error: unknown): ApiErrorBody | undefined {
    return axios.isAxiosError(error) ? (error.response?.data as ApiErrorBody | undefined) : undefined;
}

/**
 * The message to show for a failed request: the first validation error if there
 * is one, else the API's own message, else the caller's fallback for the cases
 * with no response at all (the network dropped, the server is down).
 */
export function apiErrorMessage(error: unknown, fallback: string): string {
    const body = errorBody(error);
    const firstValidationError = body?.errors ? Object.values(body.errors).flat()[0] : undefined;

    return firstValidationError ?? body?.message ?? fallback;
}

/**
 * A check-in refused for non-payment returns the registrant alongside the 422,
 * so the desk can act on it rather than just read an error.
 */
export function unpaidRegistrantFrom(error: unknown): Registrant | null {
    const user = errorBody(error)?.user;

    return user && user.is_paid === false ? user : null;
}
