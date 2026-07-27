import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    FileText,
    Filter,
    BarChart3,
    X,
    Settings,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import React from 'react';

interface ProductSale {
    product_id: number;
    product_name: string;
    total_sale: number;
    price: number;
    amount: number;
}

interface DispenserReading {
    id: number;
    sl: number;
    date: string;
    shift: string;
    product_sales: ProductSale[];
    received_due_paid: number;
    amount: number;
    credit_sale: number;
    bank_sale: number;
    expenses: number;
    purchase: number;
    cash_in_hand: number;
    total_balance: number;
}

interface Product {
    id: number;
    product_name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Reports',
        href: '/reports',
    },
    {
        title: 'Monthly Dispenser Report',
        href: '/reports/monthly-dispenser-report',
    },
];

interface MonthlyDispenserReportProps {
    readings: {
        data: DispenserReading[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    products: Product[]; // Dynamic products list
    filters: {
        search?: string;
        product_id?: string;
        start_date?: string;
        end_date?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function MonthlyDispenserReport({
    readings,
    products = [],
    filters
}: MonthlyDispenserReportProps) {


    const [search, setSearch] = useState(filters?.search || '');
    const [productId, setProductId] = useState(filters?.product_id || 'all');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);
    const [showColumnSettings, setShowColumnSettings] = useState(false);
    const [visibleColumns, setVisibleColumns] = useState({
        received_due_paid: true,
        amount: true,
        credit_sale: true,
        bank_sale: true,
        expenses: true,
        purchase: true,
        cash_in_hand: true,
        total_balance: true,
    });
    const [visibleProducts, setVisibleProducts] = useState(
        products.reduce((acc, product) => {
            acc[product.id] = true;
            return acc;
        }, {} as Record<number, boolean>)
    );

    // Calculate total columns dynamically based on visible columns and products
    const visibleColumnCount = Object.values(visibleColumns).filter(Boolean).length;
    const visibleProductCount = Object.values(visibleProducts).filter(Boolean).length;
    const totalColumns = 3 + (visibleProductCount * 3) + visibleColumnCount;

    const applyFilters = () => {
        router.get(
            '/reports/monthly-dispenser-report',
            {
                search: search || undefined,
                product_id: productId === 'all' ? undefined : productId,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                per_page: perPage,
                visible_columns: JSON.stringify(visibleColumns),
                visible_products: JSON.stringify(visibleProducts),
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setProductId('all');
        setStartDate('');
        setEndDate('');
        router.get(
            '/reports/monthly-dispenser-report',
            {
                per_page: perPage,
            },
            { preserveState: true },
        );
    };



    const handlePageChange = (page: number) => {
        router.get(
            '/reports/monthly-dispenser-report',
            {
                search: search || undefined,
                product_id: productId === 'all' ? undefined : productId,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                per_page: perPage,
                visible_columns: JSON.stringify(visibleColumns),
                visible_products: JSON.stringify(visibleProducts),
                page,
            },
            { preserveState: true },
        );
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (filters?.search || '')) {
                applyFilters();
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        if (products.length > 0) {
            setVisibleProducts(products.reduce((acc, product) => {
                acc[product.id] = true;
                return acc;
            }, {} as Record<number, boolean>));
        }
    }, [products]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Monthly Dispenser Report" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Monthly Dispenser Report
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            View and analyze dispenser readings and sales data
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setShowColumnSettings(!showColumnSettings)}
                        >
                            <Settings className="mr-2 h-4 w-4" />
                            Columns
                        </Button>
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (search) params.append('search', search);
                                if (productId !== 'all') params.append('product_id', productId);
                                if (startDate) params.append('start_date', startDate);
                                if (endDate) params.append('end_date', endDate);
                                params.append('visible_columns', JSON.stringify(visibleColumns));
                                params.append('visible_products', JSON.stringify(visibleProducts));
                                window.location.href = `/reports/monthly-dispenser-report/download-pdf?${params.toString()}`;
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                    </div>
                </div>

                {/* Filter Card */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 dark:text-white">
                            <Filter className="h-5 w-5" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-6">
                            <div>
                                <Label className="dark:text-gray-200">
                                    Search
                                </Label>
                                <Input
                                    placeholder="Search..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Product
                                </Label>
                                <Select
                                    value={productId}
                                    onValueChange={(value) => {
                                        setProductId(value);
                                        router.get(
                                            '/reports/monthly-dispenser-report',
                                            {
                                                search: search || undefined,
                                                product_id: value === 'all' ? undefined : value,
                                                start_date: startDate || undefined,
                                                end_date: endDate || undefined,
                                                per_page: perPage,
                                                visible_columns: JSON.stringify(visibleColumns),
                                                visible_products: JSON.stringify(visibleProducts),
                                            },
                                            { preserveState: true },
                                        );
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All products" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All products
                                        </SelectItem>
                                        {products.map((product) => (
                                            <SelectItem
                                                key={product.id}
                                                value={product.id.toString()}
                                            >
                                                {product.product_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Shift
                                </Label>
                                <Select
                                    value="all"
                                    onValueChange={() => { }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All shifts" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All shifts
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Start Date
                                </Label>
                                <Input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
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
                            <div className="flex items-end gap-2">
                                <Button
                                    onClick={applyFilters}
                                    className="px-4"
                                >
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

                {/* Column Settings */}
                {showColumnSettings && (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 dark:text-white">
                                <Settings className="h-5 w-5" />
                                Column Visibility
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div>
                                    <h4 className="text-sm font-medium mb-3 dark:text-white">Product Columns</h4>
                                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                        {products.map((product) => (
                                            <div key={product.id} className="flex items-center space-x-2">
                                                <input
                                                    type="checkbox"
                                                    id={`product-${product.id}`}
                                                    checked={visibleProducts[product.id] ?? true}
                                                    onChange={(e) => {
                                                        setVisibleProducts(prev => ({
                                                            ...prev,
                                                            [product.id]: e.target.checked
                                                        }));
                                                    }}
                                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                />
                                                <Label htmlFor={`product-${product.id}`} className="text-sm dark:text-gray-200">
                                                    {product.product_name}
                                                </Label>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                <div>
                                    <h4 className="text-sm font-medium mb-3 dark:text-white">Financial Columns</h4>
                                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                        {[
                                            { key: 'received_due_paid', label: 'Received (Due Paid)' },
                                            { key: 'amount', label: 'Amount' },
                                            { key: 'credit_sale', label: 'Credit Sale' },
                                            { key: 'bank_sale', label: 'Bank Sale' },
                                            { key: 'expenses', label: 'Expenses' },
                                            { key: 'purchase', label: 'Purchase' },
                                            { key: 'cash_in_hand', label: 'Cash in Hand' },
                                            { key: 'total_balance', label: 'Total Balance' },
                                        ].map((column) => (
                                            <div key={column.key} className="flex items-center space-x-2">
                                                <input
                                                    type="checkbox"
                                                    id={column.key}
                                                    checked={visibleColumns[column.key as keyof typeof visibleColumns]}
                                                    onChange={(e) => {
                                                        setVisibleColumns(prev => ({
                                                            ...prev,
                                                            [column.key]: e.target.checked
                                                        }));
                                                    }}
                                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                />
                                                <Label htmlFor={column.key} className="text-sm dark:text-gray-200">
                                                    {column.label}
                                                </Label>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse border border-gray-300">
                                <thead className="sticky top-0 bg-white dark:bg-gray-800">
                                    {/* First Header Row */}
                                    <tr className="bg-gray-100 dark:bg-gray-700">
                                        <th rowSpan={2} className="border border-gray-300 p-3 text-[12px] font-semibold dark:text-gray-200 min-w-[60px]">SL</th>
                                        <th rowSpan={2} className="border border-gray-300 p-3 text-[12px] font-semibold dark:text-gray-200 min-w-[100px]">Date</th>
                                        <th rowSpan={2} className="border border-gray-300 p-3 text-[12px] font-semibold dark:text-gray-200 min-w-[80px]">Shift</th>
                                        {/* Dynamic Product Headers */}
                                        {products.filter(product => visibleProducts[product.id]).map((product) => (
                                            <th key={product.id} colSpan={3} className="border border-gray-300 p-3 text-[12px] font-semibold dark:text-gray-200">
                                                {product.product_name}
                                            </th>
                                        ))}
                                        {visibleColumns.received_due_paid && <th rowSpan={2} className="border border-gray-300 p-3 text-[12px] font-semibold dark:text-gray-200 min-w-[120px]">Received (Due Paid)</th>}
                                        {visibleColumns.amount && <th rowSpan={2} className="border border-gray-300 p-3 text-[12px] font-semibold dark:text-gray-200 min-w-[100px]">Amount</th>}
                                        {visibleColumns.credit_sale && <th rowSpan={2} className="border border-gray-300 p-3 text-[12px] font-semibold dark:text-gray-200 min-w-[100px]">Credit Sale</th>}
                                        {visibleColumns.bank_sale && <th rowSpan={2} className="border border-gray-300 p-3 text-[12px] font-semibold dark:text-gray-200 min-w-[100px]">Bank Sale</th>}
                                        {visibleColumns.expenses && <th rowSpan={2} className="border border-gray-300 p-3 text-[12px] font-semibold dark:text-gray-200 min-w-[100px]">Expenses</th>}
                                        {visibleColumns.purchase && <th rowSpan={2} className="border border-gray-300 p-3 text-[12px] font-semibold dark:text-gray-200 min-w-[100px]">Purchase</th>}
                                        {visibleColumns.cash_in_hand && <th rowSpan={2} className="border border-gray-300 p-3 text-[12px] font-semibold dark:text-gray-200 min-w-[120px]">Cash in Hand</th>}
                                        {visibleColumns.total_balance && <th rowSpan={2} className="border border-gray-300 p-3 text-[12px] font-semibold dark:text-gray-200 min-w-[120px]">Total Balance</th>}
                                    </tr>
                                    {/* Second Header Row */}
                                    <tr className="bg-gray-50 dark:bg-gray-600">
                                        {/* Dynamic Product Sub-headers */}
                                        {products.filter(product => visibleProducts[product.id]).map((product) => (
                                            <React.Fragment key={`sub-${product.id}`}>
                                                <th className="border border-gray-300 p-2 text-[11px] font-medium dark:text-gray-200 text-center min-w-[80px]">Total Sale</th>
                                                <th className="border border-gray-300 p-2 text-[11px] font-medium dark:text-gray-200 text-center min-w-[80px]">Price</th>
                                                <th className="border border-gray-300 p-2 text-[11px] font-medium dark:text-gray-200 text-center min-w-[80px]">Amount</th>
                                            </React.Fragment>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {/* Real Data Rows */}
                                    {readings.data.map((reading) => (
                                        <tr key={reading.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td className="border border-gray-300 p-2 text-[12px] text-center font-medium dark:text-white">{reading.sl}</td>
                                            <td className="border border-gray-300 p-2 text-[12px] dark:text-white">{reading.date}</td>
                                            <td className="border border-gray-300 p-2 text-[12px] text-center dark:text-white">{reading.shift}</td>
                                            {/* Dynamic Product Data */}
                                            {products.filter(product => visibleProducts[product.id]).map((product) => {
                                                const productSale = reading.product_sales.find(ps => ps.product_id === product.id);
                                                const totalSale = productSale?.total_sale || 0;
                                                const price = productSale?.price || 0;
                                                const amount = productSale?.amount || 0;

                                                return (
                                                    <React.Fragment key={`data-${product.id}`}>
                                                        <td className="border border-gray-300 p-2 text-[12px] text-right dark:text-gray-300">{Number(totalSale).toFixed(2)}</td>
                                                        <td className="border border-gray-300 p-2 text-[12px] text-right dark:text-gray-300">{Number(price).toFixed(2)}</td>
                                                        <td className="border border-gray-300 p-2 text-[12px] text-right font-semibold dark:text-white">{Number(amount).toFixed(2)}</td>
                                                    </React.Fragment>
                                                );
                                            })}
                                            {visibleColumns.received_due_paid && <td className="border border-gray-300 p-2 text-[12px] text-right dark:text-gray-300">{Number(reading.received_due_paid).toFixed(2)}</td>}
                                            {visibleColumns.amount && <td className="border border-gray-300 p-2 text-[12px] text-right font-semibold dark:text-white">{Number(reading.amount).toFixed(2)}</td>}
                                            {visibleColumns.credit_sale && <td className="border border-gray-300 p-2 text-[12px] text-right dark:text-gray-300">{Number(reading.credit_sale).toFixed(2)}</td>}
                                            {visibleColumns.bank_sale && <td className="border border-gray-300 p-2 text-[12px] text-right dark:text-gray-300">{Number(reading.bank_sale).toFixed(2)}</td>}
                                            {visibleColumns.expenses && <td className="border border-gray-300 p-2 text-[12px] text-right dark:text-gray-300">{Number(reading.expenses).toFixed(2)}</td>}
                                            {visibleColumns.purchase && <td className="border border-gray-300 p-2 text-[12px] text-right dark:text-gray-300">{Number(reading.purchase).toFixed(2)}</td>}
                                            {visibleColumns.cash_in_hand && <td className="border border-gray-300 p-2 text-[12px] text-right font-semibold dark:text-white">{Number(reading.cash_in_hand).toFixed(2)}</td>}
                                            {visibleColumns.total_balance && <td className="border border-gray-300 p-2 text-[12px] text-right font-semibold dark:text-white">{Number(reading.total_balance).toFixed(2)}</td>}
                                        </tr>
                                    ))}
                                    {readings.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={totalColumns}
                                                className="border border-gray-300 p-8 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <BarChart3 className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No dispenser readings found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={readings.current_page}
                            lastPage={readings.last_page}
                            from={readings.from}
                            to={readings.to}
                            total={readings.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get(
                                    '/reports/monthly-dispenser-report',
                                    {
                                        search: search || undefined,
                                        product_id: productId === 'all' ? undefined : productId,
                                        start_date: startDate || undefined,
                                        end_date: endDate || undefined,
                                        per_page: newPerPage,
                                        visible_columns: JSON.stringify(visibleColumns),
                                        visible_products: JSON.stringify(visibleProducts),
                                    },
                                    { preserveState: true },
                                );
                            }}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}