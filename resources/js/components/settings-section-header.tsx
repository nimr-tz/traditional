import { IconTile } from '@/components/dashboard-card';
import { LucideIcon } from 'lucide-react';

export default function SettingsSectionHeader({
    icon: Icon,
    tone = 'green',
    title,
    description,
}: {
    icon: LucideIcon;
    tone?: 'blue' | 'green';
    title: string;
    description?: string;
}) {
    return (
        <div className="flex items-start gap-4">
            <IconTile tone={tone}>
                <Icon className="size-5" />
            </IconTile>
            <div>
                <h2 className="font-serif text-lg font-semibold">{title}</h2>
                {description && <p className="text-muted-foreground mt-1 text-sm">{description}</p>}
            </div>
        </div>
    );
}
