import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

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
}

interface UpdateProps {
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
        title: 'Edit',
        href: '#',
    },
];

export default function Update({ companySetting }: UpdateProps) {
    const { data, setData, processing, errors } = useForm<{
        company_name: string;
        company_details: string;
        proprietor_name: string;
        company_address: string;
        factory_address: string;
        company_mobile: string;
        company_phone: string;
        company_email: string;
        trade_license: string;
        tin_no: string;
        bin_no: string;
        vat_no: string;
        vat_rate: string;
        currency: string;
        company_logo: File | null;
        is_registration: boolean;
        status: number;
    }>({
        company_name: companySetting.company_name,
        company_details: companySetting.company_details || '',
        proprietor_name: companySetting.proprietor_name || '',
        company_address: companySetting.company_address || '',
        factory_address: companySetting.factory_address || '',
        company_mobile: companySetting.company_mobile || '',
        company_phone: companySetting.company_phone || '',
        company_email: companySetting.company_email || '',
        trade_license: companySetting.trade_license || '',
        tin_no: companySetting.tin_no || '',
        bin_no: companySetting.bin_no || '',
        vat_no: companySetting.vat_no || '',
        vat_rate: companySetting.vat_rate?.toString() || '',
        currency: companySetting.currency || '',
        company_logo: null,
        is_registration: companySetting.is_registration || false,
        status: companySetting.status
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const formData = new FormData();
        (Object.keys(data) as Array<keyof typeof data>).forEach(key => {
            const value = data[key];
            if (value !== null && value !== undefined) {
                formData.append(key, value as any);
            }
        });
        formData.append('_method', 'PUT');

        router.post(`/company-settings/${companySetting.id}`, formData);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Company Setting" />

            <div className="p-6 space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Edit Company Setting</h1>
                        <p className="text-gray-600 dark:text-gray-400">Update company information</p>
                    </div>
                    <Link href="/company-settings">
                        <Button variant="secondary">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to List
                        </Button>
                    </Link>
                </div>

                <Card className="dark:bg-gray-800 dark:border-gray-700">
                    <CardHeader>
                        <CardTitle className="dark:text-white">Company Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <Label htmlFor="company_name" className="dark:text-gray-200">Company Name *</Label>
                                    <Input
                                        id="company_name"
                                        value={data.company_name}
                                        onChange={(e) => setData('company_name', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        required
                                    />
                                    {errors.company_name && <span className="text-red-500 text-sm">{errors.company_name}</span>}
                                </div>
                                <div>
                                    <Label htmlFor="company_details" className="dark:text-gray-200">Company Details</Label>
                                    <Input
                                        id="company_details"
                                        value={data.company_details}
                                        onChange={(e) => setData('company_details', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="proprietor_name" className="dark:text-gray-200">Proprietor Name</Label>
                                    <Input
                                        id="proprietor_name"
                                        value={data.proprietor_name}
                                        onChange={(e) => setData('proprietor_name', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="company_address" className="dark:text-gray-200">Company Address</Label>
                                    <Input
                                        id="company_address"
                                        value={data.company_address}
                                        onChange={(e) => setData('company_address', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="factory_address" className="dark:text-gray-200">Factory Address</Label>
                                    <Input
                                        id="factory_address"
                                        value={data.factory_address}
                                        onChange={(e) => setData('factory_address', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="company_mobile" className="dark:text-gray-200">Cell Number</Label>
                                    <Input
                                        id="company_mobile"
                                        value={data.company_mobile}
                                        onChange={(e) => setData('company_mobile', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="company_phone" className="dark:text-gray-200">Phone Number</Label>
                                    <Input
                                        id="company_phone"
                                        value={data.company_phone}
                                        onChange={(e) => setData('company_phone', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="company_email" className="dark:text-gray-200">E-mail</Label>
                                    <Input
                                        id="company_email"
                                        type="email"
                                        value={data.company_email}
                                        onChange={(e) => setData('company_email', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="trade_license" className="dark:text-gray-200">Trade License No</Label>
                                    <Input
                                        id="trade_license"
                                        value={data.trade_license}
                                        onChange={(e) => setData('trade_license', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="tin_no" className="dark:text-gray-200">e-TIN No</Label>
                                    <Input
                                        id="tin_no"
                                        value={data.tin_no}
                                        onChange={(e) => setData('tin_no', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="bin_no" className="dark:text-gray-200">BIN No</Label>
                                    <Input
                                        id="bin_no"
                                        value={data.bin_no}
                                        onChange={(e) => setData('bin_no', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="vat_no" className="dark:text-gray-200">VAT No</Label>
                                    <Input
                                        id="vat_no"
                                        value={data.vat_no}
                                        onChange={(e) => setData('vat_no', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="vat_rate" className="dark:text-gray-200">VAT Rate</Label>
                                    <Input
                                        id="vat_rate"
                                        type="number"
                                        step="0.01"
                                        value={data.vat_rate}
                                        onChange={(e) => setData('vat_rate', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="currency" className="dark:text-gray-200">Currency</Label>
                                    <Input
                                        id="currency"
                                        value={data.currency}
                                        onChange={(e) => setData('currency', e.target.value)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="is_registration" className="dark:text-gray-200">User Registration</Label>
                                    <select
                                        id="is_registration"
                                        value={data.is_registration ? 1 : 0}
                                        onChange={(e) => setData('is_registration', e.target.value === '1')}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value={1}>Enable</option>
                                        <option value={0}>Disable</option>
                                    </select>
                                </div>
                                <div>
                                    <Label htmlFor="status" className="dark:text-gray-200">Status</Label>
                                    <select
                                        id="status"
                                        value={data.status}
                                        onChange={(e) => setData('status', parseInt(e.target.value))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value={1}>Active</option>
                                        <option value={0}>Disable</option>
                                    </select>
                                </div>
                                <div>
                                    <Label htmlFor="company_logo" className="dark:text-gray-200">Company Logo</Label>
                                    <Input
                                        id="company_logo"
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) => setData('company_logo', e.target.files?.[0] || null)}
                                        className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                    {companySetting.company_logo && (
                                        <p className="text-sm text-gray-500 mt-1">Current: {companySetting.company_logo.split('/').pop()}</p>
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end gap-2">
                                <Link href="/company-settings">
                                    <Button type="button" variant="secondary">Cancel</Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    <Save className="h-4 w-4 mr-2" />
                                    {processing ? 'Updating...' : 'Update'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}