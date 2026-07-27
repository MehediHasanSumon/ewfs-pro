import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteModal } from '@/components/ui/delete-modal';
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
    ChevronDown,
    ChevronUp,
    Edit,
    FileText,
    Filter,
    Plus,
    Trash2,
    ShoppingCart,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';
import { SaleModal } from './SaleModal';
import type { Sale } from './SaleModal';

interface Account {
    id: number;
    name: string;
    ac_number: string;
}

interface Product {
    id: number;
    product_name: string;
    product_code: string;
    unit: { name: string };
    sales_price: number;
    stock?: {
        current_stock: number;
        available_stock: number;
    };
}

interface Vehicle {
    id: number;
    vehicle_number: string;
    customer_id: number;
    products?: {
        id: number;
        product_name: string;
    }[];
    customer: {
        id: number;
        name: string;
    } | null;
}

interface Shift {
    id: number;
    name: string;
}

interface ClosedShift {
    close_date: string;
    shift_id: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Sales',
        href: '/sales',
    },
];

interface SalesHistory {
    vehicle_no: string;
    customer: string;
    product_id: number;
}

interface SalesProps {
    sales: {
        data: Sale[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    accounts: Account[];
    groupedAccounts: Record<string, Account[]>;
    products: Product[];
    vehicles: Vehicle[];
    salesHistory: SalesHistory[];
    shifts: Shift[];
    closedShifts: ClosedShift[];
    uniqueCustomers: string[];
    uniqueVehicles: string[];
    filters: {
        search?: string;
        customer?: string;
        payment_status?: string;
        start_date?: string;
        end_date?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function Sales({ sales, accounts = [], groupedAccounts = {}, products = [], vehicles = [], salesHistory = [], shifts = [], closedShifts = [], uniqueCustomers = [], uniqueVehicles = [], filters = {} }: SalesProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-sale') || can('delete-sale');
    const canFilter = can('can-sale-filter');
    const canDownload = can('can-sale-download');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingSale, setEditingSale] = useState<Sale | null>(null);
    const [deletingSale, setDeletingSale] = useState<Sale | null>(null);
    const [selectedSales, setSelectedSales] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters.search || '');
    const [customer, setCustomer] = useState(filters.customer || 'all');
    const [paymentStatus, setPaymentStatus] = useState(filters.payment_status || 'all');
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');
    const [sortBy, setSortBy] = useState(filters.sort_by || 'created_at');
    const [sortOrder, setSortOrder] = useState(filters.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters.per_page || 10);

    const handleEdit = (sale: Sale) => {
        setEditingSale(sale);
        setIsCreateOpen(true);
    };

    const handleDelete = (sale: Sale) => {
        setDeletingSale(sale);
    };

    const confirmDelete = () => {
        if (deletingSale) {
            router.delete(`/sales/${deletingSale.id}`, {
                onSuccess: () => setDeletingSale(null),
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/sales/bulk/delete', {
            data: { ids: selectedSales },
            onSuccess: () => {
                setSelectedSales([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const applyFilters = () => {
        router.get(
            '/sales',
            {
                search: search || undefined,
                customer: customer === 'all' ? undefined : customer,
                payment_status: paymentStatus === 'all' ? undefined : paymentStatus,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setCustomer('all');
        setPaymentStatus('all');
        setStartDate('');
        setEndDate('');
        router.get(
            '/sales',
            {
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handleSort = (column: string) => {
        const newOrder = sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(newOrder);
        router.get(
            '/sales',
            {
                search: search || undefined,
                customer: customer === 'all' ? undefined : customer,
                payment_status: paymentStatus === 'all' ? undefined : paymentStatus,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                sort_by: column,
                sort_order: newOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/sales',
            {
                search: search || undefined,
                customer: customer === 'all' ? undefined : customer,
                payment_status: paymentStatus === 'all' ? undefined : paymentStatus,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
                page,
            },
            { preserveState: true },
        );
    };

    const toggleSelectAll = () => {
        if (selectedSales.length === sales.data.length) {
            setSelectedSales([]);
        } else {
            setSelectedSales(sales.data.map((sale) => sale.id));
        }
    };

    const toggleSelectSale = (saleId: number) => {
        if (selectedSales.includes(saleId)) {
            setSelectedSales(selectedSales.filter((id) => id !== saleId));
        } else {
            setSelectedSales([...selectedSales, saleId]);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (filters.search || '')) {
                applyFilters();
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sales" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Sales
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage sales orders and customer transactions
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedSales.length > 0 && can('delete-sale') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedSales.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (customer !== 'all') params.append('customer', customer);
                                    if (paymentStatus !== 'all') params.append('payment_status', paymentStatus);
                                    if (startDate) params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    if (sortBy) params.append('sort_by', sortBy);
                                    if (sortOrder) params.append('sort_order', sortOrder);
                                    window.location.href = `/sales/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        {can('create-sale') && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Sale
                            </Button>
                        )}
                    </div>
                </div>

                {canFilter && (
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
                                <Label className="dark:text-gray-200">Search</Label>
                                <Input
                                    placeholder="Search sales..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Customer</Label>
                                <Select
                                    value={customer}
                                    onValueChange={(value) => {
                                        setCustomer(value);
                                        applyFilters();
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All customers" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All customers</SelectItem>
                                        {Array.from(new Set([
                                            ...vehicles.filter(v => v.customer).map(v => v.customer!.name),
                                            ...uniqueCustomers
                                        ])).sort().map((name) => (
                                            <SelectItem key={name} value={name}>
                                                {name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Payment Status</Label>
                                <Select
                                    value={paymentStatus}
                                    onValueChange={(value) => {
                                        setPaymentStatus(value);
                                        applyFilters();
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All status</SelectItem>
                                        <SelectItem value="paid">Paid</SelectItem>
                                        <SelectItem value="partial">Partial</SelectItem>
                                        <SelectItem value="due">Due</SelectItem>
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
                            <div className="flex items-end gap-2">
                                <Button onClick={applyFilters} className="px-4">
                                    Apply Filters
                                </Button>
                                <Button onClick={clearFilters} variant="secondary" className="px-4">
                                    <X className="mr-2 h-4 w-4" />
                                    Clear
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                )}

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={selectedSales.length === sales.data.length && sales.data.length > 0}
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('sale_date')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Date
                                                {sortBy === 'sale_date' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Shift</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Invoice No</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Customer</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Product</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Vehicle</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Quantity</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Total Amount</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Paid Amount</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Payment Type</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Status</th>
                                        {hasActionPermission && <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {sales.data.length > 0 ? (
                                        sales.data.map((sale) => (
                                            <tr key={sale.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedSales.includes(sale.id)}
                                                        onChange={() => toggleSelectSale(sale.id)}
                                                        className="rounded border-gray-300 dark:border-gray-600"
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">{new Date(sale.sale_date).toLocaleDateString('en-GB')}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{sale.shift.name}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{sale.invoice_no}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{sale.customer}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {products.find(p => p.id === sale.product_id)?.product_name || 'N/A'}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{sale.vehicle_no}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{sale.quantity}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{sale.total_amount.toLocaleString()}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{sale.paid_amount.toLocaleString()}</td>
                                                <td className="p-4">
                                                    <span className={`rounded px-2 py-1 text-xs font-medium ${
                                                        (sale.transaction?.payment_type || 'cash') === 'cash' 
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                            : (sale.transaction?.payment_type || 'cash') === 'bank'
                                                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                            : (sale.transaction?.payment_type || 'cash') === 'mobile bank'
                                                            ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                                                            : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                    }`}>
                                                        {(sale.transaction?.payment_type || 'cash') === 'cash' ? 'Cash' 
                                                            : (sale.transaction?.payment_type || 'cash') === 'bank' ? 'Bank'
                                                            : (sale.transaction?.payment_type || 'cash') === 'mobile bank' ? 'Mobile Bank'
                                                            : 'Cash'}
                                                    </span>
                                                </td>
                                                <td className="p-4">
                                                    <span className={`rounded px-2 py-1 text-xs ${
                                                        parseFloat(sale.due_amount.toString()) === 0 
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                            : parseFloat(sale.paid_amount.toString()) > 0
                                                            ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                    }`}>
                                                        {parseFloat(sale.due_amount.toString()) === 0 ? 'Paid' : parseFloat(sale.paid_amount.toString()) > 0 ? 'Partial' : 'Due'}
                                                    </span>
                                                </td>
                                                {hasActionPermission && (
                                                <td className="p-4">
                                                    <div className="flex gap-2">
                                                        {sale.batch_code && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => window.open(`/sales/batch/${sale.batch_code}/pdf`, '_blank')}
                                                                className="text-blue-600 hover:text-blue-800"
                                                                title="Download Batch PDF"
                                                            >
                                                                <FileText className="h-4 w-4" />
                                                            </Button>
                                                        )}

                                                        {can('update-sale') && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => handleEdit(sale)}
                                                                className="text-indigo-600 hover:text-indigo-800"
                                                            >
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {can('delete-sale') && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => handleDelete(sale)}
                                                                className="text-red-600 hover:text-red-800"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                                )}
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={hasActionPermission ? 13 : 12} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                <ShoppingCart className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No sales found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={sales.current_page}
                            lastPage={sales.last_page}
                            from={sales.from}
                            to={sales.to}
                            total={sales.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                applyFilters();
                            }}
                        />
                    </CardContent>
                </Card>

                <SaleModal
                    isOpen={isCreateOpen}
                    onClose={() => {
                        setIsCreateOpen(false);
                        setEditingSale(null);
                    }}
                    editingSale={editingSale}
                    accounts={accounts}
                    groupedAccounts={groupedAccounts}
                    products={products}
                    vehicles={vehicles}
                    salesHistory={salesHistory}
                    shifts={shifts}
                    closedShifts={closedShifts}
                    uniqueCustomers={uniqueCustomers}
                    uniqueVehicles={uniqueVehicles}
                />

                <DeleteModal
                    isOpen={!!deletingSale}
                    onClose={() => setDeletingSale(null)}
                    onConfirm={confirmDelete}
                    title="Delete Sale"
                    message={`Are you sure you want to delete this sale? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Sales"
                    message={`Are you sure you want to delete ${selectedSales.length} selected sales? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}
