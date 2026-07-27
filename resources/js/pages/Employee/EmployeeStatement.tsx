import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

interface PaginatedPayments {
    data: Payment[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface EmployeeStatementProps {
    employee: Employee;
    payments: PaginatedPayments;
    receipts: Receipt[];
    currentBalance: number;
}

export default function EmployeeStatement({ employee, payments, receipts, currentBalance }: EmployeeStatementProps) {
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');

    const handleFilter = () => {
        router.get(`/employees/${employee.id}/statement`, {
            start_date: startDate,
            end_date: endDate
        });
    };

    return (
        <AppLayout>
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
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Start Date</label>
                                <input 
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="w-full px-3 py-2 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">End Date</label>
                                <input 
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="w-full px-3 py-2 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                                        router.get(`/employees/${employee.id}/statement`);
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
                                        window.location.href = `/employees/${employee.id}/payments-pdf?${params.toString()}`;
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
                                                    <td className="p-4 text-[13px] dark:text-white">{index + 1}</td>
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
                            
                            {/* Pagination */}
                            {payments && payments.last_page > 1 && (
                                <div className="flex justify-between items-center mt-4 px-4 pb-4">
                                    <div className="text-sm text-gray-500 dark:text-gray-400">
                                        Showing {payments.from || 0} to {payments.to || 0} of {payments.total} results
                                    </div>
                                    <div className="flex gap-1">
                                        <Button 
                                            variant="outline" 
                                            size="sm" 
                                            className="h-8 px-2"
                                            disabled={payments.current_page === 1}
                                            onClick={() => router.get(`/employees/${employee.id}/statement`, { page: payments.current_page - 1 })}
                                        >
                                            Previous
                                        </Button>
                                        {Array.from({ length: payments.last_page }, (_, i) => i + 1).map((page) => (
                                            <Button 
                                                key={page}
                                                variant={page === payments.current_page ? "default" : "outline"} 
                                                size="sm" 
                                                className="h-8 px-3"
                                                onClick={() => router.get(`/employees/${employee.id}/statement`, { page })}
                                            >
                                                {page}
                                            </Button>
                                        ))}
                                        <Button 
                                            variant="outline" 
                                            size="sm" 
                                            className="h-8 px-2"
                                            disabled={payments.current_page === payments.last_page}
                                            onClick={() => router.get(`/employees/${employee.id}/statement`, { page: payments.current_page + 1 })}
                                        >
                                            Next
                                        </Button>
                                    </div>
                                </div>
                            )}
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
                                        window.location.href = `/employees/${employee.id}/receipts-pdf?${params.toString()}`;
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
                                        {receipts && receipts.length > 0 ? (
                                            receipts.map((receipt, index) => (
                                                <tr key={receipt.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-4 text-[13px] dark:text-white">{index + 1}</td>
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
                        </CardContent>
                    </Card>
                </div>

            </div>
        </AppLayout>
    );
}