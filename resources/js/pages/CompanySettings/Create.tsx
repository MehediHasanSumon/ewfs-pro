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

const initialData: CompanySettingFormData = {
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
};

export default function Create() {
    const {
        data,
        setData,
        post,
        processing,
        errors,
        setError,
        clearErrors,
    } = useForm<CompanySettingFormData>(initialData);

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/company-settings', { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Company Setting" />

            <div className="space-y-6 p-4 sm:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold dark:text-white">
                            Create Company Setting
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Add new company information
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
                            submitLabel="Create"
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
