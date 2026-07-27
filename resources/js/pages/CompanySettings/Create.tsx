import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useState } from 'react';

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
        title: 'Create',
        href: '/company-settings/create',
    },
];

export default function Create() {
    const [processing, setProcessing] = useState(false);
    const [data, setData] = useState<{
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
        company_name: '',
        company_details: '',
        proprietor_name: '',
        company_address: '',
        factory_address: '',
        company_mobile: '',
        company_phone: '',
        company_email: '',
        trade_license: '',
        tin_no: '',
        bin_no: '',
        vat_no: '',
        vat_rate: '',
        currency: '',
        company_logo: null,
        is_registration: false,
        status: 1,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        router.post('/company-settings', data, {
            forceFormData: true,
            onFinish: () => setProcessing(false)
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Company Setting" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Create Company Setting
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Add new company information
                        </p>
                    </div>
                    <Link href="/company-settings">
                        <Button variant="secondary">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to List
                        </Button>
                    </Link>
                </div>

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="dark:text-white">
                            Company Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <Label
                                        htmlFor="company_name"
                                        className="dark:text-gray-200"
                                    >
                                        Company Name *
                                    </Label>
                                    <Input
                                        id="company_name"
                                        value={data.company_name}
                                        onChange={(e) =>
                                            setData({ ...data, company_name: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        required
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="company_details"
                                        className="dark:text-gray-200"
                                    >
                                        Company Details
                                    </Label>
                                    <Input
                                        id="company_details"
                                        value={data.company_details}
                                        onChange={(e) =>
                                            setData({ ...data, company_details: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="proprietor_name"
                                        className="dark:text-gray-200"
                                    >
                                        Proprietor Name
                                    </Label>
                                    <Input
                                        id="proprietor_name"
                                        value={data.proprietor_name}
                                        onChange={(e) =>
                                            setData({ ...data, proprietor_name: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="company_address"
                                        className="dark:text-gray-200"
                                    >
                                        Company Address
                                    </Label>
                                    <Input
                                        id="company_address"
                                        value={data.company_address}
                                        onChange={(e) =>
                                            setData({ ...data, company_address: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="factory_address"
                                        className="dark:text-gray-200"
                                    >
                                        Factory Address
                                    </Label>
                                    <Input
                                        id="factory_address"
                                        value={data.factory_address}
                                        onChange={(e) =>
                                            setData({ ...data, factory_address: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="company_mobile"
                                        className="dark:text-gray-200"
                                    >
                                        Cell Number
                                    </Label>
                                    <Input
                                        id="company_mobile"
                                        value={data.company_mobile}
                                        onChange={(e) =>
                                            setData({ ...data, company_mobile: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="company_phone"
                                        className="dark:text-gray-200"
                                    >
                                        Phone Number
                                    </Label>
                                    <Input
                                        id="company_phone"
                                        value={data.company_phone}
                                        onChange={(e) =>
                                            setData({ ...data, company_phone: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="company_email"
                                        className="dark:text-gray-200"
                                    >
                                        E-mail
                                    </Label>
                                    <Input
                                        id="company_email"
                                        type="email"
                                        value={data.company_email}
                                        onChange={(e) =>
                                            setData({ ...data, company_email: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="trade_license"
                                        className="dark:text-gray-200"
                                    >
                                        Trade License No
                                    </Label>
                                    <Input
                                        id="trade_license"
                                        value={data.trade_license}
                                        onChange={(e) =>
                                            setData({ ...data, trade_license: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="tin_no"
                                        className="dark:text-gray-200"
                                    >
                                        e-TIN No
                                    </Label>
                                    <Input
                                        id="tin_no"
                                        value={data.tin_no}
                                        onChange={(e) =>
                                            setData({ ...data, tin_no: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="bin_no"
                                        className="dark:text-gray-200"
                                    >
                                        BIN No
                                    </Label>
                                    <Input
                                        id="bin_no"
                                        value={data.bin_no}
                                        onChange={(e) =>
                                            setData({ ...data, bin_no: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="vat_no"
                                        className="dark:text-gray-200"
                                    >
                                        VAT No
                                    </Label>
                                    <Input
                                        id="vat_no"
                                        value={data.vat_no}
                                        onChange={(e) =>
                                            setData({ ...data, vat_no: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="vat_rate"
                                        className="dark:text-gray-200"
                                    >
                                        VAT Rate
                                    </Label>
                                    <Input
                                        id="vat_rate"
                                        type="number"
                                        step="0.01"
                                        value={data.vat_rate}
                                        onChange={(e) =>
                                            setData({ ...data, vat_rate: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="currency"
                                        className="dark:text-gray-200"
                                    >
                                        Currency
                                    </Label>
                                    <Input
                                        id="currency"
                                        value={data.currency}
                                        onChange={(e) =>
                                            setData({ ...data, currency: e.target.value })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label
                                        htmlFor="is_registration"
                                        className="dark:text-gray-200"
                                    >
                                        User Registration
                                    </Label>
                                    <select
                                        id="is_registration"
                                        value={data.is_registration ? 1 : 0}
                                        onChange={(e) =>
                                            setData({ ...data, is_registration: e.target.value === '1' })
                                        }
                                        className="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value={1}>Enable</option>
                                        <option value={0}>Disable</option>
                                    </select>
                                </div>
                                <div>
                                    <Label
                                        htmlFor="status"
                                        className="dark:text-gray-200"
                                    >
                                        Status
                                    </Label>
                                    <select
                                        id="status"
                                        value={data.status}
                                        onChange={(e) =>
                                            setData({ ...data, status: parseInt(e.target.value) })
                                        }
                                        className="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value={1}>Active</option>
                                        <option value={0}>Disable</option>
                                    </select>
                                </div>
                                <div>
                                    <Label
                                        htmlFor="company_logo"
                                        className="dark:text-gray-200"
                                    >
                                        Company Logo
                                    </Label>
                                    <Input
                                        id="company_logo"
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) =>
                                            setData({ ...data, company_logo: e.target.files?.[0] || null })
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end gap-2">
                                <Link href="/company-settings">
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Creating...' : 'Create'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}