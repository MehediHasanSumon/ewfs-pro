import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { 
    ArrowLeft, 
    Edit, 
    Mail, 
    Phone, 
    MapPin, 
    Calendar, 
    DollarSign,
    Building,
    User,
    Clock,
    FileText,
    UserPlus,
    Briefcase
} from 'lucide-react';

interface Employee {
    id: number;
    account_id: number;
    emp_type_id: number;
    department_id: number;
    designation_id: number;
    employee_code: string;
    employee_name: string;
    email: string;
    order: number;
    dob: string;
    gender: string;
    blood_group: string;
    marital_status: string;
    emergency_contact_person: string;
    religion: string;
    nid: string;
    mobile: string;
    mobile_two: string;
    emergency_contact_number: string;
    father_name: string;
    mother_name: string;
    present_address: string;
    permanent_address: string;
    job_status: string;
    salary: number;
    joining_date: string;
    status: boolean;
    status_date: string;
    photo: string;
    signature: string;
    highest_education: string;
    reference_one_name: string;
    reference_one_phone: string;
    reference_one_address: string;
    reference_two_name: string;
    reference_two_phone: string;
    reference_two_address: string;
    created_at: string;
    updated_at: string;
    department?: {
        id: number;
        name: string;
    };
    designation?: {
        id: number;
        name: string;
    };
    emp_type?: {
        id: number;
        name: string;
    };
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
        title: 'Details',
        href: '#',
    },
];

interface RecentSalaryPayment {
    id: number;
    voucher_no: string;
    date: string;
    amount: number;
    type: string;
    sub_type: string;
    status: string;
}

interface RecentAdvancedPayment {
    id: number;
    voucher_no: string;
    date: string;
    amount: number;
    type: string;
    sub_type: string;
    status: string;
}

interface ShowEmployeeProps {
    employee: Employee;
    recentSalaryPayments: RecentSalaryPayment[];
    recentAdvancedPayments: RecentAdvancedPayment[];
    totalPaidSalary: number;
    salaryPaymentCount: number;
    totalAdvanced: number;
    advancedCount: number;
    totalAdvancedReturns: number;
    advancedReturnCount: number;
    netAdvanced: number;
    salaryDue: number;
    netBalance: number;
    monthsWorked: number;
}

export default function ShowEmployee({ 
    employee, 
    recentSalaryPayments, 
    recentAdvancedPayments, 
    totalPaidSalary, 
    salaryPaymentCount, 
    totalAdvanced, 
    advancedCount, 
    totalAdvancedReturns,
    advancedReturnCount,
    netAdvanced,
    salaryDue,
    netBalance,
    monthsWorked
}: ShowEmployeeProps) {
    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    const formatCurrency = (amount: string) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(parseFloat(amount));
    };

    const getStatusColor = (status: boolean) => {
        return status 
            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    };

    const getStatusText = (status: boolean) => {
        return status ? 'Active' : 'Inactive';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Employee - ${employee.employee_name}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            {employee.employee_name}
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Employee details and information
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.get(`/employees/${employee.id}/statement`)}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Statement
                        </Button>
                        <Button
                            onClick={() => router.get(`/employees/${employee.id}/edit`)}
                        >
                            <Edit className="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() => router.get('/employees')}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to List
                        </Button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Monthly Salary</p>
                                    <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">{(employee.salary || 0).toLocaleString()}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{monthsWorked} months worked</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Paid Salary</p>
                                    <p className="text-2xl font-bold text-green-600 dark:text-green-400">{totalPaidSalary.toLocaleString()}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{salaryPaymentCount} payments</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Net Advanced</p>
                                    <p className="text-2xl font-bold text-orange-600 dark:text-orange-400">{netAdvanced.toLocaleString()}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">Given: {totalAdvanced.toLocaleString()}, Returned: {totalAdvancedReturns.toLocaleString()}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Salary Due</p>
                                    <p className={`text-2xl font-bold ${
                                        salaryDue > 0 
                                            ? 'text-red-600 dark:text-red-400' 
                                            : salaryDue < 0 
                                                ? 'text-green-600 dark:text-green-400' 
                                                : 'text-gray-900 dark:text-white'
                                    }`}>
                                        {salaryDue.toLocaleString()}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {netBalance > 0 ? 'Employee Will Get' : netBalance < 0 ? 'Employee Owes' : 'Balanced'}
                                    </p>
                                    <p className={`text-2xl font-bold ${
                                        netBalance > 0 
                                            ? 'text-blue-600 dark:text-blue-400' 
                                            : netBalance < 0 
                                                ? 'text-red-600 dark:text-red-400' 
                                                : 'text-gray-900 dark:text-white'
                                    }`}>
                                        {Math.abs(netBalance).toLocaleString()}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Employee Basic Info */}
                    <div className="lg:col-span-2">
                        <Card className="h-full dark:border-gray-700 dark:bg-gray-800">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 dark:text-white">
                                    <User className="h-5 w-5" />
                                    Personal Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Full Name
                                        </label>
                                        <p className="mt-1 text-lg font-semibold dark:text-white">
                                            {employee.employee_name}
                                        </p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Status
                                        </label>
                                        <div className="mt-1">
                                            <Badge className={getStatusColor(employee.status)}>
                                                {getStatusText(employee.status)}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div className="flex items-center gap-3">
                                        <Mail className="h-5 w-5 text-gray-400" />
                                        <div>
                                            <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                Email
                                            </label>
                                            <p className="dark:text-white">{employee.email}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <Phone className="h-5 w-5 text-gray-400" />
                                        <div>
                                            <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                Phone
                                            </label>
                                            <p className="dark:text-white">{employee.mobile}</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Date of Birth
                                        </label>
                                        <p className="dark:text-white">{employee.dob ? formatDate(employee.dob) : 'Not provided'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Blood Group
                                        </label>
                                        <p className="dark:text-white">{employee.blood_group || 'Not provided'}</p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Marital Status
                                        </label>
                                        <p className="dark:text-white">{employee.marital_status || 'Not provided'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Religion
                                        </label>
                                        <p className="dark:text-white">{employee.religion || 'Not provided'}</p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            NID
                                        </label>
                                        <p className="dark:text-white">{employee.nid || 'Not provided'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Father's Name
                                        </label>
                                        <p className="dark:text-white">{employee.father_name || 'Not provided'}</p>
                                    </div>
                                </div>

                                {employee.present_address && (
                                    <div className="flex items-start gap-3">
                                        <MapPin className="h-5 w-5 text-gray-400 mt-1" />
                                        <div>
                                            <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                Present Address
                                            </label>
                                            <p className="dark:text-white">{employee.present_address}</p>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Work Information */}
                    <div>
                        <Card className="h-full dark:border-gray-700 dark:bg-gray-800">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 dark:text-white">
                                    <Building className="h-5 w-5" />
                                    Work Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Joining Date
                                    </label>
                                    <p className="flex items-center gap-2 dark:text-white">
                                        <Calendar className="h-4 w-4" />
                                        {formatDate(employee.joining_date)}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Job Status
                                    </label>
                                    <p className="font-semibold dark:text-white">
                                        {employee.job_status}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Salary
                                    </label>
                                    <p className="font-semibold dark:text-white">
                                        {employee.salary?.toLocaleString() || 'Not Set'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Department
                                    </label>
                                    <p className="font-semibold dark:text-white">
                                        {employee.department?.name || 'Not Assigned'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Designation
                                    </label>
                                    <p className="font-semibold dark:text-white">
                                        {employee.designation?.name || 'Not Assigned'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Employee Type
                                    </label>
                                    <p className="font-semibold dark:text-white">
                                        {employee.emp_type?.name || 'Not Assigned'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Gender
                                    </label>
                                    <p className="font-semibold dark:text-white">
                                        {employee.gender}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Action Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">Recent Salary Summary</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-gray-200 dark:border-gray-700">
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">SL</th>
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">Voucher No</th>
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">Date</th>
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">Amount</th>
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">Type</th>
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">Payment Type</th>
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {recentSalaryPayments.length > 0 ? (
                                                recentSalaryPayments.map((payment, index) => (
                                                    <tr key={payment.id} className="border-b border-gray-100 dark:border-gray-700">
                                                        <td className="py-2 text-gray-900 dark:text-white">{index + 1}</td>
                                                        <td className="py-2 text-gray-900 dark:text-white">{payment.voucher_no}</td>
                                                        <td className="py-2 text-gray-900 dark:text-white">{payment.date}</td>
                                                        <td className="py-2 text-gray-900 dark:text-white">{payment.amount.toLocaleString()}</td>
                                                        <td className="py-2 text-gray-600 dark:text-gray-400">{payment.sub_type}</td>
                                                        <td className="py-2 text-gray-600 dark:text-gray-400">{payment.type}</td>
                                                        <td className="py-2">
                                                            <span className={`px-2 py-1 rounded-full text-xs ${
                                                                payment.status === 'Paid' 
                                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                            }`}>
                                                                {payment.status}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan={7} className="text-center py-8 text-gray-500 dark:text-gray-400">
                                                        No salary records found
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">Recent Advanced Summary</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-gray-200 dark:border-gray-700">
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">SL</th>
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">Voucher No</th>
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">Date</th>
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">Amount</th>
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">Type</th>
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">Payment Type</th>
                                                <th className="text-left py-2 text-gray-600 dark:text-gray-400">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {recentAdvancedPayments.length > 0 ? (
                                                recentAdvancedPayments.map((payment, index) => (
                                                    <tr key={payment.id} className="border-b border-gray-100 dark:border-gray-700">
                                                        <td className="py-2 text-gray-900 dark:text-white">{index + 1}</td>
                                                        <td className="py-2 text-gray-900 dark:text-white">{payment.voucher_no}</td>
                                                        <td className="py-2 text-gray-900 dark:text-white">{payment.date}</td>
                                                        <td className="py-2 text-gray-900 dark:text-white">{payment.amount.toLocaleString()}</td>
                                                        <td className="py-2 text-gray-600 dark:text-gray-400">{payment.sub_type}</td>
                                                        <td className="py-2 text-gray-600 dark:text-gray-400">{payment.type}</td>
                                                        <td className="py-2">
                                                            <span className={`px-2 py-1 rounded-full text-xs ${
                                                                payment.status === 'Given' 
                                                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                                            }`}>
                                                                {payment.status}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan={7} className="text-center py-8 text-gray-500 dark:text-gray-400">
                                                        No advanced records found
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>


            </div>
        </AppLayout>
    );
}