import {
    EmployeeForm,
    type EmployeeFormData,
    type EmployeeOption,
    type EmployeeUploadLimits,
} from '@/components/forms/employee-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Employees', href: '/employees' },
    { title: 'Create', href: '/employees/create' },
];

interface CreateEmployeeProps {
    departments: EmployeeOption[];
    designations: EmployeeOption[];
    empTypes: EmployeeOption[];
    employeeUploadLimits: EmployeeUploadLimits;
}

const initialData: EmployeeFormData = {
    employee_name: '',
    email: '',
    mobile: '',
    mobile_two: '',
    department_id: '',
    designation_id: '',
    emp_type_id: '',
    dob: '',
    gender: '',
    blood_group: '',
    marital_status: '',
    religion: '',
    nid: '',
    emergency_contact_person: '',
    emergency_contact_number: '',
    father_name: '',
    mother_name: '',
    present_address: '',
    permanent_address: '',
    job_status: '',
    joining_date: '',
    highest_education: '',
    status: true,
    photo: null,
    signature: null,
    nid_document: null,
    remove_photo: false,
    remove_signature: false,
    remove_nid_document: false,
    salary_structure: {
        basic_salary: '',
        home_rent_percent: '0',
        medical_percent: '0',
        conveyance_percent: '0',
        other_allowances: '0',
        deductions: '0',
    },
};

export default function CreateEmployee({
    departments = [],
    designations = [],
    empTypes = [],
    employeeUploadLimits = {
        image_max_kb: 5120,
        nid_max_kb: 10240,
    },
}: CreateEmployeeProps) {
    const { data, setData, post, processing, errors } =
        useForm<EmployeeFormData>(initialData);

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/employees', { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Employee" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Create Employee
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Add a new employee to the system
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => router.get('/employees')}
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Employees
                    </Button>
                </div>

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardContent>
                        <EmployeeForm
                            data={data}
                            errors={errors as Record<string, string>}
                            processing={processing}
                            submitLabel="Create Employee"
                            departments={departments}
                            designations={designations}
                            empTypes={empTypes}
                            uploadLimits={employeeUploadLimits}
                            onSubmit={handleSubmit}
                            setData={setData}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
