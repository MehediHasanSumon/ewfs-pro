import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteModal } from '@/components/ui/delete-modal';
import { FormModal } from '@/components/ui/form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Edit, Eye, FileText, Filter, Plus, Trash2, X, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface Account {
    id: number;
    name: string;
    ac_number: string;
    group_id: number;
    group?: Group;
}

interface Customer {
    id: number;
    account_id?: number;
    code?: string;
    name: string;
    mobile?: string;
    email?: string;
    nid_number?: string;
    vat_reg_no?: string;
    tin_no?: string;
    trade_license?: string;
    discount_rate?: number;
    security_deposit?: number;
    credit_limit?: number;
    address?: string;
    status: boolean;
    account?: Account;
    total_sales?: number;
    total_paid?: number;
    current_due?: number;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Customers',
        href: '/customers',
    },
];

interface Group {
    id: number;
    code: string;
    name: string;
}

interface Product {
    id: number;
    name: string;
}

interface CustomersProps {
    customers: {
        data: Customer[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    groups: Group[];
    products: Product[];
    lastCustomerGroup?: {
        id: number;
        code: string;
    } | null;
    filters: {
        search?: string;
        status?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function Customers({ customers, groups = [], products = [], lastCustomerGroup, filters }: CustomersProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-customer') || can('delete-customer');
    const canFilter = can('can-customer-filter');
    const canDownload = can('can-customer-download');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState<Customer | null>(null);
    const [deletingCustomer, setDeletingCustomer] = useState<Customer | null>(null);
    const [selectedCustomers, setSelectedCustomers] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'created_at');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',

        mobile: '',
        email: '',
        nid_number: '',
        vat_reg_no: '',
        tin_no: '',
        trade_license: '',
        discount_rate: 0,
        security_deposit: 0,
        credit_limit: 0,
        previous_due: 0,
        address: '',
        status: true,
        // Vehicle fields
        product_ids: [] as string[],
        vehicle_type: '',
        vehicle_name: '',
        vehicle_number: '',
        reg_date: ''
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingCustomer) {
            put(`/customers/${editingCustomer.id}`, {
                onSuccess: () => {
                    setEditingCustomer(null);
                    reset();
                },
            });
        } else {
            post('/customers', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (customer: Customer) => {
        setEditingCustomer(customer);
        setData({
            name: customer.name,

            mobile: customer.mobile || '',
            email: customer.email || '',
            nid_number: customer.nid_number || '',
            vat_reg_no: customer.vat_reg_no || '',
            tin_no: customer.tin_no || '',
            trade_license: customer.trade_license || '',
            discount_rate: customer.discount_rate || 0,
            security_deposit: customer.security_deposit || 0,
            credit_limit: customer.credit_limit || 0,
            address: customer.address || '',
            status: customer.status
        });
    };

    const handleDelete = (customer: Customer) => {
        setDeletingCustomer(customer);
    };

    const confirmDelete = () => {
        if (deletingCustomer) {
            router.delete(`/customers/${deletingCustomer.id}`, {
                onSuccess: () => setDeletingCustomer(null),
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/customers/bulk/delete', {
            data: { ids: selectedCustomers },
            onSuccess: () => {
                setSelectedCustomers([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const toggleSelectAll = () => {
        if (selectedCustomers.length === customers.data.length) {
            setSelectedCustomers([]);
        } else {
            setSelectedCustomers(customers.data.map((customer) => customer.id));
        }
    };

    const toggleSelectCustomer = (customerId: number) => {
        if (selectedCustomers.includes(customerId)) {
            setSelectedCustomers(selectedCustomers.filter((id) => id !== customerId));
        } else {
            setSelectedCustomers([...selectedCustomers, customerId]);
        }
    };

    const applyFilters = () => {
        router.get(
            '/customers',
            {
                search: search || undefined,
                status: status === 'all' ? undefined : status,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('all');
        router.get(
            '/customers',
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
            '/customers',
            {
                search: search || undefined,
                status: status === 'all' ? undefined : status,
                sort_by: column,
                sort_order: newOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/customers',
            {
                search: search || undefined,
                status: status === 'all' ? undefined : status,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Customers" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Customers</h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage customer information
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedCustomers.length > 0 && can('delete-customer') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedCustomers.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (status !== 'all') params.append('status', status);
                                    if (sortBy) params.append('sort_by', sortBy);
                                    if (sortOrder) params.append('sort_order', sortOrder);
                                    window.location.href = `/customers/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        {can('create-customer') && (
                            <Button onClick={() => {
                                setIsCreateOpen(true);

                            }}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Customer
                            </Button>
                        )}
                    </div>
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
                                <Label className="dark:text-gray-200">Search</Label>
                                <Input
                                    placeholder="Search customers..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Status</Label>
                                <Select
                                    value={status}
                                    onValueChange={(value) => {
                                        setStatus(value);
                                        router.get(
                                            '/customers',
                                            {
                                                search: search || undefined,
                                                status: value === 'all' ? undefined : value,
                                                sort_by: sortBy,
                                                sort_order: sortOrder,
                                                per_page: perPage,
                                            },
                                            { preserveState: true },
                                        );
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All status</SelectItem>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
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
                                                checked={
                                                    selectedCustomers.length ===
                                                        customers.data.length &&
                                                    customers.data.length > 0
                                                }
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            SL
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('name')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Name
                                                {sortBy === 'name' &&
                                                    (sortOrder === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ))}
                                            </div>
                                        </th>

                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Account Number
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Mobile
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Total Sale
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Total Payment
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Total
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Status
                                        </th>
                                        {hasActionPermission && (
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Actions
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {customers.data.length > 0 ? (
                                        customers.data.map((customer, index) => (
                                            <tr
                                                key={customer.id}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedCustomers.includes(
                                                            customer.id,
                                                        )}
                                                        onChange={() =>
                                                            toggleSelectCustomer(
                                                                customer.id,
                                                            )
                                                        }
                                                        className="rounded border-gray-300 dark:border-gray-600"
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {(customers.current_page - 1) * customers.per_page + index + 1}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {customer.name}
                                                </td>

                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {customer.account?.ac_number || 'N/A'}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {customer.mobile || 'N/A'}
                                                </td>
                                                <td className="p-4 text-[13px] text-right dark:text-white font-semibold">
                                                    {customer.total_sales?.toLocaleString() || '0'}
                                                </td>
                                                <td className="p-4 text-[13px] text-right dark:text-green-400 font-semibold">
                                                    {customer.total_paid?.toLocaleString() || '0'}
                                                </td>
                                                <td className="p-4 text-[13px] text-right font-semibold">
                                                    <span className={(customer.current_due || 0) > 0 ? 'text-red-600 dark:text-red-400' : (customer.current_due || 0) < 0 ? 'text-green-600 dark:text-green-400' : 'dark:text-white'}>
                                                        {Math.abs(customer.current_due || 0).toLocaleString()}
                                                        {(customer.current_due || 0) > 0 && ' (Due)'}
                                                        {(customer.current_due || 0) < 0 && ' (Adv)'}
                                                    </span>
                                                </td>
                                                <td className="p-4">
                                                    <span className={`px-2 py-1 rounded text-xs ${
                                                        customer.status 
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                    }`}>
                                                        {customer.status ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                {hasActionPermission && (
                                                    <td className="p-4">
                                                        <div className="flex gap-2">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => router.get(`/customers/${customer.id}`)}
                                                                className="text-blue-600 hover:text-blue-800"
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                            {can('update-customer') && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleEdit(customer)}
                                                                    className="text-indigo-600 hover:text-indigo-800"
                                                                >
                                                                    <Edit className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {can('delete-customer') && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleDelete(customer)}
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
                                            <td
                                                colSpan={hasActionPermission ? 10 : 9}
                                                className="p-8 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <Users className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No customers found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={customers.current_page}
                            lastPage={customers.last_page}
                            from={customers.from}
                            to={customers.to}
                            total={customers.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get(
                                    '/customers',
                                    {
                                        search: search || undefined,
                                        status: status === 'all' ? undefined : status,
                                        sort_by: sortBy,
                                        sort_order: sortOrder,
                                        per_page: newPerPage,
                                    },
                                    { preserveState: true },
                                );
                            }}
                        />
                    </CardContent>
                </Card>

                <FormModal
                    isOpen={isCreateOpen}
                    onClose={() => {
                        setIsCreateOpen(false);
                        reset();
                    }}
                    title="Create Customer & Vehicle"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                    wide={true}
                >
                    <div className="grid grid-cols-2 gap-8">
                        {/* Customer Information */}
                        <div className="space-y-4">
                            <h3 className="text-lg font-medium dark:text-white border-b pb-2">Customer Information</h3>
                            
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="name" className="dark:text-gray-200">Name *</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                    {errors.name && <span className="text-sm text-red-500">{errors.name}</span>}
                                </div>
                                <div>
                                    <Label htmlFor="email" className="dark:text-gray-200">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                            </div>
                            
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="mobile" className="dark:text-gray-200">Mobile</Label>
                                    <Input
                                        id="mobile"
                                        value={data.mobile}
                                        onChange={(e) => setData('mobile', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="address" className="dark:text-gray-200">Address</Label>
                                    <Input
                                        id="address"
                                        value={data.address}
                                        onChange={(e) => setData('address', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                            </div>
                            
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="previous_due" className="dark:text-gray-200">Previous Due</Label>
                                    <Input
                                        id="previous_due"
                                        type="number"
                                        step="0.01"
                                        value={data.previous_due}
                                        onChange={(e) => setData('previous_due', parseFloat(e.target.value) || 0)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="security_deposit" className="dark:text-gray-200">Security Deposit</Label>
                                    <Input
                                        id="security_deposit"
                                        type="number"
                                        step="0.01"
                                        value={data.security_deposit}
                                        onChange={(e) => setData('security_deposit', parseFloat(e.target.value) || 0)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                            </div>
                            
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="nid_number" className="dark:text-gray-200">NID Card</Label>
                                    <Input
                                        id="nid_number"
                                        value={data.nid_number}
                                        onChange={(e) => setData('nid_number', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label className="dark:text-gray-200">Status</Label>
                                    <Select
                                        value={data.status ? 'true' : 'false'}
                                        onValueChange={(value) => setData('status', value === 'true')}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="Select Status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="true">Active</SelectItem>
                                            <SelectItem value="false">Inactive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        {/* Vehicle Information */}
                        <div className="space-y-4">
                            <h3 className="text-lg font-medium dark:text-white border-b pb-2">Vehicle Information</h3>
                            
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="vehicle_type" className="dark:text-gray-200">Vehicle Type</Label>
                                    <Input
                                        id="vehicle_type"
                                        value={data.vehicle_type}
                                        onChange={(e) => setData('vehicle_type', e.target.value)}
                                        placeholder="e.g., Car, Truck"
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="vehicle_name" className="dark:text-gray-200">Vehicle Name</Label>
                                    <Input
                                        id="vehicle_name"
                                        value={data.vehicle_name}
                                        onChange={(e) => setData('vehicle_name', e.target.value)}
                                        placeholder="e.g., Toyota Corolla"
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                            </div>
                            
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="vehicle_number" className="dark:text-gray-200">Vehicle Number</Label>
                                    <Input
                                        id="vehicle_number"
                                        value={data.vehicle_number}
                                        onChange={(e) => setData('vehicle_number', e.target.value)}
                                        placeholder="e.g., ABC-1234"
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="reg_date" className="dark:text-gray-200">Registration Date</Label>
                                    <Input
                                        id="reg_date"
                                        type="date"
                                        value={data.reg_date}
                                        onChange={(e) => setData('reg_date', e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                            </div>
                            
                            <div>
                                <Label className="dark:text-gray-200">Products</Label>
                                <div className="grid grid-cols-3 gap-2 max-h-32 overflow-y-auto border rounded p-2 dark:border-gray-600 dark:bg-gray-700">
                                    {products.map((product) => (
                                        <label key={product.id} className="flex items-center space-x-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={data.product_ids?.includes(product.id.toString()) || false}
                                                onChange={(e) => {
                                                    if (e.target.checked) {
                                                        setData('product_ids', [...(data.product_ids || []), product.id.toString()]);
                                                    } else {
                                                        setData('product_ids', (data.product_ids || []).filter(id => id !== product.id.toString()));
                                                    }
                                                }}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                            <span className="text-sm dark:text-white">{product.name}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingCustomer}
                    onClose={() => {
                        setEditingCustomer(null);
                        reset();
                    }}
                    title="Edit Customer"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                >
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="edit-name" className="dark:text-gray-200">Name *</Label>
                            <Input
                                id="edit-name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.name && <span className="text-sm text-red-500">{errors.name}</span>}
                        </div>
                        <div>
                            <Label htmlFor="edit-email" className="dark:text-gray-200">Email</Label>
                            <Input
                                id="edit-email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="edit-mobile" className="dark:text-gray-200">Mobile</Label>
                            <Input
                                id="edit-mobile"
                                value={data.mobile}
                                onChange={(e) => setData('mobile', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-address" className="dark:text-gray-200">Address</Label>
                            <Input
                                id="edit-address"
                                value={data.address}
                                onChange={(e) => setData('address', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="edit-previous_due" className="dark:text-gray-200">Previous Due</Label>
                            <Input
                                id="edit-previous_due"
                                type="number"
                                step="0.01"
                                value={data.previous_due}
                                onChange={(e) => setData('previous_due', parseFloat(e.target.value) || 0)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-security_deposit" className="dark:text-gray-200">Security Deposit</Label>
                            <Input
                                id="edit-security_deposit"
                                type="number"
                                step="0.01"
                                value={data.security_deposit}
                                onChange={(e) => setData('security_deposit', parseFloat(e.target.value) || 0)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="edit-nid_number" className="dark:text-gray-200">NID Card</Label>
                            <Input
                                id="edit-nid_number"
                                value={data.nid_number}
                                onChange={(e) => setData('nid_number', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <Label className="dark:text-gray-200">Status</Label>
                            <Select
                                value={data.status ? 'true' : 'false'}
                                onValueChange={(value) => setData('status', value === 'true')}
                            >
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
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

                <DeleteModal
                    isOpen={!!deletingCustomer}
                    onClose={() => setDeletingCustomer(null)}
                    onConfirm={confirmDelete}
                    title="Delete Customer"
                    message={`Are you sure you want to delete the customer "${deletingCustomer?.name}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Customers"
                    message={`Are you sure you want to delete ${selectedCustomers.length} selected customers? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}