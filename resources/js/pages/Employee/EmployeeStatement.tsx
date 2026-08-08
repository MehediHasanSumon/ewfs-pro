import { openPdfViewer } from '@/components/documents/pdf-viewer';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { ArrowLeft, FileText, Filter, X, Download } from 'lucide-react';
import { useState } from 'react';

interface Employee {
    id: number;
    employee_name: string;
    mobile?: string;
    present_address?: string;
    account?: {
        id: number;
        name: string;
        ac_number: string;
    };
}

interface Payment {
    id: number;
    voucher_no: string;
    date: string;
    amount: number;
    payment_type: string;
    sub_type: string;
    description?: string;
}

interface Receipt {
    id: number;
    voucher_no: string;
    date: string;
    amount: number;
    payment_type: string;
    sub_type: string;
    description?: string;
}

interface PaginatedRows<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface EmployeeStatementProps {
    employee: Employee;
    payments: PaginatedRows<Payment>;
    receipts: PaginatedRows<Receipt>;
    currentBalance: number;
    view: string;
    filters: {
        start_date?: string;
        end_date?: string;
        per_page?: number;
    };
}

export default function EmployeeStatement({
    employee,
    payments,
    receipts,
    currentBalance,
    view,
    filters,
}: EmployeeStatementProps) {
    const [startDate, setStartDate] = useState(filters.start_date ?? '');
    const [endDate, setEndDate] = useState(filters.end_date ?? '');
    const [perPage, setPerPage] = useState(filters.per_page ?? payments.per_page ?? 10);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Employees', href: '/employees' },
        { title: employee.employee_name, href: `/employees/${employee.id}` },
        { title: 'Statement', href: `/employees/${employee.id}/statement` },
    ];

    const query = (overrides: Record<string, string | number | undefined> = {}) => ({
        view: view === 'all' ? undefined : view,
        start_date: overrides.start_date ?? (startDate || undefined),
        end_date: overrides.end_date ?? (endDate || undefined),
        per_page: overrides.per_page ?? perPage,
        payment_page: overrides.payment_page ?? payments.current_page,
        receipt_page: overrides.receipt_page ?? receipts.current_page,
    });

    const handleFilter = () => {
        router.get(`/employees/${employee.id}/statement`, query({
            payment_page: 1,
            receipt_page: 1,
        }), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Statement - ${employee.employee_name}`} />
            
            <div className="p-6 space-y-6">
                {/* Header */}
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Employee Statement</h1>
                        <p className="text-gray-600 dark:text-gray-400">{employee.employee_name} - Payment History</p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="success"
                            onClick={() => window.print()}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Print
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() => router.get(`/employees/${employee.id}`)}
                        >
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Details
                        </Button>
                    </div>
                </div>

                {/* Employee Info */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="dark:text-white">Employee Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Name</label>
                                <p className="text-gray-900 dark:text-white">{employee.employee_name}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Mobile</label>
                                <p className="text-gray-900 dark:text-white">{employee.mobile || 'N/A'}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</label>
                                <p className="text-gray-900 dark:text-white">{employee.account?.ac_number || 'N/A'}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Current Balance</label>
                                <p className={`text-lg font-bold ${
                                    currentBalance > 0 
                                        ? 'text-green-600 dark:text-green-400' 
                                        : currentBalance < 0 
                                            ? 'text-red-600 dark:text-red-400' 
                                            : 'text-gray-900 dark:text-white'
                                }`}>
                                    {currentBalance < 0 ? '-' : ''}{Math.abs(currentBalance).toLocaleString()}
                                    {currentBalance > 0 && ' (Due)'}
                                    {currentBalance < 0 && ' (Advanced)'}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Filter Card */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 dark:text-white">
                            <Filter className="h-5 w-5" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <Label className="dark:text-gray-200">Start Date</Label>
                                <Input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">End Date</Label>
                                <Input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div className="flex items-end gap-2">
                                <Button onClick={handleFilter} className="px-4">
                                    Apply Filters
                                </Button>
                                <Button
                                    onClick={() => {
                                        setStartDate('');
                                        setEndDate('');
                                        router.get(`/employees/${employee.id}/statement`, query({
                                            start_date: '',
                                            end_date: '',
                                            payment_page: 1,
                                            receipt_page: 1,
                                        }), {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                    variant="secondary"
                                    className="px-4"
                                >
                                    <X className="mr-2 h-4 w-4" />
                                    Clear
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Two Cards Below */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <div className="flex justify-between items-center">
                                <CardTitle className="dark:text-white">Payment Summary</CardTitle>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        const params = new URLSearchParams();
                                        if (startDate) params.append('start_date', startDate);
                                        if (endDate) params.append('end_date', endDate);
                                        openPdfViewer(`/employees/${employee.id}/payments-pdf?${params.toString()}`);
                                    }}
                                >
                                    <Download className="h-4 w-4 mr-2" />
                                    Download
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">SL</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Voucher No</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Amount</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Type</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Sub Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {payments && payments.data && payments.data.length > 0 ? (
                                            payments.data.map((payment, index) => (
                                                <tr key={payment.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {(payments.current_page - 1) * payments.per_page + index + 1}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-white">{payment.voucher_no}</td>
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {new Date(payment.date).toLocaleDateString('en-GB')}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-white font-semibold">
                                                        {payment.amount.toLocaleString()}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {payment.payment_type || 'N/A'}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {payment.sub_type || 'N/A'}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={6} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                    No payments found
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            
                            <Pagination
                                currentPage={payments.current_page}
                                lastPage={payments.last_page}
                                from={payments.from}
                                to={payments.to}
                                total={payments.total}
                                perPage={perPage}
                                onPageChange={(page) => router.get(
                                    `/employees/${employee.id}/statement`,
                                    query({ payment_page: page }),
                                    { preserveState: true, preserveScroll: true },
                                )}
                                onPerPageChange={(value) => {
                                    setPerPage(value);
                                    router.get(
                                        `/employees/${employee.id}/statement`,
                                        query({
                                            per_page: value,
                                            payment_page: 1,
                                            receipt_page: 1,
                                        }),
                                        { preserveState: true, preserveScroll: true },
                                    );
                                }}
                            />
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <div className="flex justify-between items-center">
                                <CardTitle className="dark:text-white">Receipt Summary</CardTitle>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        const params = new URLSearchParams();
                                        if (startDate) params.append('start_date', startDate);
                                        if (endDate) params.append('end_date', endDate);
                                        openPdfViewer(`/employees/${employee.id}/receipts-pdf?${params.toString()}`);
                                    }}
                                >
                                    <Download className="h-4 w-4 mr-2" />
                                    Download
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">SL</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Voucher No</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Amount</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Type</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Sub Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {receipts.data.length > 0 ? (
                                            receipts.data.map((receipt, index) => (
                                                <tr key={receipt.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {(receipts.current_page - 1) * receipts.per_page + index + 1}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-white">{receipt.voucher_no}</td>
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {new Date(receipt.date).toLocaleDateString('en-GB')}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-white font-semibold">
                                                        {receipt.amount.toLocaleString()}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {receipt.payment_type || 'N/A'}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {receipt.sub_type || 'N/A'}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={6} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                    No receipts found
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <Pagination
                                currentPage={receipts.current_page}
                                lastPage={receipts.last_page}
                                from={receipts.from}
                                to={receipts.to}
                                total={receipts.total}
                                perPage={perPage}
                                onPageChange={(page) => router.get(
                                    `/employees/${employee.id}/statement`,
                                    query({ receipt_page: page }),
                                    { preserveState: true, preserveScroll: true },
                                )}
                                onPerPageChange={(value) => {
                                    setPerPage(value);
                                    router.get(
                                        `/employees/${employee.id}/statement`,
                                        query({
                                            per_page: value,
                                            payment_page: 1,
                                            receipt_page: 1,
                                        }),
                                        { preserveState: true, preserveScroll: true },
                                    );
                                }}
                            />
                        </CardContent>
                    </Card>
                </div>

            </div>
        </AppLayout>
    );
}
