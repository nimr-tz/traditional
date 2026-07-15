import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

interface AuthLayoutProps {
    children: React.ReactNode;
    title: string;
    description: string;
    contentClassName?: string;
}

export default function AuthLayout({ children, title, description, contentClassName }: AuthLayoutProps) {
    return (
        <AuthLayoutTemplate title={title} description={description} contentClassName={contentClassName}>
            {children}
        </AuthLayoutTemplate>
    );
}
