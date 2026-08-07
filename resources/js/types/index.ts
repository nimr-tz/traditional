import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

/** Whether new abstracts are still being accepted. Mirrors App\Support\SubmissionWindow. */
export interface SubmissionWindow {
    deadline: string | null;
    is_open: boolean;
    closed_message: string | null;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    conference: Record<string, string | null>;
    submissionWindow: SubmissionWindow;
    auth: Auth;
    [key: string]: unknown;
}

export type UserRole = 'user' | 'reviewer' | 'staff' | 'finance' | 'admin' | 'super_admin';

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    /** Primary role — decides default landing/exports/audit-log labels. */
    role: UserRole;
    /** Every role assigned to this user; may hold more than one. */
    roles: UserRole[];
    /** Which assigned role's nav/dashboard is currently shown — a view preference only. */
    active_role: UserRole;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
