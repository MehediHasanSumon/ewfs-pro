import UserLayout from '../layouts/UserLayout';

export default function Services() {
    return (
        <UserLayout title="Our Services - East-West Filling Station">
            <section id="services" className="services py-32 bg-white">
                {/* Section Title */}
                <div className="container mx-auto max-w-7xl px-4 mb-16 text-center">
                    <h2 className="text-4xl font-bold text-[#012970] font-nunito mb-4">Our Services</h2>
                    <p className="text-lg text-[#4154f1] font-semibold tracking-wide uppercase mb-6">24/7 Service Available</p>
                    <div className="max-w-3xl mx-auto">
                        <p className="text-[#444444] font-roboto leading-relaxed text-lg italic">
                            Welcome to Our Filling Station – Your One-Stop Stop for Convenience and Quality Service
                            At our filling station, we aim to offer much more than just fuel. Whether you're passing through or need to
                            stock up on essentials, we provide a range of services designed to make your visit quick, easy, and enjoyable.
                            Our goal is to ensure that your journey is smooth and that you always leave with a smile.
                        </p>
                    </div>
                </div>

                <div className="container mx-auto max-w-7xl px-4">
                    <div className="flex flex-wrap -mx-4 gap-y-8">
                        <div className="w-full md:w-1/2 lg:w-1/3 px-4">
                            <div className="relative overflow-hidden group rounded-xl shadow-sm hover:shadow-lg transition-all">
                                <img className="w-full h-72 object-cover transition-transform duration-500 group-hover:scale-110"
                                    src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRQMC15CJIPwrl7mCLqREsmSpLqPyiVFItzJqHq-fAkjLoYjS12yTELgHngNXGV936OxiQ&usqp=CAU"
                                    alt="Diesel" />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent flex items-end p-6">
                                    <h4 className="text-white text-xl font-bold font-nunito">Our Diesel</h4>
                                </div>
                            </div>
                        </div>

                        <div className="w-full md:w-1/2 lg:w-1/3 px-4">
                            <div className="relative overflow-hidden group rounded-xl shadow-sm hover:shadow-lg transition-all">
                                <img className="w-full h-72 object-cover transition-transform duration-500 group-hover:scale-110"
                                    src="https://cstoredecisions.com/wp-content/uploads/2020/03/unbranded-c-store-exterior.jpg" alt="Petrol" />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent flex items-end p-6">
                                    <h4 className="text-white text-xl font-bold font-nunito">Our Petrol</h4>
                                </div>
                            </div>
                        </div>

                        <div className="w-full md:w-1/2 lg:w-1/3 px-4">
                            <div className="relative overflow-hidden group rounded-xl shadow-sm hover:shadow-lg transition-all">
                                <img className="w-full h-72 object-cover transition-transform duration-500 group-hover:scale-110"
                                    src="https://www.lumina-intelligence.com/wp-content/uploads/2020/06/forecourt-retail-trends-growth-statistics-thegem-blog-default.png"
                                    alt="Octane" />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent flex items-end p-6">
                                    <h4 className="text-white text-xl font-bold font-nunito">Our Octane</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </UserLayout>
    );
}