import { useDocumentLocale } from '@/hooks/use-document-locale';
import AuthLayoutTemplate from '@/layouts/auth/auth-split-layout';
import type { AuthLayoutProps } from '@/types';

export default function AuthLayout({
    title,
    description,
    children,
}: AuthLayoutProps) {
    useDocumentLocale();

    return (
        <AuthLayoutTemplate title={title} description={description}>
            {children}
        </AuthLayoutTemplate>
    );
}
