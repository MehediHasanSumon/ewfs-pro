import { GlobalFeedback } from '@/components/global-feedback';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';
import { AppSidebarLayout } from './app/app-sidebar-layout';
import { AppHeaderLayout } from './app/app-header-layout';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    variant?: 'sidebar' | 'header';
}

export default function AppLayout({ 
    children, 
    breadcrumbs, 
    variant = 'sidebar' 
}: AppLayoutProps) {
    const Layout = variant === 'sidebar' ? AppSidebarLayout : AppHeaderLayout;
    
    return (
        <>
            <Layout breadcrumbs={breadcrumbs}>{children}</Layout>
            <GlobalFeedback />
        </>
    );
}
