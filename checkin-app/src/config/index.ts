import { Platform } from 'react-native';

// Android emulators reach the host machine through 10.0.2.2. For a physical
// phone, set EXPO_PUBLIC_API_BASE_URL to this computer's LAN address.
export const API_BASE_URL =
    process.env.EXPO_PUBLIC_API_BASE_URL ??
    (Platform.OS === 'android' ? 'http://10.0.2.2:8000/api' : 'http://127.0.0.1:8000/api');
