import { AppSidebarLayout } from './app/app-sidebar-layout';
import { AppHeaderLayout } from './app/app-header-layout';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';

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
        <Layout breadcrumbs={breadcrumbs}>
            {children}
        </Layout>
    );
}
