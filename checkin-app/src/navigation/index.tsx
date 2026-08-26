import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { NavigationContainer } from '@react-navigation/native';
import React from 'react';
import { ActivityIndicator, Image, Pressable, Text, View } from 'react-native';
import { useAuth } from '../context/AuthContext';
import AttendanceScreen from '../screens/AttendanceScreen';
import LoginScreen from '../screens/LoginScreen';
import RegisterScreen from '../screens/RegisterScreen';
import { colors } from '../theme';

const Tab = createBottomTabNavigator();

function SignOutButton() {
    const { signOut } = useAuth();
    return (
        <Pressable onPress={signOut} style={{ minHeight: 44, justifyContent: 'center', marginRight: 16 }} accessibilityRole="button">
            <Text style={{ color: colors.onPrimary, fontWeight: '700' }}>Sign out</Text>
        </Pressable>
    );
}

/** The NIMR mark rides in the green bar so every screen is visibly the official app. */
function HeaderMark() {
    return (
        <Image
            source={require('../../assets/nimr-logo.png')}
            style={{ width: 26, height: 26, borderRadius: 13, backgroundColor: colors.surface, marginLeft: 16 }}
            accessibilityIgnoresInvertColors
        />
    );
}

export default function RootNavigator() {
    const { staff, isLoading } = useAuth();

    if (isLoading) {
        return (
            <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.background }}>
                <ActivityIndicator color={colors.primary} />
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
            {/* Attendance is the job: staff scan badges all day and register the
                occasional walk-in, so the app opens on the scanner rather than
                on a form nobody needs most of the time. */}
            <Tab.Navigator
                initialRouteName="Attendance"
                screenOptions={{
                    headerTitleAlign: 'left',
                    headerLeft: () => <HeaderMark />,
                    headerRight: () => <SignOutButton />,
                    headerStyle: { backgroundColor: colors.primary },
                    headerShadowVisible: false,
                    headerTintColor: colors.onPrimary,
                    headerTitleStyle: { color: colors.onPrimary, fontWeight: '700' },
                    tabBarActiveTintColor: colors.primary,
                    tabBarInactiveTintColor: '#718078',
                    tabBarLabelStyle: { fontSize: 13, fontWeight: '700', paddingBottom: 4 },
                    tabBarStyle: { height: 64, paddingTop: 8, backgroundColor: colors.surface, borderTopColor: colors.headerDivider },
                }}
            >
                <Tab.Screen name="Attendance" component={AttendanceScreen} options={{ title: 'Attendance' }} />
                <Tab.Screen name="Register" component={RegisterScreen} options={{ title: 'Registration' }} />
            </Tab.Navigator>
        </NavigationContainer>
    );
}
