import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, FileText } from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';

interface Transaction {
    id: number;
    transaction_id: string;
    voucher_no: string;
    date: string;
    transaction_time: string;
    transaction_type: 'Dr' | 'Cr';
    amount: number;
    description: string;
    payment_type: string;
    category_name: string;
    from_account_name: string;
    to_account_name: string;
}

interface ShiftClosed {
    id: number;
    close_date: string;
    shift: {
        name: string;
    };
}

interface Props {
    shiftClosed: ShiftClosed;
    cashTransactions: Transaction[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Cash Book Ledger', href: '/cash-book-ledger' },
    { title: 'Details', href: '#' },
];

export default function Show({ shiftClosed, cashTransactions }: Props) {
    const { can } = usePermission();
    const canDownload = can('can-account-download');

    const totalDebit = cashTransactions.reduce((sum, t) => 
        t.from_account_name.toLowerCase().includes('cash') ? sum + Number(t.amount) : sum, 0
    );
    const totalCredit = cashTransactions.reduce((sum, t) => 
        t.to_account_name.toLowerCase().includes('cash') ? sum + Number(t.amount) : sum, 0
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cash Book Details" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Cash Book Details
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            {shiftClosed.shift.name} - {new Date(shiftClosed.close_date).toLocaleDateString()}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => window.location.href = `/cash-book-ledger/${shiftClosed.id}/download-pdf`}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => router.get('/cash-book-ledger')}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back
                        </Button>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="flex items-center p-6">
                            <div>
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Total Debit</p>
                                <p className="text-2xl font-bold text-red-600 dark:text-red-400">
                                    {totalDebit.toLocaleString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="flex items-center p-6">
                            <div>
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Total Credit</p>
                                <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {totalCredit.toLocaleString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="flex items-center p-6">
                            <div>
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Closing Balance</p>
                                <p className={`text-2xl font-bold ${(totalCredit - totalDebit) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                    {Math.abs(totalCredit - totalDebit).toLocaleString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="dark:text-white">
                            Cash Transactions
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Date
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Shift
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            From Account
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            To Account
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Amount
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Category
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Transaction ID
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Debit
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Credit
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {cashTransactions.length > 0 ? (
                                        <>
                                            {cashTransactions.map((transaction) => (
                                                <tr
                                                    key={transaction.id}
                                                    className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                                >
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {new Date(transaction.date).toLocaleDateString()}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {shiftClosed.shift.name}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {transaction.from_account_name}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {transaction.to_account_name}
                                                    </td>
                                                    <td className="p-4 text-right text-[13px] dark:text-white">
                                                        {Number(transaction.amount).toFixed(2)}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {transaction.category_name}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {transaction.transaction_id}
                                                    </td>
                                                    <td className="p-4 text-right text-[13px] dark:text-white">
                                                        {transaction.from_account_name.toLowerCase().includes('cash')
                                                            ? Number(transaction.amount).toFixed(2)
                                                            : '-'}
                                                    </td>
                                                    <td className="p-4 text-right text-[13px] dark:text-white">
                                                        {transaction.to_account_name.toLowerCase().includes('cash')
                                                            ? Number(transaction.amount).toFixed(2)
                                                            : '-'}
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="border-t-2 bg-gray-100 font-semibold dark:border-gray-600 dark:bg-gray-700">
                                                <td colSpan={7} className="p-4 text-right text-[13px] dark:text-white">
                                                    Total:
                                                </td>
                                                <td className="p-4 text-right text-[13px] dark:text-white">
                                                    {totalDebit.toFixed(2)}
                                                    <div className="text-[11px] text-gray-500 dark:text-gray-400">Cash Payment</div>
                                                </td>
                                                <td className="p-4 text-right text-[13px] dark:text-white">
                                                    {totalCredit.toFixed(2)}
                                                    <div className="text-[11px] text-gray-500 dark:text-gray-400">Cash Received</div>
                                                </td>
                                            </tr>
                                        </>
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={9}
                                                className="p-8 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                No cash transactions found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
