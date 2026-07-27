import React from 'react';
import { usePage, Link } from '@inertiajs/react';

interface AuthUser {
    id: number;
    name: string;
    email: string;
}

interface PageProps {
    auth?: {
        user: AuthUser | null;
    };
    company?: {
        name?: string;
        logo?: string;
        is_registration?: boolean;
    };
    [key: string]: unknown;
}

export default function UserNavbar() {
    const pageProps = usePage<PageProps>();
    const { auth, company, url } = pageProps.props || {};

    // Fallback if auth is undefined
    const user = auth?.user || null;
    const isRegistrationEnabled = company?.is_registration === true;
    const companyName = company?.name || 'East West Filling Station';
    const companyLogo = company?.logo || '/images/logo.jpg';

    const isActive = (path: string) => {
        return url === path ? 'text-[#4154f1]' : 'text-[#012970] hover:text-[#4154f1]';
    };

    return (
        <header id="header" className="header flex items-center fixed top-0 w-full z-[997] bg-white transition-all duration-500 py-5">
            <div className="container mx-auto max-w-7xl relative flex items-center px-4">
                <a href="/" className="logo flex items-center mr-auto no-underline">
                    <img src={companyLogo} alt="" className="max-h-9 mr-2" />
                    <h2 className="sitename pt-2 text-3xl font-bold text-[#012970] font-nunito">{companyName}</h2>
                </a>
                <nav id="navmenu" className="navmenu px-3">
                    <ul className="flex list-none p-0 m-0 items-center">
                        <li><Link href="/" className={`px-3 py-4 ${isActive('/')} font-medium font-poppins text-sm transition-colors`}>Home</Link></li>
                        <li><Link href="/about" className={`px-3 py-4 ${isActive('/about')} font-medium font-poppins text-sm transition-colors`}>About</Link></li>
                        <li><Link href="/services" className={`px-3 py-4 ${isActive('/services')} font-medium font-poppins text-sm transition-colors`}>Services</Link></li>
                        <li><Link href="/contact" className={`px-3 py-4 ${isActive('/contact')} font-medium font-poppins text-sm transition-colors`}>Contact</Link></li>
                        {user ? (
                            <>
                                <li><a href="https://dispenser.ddrbit.com/" className="px-3 py-4 font-medium font-poppins text-sm transition-colors">Dispenser</a></li>
                                <li><a href="https://statement.ddrbit.com/" className="px-3 py-4 font-medium font-poppins text-sm transition-colors">Statement</a></li>
                                <li><Link href="/dashboard" className={`px-3 py-4 ${isActive('/dashboard')} font-medium font-poppins text-sm transition-colors`}>Dashboard</Link></li>
                                <li><Link href="/logout" method="post" as="button" className="px-3 py-4 text-[#012970] hover:text-[#4154f1] font-medium font-poppins text-sm transition-colors bg-transparent border-none cursor-pointer">Logout</Link></li>
                            </>
                        ) : (
                            <>
                                <li><Link href="/login" className={`px-3 py-4 ${isActive('/login')} font-medium font-poppins text-sm transition-colors`}>SignIn</Link></li>
                                {isRegistrationEnabled && (
                                    <li><Link href="/register" className={`px-3 py-4 ${isActive('/register')} font-medium font-poppins text-sm transition-colors`}>SignUp</Link></li>
                                )}
                            </>
                        )}
                    </ul>
                </nav>
            </div>
        </header>
    );
}