import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, FileText, DollarSign, ShoppingCart, Banknote } from 'lucide-react';

interface Supplier {
    id: number;
    name: string;
    mobile?: string;
    email?: string;
    address?: string;
    proprietor_name?: string;
    status: boolean;
    account?: {
        id: number;
        name: string;
        ac_number: string;
    };
    created_at: string;
}

interface RecentPayment {
    id: number;
    date: string;
    amount: number;
    remarks?: string;
}

interface RecentPurchase {
    id: number;
    date: string;
    total_amount: number;
    paid_amount: number;
    due_amount: number;
    invoice_no: string;
    status: string;
}

interface SupplierDetailsProps {
    supplier: Supplier;
    recentPayments: RecentPayment[];
    recentPurchases: RecentPurchase[];
    totalPurchases: number;
    purchaseCount: number;
    totalPaid: number;
    paymentCount: number;
    currentDue: number;
}

export default function SupplierDetails({ supplier, recentPayments, recentPurchases, totalPurchases, purchaseCount, totalPaid, paymentCount, currentDue }: SupplierDetailsProps) {
    return (
        <AppLayout>
            <Head title={`Supplier - ${supplier.name}`} />
            
            <div className="p-6 space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">{supplier.name}</h1>
                        <p className="text-gray-600 dark:text-gray-400">Supplier details and information</p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.get(`/suppliers/${supplier.id}/statement`)}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Statement
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() => router.get('/suppliers')}
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
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Total Purchases</p>
                                    <p className="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{totalPurchases.toLocaleString()}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{purchaseCount} purchases</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Total Paid</p>
                                    <p className="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{totalPaid.toLocaleString()}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{paymentCount} payments</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {currentDue > 0 ? 'Current Due' : currentDue < 0 ? 'Current Advanced' : 'Balanced'}
                                    </p>
                                    <p className={`text-2xl font-bold ${
                                        currentDue > 0 
                                            ? 'text-red-600 dark:text-red-400' 
                                            : currentDue < 0 
                                                ? 'text-green-600 dark:text-green-400' 
                                                : 'text-gray-900 dark:text-white'
                                    }`}>
                                        {currentDue < 0 ? '-' : ''}{Math.abs(currentDue).toLocaleString()}
                                    </p>
                                    {currentDue > 0 && (
                                        <p className="text-xs text-gray-500 dark:text-gray-400">Outstanding</p>
                                    )}
                                    {currentDue < 0 && (
                                        <p className="text-xs text-gray-500 dark:text-gray-400">Advance Paid</p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                
                {/* Supplier Details Card */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="dark:text-white">Supplier Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Name</label>
                                    <p className="text-gray-900 dark:text-white">{supplier.name}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Mobile</label>
                                    <p className="text-gray-900 dark:text-white">{supplier.mobile || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Email</label>
                                    <p className="text-gray-900 dark:text-white">{supplier.email || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Proprietor Name</label>
                                    <p className="text-gray-900 dark:text-white">{supplier.proprietor_name || 'N/A'}</p>
                                </div>
                            </div>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Address</label>
                                    <p className="text-gray-900 dark:text-white">{supplier.address || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Status</label>
                                    <span className={`px-2 py-1 rounded text-xs ${
                                        supplier.status 
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                    }`}>
                                        {supplier.status ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">Created Date</label>
                                    <p className="text-gray-900 dark:text-white">{new Date(supplier.created_at).toLocaleDateString('en-GB')}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                
                {/* Action Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Card className="dark:border-gray-700 dark:bg-gray-800 cursor-pointer" onClick={() => router.get('/purchases')}>
                        <CardContent className="p-6">
                            <div className="flex items-center gap-4">
                                <div className="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                                    <ShoppingCart className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Create Purchase</h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Add new purchase</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card className="dark:border-gray-700 dark:bg-gray-800 cursor-pointer" onClick={() => router.get('/vouchers/payment')}>
                        <CardContent className="p-6">
                            <div className="flex items-center gap-4">
                                <div className="p-3 bg-purple-100 dark:bg-purple-900 rounded-lg">
                                    <Banknote className="h-6 w-6 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Make Payment</h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Pay to supplier</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                
                {/* Recent Activity Cards */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="dark:text-white">Recent Purchases</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Invoice No</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Amount</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Due</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentPurchases && recentPurchases.length > 0 ? (
                                            recentPurchases.map((purchase) => (
                                                <tr key={purchase.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {new Date(purchase.date).toLocaleDateString('en-GB')}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {purchase.invoice_no}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-white font-semibold">
                                                        {purchase.total_amount.toLocaleString()}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-red-400 font-semibold">
                                                        {purchase.due_amount.toLocaleString()}
                                                    </td>
                                                    <td className="p-4">
                                                        <span className={`px-2 py-1 rounded text-xs ${
                                                            purchase.status === 'paid' 
                                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                                                : purchase.status === 'partial'
                                                                    ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                        }`}>
                                                            {purchase.status}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={5} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                    No recent purchases found
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
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Amount</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Type</th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentPayments && recentPayments.length > 0 ? (
                                            recentPayments.map((payment) => (
                                                <tr key={payment.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {new Date(payment.date).toLocaleDateString('en-GB')}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-white font-semibold">
                                                        {payment.amount.toLocaleString()}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        Payment
                                                    </td>
                                                    <td className="p-4">
                                                        <span className="px-2 py-1 rounded text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                            Completed
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={4} className="p-8 text-center text-gray-500 dark:text-gray-400">
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
