import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { FileText, Filter, X, Eye } from 'lucide-react';
import { useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface Account {
    id: number;
    name: string;
    ac_number: string;
    group: {
        name: string;
    };
}

interface Transaction {
    id: number;
    transaction_id: string;
    transaction_date: string;
    transaction_type: 'Dr' | 'Cr';
    amount: number;
    description: string;
    payment_type: string;
    balance: number;
}

interface Ledger {
    account: Account;
    transactions: Transaction[];
    total_debit: number;
    total_credit: number;
    closing_balance: number;
}

interface Props {
    ledgers: Ledger[];
    filters: {
        start_date?: string;
        end_date?: string;
    };
    summary: {
        total_debit: number;
        total_credit: number;
        net_balance: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Bank Book Ledger', href: '/bank-book-ledger' },
];

export default function BankBookLedger({ ledgers, filters }: Props) {
    const { can } = usePermission();
    const canFilter = can('can-account-filter');
    const canDownload = can('can-account-download');

    const [startDate, setStartDate] = useState(
        filters?.start_date || new Date().toISOString().split('T')[0],
    );
    const [endDate, setEndDate] = useState(
        filters?.end_date || new Date().toISOString().split('T')[0],
    );

    const applyFilters = () => {
        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);

        router.get(`/bank-book-ledger?${params.toString()}`);
    };

    const clearFilters = () => {
        const today = new Date().toISOString().split('T')[0];
        setStartDate(today);
        setEndDate(today);
        router.get('/bank-book-ledger');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bank Book Ledger" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Bank Book Ledger
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            View bank and mobile bank account transactions and
                            balances
                        </p>
                    </div>
                    {ledgers.length > 0 && canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (startDate)
                                    params.append('start_date', startDate);
                                if (endDate) params.append('end_date', endDate);
                                window.location.href = `/bank-book-ledger/download-pdf?${params.toString()}`;
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                    )}
                </div>

                {/* Filter Card */}
                {canFilter && (
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
                                    <Label className="dark:text-gray-200">
                                        Start Date
                                    </Label>
                                    <Input
                                        type="date"
                                        value={startDate}
                                        onChange={(e) =>
                                            setStartDate(e.target.value)
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label className="dark:text-gray-200">
                                        End Date
                                    </Label>
                                    <Input
                                        type="date"
                                        value={endDate}
                                        onChange={(e) => setEndDate(e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div className="flex items-end gap-2 md:col-span-2">
                                    <Button onClick={applyFilters} className="px-4">
                                        Apply Filters
                                    </Button>
                                    <Button
                                        onClick={clearFilters}
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
                )}

                {ledgers.length > 0 ? (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                SL.
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Account Name
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Account Number
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Group
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {ledgers.map((ledger, index) => (
                                            <tr
                                                key={index}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {index + 1}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {ledger.account.name}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {ledger.account.ac_number}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {ledger.account.group?.name || 'N/A'}
                                                </td>
                                                <td className="p-4">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-indigo-600 hover:text-indigo-800"
                                                        onClick={() => router.get(`/bank-book-ledger/${ledger.account.ac_number}`)}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent>
                            <p className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">
                                No bank or mobile bank transactions found for
                                the selected period
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
