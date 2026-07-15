import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { createContext, useContext, useEffect, useState } from 'react';
import { TOKEN_KEY, login as apiLogin, logout as apiLogout } from '../api/client';

interface StaffUser {
    id: number;
    name: string;
    email: string;
}

interface AuthContextValue {
    staff: StaffUser | null;
    isLoading: boolean;
    signIn: (email: string, password: string) => Promise<void>;
    signOut: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

const STAFF_KEY = 'tmsc_checkin_staff';

export function AuthProvider({ children }: { children: React.ReactNode }) {
    const [staff, setStaff] = useState<StaffUser | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        (async () => {
            const [token, storedStaff] = await Promise.all([AsyncStorage.getItem(TOKEN_KEY), AsyncStorage.getItem(STAFF_KEY)]);
            if (token && storedStaff) {
                setStaff(JSON.parse(storedStaff));
            }
            setIsLoading(false);
        })();
    }, []);

    const signIn = async (email: string, password: string) => {
        const { token, user } = await apiLogin(email, password);
        await AsyncStorage.setItem(TOKEN_KEY, token);
        await AsyncStorage.setItem(STAFF_KEY, JSON.stringify(user));
        setStaff(user);
    };

    const signOut = async () => {
        try {
            await apiLogout();
        } catch {
            // ignore network errors on logout — clear local session regardless
        }
        await AsyncStorage.multiRemove([TOKEN_KEY, STAFF_KEY]);
        setStaff(null);
    };

    return <AuthContext.Provider value={{ staff, isLoading, signIn, signOut }}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within AuthProvider');
    }
    return context;
}
