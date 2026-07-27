import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import { type BreadcrumbItem } from '@/types';
import type { PropsWithChildren } from 'react';

interface AppHeaderLayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
}

export function AppHeaderLayout({
    children,
    breadcrumbs,
}: AppHeaderLayoutProps) {
    return (
        <AppShell>
            <AppHeader breadcrumbs={breadcrumbs} />
            <AppContent>
                <main className="flex-1">
                    {children}
                </main>
            </AppContent>
        </AppShell>
    );
}

export default AppHeaderLayout;
