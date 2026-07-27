import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Edit, Building } from 'lucide-react';

interface CompanySetting {
    id: number;
    company_name: string;
    company_details?: string;
    proprietor_name?: string;
    company_address?: string;
    factory_address?: string;
    company_mobile?: string;
    company_phone?: string;
    company_email?: string;
    trade_license?: string;
    tin_no?: string;
    bin_no?: string;
    vat_no?: string;
    vat_rate?: number;
    currency?: string;
    company_logo?: string;
    is_registration?: boolean;
    status: number;
    created_at: string;
}

interface ShowProps {
    companySetting: CompanySetting;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Company Settings',
        href: '/company-settings',
    },
    {
        title: 'View',
        href: '#',
    },
];

export default function Show({ companySetting }: ShowProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="View Company Setting" />
            
            <div className="p-6 space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Company Setting Details</h1>
                        <p className="text-gray-600 dark:text-gray-400">View company information</p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={`/company-settings/${companySetting.id}/edit`}>
                            <Button>
                                <Edit className="h-4 w-4 mr-2" />
                                Edit
                            </Button>
                        </Link>
                        <Link href="/company-settings">
                            <Button variant="secondary">
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back to List
                            </Button>
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2">
                        <Card className="dark:bg-gray-800 dark:border-gray-700">
                            <CardHeader>
                                <CardTitle className="dark:text-white flex items-center gap-2">
                                    <Building className="h-5 w-5" />
                                    Company Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Company Name</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.company_name}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Company Details</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.company_details || '-'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Proprietor Name</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.proprietor_name || '-'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Status</label>
                                        <span className={`inline-flex px-2 py-1 rounded text-xs ${companySetting.status == 1 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
                                            {companySetting.status == 1 ? 'Active' : 'Disabled'}
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="dark:bg-gray-800 dark:border-gray-700 mt-6">
                            <CardHeader>
                                <CardTitle className="dark:text-white">Contact Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Company Address</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.company_address || '-'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Factory Address</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.factory_address || '-'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Cell Number</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.company_mobile || '-'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Phone Number</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.company_phone || '-'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Email</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.company_email || '-'}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="dark:bg-gray-800 dark:border-gray-700 mt-6">
                            <CardHeader>
                                <CardTitle className="dark:text-white">Legal Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Trade License No</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.trade_license || '-'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">e-TIN No</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.tin_no || '-'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">BIN No</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.bin_no || '-'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">VAT No</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.vat_no || '-'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">VAT Rate</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.vat_rate ? `${companySetting.vat_rate}%` : '-'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Currency</label>
                                        <p className="text-gray-900 dark:text-white">{companySetting.currency || '-'}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div>
                        <Card className="dark:bg-gray-800 dark:border-gray-700">
                            <CardHeader>
                                <CardTitle className="dark:text-white">Company Logo</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {companySetting.company_logo ? (
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Company Logo</label>
                                        <div className="mt-2 p-4 border rounded-lg dark:border-gray-600">
                                            <img 
                                                src={`/storage/${companySetting.company_logo}`} 
                                                alt="Company Logo" 
                                                className="max-w-[200px] h-auto mx-auto"
                                            />
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-gray-500 dark:text-gray-400 text-center py-8">No logo uploaded</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="dark:bg-gray-800 dark:border-gray-700 mt-6">
                            <CardHeader>
                                <CardTitle className="dark:text-white">User Registration</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Registration Status</label>
                                    <span className={`inline-flex px-2 py-1 rounded text-xs mt-2 ${companySetting.is_registration ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
                                        {companySetting.is_registration ? 'Enabled' : 'Disabled'}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}