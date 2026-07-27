import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, FileText, Filter, Download } from 'lucide-react';
import { useState } from 'react';

interface LoanAccount {
    id: number;
    name: string;
    ac_number: string;
    status: boolean;
    created_at: string;
}

interface LoanTransaction {
    id: number;
    voucher_no: string;
    date: string;
    amount: number;
    description: string;
}

interface PaymentTransaction {
    id: number;
    voucher_no: string;
    date: string;
    amount: number;
    description: string;
}

interface PaginatedPayments {
    data: PaymentTransaction[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface LoanStatementProps {
    loanAccount: LoanAccount;
    totalLoan: number;
    totalPayment: number;
    currentBalance: number;
    recentLoans: LoanTransaction[];
    recentPayments: PaginatedPayments;
}

export default function LoanStatement({ loanAccount, totalLoan, totalPayment, currentBalance, recentLoans, recentPayments }: LoanStatementProps) {
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');

    const handlePaymentFilter = () => {
        router.get(`/loans/${loanAccount.id}/statement`, {
            start_date: startDate,
            end_date: endDate
        });
    };

    return (
        <AppLayout>
            <Head title={`Loan Statement - ${loanAccount.name}`} />
            
            <div className="p-6 space-y-6">
                {/* Header */}
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Loan Statement</h1>
                        <p className="text-gray-600 dark:text-gray-400">{loanAccount.name} - Transaction History</p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="success"
                            onClick={() => window.location.href = `/loans/${loanAccount.id}/statement-pdf`}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Download PDF
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() => router.get(`/loans/${loanAccount.id}`)}
                        >
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Details
                        </Button>
                    </div>
                </div>

                {/* Loan Account Info */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="dark:text-white">Loan Account Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Account Name</label>
                                <p className="text-gray-900 dark:text-white">{loanAccount.name}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</label>
                                <p className="text-gray-900 dark:text-white">{loanAccount.ac_number}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Total Loan</label>
                                <p className="text-gray-900 dark:text-white">{totalLoan.toLocaleString()}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Total Payment</label>
                                <p className="text-gray-900 dark:text-white">{totalPayment.toLocaleString()}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Current Balance</label>
                                <p className={`text-lg font-bold ${
                                    currentBalance > 0 
                                        ? 'text-red-600 dark:text-red-400' 
                                        : 'text-gray-900 dark:text-white'
                                }`}>
                                    {currentBalance.toLocaleString()}
                                    {currentBalance > 0 && ' (Outstanding)'}
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
                                <CardTitle className="dark:text-white">Loan Summary</CardTitle>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => window.location.href = `/loans/${loanAccount.id}/loans-pdf`}
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
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentLoans && recentLoans.length > 0 ? (
                                            recentLoans.map((loan, index) => (
                                                <tr key={loan.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-4 text-[13px] dark:text-white">{index + 1}</td>
                                                    <td className="p-4 text-[13px] dark:text-white">{loan.voucher_no}</td>
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {new Date(loan.date).toLocaleDateString('en-GB')}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-white font-semibold">{loan.amount.toLocaleString()}</td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">{loan.description}</td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={5} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                    No loan transactions found
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
                                            window.location.href = `/loans/${loanAccount.id}/payments-pdf?${params.toString()}`;
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
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">SL</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Voucher No</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Amount</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentPayments && recentPayments.data && recentPayments.data.length > 0 ? (
                                            recentPayments.data.map((payment, index) => (
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
                                                        {payment.description}
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
                                            onClick={() => router.get(`/loans/${loanAccount.id}/statement`, { page: recentPayments.current_page - 1 })}
                                        >
                                            Previous
                                        </Button>
                                        {Array.from({ length: recentPayments.last_page }, (_, i) => i + 1).map((page) => (
                                            <Button 
                                                key={page}
                                                variant={page === recentPayments.current_page ? "default" : "outline"} 
                                                size="sm" 
                                                className="h-8 px-3"
                                                onClick={() => router.get(`/loans/${loanAccount.id}/statement`, { page })}
                                            >
                                                {page}
                                            </Button>
                                        ))}
                                        <Button 
                                            variant="outline" 
                                            size="sm" 
                                            className="h-8 px-2"
                                            disabled={recentPayments.current_page === recentPayments.last_page}
                                            onClick={() => router.get(`/loans/${loanAccount.id}/statement`, { page: recentPayments.current_page + 1 })}
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