import { openPdfViewer } from '@/components/documents/pdf-viewer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormModal } from '@/components/ui/form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermission } from '@/hooks/usePermission';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowLeft,
    ArrowUpRight,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    CreditCard,
    Database,
    Edit,
    ExternalLink,
    FileText,
    Filter,
    Hash,
    Layers,
    Shield,
    TrendingUp,
    UserCheck,
    Users,
    Wallet,
    X,
} from 'lucide-react';
import React, { useState } from 'react';

interface Group {
    id: number;
    code: string;
    name: string;
    account_class?: string;
    normal_balance?: string;
    status?: boolean;
}

interface LinkedEntity {
    id: number;
    name?: string;
    employee_name?: string;
    code?: string;
    employee_code?: string;
    mobile?: string;
    designation?: {
        id: number;
        name: string;
    };
}

interface Account {
    id: number;
    name: string;
    ac_number: string;
    group_id?: number;
    group?: Group;
    currency: string;
    is_control_account: boolean;
    allow_manual_posting: boolean;
    is_system: boolean;
    status: boolean;
    created_at: string;
    customer?: LinkedEntity;
    supplier?: LinkedEntity;
    employee?: LinkedEntity;
}

interface Transaction {
    id: number;
    transaction_id: string;
    transaction_date: string;
    transaction_time?: string;
    transaction_type: 'Dr' | 'Cr';
    voucher_no?: string;
    voucher_type?: string;
    voucher_date?: string;
    shift_name?: string;
    description?: string;
    payment_type?: string;
    debit_amount: number;
    credit_amount: number;
    amount: number;
    balance: number;
}

interface AccountDetailsProps {
    account: Account;
    groups: Group[];
    transactions: {
        data: Transaction[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    openingBalance: number;
    periodDebit: number;
    periodCredit: number;
    closingBalance: number;
    allTimeBalance: number;
    allTimeDebit: number;
    allTimeCredit: number;
    transactionCount: number;
    filters: {
        start_date?: string;
        end_date?: string;
        search?: string;
        per_page?: number;
    };
}

export default function AccountDetails({
    account,
    groups = [],
    transactions,
    openingBalance = 0,
    periodDebit = 0,
    periodCredit = 0,
    closingBalance = 0,
    allTimeBalance = 0,
    allTimeDebit = 0,
    allTimeCredit = 0,
    transactionCount = 0,
    filters,
}: AccountDetailsProps) {
    const { can } = usePermission();
    const canDownload = can('can-account-download') || can('view-account');
    const canUpdate = can('update-account');

    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');
    const [search, setSearch] = useState(filters?.search || '');
    const [perPage, setPerPage] = useState(filters?.per_page || 15);
    const [isEditOpen, setIsEditOpen] = useState(false);

    const isCreditNormal = account.group?.normal_balance === 'credit';

    const { data: editData, setData: setEditData, put, processing, errors, reset } = useForm({
        name: account.name,
        ac_number: account.ac_number,
        group_id: account.group_id?.toString() || '',
        status: account.status,
    });

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-BD', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    };

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: dashboard().url,
        },
        {
            title: 'Accounts',
            href: '/accounts',
        },
        {
            title: account.name,
            href: `/accounts/${account.id}`,
        },
    ];

    const applyFilters = () => {
        router.get(
            `/accounts/${account.id}`,
            {
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                search: search || undefined,
                per_page: perPage,
            },
            { preserveState: true }
        );
    };

    const clearFilters = () => {
        const today = new Date().toISOString().split('T')[0];
        const yearStart = `${new Date().getFullYear()}-01-01`;
        setStartDate(yearStart);
        setEndDate(today);
        setSearch('');
        router.get(`/accounts/${account.id}`, {
            start_date: yearStart,
            end_date: today,
        });
    };

    const handlePageChange = (page: number) => {
        router.get(
            `/accounts/${account.id}`,
            {
                page,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                search: search || undefined,
                per_page: perPage,
            },
            { preserveState: true }
        );
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/accounts/${account.id}`, {
            onSuccess: () => {
                setIsEditOpen(false);
            },
        });
    };

    const handlePrintStatement = () => {
        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        openPdfViewer(`/accounts/${account.id}/statement-pdf?${params.toString()}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Account Details - ${account.name}`} />

            <div className="space-y-6 p-4 sm:p-6">
                {/* 1. Header Section */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight dark:text-white sm:text-3xl">
                                {account.name}
                            </h1>
                            <Badge
                                variant={account.status ? 'default' : 'secondary'}
                                className={
                                    account.status
                                        ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300'
                                        : 'bg-rose-100 text-rose-800 hover:bg-rose-100 dark:bg-rose-950 dark:text-rose-300'
                                }
                            >
                                {account.status ? 'Active' : 'Inactive'}
                            </Badge>
                            {account.is_system && (
                                <Badge variant="outline" className="border-purple-300 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-950 dark:text-purple-300">
                                    System Account
                                </Badge>
                            )}
                            {account.group && (
                                <Badge variant="outline" className="border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-300">
                                    {account.group.name} ({account.group.code})
                                </Badge>
                            )}
                        </div>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            A/C Number: <span className="font-semibold text-gray-700 dark:text-gray-200">{account.ac_number}</span> • Currency: {account.currency || 'BDT'}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {canDownload && (
                            <Button
                                variant="outline"
                                onClick={handlePrintStatement}
                                className="dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                <FileText className="mr-2 h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                PDF Statement
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            asChild
                            className="dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <Link href={`/general-ledger?account_id=${account.id}`}>
                                <ExternalLink className="mr-2 h-4 w-4 text-blue-600 dark:text-blue-400" />
                                General Ledger
                            </Link>
                        </Button>
                        {canUpdate && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setEditData({
                                        name: account.name,
                                        ac_number: account.ac_number,
                                        group_id: account.group_id?.toString() || '',
                                        status: account.status,
                                    });
                                    setIsEditOpen(true);
                                }}
                                className="dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                <Edit className="mr-2 h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                                Edit
                            </Button>
                        )}
                        <Button
                            variant="secondary"
                            asChild
                            className="dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600"
                        >
                            <Link href="/accounts">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Accounts
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* 2. Top Metric Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* All-Time Balance */}
                    <Card className="border-l-4 border-l-indigo-500 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Current Net Balance
                                    </p>
                                    <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                                        ৳ {formatCurrency(Math.abs(allTimeBalance))}
                                    </p>
                                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {allTimeBalance >= 0 ? (
                                            <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                                {isCreditNormal ? 'Credit (Cr) Balance' : 'Debit (Dr) Balance'}
                                            </span>
                                        ) : (
                                            <span className="font-semibold text-rose-600 dark:text-rose-400">
                                                {isCreditNormal ? 'Debit (Dr) Overdrawn' : 'Credit (Cr) Overdrawn'}
                                            </span>
                                        )}
                                    </p>
                                </div>
                                <div className="rounded-xl bg-indigo-50 p-3 dark:bg-indigo-950/50">
                                    <Wallet className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Period Total Debit */}
                    <Card className="border-l-4 border-l-blue-500 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Period Total Debit
                                    </p>
                                    <p className="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">
                                        ৳ {formatCurrency(periodDebit)}
                                    </p>
                                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        All-Time Dr: ৳ {formatCurrency(allTimeDebit)}
                                    </p>
                                </div>
                                <div className="rounded-xl bg-blue-50 p-3 dark:bg-blue-950/50">
                                    <ArrowDownLeft className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Period Total Credit */}
                    <Card className="border-l-4 border-l-amber-500 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Period Total Credit
                                    </p>
                                    <p className="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">
                                        ৳ {formatCurrency(periodCredit)}
                                    </p>
                                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        All-Time Cr: ৳ {formatCurrency(allTimeCredit)}
                                    </p>
                                </div>
                                <div className="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/50">
                                    <ArrowUpRight className="h-6 w-6 text-amber-600 dark:text-amber-400" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Period Closing Balance */}
                    <Card className="border-l-4 border-l-emerald-500 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Period Closing Balance
                                    </p>
                                    <p className="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                                        ৳ {formatCurrency(Math.abs(closingBalance))}
                                    </p>
                                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        Opening: ৳ {formatCurrency(openingBalance)}
                                    </p>
                                </div>
                                <div className="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/50">
                                    <TrendingUp className="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* 3. Account Information & Linked Parties Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Account Master Info */}
                    <Card className="shadow-sm lg:col-span-2 dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader className="border-b bg-gray-50/50 px-5 py-3 dark:border-gray-700 dark:bg-gray-750">
                            <CardTitle className="flex items-center text-sm font-bold text-gray-900 dark:text-white">
                                <Building2 className="mr-2 h-4 w-4 text-indigo-500" />
                                Account Configuration & Master Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-5">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div className="rounded-lg border bg-gray-50/50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Account Name</span>
                                    <p className="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{account.name}</p>
                                </div>
                                <div className="rounded-lg border bg-gray-50/50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Account Number</span>
                                    <p className="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{account.ac_number}</p>
                                </div>
                                <div className="rounded-lg border bg-gray-50/50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Account Group</span>
                                    <p className="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                        {account.group?.name || 'N/A'} ({account.group?.code || 'N/A'})
                                    </p>
                                </div>
                                <div className="rounded-lg border bg-gray-50/50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Normal Balance</span>
                                    <p className="mt-1 text-sm font-semibold capitalize text-gray-900 dark:text-white">
                                        {account.group?.normal_balance || 'Debit'}
                                    </p>
                                </div>
                                <div className="rounded-lg border bg-gray-50/50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Account Classification</span>
                                    <p className="mt-1 text-sm font-semibold capitalize text-gray-900 dark:text-white">
                                        {account.group?.account_class || 'Asset'}
                                    </p>
                                </div>
                                <div className="rounded-lg border bg-gray-50/50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Manual Posting</span>
                                    <p className="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                        {account.allow_manual_posting ? 'Allowed' : 'System Only'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Linked Entity Card */}
                    <Card className="shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader className="border-b bg-gray-50/50 px-5 py-3 dark:border-gray-700 dark:bg-gray-750">
                            <CardTitle className="flex items-center text-sm font-bold text-gray-900 dark:text-white">
                                <Users className="mr-2 h-4 w-4 text-blue-500" />
                                Linked Entity Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-5">
                            {account.customer ? (
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <Badge className="bg-blue-100 text-blue-800 hover:bg-blue-100 dark:bg-blue-950 dark:text-blue-300">
                                            Customer Account
                                        </Badge>
                                        <Link
                                            href={`/customers/${account.customer.id}`}
                                            className="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                        >
                                            View Customer Profile →
                                        </Link>
                                    </div>
                                    <div className="rounded-lg border bg-gray-50/50 p-3 text-sm dark:border-gray-700 dark:bg-gray-900/40">
                                        <p className="font-semibold text-gray-900 dark:text-white">{account.customer.name}</p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">Code: {account.customer.code || 'N/A'}</p>
                                        {account.customer.mobile && (
                                            <p className="text-xs text-gray-500 dark:text-gray-400">Phone: {account.customer.mobile}</p>
                                        )}
                                    </div>
                                </div>
                            ) : account.supplier ? (
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <Badge className="bg-amber-100 text-amber-800 hover:bg-amber-100 dark:bg-amber-950 dark:text-amber-300">
                                            Supplier Account
                                        </Badge>
                                        <Link
                                            href={`/suppliers/${account.supplier.id}`}
                                            className="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                        >
                                            View Supplier Profile →
                                        </Link>
                                    </div>
                                    <div className="rounded-lg border bg-gray-50/50 p-3 text-sm dark:border-gray-700 dark:bg-gray-900/40">
                                        <p className="font-semibold text-gray-900 dark:text-white">{account.supplier.name}</p>
                                        {account.supplier.mobile && (
                                            <p className="text-xs text-gray-500 dark:text-gray-400">Phone: {account.supplier.mobile}</p>
                                        )}
                                    </div>
                                </div>
                            ) : account.employee ? (
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <Badge className="bg-purple-100 text-purple-800 hover:bg-purple-100 dark:bg-purple-950 dark:text-purple-300">
                                            Employee Account
                                        </Badge>
                                        <Link
                                            href={`/employees/${account.employee.id}`}
                                            className="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                        >
                                            View Employee Profile →
                                        </Link>
                                    </div>
                                    <div className="rounded-lg border bg-gray-50/50 p-3 text-sm dark:border-gray-700 dark:bg-gray-900/40">
                                        <p className="font-semibold text-gray-900 dark:text-white">{account.employee.employee_name}</p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">Code: {account.employee.employee_code || 'N/A'}</p>
                                        {account.employee.designation?.name && (
                                            <p className="text-xs text-gray-500 dark:text-gray-400">Designation: {account.employee.designation.name}</p>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-6 text-center text-gray-500 dark:text-gray-400">
                                    <Shield className="h-8 w-8 text-gray-300 dark:text-gray-600" />
                                    <p className="mt-2 text-xs font-medium">General Chart of Account</p>
                                    <p className="text-[11px] text-gray-400">Not tied to a specific sub-ledger party</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* 4. Ledger Transactions & Journal Lines Section */}
                <Card className="shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader className="border-b bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-750">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="text-base font-bold text-gray-900 dark:text-white">
                                    Account Transactions & Ledger
                                </CardTitle>
                                <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    Showing journal lines and ledger balance records for the selected period
                                </p>
                            </div>

                            {/* Filter Bar */}
                            <div className="flex flex-wrap items-center gap-2">
                                <div className="flex items-center gap-1.5">
                                    <Label className="text-xs text-gray-500 dark:text-gray-400">From:</Label>
                                    <Input
                                        type="date"
                                        value={startDate}
                                        onChange={(e) => setStartDate(e.target.value)}
                                        className="h-8 w-36 text-xs dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <Label className="text-xs text-gray-500 dark:text-gray-400">To:</Label>
                                    <Input
                                        type="date"
                                        value={endDate}
                                        onChange={(e) => setEndDate(e.target.value)}
                                        className="h-8 w-36 text-xs dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <Button
                                    size="sm"
                                    onClick={applyFilters}
                                    className="h-8 px-3 text-xs bg-indigo-600 hover:bg-indigo-700 text-white"
                                >
                                    <Filter className="mr-1.5 h-3.5 w-3.5" />
                                    Filter
                                </Button>
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    onClick={clearFilters}
                                    className="h-8 px-3 text-xs"
                                >
                                    <X className="mr-1.5 h-3.5 w-3.5" />
                                    Clear
                                </Button>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        {/* Opening Balance Row */}
                        <div className="flex items-center justify-between border-b bg-gray-50/80 px-5 py-2.5 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-200">
                            <span>Opening Balance (Before {startDate || 'Selected Period'})</span>
                            <span className="font-mono text-sm font-bold text-gray-900 dark:text-white">
                                ৳ {formatCurrency(Math.abs(openingBalance))} {openingBalance >= 0 ? (isCreditNormal ? 'Cr' : 'Dr') : (isCreditNormal ? 'Dr' : 'Cr')}
                            </span>
                        </div>

                        {/* Transaction Table */}
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b bg-gray-50 text-[12px] font-semibold text-gray-600 dark:border-gray-700 dark:bg-gray-750 dark:text-gray-300">
                                        <th className="p-3 text-left">SL</th>
                                        <th className="p-3 text-left">Date</th>
                                        <th className="p-3 text-left">Voucher / Entry #</th>
                                        <th className="p-3 text-left">Type</th>
                                        <th className="p-3 text-left">Shift</th>
                                        <th className="p-3 text-left">Description</th>
                                        <th className="p-3 text-right">Debit (Dr)</th>
                                        <th className="p-3 text-right">Credit (Cr)</th>
                                        <th className="p-3 text-right">Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transactions.data && transactions.data.length > 0 ? (
                                        transactions.data.map((tx, idx) => (
                                            <tr
                                                key={tx.id || idx}
                                                className="border-b text-[13px] hover:bg-gray-50/80 dark:border-gray-700 dark:hover:bg-gray-700/50"
                                            >
                                                <td className="p-3 text-gray-500 dark:text-gray-400">
                                                    {(transactions.current_page - 1) * transactions.per_page + idx + 1}
                                                </td>
                                                <td className="p-3 font-medium text-gray-900 dark:text-white">
                                                    <div>{tx.transaction_date || tx.voucher_date}</div>
                                                    {tx.transaction_time && (
                                                        <div className="text-[11px] text-gray-400">{tx.transaction_time}</div>
                                                    )}
                                                </td>
                                                <td className="p-3 font-mono text-xs font-medium text-indigo-600 dark:text-indigo-400">
                                                    {tx.voucher_no || tx.transaction_id || '-'}
                                                </td>
                                                <td className="p-3">
                                                    <span className="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-800 dark:bg-gray-750 dark:text-gray-300">
                                                        {tx.voucher_type ? tx.voucher_type.replace(/_/g, ' ') : (tx.transaction_type === 'Dr' ? 'Debit' : 'Credit')}
                                                    </span>
                                                </td>
                                                <td className="p-3 text-gray-600 dark:text-gray-300">
                                                    {tx.shift_name || '-'}
                                                </td>
                                                <td className="max-w-xs truncate p-3 text-gray-600 dark:text-gray-300" title={tx.description}>
                                                    {tx.description || '-'}
                                                </td>
                                                <td className="p-3 text-right font-mono font-medium text-blue-600 dark:text-blue-400">
                                                    {tx.debit_amount > 0 ? formatCurrency(tx.debit_amount) : '-'}
                                                </td>
                                                <td className="p-3 text-right font-mono font-medium text-amber-600 dark:text-amber-400">
                                                    {tx.credit_amount > 0 ? formatCurrency(tx.credit_amount) : '-'}
                                                </td>
                                                <td className="p-3 text-right font-mono font-bold text-gray-900 dark:text-white">
                                                    ৳ {formatCurrency(Math.abs(tx.balance))} {tx.balance >= 0 ? (isCreditNormal ? 'Cr' : 'Dr') : (isCreditNormal ? 'Dr' : 'Cr')}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={9}
                                                className="p-8 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <Database className="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-gray-600" />
                                                <p className="text-sm font-medium">No transactions recorded for this period</p>
                                                <p className="mt-1 text-xs text-gray-400">Try adjusting the date range filters above</p>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                                <tfoot>
                                    <tr className="border-t-2 border-gray-200 bg-gray-50/80 font-bold dark:border-gray-700 dark:bg-gray-750">
                                        <td colSpan={6} className="p-3 text-right text-xs uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Period Total & Closing Balance:
                                        </td>
                                        <td className="p-3 text-right font-mono text-blue-600 dark:text-blue-400">
                                            ৳ {formatCurrency(periodDebit)}
                                        </td>
                                        <td className="p-3 text-right font-mono text-amber-600 dark:text-amber-400">
                                            ৳ {formatCurrency(periodCredit)}
                                        </td>
                                        <td className="p-3 text-right font-mono text-emerald-600 dark:text-emerald-400">
                                            ৳ {formatCurrency(Math.abs(closingBalance))} {closingBalance >= 0 ? (isCreditNormal ? 'Cr' : 'Dr') : (isCreditNormal ? 'Dr' : 'Cr')}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {/* Pagination */}
                        <div className="border-t p-4 dark:border-gray-700">
                            <Pagination
                                currentPage={transactions.current_page}
                                lastPage={transactions.last_page}
                                from={transactions.from}
                                to={transactions.to}
                                total={transactions.total}
                                perPage={perPage}
                                onPageChange={handlePageChange}
                                onPerPageChange={(newPerPage) => {
                                    setPerPage(newPerPage);
                                    router.get(
                                        `/accounts/${account.id}`,
                                        {
                                            start_date: startDate || undefined,
                                            end_date: endDate || undefined,
                                            per_page: newPerPage,
                                        },
                                        { preserveState: true }
                                    );
                                }}
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* 5. Edit Account Modal */}
                <FormModal
                    isOpen={isEditOpen}
                    onClose={() => setIsEditOpen(false)}
                    title="Edit Account"
                    onSubmit={handleEditSubmit}
                    processing={processing}
                    submitText="Update Account"
                >
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="edit-name" className="dark:text-gray-200">
                                Account Name
                            </Label>
                            <Input
                                id="edit-name"
                                value={editData.name}
                                onChange={(e) => setEditData('name', e.target.value)}
                                className="mt-1 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.name && <span className="text-xs text-red-500">{errors.name}</span>}
                        </div>

                        <div>
                            <Label htmlFor="edit-ac-number" className="dark:text-gray-200">
                                Account Number
                            </Label>
                            <Input
                                id="edit-ac-number"
                                value={editData.ac_number}
                                onChange={(e) => setEditData('ac_number', e.target.value)}
                                className="mt-1 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.ac_number && <span className="text-xs text-red-500">{errors.ac_number}</span>}
                        </div>

                        <div>
                            <Label className="dark:text-gray-200">Account Group</Label>
                            <Select
                                value={editData.group_id}
                                onValueChange={(val) => setEditData('group_id', val)}
                                disabled={account.is_system}
                            >
                                <SelectTrigger className="mt-1 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select Group" />
                                </SelectTrigger>
                                <SelectContent>
                                    {groups.map((grp) => (
                                        <SelectItem key={grp.id} value={grp.id.toString()}>
                                            {grp.name} ({grp.code})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {account.is_system && (
                                <p className="mt-1 text-xs text-gray-400">System account groups cannot be changed.</p>
                            )}
                            {errors.group_id && <span className="text-xs text-red-500">{errors.group_id}</span>}
                        </div>

                        <div>
                            <Label className="dark:text-gray-200">Status</Label>
                            <Select
                                value={editData.status ? 'true' : 'false'}
                                onValueChange={(val) => setEditData('status', val === 'true')}
                            >
                                <SelectTrigger className="mt-1 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="true">Active</SelectItem>
                                    <SelectItem value="false">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </FormModal>
            </div>
        </AppLayout>
    );
}
