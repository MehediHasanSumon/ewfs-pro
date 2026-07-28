import {
    CompanySettingForm,
    type CompanySettingFormData,
} from '@/components/forms/company-setting-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

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
    const {
        data,
        setData,
        post,
        processing,
        errors,
        setError,
        clearErrors,
        transform,
    } = useForm<CompanySettingFormData>({
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
        status: companySetting.status,
    });

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        transform((formData) => ({ ...formData, _method: 'PUT' }));
        post(`/company-settings/${companySetting.id}`, {
            forceFormData: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Company Setting" />

            <div className="space-y-6 p-4 sm:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold dark:text-white">
                            Edit Company Setting
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Update company information
                        </p>
                    </div>
                    <Link href="/company-settings">
                        <Button variant="secondary">
                            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
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
                        <CompanySettingForm
                            data={data}
                            errors={errors}
                            processing={processing}
                            submitLabel="Update"
                            currentLogo={companySetting.company_logo}
                            onSubmit={handleSubmit}
                            setData={setData}
                            setError={setError}
                            clearErrors={clearErrors}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
