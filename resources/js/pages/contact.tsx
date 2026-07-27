import { MapPin, Phone, Mail, Clock } from 'lucide-react';
import UserLayout from '../layouts/UserLayout';

export default function Contact() {
    return (
        <UserLayout title="Contact Us - East-West Filling Station">
            <section id="contact" className="contact py-32 bg-white">
                {/* Section Title */}
                <div className="container mx-auto max-w-7xl px-4 mb-16 text-center">
                    <h2 className="text-4xl font-bold text-[#012970] font-nunito mb-4">Contact Us</h2>
                    <p className="text-lg text-gray-500">Get in touch with us for any inquiries</p>
                </div>

                <div className="container mx-auto max-w-7xl px-4">
                    <div className="flex flex-wrap -mx-4 gap-y-10">
                        <div className="w-full lg:w-1/2 px-4">
                            <div className="flex flex-wrap -mx-4 gap-y-8">
                                <div className="w-full md:w-1/2 px-4">
                                    <div className="bg-gray-50 p-8 rounded-xl border border-gray-100 h-full">
                                        <MapPin className="w-10 h-10 text-[#4154f1] mb-4" />
                                        <h3 className="text-xl font-bold text-[#012970] font-nunito mb-2">Address</h3>
                                        <p className="text-[#444444] font-roboto">Dhour Baribad<br />Turag, Dhaka.</p>
                                    </div>
                                </div>

                                <div className="w-full md:w-1/2 px-4">
                                    <div className="bg-gray-50 p-8 rounded-xl border border-gray-100 h-full">
                                        <Phone className="w-10 h-10 text-[#4154f1] mb-4" />
                                        <h3 className="text-xl font-bold text-[#012970] font-nunito mb-2">Call Us</h3>
                                        <p className="text-[#444444] font-roboto">+880 1777 787 027<br />+880 1713 861 696</p>
                                    </div>
                                </div>

                                <div className="w-full md:w-1/2 px-4">
                                    <div className="bg-gray-50 p-8 rounded-xl border border-gray-100 h-full">
                                        <Mail className="w-10 h-10 text-[#4154f1] mb-4" />
                                        <h3 className="text-xl font-bold text-[#012970] font-nunito mb-2">Email Us</h3>
                                        <p className="text-[#444444] font-roboto truncate">
                                            eastwestfillingstation1@gmail.com<br />eastwestfillingstation.com
                                        </p>
                                    </div>
                                </div>

                                <div className="w-full md:w-1/2 px-4">
                                    <div className="bg-gray-50 p-8 rounded-xl border border-gray-100 h-full">
                                        <Clock className="w-10 h-10 text-[#4154f1] mb-4" />
                                        <h3 className="text-xl font-bold text-[#012970] font-nunito mb-2">Open Hours</h3>
                                        <p className="text-[#444444] font-roboto">Every day 24 Hours</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="w-full lg:w-1/2 px-4">
                            <form className="bg-gray-50 p-8 lg:p-10 rounded-xl border border-gray-100 h-full">
                                <div className="flex flex-wrap -mx-2 gap-y-6">
                                    <div className="w-full md:w-1/2 px-2">
                                        <input 
                                            type="text" 
                                            name="name"
                                            className="w-full px-4 py-3 rounded-md border border-gray-200 focus:border-[#4154f1] focus:ring-1 focus:ring-[#4154f1] outline-none transition-all"
                                            placeholder="Your Name" 
                                            required 
                                        />
                                    </div>

                                    <div className="w-full md:w-1/2 px-2">
                                        <input 
                                            type="email"
                                            className="w-full px-4 py-3 rounded-md border border-gray-200 focus:border-[#4154f1] focus:ring-1 focus:ring-[#4154f1] outline-none transition-all"
                                            name="email" 
                                            placeholder="Your Email" 
                                            required 
                                        />
                                    </div>

                                    <div className="w-full px-2">
                                        <input 
                                            type="text"
                                            className="w-full px-4 py-3 rounded-md border border-gray-200 focus:border-[#4154f1] focus:ring-1 focus:ring-[#4154f1] outline-none transition-all"
                                            name="subject" 
                                            placeholder="Subject" 
                                            required 
                                        />
                                    </div>

                                    <div className="w-full px-2">
                                        <textarea
                                            className="w-full px-4 py-3 rounded-md border border-gray-200 focus:border-[#4154f1] focus:ring-1 focus:ring-[#4154f1] outline-none transition-all"
                                            name="message" 
                                            rows={6} 
                                            placeholder="Message" 
                                            required
                                        ></textarea>
                                    </div>

                                    <div className="w-full px-2 text-center">
                                        <button 
                                            type="submit"
                                            className="bg-[#4154f1] text-white px-10 py-3 rounded-md font-medium transition-all hover:bg-opacity-90"
                                        >
                                            Send Message
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div className="mt-20">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3647.94365136243!2d90.36018068892479!3d23.89161674121059!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3755c390ebee894f%3A0xeffade432442bf4d!2z4KaH4Ka44KeN4KafLeCmk-Cmr-CmvOCnh-CmuOCnjeCmnyDgpqvgpr_gprLgpr_gpoIg4Ka44KeN4Kaf4KeH4Ka24KaoIDAx!5e0!3m2!1sen!2sbd!4v1737641915697!5m2!1sen!2sbd"
                        width="100%" 
                        height="450" 
                        style={{ border: 0 }} 
                        allowFullScreen 
                        loading="lazy"
                        referrerPolicy="no-referrer-when-downgrade"
                    ></iframe>
                </div>
            </section>
        </UserLayout>
    );
}