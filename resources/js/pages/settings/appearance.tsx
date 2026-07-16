import { Head } from '@inertiajs/react';
import { Palette } from 'lucide-react';

import AppearanceTabs from '@/components/appearance-tabs';
import { DashboardCard } from '@/components/dashboard-card';
import SettingsSectionHeader from '@/components/settings-section-header';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: '/settings/appearance',
    },
];

export default function Appearance() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Appearance settings" />

            <SettingsLayout>
                <DashboardCard>
                    <SettingsSectionHeader
                        icon={Palette}
                        tone="green"
                        title="Appearance"
                        description="Choose how the conference portal looks on this device."
                    />

                    <div className="mt-6">
                        <AppearanceTabs />
                    </div>
                </DashboardCard>
            </SettingsLayout>
        </AppLayout>
    );
}
