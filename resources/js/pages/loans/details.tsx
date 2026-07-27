import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, FileText, DollarSign, CreditCard, Banknote } from 'lucide-react';

interface LoanAccount {
    id: number;
    name: string;
    ac_number: string;
    status: boolean;
    created_at: string;
}

interface RecentLoan {
    id: number;
    voucher_no: string;
    date: string;
    amount: number;
    description: string;
}

interface RecentPayment {
    id: number;
    voucher_no: string;
    date: string;
    amount: number;
    description: string;
}

interface LoanDetailsProps {
    loanAccount: LoanAccount;
    totalLoan: number;
    totalPayment: number;
    currentBalance: number;
    recentLoans: RecentLoan[];
    recentPayments: RecentPayment[];
}

export default function LoanDetails({ loanAccount, totalLoan, totalPayment, currentBalance, recentLoans, recentPayments }: LoanDetailsProps) {
    return (
        <AppLayout>
            <Head title={`Loan - ${loanAccount.name}`} />
            
            <div className="p-6 space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">{loanAccount.name}</h1>
                        <p className="text-gray-600 dark:text-gray-400">Loan account details and transactions</p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.get(`/loans/${loanAccount.id}/statement`)}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Statement
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() => router.get('/loans')}
                        >
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to List
                        </Button>
                    </div>
                </div>
                
                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Total Loan</p>
                                    <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">{totalLoan.toLocaleString()}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Total Payment</p>
                                    <p className="text-2xl font-bold text-green-600 dark:text-green-400">{totalPayment.toLocaleString()}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Current Balance</p>
                                    <p className={`text-2xl font-bold ${
                                        currentBalance > 0 
                                            ? 'text-red-600 dark:text-red-400' 
                                            : 'text-gray-900 dark:text-white'
                                    }`}>
                                        {currentBalance.toLocaleString()}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                
                {/* Loan Account Details */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="dark:text-white">Loan Account Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Account Name</label>
                                    <p className="text-gray-900 dark:text-white">{loanAccount.name}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</label>
                                    <p className="text-gray-900 dark:text-white">{loanAccount.ac_number}</p>
                                </div>
                            </div>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Status</label>
                                    <span className={`px-2 py-1 rounded text-xs ${
                                        loanAccount.status 
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                    }`}>
                                        {loanAccount.status ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Created Date</label>
                                    <p className="text-gray-900 dark:text-white">{new Date(loanAccount.created_at).toLocaleDateString('en-GB')}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                
                {/* Action Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Card className="dark:border-gray-700 dark:bg-gray-800 cursor-pointer" onClick={() => router.get('/vouchers/received')}>
                        <CardContent className="p-6">
                            <div className="flex items-center gap-4">
                                <div className="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                                    <DollarSign className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Receive Loan</h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Record new loan received</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card className="dark:border-gray-700 dark:bg-gray-800 cursor-pointer" onClick={() => router.get('/vouchers/payment')}>
                        <CardContent className="p-6">
                            <div className="flex items-center gap-4">
                                <div className="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                                    <Banknote className="h-6 w-6 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Make Payment</h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Pay loan installment</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                
                {/* Recent Activity Cards */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">Recent Loans</CardTitle>
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
                                                    <td className="p-4 text-[13px] dark:text-white font-semibold">
                                                        {loan.amount.toLocaleString()}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">{loan.description}</td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={5} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                    No recent loans found
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
                            <CardTitle className="dark:text-white">Recent Payments</CardTitle>
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
                                        {recentPayments && recentPayments.length > 0 ? (
                                            recentPayments.map((payment, index) => (
                                                <tr key={payment.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-4 text-[13px] dark:text-white">{index + 1}</td>
                                                    <td className="p-4 text-[13px] dark:text-white">{payment.voucher_no}</td>
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {new Date(payment.date).toLocaleDateString('en-GB')}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-white font-semibold">
                                                        {payment.amount.toLocaleString()}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">{payment.description}</td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={5} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                    No recent payments found
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