import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, ArrowRight } from 'lucide-react';
import UserLayout from '../layouts/UserLayout';

export default function Home() {
    const [currentSlide, setCurrentSlide] = useState(0);
    const slides = [1, 2, 3, 4, 5, 6, 7, 8];

    useEffect(() => {
        const interval = setInterval(() => {
            setCurrentSlide((prev) => (prev + 1) % slides.length);
        }, 3000);

        return () => clearInterval(interval);
    }, []);

    const nextSlide = () => {
        setCurrentSlide((prev) => (prev + 1) % slides.length);
    };

    const prevSlide = () => {
        setCurrentSlide((prev) => (prev - 1 + slides.length) % slides.length);
    };

    return (
        <UserLayout title="East-West Filling Station">
            {/* Custom Slider */}
            <div className="relative w-full h-[860px] overflow-hidden">
                <div className="flex transition-transform duration-500 ease-in-out" style={{ transform: `translateX(-${currentSlide * 100}%)` }}>
                    {slides.map((num) => (
                        <div key={num} className="w-full h-[860px] flex-shrink-0">
                            <img src={`/images/slider/0${num}.jpg`} alt="" className="w-full h-full object-cover" />
                        </div>
                    ))}
                </div>
                <button onClick={prevSlide} className="absolute left-4 top-1/2 transform -translate-y-1/2 bg-black/50 text-white p-2 rounded-full hover:bg-black/70 transition-colors">
                    <ChevronLeft className="w-6 h-6" />
                </button>
                <button onClick={nextSlide} className="absolute right-4 top-1/2 transform -translate-y-1/2 bg-black/50 text-white p-2 rounded-full hover:bg-black/70 transition-colors">
                    <ChevronRight className="w-6 h-6" />
                </button>
            </div>

            <section id="about" className="about py-20 bg-white">
                <div className="container mx-auto max-w-7xl px-4">
                    <div className="flex flex-wrap items-center">
                        <div className="w-full lg:w-1/2 flex flex-col justify-center mb-10 lg:mb-0">
                            <div className="content p-5 lg:p-10">
                                <h3 className="text-3xl font-bold text-[#012970] font-nunito mb-6">Why Choose Us?</h3>
                                <p className="text-[#444444] font-roboto leading-relaxed mb-8">
                                    <strong>Convenience:</strong> All your fueling and convenience needs in one place.<br />
                                    <strong>Quality:</strong> We provide top-quality fuel and services for your vehicle.<br />
                                    <strong>Customer-Centric:</strong> Our focus is always on you—ensuring you have a positive experience every time you visit.<br />
                                    <strong>Reliable:</strong> Whether it's fuel, food, or a quick restroom stop, we're here for you whenever you need us.
                                </p>
                                <div className="text-center lg:text-left">
                                    <Link href="/about" className="btn-read-more inline-flex items-center justify-center bg-[#4154f1] text-white px-8 py-3 rounded-md font-medium transition-all hover:bg-opacity-90">
                                        <span>Read More</span>
                                        <ArrowRight className="ml-2 w-4 h-4" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <div className="w-full lg:w-1/2 flex items-center">
                            <img src="https://d2u0ktu8omkpf6.cloudfront.net/bcd5b0f75b763ec85095960128da260a9ad95940f799ccd6.jpg" className="max-w-full h-auto rounded-lg shadow-lg" alt="East West Filling Station" />
                        </div>
                    </div>
                </div>
            </section>

            <section id="values" className="values py-20 bg-gray-50">
                <div className="container mx-auto max-w-7xl px-4 mb-12 text-center">
                    <h2 className="text-4xl font-bold text-[#012970] font-nunito mb-4">Our Values</h2>
                    <p className="text-lg text-gray-500">What we value most</p>
                </div>
                <div className="container mx-auto max-w-7xl px-4">
                    <div className="flex flex-wrap -mx-4 gap-y-8">
                        {[
                            { title: 'Diesel', image: '/images/01.jpg', description: 'At our filling station, we understand that diesel-powered vehicles have unique needs, and we\'re proud to offer high-quality diesel fuel to keep your engine running smoothly.' },
                            { title: 'Octane', image: '/images/022.jpg', description: 'Our Premium Octane Fuel (typically 91-93 octane) is designed for vehicles that require higher compression in their engines.' },
                            { title: 'Petrol', image: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTbbLLMnNdSiwTyzjS9UIAHfHrITUKDcKhWadnujpcA6vnHrOuMPsiDu9ZyVg-l7uniCz8&usqp=CAU', description: 'Our premium petrol (often 91 octane or higher) is perfect for vehicles that require higher compression, providing smoother acceleration.' }
                        ].map((item, index) => (
                            <div key={index} className="w-full lg:w-1/3 px-4">
                                <div className="bg-white p-8 rounded-xl shadow-sm hover:shadow-md transition-shadow h-full border border-gray-100 flex flex-col">
                                    <img className="w-full h-64 object-cover rounded-lg mb-6" src={item.image} alt={item.title} />
                                    <div className="mt-4">
                                        <h3 className="text-2xl font-bold text-[#012970] font-nunito mb-4">{item.title}</h3>
                                        <p className="text-[#444444] font-roboto leading-relaxed">{item.description}</p>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section id="services" className="services py-20 bg-white">
                <div className="container mx-auto max-w-7xl px-4 mb-12 text-center">
                    <h2 className="text-4xl font-bold text-[#012970] font-nunito mb-4">Our Services</h2>
                    <p className="text-lg text-[#4154f1] font-semibold tracking-wide">24/7</p>
                </div>
                <div className="container mx-auto max-w-7xl px-4">
                    <div className="flex flex-wrap -mx-4 gap-y-8">
                        {[
                            { title: 'Our Diesel', image: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRQMC15CJIPwrl7mCLqREsmSpLqPyiVFItzJqHq-fAkjLoYjS12yTELgHngNXGV936OxiQ&usqp=CAU' },
                            { title: 'Our Petrol', image: 'https://cstoredecisions.com/wp-content/uploads/2020/03/unbranded-c-store-exterior.jpg' },
                            { title: 'Our Octane', image: 'https://www.lumina-intelligence.com/wp-content/uploads/2020/06/forecourt-retail-trends-growth-statistics-thegem-blog-default.png' }
                        ].map((service, index) => (
                            <div key={index} className="w-full md:w-1/2 lg:w-1/3 px-4">
                                <div className="relative overflow-hidden group rounded-xl shadow-sm hover:shadow-lg transition-all">
                                    <img className="w-full h-72 object-cover transition-transform duration-500 group-hover:scale-110" src={service.image} alt={service.title} />
                                    <div className="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent flex items-end p-6">
                                        <h4 className="text-white text-xl font-bold font-nunito">{service.title}</h4>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </UserLayout>
    );
}