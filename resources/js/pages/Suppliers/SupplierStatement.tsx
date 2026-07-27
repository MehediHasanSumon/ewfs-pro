import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, FileText, Filter, X, Download } from 'lucide-react';
import { useState } from 'react';

interface Supplier {
    id: number;
    name: string;
    mobile?: string;
    address?: string;
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

interface Purchase {
    date: string;
    invoice_no: string;
    total: number;
    paid: number;
    due: number;
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

interface SupplierStatementProps {
    supplier: Supplier;
    transactions: Transaction[];
    currentBalance: number;
    allPurchases: Purchase[];
    recentPayments: PaginatedPayments;
}

export default function SupplierStatement({ supplier, transactions, currentBalance, allPurchases, recentPayments }: SupplierStatementProps) {
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');

    const handlePaymentFilter = () => {
        router.get(`/suppliers/${supplier.id}/statement`, {
            start_date: startDate,
            end_date: endDate
        });
    };

    return (
        <AppLayout>
            <Head title={`Statement - ${supplier.name}`} />
            
            <div className="p-6 space-y-6">
                {/* Header */}
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Supplier Statement</h1>
                        <p className="text-gray-600 dark:text-gray-400">{supplier.name} - Transaction History</p>
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
                            onClick={() => router.get(`/suppliers/${supplier.id}`)}
                        >
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Details
                        </Button>
                    </div>
                </div>

                {/* Supplier Info */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="dark:text-white">Supplier Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Name</label>
                                <p className="text-gray-900 dark:text-white">{supplier.name}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Address</label>
                                <p className="text-gray-900 dark:text-white">{supplier.address || 'N/A'}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</label>
                                <p className="text-gray-900 dark:text-white">{supplier.account?.ac_number || 'N/A'}</p>
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
                                <Button onClick={handlePaymentFilter} className="px-4">
                                    Apply Filters
                                </Button>
                                <Button
                                    onClick={() => {
                                        setStartDate('');
                                        setEndDate('');
                                        router.get(`/suppliers/${supplier.id}/statement`);
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
                                <CardTitle className="dark:text-white">Purchase Summary</CardTitle>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        const params = new URLSearchParams();
                                        if (startDate) params.append('start_date', startDate);
                                        if (endDate) params.append('end_date', endDate);
                                        window.location.href = `/suppliers/${supplier.id}/purchases-pdf?${params.toString()}`;
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
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Invoice No</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Paid</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Due</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {allPurchases && allPurchases.length > 0 ? (
                                            allPurchases.map((purchase, index) => (
                                                <tr key={index} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-4 text-[13px] dark:text-white">{index + 1}</td>
                                                    <td className="p-4 text-[13px] dark:text-white">{new Date(purchase.date).toLocaleDateString('en-GB')}</td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">{purchase.invoice_no}</td>
                                                    <td className="p-4 text-[13px] dark:text-green-400 font-semibold">{purchase.paid.toLocaleString()}</td>
                                                    <td className="p-4 text-[13px] dark:text-red-400 font-semibold">{purchase.due.toLocaleString()}</td>
                                                    <td className="p-4 text-[13px] dark:text-white font-semibold">{purchase.total.toLocaleString()}</td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={6} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                    No purchase data found
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
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        const params = new URLSearchParams();
                                        if (startDate) params.append('start_date', startDate);
                                        if (endDate) params.append('end_date', endDate);
                                        window.location.href = `/suppliers/${supplier.id}/payments-pdf?${params.toString()}`;
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
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Amount</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Payment Type</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Remark</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentPayments && recentPayments.data && recentPayments.data.length > 0 ? (
                                            recentPayments.data.map((payment, index) => (
                                                <tr key={payment.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-4 text-[13px] dark:text-white">{index + 1}</td>
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
                                                        {payment.remarks || 'N/A'}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={5} className="p-8 text-center text-gray-500 dark:text-gray-400">
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
                                            onClick={() => router.get(`/suppliers/${supplier.id}/statement`, { page: recentPayments.current_page - 1 })}
                                        >
                                            Previous
                                        </Button>
                                        {Array.from({ length: recentPayments.last_page }, (_, i) => i + 1).map((page) => (
                                            <Button 
                                                key={page}
                                                variant={page === recentPayments.current_page ? "default" : "outline"} 
                                                size="sm" 
                                                className="h-8 px-3"
                                                onClick={() => router.get(`/suppliers/${supplier.id}/statement`, { page })}
                                            >
                                                {page}
                                            </Button>
                                        ))}
                                        <Button 
                                            variant="outline" 
                                            size="sm" 
                                            className="h-8 px-2"
                                            disabled={recentPayments.current_page === recentPayments.last_page}
                                            onClick={() => router.get(`/suppliers/${supplier.id}/statement`, { page: recentPayments.current_page + 1 })}
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
