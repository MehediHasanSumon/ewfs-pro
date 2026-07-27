import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronRight } from 'lucide-react';
import UserLayout from '../layouts/UserLayout';

export default function About() {
    return (
        <UserLayout title="About Us - East-West Filling Station">
            {/* Page Title */}
            <div className="page-title pt-32 pb-16 bg-gray-50 border-b border-gray-100">
                <div className="heading bg-cover bg-center py-20 relative" style={{ backgroundImage: "url('/images/02.jpg')" }}>
                    <div className="absolute inset-0 bg-black/40"></div>
                    <div className="container mx-auto max-w-7xl px-4 relative z-10">
                        <div className="flex flex-wrap justify-center text-center">
                            <div className="w-full lg:w-2/3">
                                <h1 className="text-4xl md:text-5xl font-bold text-white font-nunito mb-6">About Us</h1>
                                <p className="text-white/90 text-lg md:text-xl font-roboto leading-relaxed">
                                    East West Filling Station is a well-known fuel station that typically provides a
                                    range of services to motorists, including gasoline, diesel, and sometimes alternative fuels.
                                    Located at a strategic point,
                                    it serves as a vital pit stop for travelers, commuters, and those seeking convenience
                                    in refueling their vehicles. Often, filling stations like East West might also offer additional
                                    amenities such as car washes, convenience stores, and food services.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <nav className="breadcrumbs py-4 bg-white">
                    <div className="container mx-auto max-w-7xl px-4">
                        <ol className="flex list-none p-0 m-0 text-sm font-medium">
                            <li><Link href="/" className="text-gray-500 hover:text-[#4154f1] transition-colors">Home</Link></li>
                            <li className="mx-2 text-gray-400">/</li>
                            <li><span className="text-[#4154f1] font-bold">About</span></li>
                        </ol>
                    </div>
                </nav>
            </div>

            {/* About Section */}
            <section id="about" className="about py-20 bg-white">
                <div className="container mx-auto max-w-7xl px-4">
                    <div className="flex flex-wrap items-center">
                        <div className="w-full lg:w-1/2 flex flex-col justify-center mb-10 lg:mb-0">
                            <div className="content p-5 lg:p-10">
                                <h3 className="text-3xl font-bold text-[#012970] font-nunito mb-6">Why Choose Us?</h3>
                                <p className="text-[#444444] font-roboto leading-relaxed mb-8">
                                    <strong>Convenience:</strong> All your fueling and convenience needs in one place.<br />
                                    <strong>Quality:</strong> We provide top-quality fuel and services for your vehicle.<br />
                                    <strong>Customer-Centric:</strong> Our focus is always on you—ensuring you have a positive
                                    experience every time you visit.<br />
                                    <strong>Reliable:</strong> Whether it's fuel, food, or a quick restroom stop, we're here for you
                                    whenever you need us.
                                </p>
                                <div className="text-center lg:text-left">
                                    <Link href="/services" className="btn-read-more inline-flex items-center justify-center bg-[#4154f1] text-white px-8 py-3 rounded-md font-medium transition-all hover:bg-opacity-90">
                                        <span>Our Services</span>
                                        <ArrowRight className="ml-2 w-4 h-4" />
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div className="w-full lg:w-1/2 flex items-center">
                            <img src="https://d2u0ktu8omkpf6.cloudfront.net/bcd5b0f75b763ec85095960128da260a9ad95940f799ccd6.jpg"
                                className="max-w-full h-auto rounded-lg shadow-lg" alt="East West Filling Station" />
                        </div>
                    </div>
                </div>
            </section>
        </UserLayout>
    );
}