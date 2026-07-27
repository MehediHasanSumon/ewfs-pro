import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem } from '@/types';
import { useState, useRef, useEffect } from 'react';
import { usePage, router, Link } from '@inertiajs/react';

interface AppSidebarHeaderProps {
    breadcrumbs?: BreadcrumbItem[];
}

export function AppSidebarHeader({ breadcrumbs = [] }: AppSidebarHeaderProps) {
    const [showProfile, setShowProfile] = useState(false);
    const profileRef = useRef<HTMLDivElement>(null);
    const { props } = usePage();
    const user = (props.auth as { user: { name: string; avatar?: string } })?.user;

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (profileRef.current && !profileRef.current.contains(event.target as Node)) {
                setShowProfile(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    return (
        <header className="fixed top-0 left-72 right-0 z-50 flex h-16 shrink-0 items-center justify-between border-b border-sidebar-border/50 bg-white dark:bg-gray-900 px-6 transition-[left] duration-200 ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 group-has-data-[state=collapsed]/sidebar-wrapper:left-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            
            <div className="flex items-center gap-4">
                <div className="relative" ref={profileRef}>
                    <Button
                        variant="ghost"
                        onClick={() => setShowProfile(!showProfile)}
                        className="flex items-center gap-2 px-2"
                    >
                        <img
                            src={user?.avatar ? `/storage/${user.avatar}` : "https://picsum.photos/seed/user/32/32"}
                            alt="User"
                            className="w-8 h-8 rounded-full"
                        />
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                        </svg>
                    </Button>
                    
                    {showProfile && (
                        <div className="absolute right-0 mt-2 w-48 bg-background border rounded-lg shadow-lg py-2 z-50">
                            <div className="px-4 py-2 border-b">
                                <p className="font-medium">{user?.name || 'User'}</p>
                            </div>
                            <Link href="/settings/profile">
                                <Button variant="ghost" className="w-full justify-start px-4 py-2">
                                    Profile
                                </Button>
                            </Link>
                            <Link href="/settings">
                                <Button variant="ghost" className="w-full justify-start px-4 py-2">
                                    Settings
                                </Button>
                            </Link>
                            <div className="border-t mt-2 pt-2">
                                <Button 
                                    variant="ghost" 
                                    className="w-full justify-start px-4 py-2 text-red-600"
                                    onClick={() => router.post('/logout')}
                                >
                                    Logout
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}
