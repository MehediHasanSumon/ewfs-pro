import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft, FileText, Filter, Download } from 'lucide-react';
import { useState } from 'react';

interface Customer {
    id: number;
    code?: string;
    name: string;
    mobile?: string;
    address?: string;
    security_deposit?: number;
    account?: {
        id: number;
        name: string;
        ac_number: string;
    };
}

interface Transaction {
    id: number;
    date: string;
    type: string;
    description: string;
    debit: number;
    credit: number;
    balance: number;
    invoice_no?: string;
    voucher_no?: string;
}

interface MonthlySale {
    month: string;
    total: number;
}

interface Payment {
    id: number;
    date: string;
    amount: number;
    remarks?: string;
    payment_type?: string;
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

interface CustomerStatementProps {
    customer: Customer;
    transactions: Transaction[];
    currentBalance: number;
    monthlySales: MonthlySale[];
    availableYears: number[];
    recentPayments: PaginatedPayments;
}

export default function CustomerStatement({ customer, transactions, currentBalance, monthlySales, availableYears, recentPayments }: CustomerStatementProps) {
    const [selectedYear, setSelectedYear] = useState(new Date().getFullYear().toString());
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');

    const handleFilter = () => {
        router.get(`/customers/${customer.id}/statement`, {
            year: selectedYear
        });
    };

    const handlePaymentFilter = () => {
        router.get(`/customers/${customer.id}/statement`, {
            start_date: startDate,
            end_date: endDate
        });
    };

    return (
        <AppLayout>
            <Head title={`Statement - ${customer.name}`} />
            
            <div className="p-6 space-y-6">
                {/* Header */}
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Customer Statement</h1>
                        <p className="text-gray-600 dark:text-gray-400">{customer.name} - Transaction History</p>
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
                            variant="outline"
                            onClick={() => router.get(`/customer-ledger-details/${customer.id}`)}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Account Statement
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() => router.get(`/customers/${customer.id}`)}
                        >
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Details
                        </Button>
                    </div>
                </div>

                {/* Customer Info */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="dark:text-white">Customer Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Name</label>
                                <p className="text-gray-900 dark:text-white">{customer.name}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Address</label>
                                <p className="text-gray-900 dark:text-white">{customer.address || 'N/A'}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</label>
                                <p className="text-gray-900 dark:text-white">{customer.account?.ac_number || 'N/A'}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Security Deposit</label>
                                <p className="text-gray-900 dark:text-white">{customer.security_deposit?.toLocaleString() || '0'}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Current Balance</label>
                                <p className={`text-lg font-bold ${
                                    currentBalance > 0 
                                        ? 'text-red-600 dark:text-red-400' 
                                        : currentBalance < 0 
                                            ? 'text-green-600 dark:text-green-400' 
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

                {/* Two Cards Below */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <div className="flex justify-between items-center">
                                <CardTitle className="dark:text-white">Sales Summary</CardTitle>
                                <div className="flex gap-2 items-center">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            window.location.href = `/customers/${customer.id}/sales-pdf?year=${selectedYear}`;
                                        }}
                                    >
                                        <Download className="h-4 w-4 mr-2" />
                                        Download
                                    </Button>
                                    <Select value={selectedYear} onValueChange={setSelectedYear}>
                                        <SelectTrigger className="w-20 h-8 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableYears && availableYears.length > 0 ? (
                                                availableYears.filter(year => year != null).map((year) => (
                                                    <SelectItem key={year} value={year.toString()}>
                                                        {year}
                                                    </SelectItem>
                                                ))
                                            ) : (
                                                <SelectItem value={new Date().getFullYear().toString()}>
                                                    {new Date().getFullYear()}
                                                </SelectItem>
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={handleFilter}
                                        className="h-8 px-2"
                                    >
                                        <Filter className="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Month</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Total Sale</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {monthlySales && monthlySales.length > 0 ? (
                                            monthlySales.map((sale, index) => (
                                                <tr key={index} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-4 text-[13px] dark:text-white">{sale.month}</td>
                                                    <td className="p-4 text-[13px] dark:text-white font-semibold">{sale.total.toLocaleString()}</td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={2} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                    No sales data found
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <div className="flex justify-between items-center">
                                <CardTitle className="dark:text-white">Payment Summary</CardTitle>
                                <div className="flex gap-2 items-center">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            const params = new URLSearchParams();
                                            if (startDate) params.append('start_date', startDate);
                                            if (endDate) params.append('end_date', endDate);
                                            window.location.href = `/customers/${customer.id}/payments-pdf?${params.toString()}`;
                                        }}
                                    >
                                        <Download className="h-4 w-4 mr-2" />
                                        Download
                                    </Button>
                                    <input 
                                        type="date"
                                        value={startDate}
                                        onChange={(e) => setStartDate(e.target.value)}
                                        className="px-2 py-1 text-xs bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 w-32"
                                        placeholder="Start Date"
                                    />
                                    <input 
                                        type="date"
                                        value={endDate}
                                        onChange={(e) => setEndDate(e.target.value)}
                                        className="px-2 py-1 text-xs bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 w-32"
                                        placeholder="End Date"
                                    />
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={handlePaymentFilter}
                                        className="h-8 px-2"
                                    >
                                        <Filter className="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Amount</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Payment Type</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Remark</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentPayments && recentPayments.data && recentPayments.data.length > 0 ? (
                                            recentPayments.data.map((payment) => (
                                                <tr key={payment.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {new Date(payment.date).toLocaleDateString('en-GB')}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-white font-semibold">
                                                        {payment.amount.toLocaleString()}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {payment.payment_type || 'Cash'}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {payment.remarks || 'N/A'}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={4} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                    No payments found
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            
                            {/* Pagination */}
                            {recentPayments && recentPayments.last_page > 1 && (
                                <div className="flex justify-between items-center mt-4 px-4 pb-4">
                                    <div className="text-sm text-gray-500 dark:text-gray-400">
                                        Showing {recentPayments.from || 0} to {recentPayments.to || 0} of {recentPayments.total} results
                                    </div>
                                    <div className="flex gap-1">
                                        <Button 
                                            variant="outline" 
                                            size="sm" 
                                            className="h-8 px-2"
                                            disabled={recentPayments.current_page === 1}
                                            onClick={() => router.get(`/customers/${customer.id}/statement`, { page: recentPayments.current_page - 1 })}
                                        >
                                            Previous
                                        </Button>
                                        {Array.from({ length: recentPayments.last_page }, (_, i) => i + 1).map((page) => (
                                            <Button 
                                                key={page}
                                                variant={page === recentPayments.current_page ? "default" : "outline"} 
                                                size="sm" 
                                                className="h-8 px-3"
                                                onClick={() => router.get(`/customers/${customer.id}/statement`, { page })}
                                            >
                                                {page}
                                            </Button>
                                        ))}
                                        <Button 
                                            variant="outline" 
                                            size="sm" 
                                            className="h-8 px-2"
                                            disabled={recentPayments.current_page === recentPayments.last_page}
                                            onClick={() => router.get(`/customers/${customer.id}/statement`, { page: recentPayments.current_page + 1 })}
                                        >
                                            Next
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

            </div>
        </AppLayout>
    );
}