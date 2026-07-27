import React from 'react';
import { Link } from '@inertiajs/react';
import { ChevronRight, Twitter, Facebook, Instagram, Linkedin } from 'lucide-react';

export default function UserFooter() {
    return (
        <footer id="footer" className="footer border-t border-gray-100 pt-16 pb-10 bg-white">
            <div className="container mx-auto max-w-7xl px-4">
                <div className="flex flex-wrap -mx-4 gap-y-10">
                    <div className="w-full lg:w-1/3 px-4">
                        <div className="flex items-center gap-3 mb-6">
                            <img src="/images/logo.jpg" alt="Logo" className="w-12 h-12 rounded-full object-cover" />
                            <h2 className="text-2xl font-bold text-[#012970] font-nunito">Filling Station</h2>
                        </div>
                        <div className="text-[#444444] font-roboto space-y-2">
                            <p>Dhour Barebad</p>
                            <p>Turag, Dhaka.</p>
                            <p className="pt-4"><strong>Phone:</strong> <span>+880 1777 787 027</span></p>
                            <p><strong>Email:</strong> <span>info@eastwestfillingstation.com</span></p>
                        </div>
                    </div>
                    <div className="w-full sm:w-1/2 lg:w-1/4 px-4 lg:ml-auto">
                        <h4 className="text-lg font-bold text-[#012970] font-nunito mb-6 relative pb-2 after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-12 after:h-0.5 after:bg-[#4154f1]">Useful Links</h4>
                        <ul className="space-y-4">
                            <li><Link href="/" className="text-[#444444] hover:text-[#4154f1] transition-colors flex items-center gap-2"><ChevronRight className="w-3 h-3 text-[#4154f1]" /> Home</Link></li>
                            <li><Link href="/about" className="text-[#444444] hover:text-[#4154f1] transition-colors flex items-center gap-2"><ChevronRight className="w-3 h-3 text-[#4154f1]" /> About us</Link></li>
                            <li><Link href="/services" className="text-[#444444] hover:text-[#4154f1] transition-colors flex items-center gap-2"><ChevronRight className="w-3 h-3 text-[#4154f1]" /> Services</Link></li>
                            <li><Link href="/contact" className="text-[#444444] hover:text-[#4154f1] transition-colors flex items-center gap-2"><ChevronRight className="w-3 h-3 text-[#4154f1]" /> Contact</Link></li>
                        </ul>
                    </div>
                    <div className="w-full sm:w-1/2 lg:w-1/3 px-4">
                        <h4 className="text-lg font-bold text-[#012970] font-nunito mb-6 relative pb-2 after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-12 after:h-0.5 after:bg-[#4154f1]">Follow Us</h4>
                        <p className="text-[#444444] mb-6">Connect with us on social media for latest updates and offers.</p>
                        <div className="flex gap-4">
                            <a href="" className="w-10 h-10 rounded border border-[#4154f1]/20 flex items-center justify-center text-[#4154f1] hover:bg-[#4154f1] hover:text-white transition-all"><Twitter className="w-4 h-4" /></a>
                            <a href="" className="w-10 h-10 rounded border border-[#4154f1]/20 flex items-center justify-center text-[#4154f1] hover:bg-[#4154f1] hover:text-white transition-all"><Facebook className="w-4 h-4" /></a>
                            <a href="" className="w-10 h-10 rounded border border-[#4154f1]/20 flex items-center justify-center text-[#4154f1] hover:bg-[#4154f1] hover:text-white transition-all"><Instagram className="w-4 h-4" /></a>
                            <a href="" className="w-10 h-10 rounded border border-[#4154f1]/20 flex items-center justify-center text-[#4154f1] hover:bg-[#4154f1] hover:text-white transition-all"><Linkedin className="w-4 h-4" /></a>
                        </div>
                    </div>
                </div>
            </div>
            <div className="container mx-auto max-w-7xl px-4 mt-16 pt-8 border-t border-gray-100 text-center">
                <p className="text-[#444444]">© <span>Copyright</span> <strong className="text-[#012970]">East West Filling Station</strong>. All Rights Reserved</p>
                <div className="mt-2 text-sm text-gray-400">
                    Developed By <a href="https://www.linkedin.com/in/mehedihsumon/" target="_blank" rel="noopener noreferrer" className="text-[#4154f1] hover:underline">Md Mehedi Hasan</a>
                </div>
            </div>
        </footer>
    );
}