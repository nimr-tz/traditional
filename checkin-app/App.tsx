import { StatusBar } from 'expo-status-bar';
import { AuthProvider } from './src/context/AuthContext';
import RootNavigator from './src/navigation';

export default function App() {
    return (
        <AuthProvider>
            <StatusBar style="dark" />
            <RootNavigator />
        </AuthProvider>
    );
}
