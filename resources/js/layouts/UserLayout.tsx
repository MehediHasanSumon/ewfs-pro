import React, { useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { ArrowUp } from 'lucide-react';
import UserNavbar from '../components/UserNavbar';
import UserFooter from '../components/UserFooter';

interface UserLayoutProps {
    title?: string;
    children: React.ReactNode;
}

export default function UserLayout({ title = 'East-West Filling Station', children }: UserLayoutProps) {
    useEffect(() => {
        // Scroll top functionality
        const scrollTop = document.getElementById('scroll-top');
        if (scrollTop) {
            const handleScroll = () => {
                if (window.scrollY > 100) {
                    scrollTop.classList.add('opacity-100', 'visible');
                    scrollTop.classList.remove('opacity-0');
                } else {
                    scrollTop.classList.add('opacity-0');
                    scrollTop.classList.remove('opacity-100', 'visible');
                }
            };

            const handleClick = (e: Event) => {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            };

            window.addEventListener('scroll', handleScroll);
            scrollTop.addEventListener('click', handleClick);

            return () => {
                window.removeEventListener('scroll', handleScroll);
                scrollTop.removeEventListener('click', handleClick);
            };
        }
    }, []);

    return (
        <>
            <Head title={title} />
            
            <div className="index-page">
                <UserNavbar />
                
                <main className="main mt-[99px]">
                    {children}
                </main>

                <UserFooter />

                {/* Scroll Top */}
                <a href="#" id="scroll-top" className="scroll-top fixed bottom-4 right-4 z-[99999] bg-[#4154f1] w-10 h-10 rounded flex items-center justify-center text-white opacity-0 transition-all hover:bg-opacity-80">
                    <ArrowUp className="w-5 h-5" />
                </a>
            </div>
        </>
    );
}