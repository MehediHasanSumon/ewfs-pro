import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useEffect } from 'react';

interface Department {
    id: number;
    name: string;
}

interface Designation {
    id: number;
    name: string;
}

interface EmpType {
    id: number;
    name: string;
}

interface Group {
    id: number;
    code: string;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Employees',
        href: '/employees',
    },
    {
        title: 'Create',
        href: '/employees/create',
    },
];

interface CreateEmployeeProps {
    departments: Department[];
    designations: Designation[];
    empTypes: EmpType[];
    groups: Group[];
    lastEmployeeGroup?: { id: number; code: string };
}

export default function CreateEmployee({ 
    departments = [], 
    designations = [], 
    empTypes = [],
    groups = [],
    lastEmployeeGroup 
}: CreateEmployeeProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        employee_code: '',
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
        salary: '',
        joining_date: '',
        highest_education: '',
        status: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/employees', {
            onSuccess: () => {
                router.get('/employees');
            },
        });
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
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Employees
                    </Button>
                </div>

                <Card className="dark:border-gray-700 dark:bg-gray-800">

                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                                <div>
                                    <Label htmlFor="employee_name" className="dark:text-gray-200">
                                        Full Name *
                                    </Label>
                                    <Input
                                        id="employee_name"
                                        value={data.employee_name}
                                        onChange={(e) => setData('employee_name', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter full name"
                                    />
                                    {errors.employee_name && (
                                        <span className="text-sm text-red-500">{errors.employee_name}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="employee_code" className="dark:text-gray-200">
                                        Employee Code
                                    </Label>
                                    <Input
                                        id="employee_code"
                                        value={data.employee_code}
                                        onChange={(e) => setData('employee_code', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter employee code"
                                    />
                                    {errors.employee_code && (
                                        <span className="text-sm text-red-500">{errors.employee_code}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="mobile" className="dark:text-gray-200">
                                        Mobile
                                    </Label>
                                    <Input
                                        id="mobile"
                                        value={data.mobile}
                                        onChange={(e) => setData('mobile', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter mobile number"
                                    />
                                    {errors.mobile && (
                                        <span className="text-sm text-red-500">{errors.mobile}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="email" className="dark:text-gray-200">
                                        Email
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter email address"
                                    />
                                    {errors.email && (
                                        <span className="text-sm text-red-500">{errors.email}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="department_id" className="dark:text-gray-200">
                                        Department
                                    </Label>
                                    <Select
                                        value={data.department_id}
                                        onValueChange={(value) => setData('department_id', value)}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="Select department" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {departments.map((dept) => (
                                                <SelectItem key={dept.id} value={dept.id.toString()}>
                                                    {dept.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.department_id && (
                                        <span className="text-sm text-red-500">{errors.department_id}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="designation_id" className="dark:text-gray-200">
                                        Designation
                                    </Label>
                                    <Select
                                        value={data.designation_id}
                                        onValueChange={(value) => setData('designation_id', value)}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="Select designation" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {designations.map((desig) => (
                                                <SelectItem key={desig.id} value={desig.id.toString()}>
                                                    {desig.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.designation_id && (
                                        <span className="text-sm text-red-500">{errors.designation_id}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="emp_type_id" className="dark:text-gray-200">
                                        Employee Type
                                    </Label>
                                    <Select
                                        value={data.emp_type_id}
                                        onValueChange={(value) => setData('emp_type_id', value)}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="Select employee type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {empTypes.map((type) => (
                                                <SelectItem key={type.id} value={type.id.toString()}>
                                                    {type.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.emp_type_id && (
                                        <span className="text-sm text-red-500">{errors.emp_type_id}</span>
                                    )}
                                </div>



                                <div>
                                    <Label htmlFor="joining_date" className="dark:text-gray-200">
                                        Joining Date
                                    </Label>
                                    <Input
                                        id="joining_date"
                                        type="date"
                                        value={data.joining_date}
                                        onChange={(e) => setData('joining_date', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                    {errors.joining_date && (
                                        <span className="text-sm text-red-500">{errors.joining_date}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="job_status" className="dark:text-gray-200">
                                        Job Status
                                    </Label>
                                    <Input
                                        id="job_status"
                                        value={data.job_status}
                                        onChange={(e) => setData('job_status', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter job status"
                                    />
                                    {errors.job_status && (
                                        <span className="text-sm text-red-500">{errors.job_status}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="salary" className="dark:text-gray-200">
                                        Salary
                                    </Label>
                                    <Input
                                        id="salary"
                                        type="number"
                                        step="0.01"
                                        value={data.salary}
                                        onChange={(e) => setData('salary', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter salary"
                                    />
                                    {errors.salary && (
                                        <span className="text-sm text-red-500">{errors.salary}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="dob" className="dark:text-gray-200">
                                        Date of Birth
                                    </Label>
                                    <Input
                                        id="dob"
                                        type="date"
                                        value={data.dob}
                                        onChange={(e) => setData('dob', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                    {errors.dob && (
                                        <span className="text-sm text-red-500">{errors.dob}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="gender" className="dark:text-gray-200">
                                        Gender
                                    </Label>
                                    <Select
                                        value={data.gender}
                                        onValueChange={(value) => setData('gender', value)}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="Select gender" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Male">Male</SelectItem>
                                            <SelectItem value="Female">Female</SelectItem>
                                            <SelectItem value="Other">Other</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.gender && (
                                        <span className="text-sm text-red-500">{errors.gender}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="mobile_two" className="dark:text-gray-200">
                                        Alternative Mobile
                                    </Label>
                                    <Input
                                        id="mobile_two"
                                        value={data.mobile_two}
                                        onChange={(e) => setData('mobile_two', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter alternative mobile"
                                    />
                                    {errors.mobile_two && (
                                        <span className="text-sm text-red-500">{errors.mobile_two}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="blood_group" className="dark:text-gray-200">
                                        Blood Group
                                    </Label>
                                    <Select
                                        value={data.blood_group}
                                        onValueChange={(value) => setData('blood_group', value)}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="Select blood group" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="A+">A+</SelectItem>
                                            <SelectItem value="A-">A-</SelectItem>
                                            <SelectItem value="B+">B+</SelectItem>
                                            <SelectItem value="B-">B-</SelectItem>
                                            <SelectItem value="AB+">AB+</SelectItem>
                                            <SelectItem value="AB-">AB-</SelectItem>
                                            <SelectItem value="O+">O+</SelectItem>
                                            <SelectItem value="O-">O-</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.blood_group && (
                                        <span className="text-sm text-red-500">{errors.blood_group}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="marital_status" className="dark:text-gray-200">
                                        Marital Status
                                    </Label>
                                    <Select
                                        value={data.marital_status}
                                        onValueChange={(value) => setData('marital_status', value)}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="Select marital status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Single">Single</SelectItem>
                                            <SelectItem value="Married">Married</SelectItem>
                                            <SelectItem value="Divorced">Divorced</SelectItem>
                                            <SelectItem value="Widowed">Widowed</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.marital_status && (
                                        <span className="text-sm text-red-500">{errors.marital_status}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="religion" className="dark:text-gray-200">
                                        Religion
                                    </Label>
                                    <Input
                                        id="religion"
                                        value={data.religion}
                                        onChange={(e) => setData('religion', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter religion"
                                    />
                                    {errors.religion && (
                                        <span className="text-sm text-red-500">{errors.religion}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="nid" className="dark:text-gray-200">
                                        NID Number
                                    </Label>
                                    <Input
                                        id="nid"
                                        value={data.nid}
                                        onChange={(e) => setData('nid', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter NID number"
                                    />
                                    {errors.nid && (
                                        <span className="text-sm text-red-500">{errors.nid}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="father_name" className="dark:text-gray-200">
                                        Father's Name
                                    </Label>
                                    <Input
                                        id="father_name"
                                        value={data.father_name}
                                        onChange={(e) => setData('father_name', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter father's name"
                                    />
                                    {errors.father_name && (
                                        <span className="text-sm text-red-500">{errors.father_name}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="mother_name" className="dark:text-gray-200">
                                        Mother's Name
                                    </Label>
                                    <Input
                                        id="mother_name"
                                        value={data.mother_name}
                                        onChange={(e) => setData('mother_name', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter mother's name"
                                    />
                                    {errors.mother_name && (
                                        <span className="text-sm text-red-500">{errors.mother_name}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="emergency_contact_person" className="dark:text-gray-200">
                                        Emergency Contact Person
                                    </Label>
                                    <Input
                                        id="emergency_contact_person"
                                        value={data.emergency_contact_person}
                                        onChange={(e) => setData('emergency_contact_person', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter emergency contact person"
                                    />
                                    {errors.emergency_contact_person && (
                                        <span className="text-sm text-red-500">{errors.emergency_contact_person}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="emergency_contact_number" className="dark:text-gray-200">
                                        Emergency Contact Number
                                    </Label>
                                    <Input
                                        id="emergency_contact_number"
                                        value={data.emergency_contact_number}
                                        onChange={(e) => setData('emergency_contact_number', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter emergency contact number"
                                    />
                                    {errors.emergency_contact_number && (
                                        <span className="text-sm text-red-500">{errors.emergency_contact_number}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="highest_education" className="dark:text-gray-200">
                                        Highest Education
                                    </Label>
                                    <Input
                                        id="highest_education"
                                        value={data.highest_education}
                                        onChange={(e) => setData('highest_education', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter highest education"
                                    />
                                    {errors.highest_education && (
                                        <span className="text-sm text-red-500">{errors.highest_education}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="present_address" className="dark:text-gray-200">
                                        Present Address
                                    </Label>
                                    <Input
                                        id="present_address"
                                        value={data.present_address}
                                        onChange={(e) => setData('present_address', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter present address"
                                    />
                                    {errors.present_address && (
                                        <span className="text-sm text-red-500">{errors.present_address}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="permanent_address" className="dark:text-gray-200">
                                        Permanent Address
                                    </Label>
                                    <Input
                                        id="permanent_address"
                                        value={data.permanent_address}
                                        onChange={(e) => setData('permanent_address', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Enter permanent address"
                                    />
                                    {errors.permanent_address && (
                                        <span className="text-sm text-red-500">{errors.permanent_address}</span>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="status" className="dark:text-gray-200">
                                        Status
                                    </Label>
                                    <Select
                                        value={data.status ? 'true' : 'false'}
                                        onValueChange={(value) => setData('status', value === 'true')}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="true">Active</SelectItem>
                                            <SelectItem value="false">Inactive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.status && (
                                        <span className="text-sm text-red-500">{errors.status}</span>
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end gap-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.get('/employees')}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Creating...' : 'Create Employee'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}