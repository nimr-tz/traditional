import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { NavigationContainer } from '@react-navigation/native';
import React from 'react';
import { ActivityIndicator, Pressable, Text, View } from 'react-native';
import { useAuth } from '../context/AuthContext';
import AttendanceScreen from '../screens/AttendanceScreen';
import LoginScreen from '../screens/LoginScreen';
import RegisterScreen from '../screens/RegisterScreen';

const Tab = createBottomTabNavigator();

function SignOutButton() {
    const { signOut } = useAuth();
    return (
        <Pressable onPress={signOut} style={{ minHeight: 44, justifyContent: 'center', marginRight: 16 }} accessibilityRole="button">
            <Text style={{ color: '#173f2a', fontWeight: '700' }}>Sign out</Text>
        </Pressable>
    );
}

export default function RootNavigator() {
    const { staff, isLoading } = useAuth();

    if (isLoading) {
        return (
            <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
                <ActivityIndicator />
            </View>
        );
    }

    if (!staff) {
        return (
            <NavigationContainer>
                <LoginScreen />
            </NavigationContainer>
        );
    }

    return (
        <NavigationContainer>
            <Tab.Navigator
                screenOptions={{
                    headerTitleAlign: 'left',
                    headerRight: () => <SignOutButton />,
                    headerStyle: { backgroundColor: '#f7f5ef' },
                    headerShadowVisible: false,
                    headerTitleStyle: { color: '#173f2a', fontWeight: '700' },
                    tabBarActiveTintColor: '#173f2a',
                    tabBarInactiveTintColor: '#718078',
                    tabBarLabelStyle: { fontSize: 13, fontWeight: '700', paddingBottom: 4 },
                    tabBarStyle: { height: 64, paddingTop: 8, backgroundColor: '#ffffff', borderTopColor: '#d7dcd8' },
                }}
            >
                <Tab.Screen name="Register" component={RegisterScreen} options={{ title: 'Registration' }} />
                <Tab.Screen name="Attendance" component={AttendanceScreen} options={{ title: 'Attendance' }} />
            </Tab.Navigator>
        </NavigationContainer>
    );
}
