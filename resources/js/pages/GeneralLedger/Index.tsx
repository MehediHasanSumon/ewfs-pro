import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { FileText, Filter, X } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
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

interface Props {
    accounts: Account[];
    selectedAccount: Account | null;
    transactions: Transaction[];
    currentBalance: number;
    filters: {
        account_id?: string;
        start_date?: string;
        end_date?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'General Ledger', href: '/general-ledger' },
];

export default function GeneralLedger({ accounts, selectedAccount, transactions, currentBalance, filters }: Props) {
    const { can } = usePermission();
    const canFilter = can('can-account-filter');
    const canDownload = can('can-account-download');

    const [accountId, setAccountId] = useState(filters?.account_id || '');
    const [startDate, setStartDate] = useState(filters?.start_date || new Date().toISOString().split('T')[0]);
    const [endDate, setEndDate] = useState(filters?.end_date || new Date().toISOString().split('T')[0]);

    const applyFilters = () => {
        if (!accountId) return;
        const params = new URLSearchParams();
        if (accountId) params.append('account_id', accountId);
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        
        router.get(`/general-ledger?${params.toString()}`);
    };

    const clearFilters = () => {
        const today = new Date().toISOString().split('T')[0];
        setAccountId('');
        setStartDate(today);
        setEndDate(today);
        router.get('/general-ledger');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="General Ledger" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            General Ledger
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            View account transactions and balances
                        </p>
                    </div>
                    {selectedAccount && canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (accountId) params.append('account_id', accountId);
                                if (startDate) params.append('start_date', startDate);
                                if (endDate) params.append('end_date', endDate);
                                window.location.href = `/general-ledger/download-pdf?${params.toString()}`;
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
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-5">
                                <div>
                                    <Label className="dark:text-gray-200">Account</Label>
                                    <Select value={accountId} onValueChange={setAccountId}>
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="Select account" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {accounts.map((account) => (
                                                <SelectItem key={account.id} value={account.id.toString()}>
                                                    {account.name} ({account.ac_number})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
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

                {selectedAccount ? (
                    <div className="space-y-6">
                        {/* Account Info */}
                        <Card className="dark:border-gray-700 dark:bg-gray-800">
                            <CardHeader>
                                <CardTitle className="text-[16px] font-bold dark:text-white mb-2">Account Information</CardTitle>
                                <div className="space-y-1 text-[13px]">
                                    <div><span className="font-semibold dark:text-gray-300">Account Name:</span> <span className="dark:text-white">{selectedAccount.name}</span></div>
                                    <div><span className="font-semibold dark:text-gray-300">Account Number:</span> <span className="dark:text-white">{selectedAccount.ac_number}</span></div>
                                    <div><span className="font-semibold dark:text-gray-300">Group:</span> <span className="dark:text-white">{selectedAccount.group?.name || 'N/A'}</span></div>
                                    <div>
                                        <span className="font-semibold dark:text-gray-300">Current Balance:</span> 
                                        <span className={`font-bold ${currentBalance >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                            {Math.abs(currentBalance).toLocaleString()} {currentBalance >= 0 ? 'Cr' : 'Dr'}
                                        </span>
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>

                        {/* Transactions Table */}
                        <Card className="dark:border-gray-700 dark:bg-gray-800">
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b dark:border-gray-700">
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Date</th>
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Transaction ID</th>
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Description</th>
                                                <th className="p-2 text-left text-[13px] font-medium dark:text-gray-300">Payment Type</th>
                                                <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Debit</th>
                                                <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Credit</th>
                                                <th className="p-2 text-right text-[13px] font-medium dark:text-gray-300">Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {transactions.length > 0 ? (
                                                transactions.map((transaction) => (
                                                    <tr key={transaction.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                        <td className="p-2 text-[13px] dark:text-white">{transaction.transaction_date}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{transaction.transaction_id}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300">{transaction.description}</td>
                                                        <td className="p-2 text-[13px] dark:text-gray-300 capitalize">{transaction.payment_type}</td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">
                                                            {transaction.transaction_type === 'Dr' ? `${transaction.amount.toLocaleString()}` : '-'}
                                                        </td>
                                                        <td className="p-2 text-right text-[13px] dark:text-gray-300">
                                                            {transaction.transaction_type === 'Cr' ? `${transaction.amount.toLocaleString()}` : '-'}
                                                        </td>
                                                        <td className={`p-2 text-right text-[13px] font-medium ${transaction.balance >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                            {Math.abs(transaction.balance).toLocaleString()} {transaction.balance >= 0 ? 'Cr' : 'Dr'}
                                                        </td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan={7} className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">
                                                        No transactions found for the selected period
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                ) : (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent>
                            <p className="p-4 text-center text-[13px] text-gray-500 dark:text-gray-400">
                                Please select an account to view its general ledger
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}