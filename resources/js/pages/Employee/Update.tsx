import type { SalaryStructureInput } from '@/components/employee/salary-preview-table';
import {
    EmployeeForm,
    type EmployeeFormData,
    type EmployeeOption,
    type EmployeeUploadLimits,
    type ExistingEmployeeFiles,
} from '@/components/forms/employee-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface Employee extends ExistingEmployeeFiles {
    id: number;
    employee_code: string;
    employee_name: string;
    email?: string | null;
    mobile?: string | null;
    mobile_two?: string | null;
    department_id?: number | null;
    designation_id?: number | null;
    emp_type_id?: number | null;
    dob?: string | null;
    gender?: string | null;
    blood_group?: string | null;
    marital_status?: string | null;
    religion?: string | null;
    nid?: string | null;
    emergency_contact_person?: string | null;
    emergency_contact_number?: string | null;
    father_name?: string | null;
    mother_name?: string | null;
    present_address?: string | null;
    permanent_address?: string | null;
    job_status?: string | null;
    salary: number;
    joining_date?: string | null;
    highest_education?: string | null;
    status: boolean;
    salary_structure?:
        | (SalaryStructureInput & {
              gross_salary: number;
          })
        | null;
}

interface UpdateEmployeeProps {
    employee: Employee;
    departments: EmployeeOption[];
    designations: EmployeeOption[];
    empTypes: EmployeeOption[];
    employeeUploadLimits: EmployeeUploadLimits;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Employees', href: '/employees' },
    { title: 'Edit', href: '#' },
];

const stringValue = (value?: string | null) => value ?? '';

export default function UpdateEmployee({
    employee,
    departments = [],
    designations = [],
    empTypes = [],
    employeeUploadLimits = {
        image_max_kb: 5120,
        nid_max_kb: 10240,
    },
}: UpdateEmployeeProps) {
    const salaryStructure = employee.salary_structure;
    const { data, setData, post, transform, processing, errors } =
        useForm<EmployeeFormData>({
            employee_name: employee.employee_name,
            email: stringValue(employee.email),
            mobile: stringValue(employee.mobile),
            mobile_two: stringValue(employee.mobile_two),
            department_id: employee.department_id?.toString() ?? '',
            designation_id: employee.designation_id?.toString() ?? '',
            emp_type_id: employee.emp_type_id?.toString() ?? '',
            dob: stringValue(employee.dob),
            gender: stringValue(employee.gender),
            blood_group: stringValue(employee.blood_group),
            marital_status: stringValue(employee.marital_status),
            religion: stringValue(employee.religion),
            nid: stringValue(employee.nid),
            emergency_contact_person: stringValue(
                employee.emergency_contact_person,
            ),
            emergency_contact_number: stringValue(
                employee.emergency_contact_number,
            ),
            father_name: stringValue(employee.father_name),
            mother_name: stringValue(employee.mother_name),
            present_address: stringValue(employee.present_address),
            permanent_address: stringValue(employee.permanent_address),
            job_status: stringValue(employee.job_status),
            joining_date: stringValue(employee.joining_date),
            highest_education: stringValue(employee.highest_education),
            status: employee.status,
            photo: null,
            signature: null,
            nid_document: null,
            remove_photo: false,
            remove_signature: false,
            remove_nid_document: false,
            salary_structure: salaryStructure
                ? {
                      basic_salary: salaryStructure.basic_salary.toString(),
                      home_rent_percent:
                          salaryStructure.home_rent_percent.toString(),
                      medical_percent:
                          salaryStructure.medical_percent.toString(),
                      conveyance_percent:
                          salaryStructure.conveyance_percent.toString(),
                      other_allowances:
                          salaryStructure.other_allowances.toString(),
                      deductions: salaryStructure.deductions.toString(),
                  }
                : {
                      basic_salary:
                          employee.salary > 0 ? employee.salary.toString() : '',
                      home_rent_percent: '0',
                      medical_percent: '0',
                      conveyance_percent: '0',
                      other_allowances: '0',
                      deductions: '0',
                  },
        });

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        transform((formData) => ({ ...formData, _method: 'PUT' }));
        post(`/employees/${employee.id}`, { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Employee - ${employee.employee_name}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Edit Employee
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Update employee information
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
                            submitLabel="Update Employee"
                            departments={departments}
                            designations={designations}
                            empTypes={empTypes}
                            uploadLimits={employeeUploadLimits}
                            existingFiles={employee}
                            onSubmit={handleSubmit}
                            setData={setData}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
