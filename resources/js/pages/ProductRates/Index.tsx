import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pagination } from '@/components/ui/pagination';
import { FormModal } from '@/components/ui/form-modal';
import { DeleteModal } from '@/components/ui/delete-modal';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import { Plus, Edit, Trash2, DollarSign, ChevronUp, ChevronDown, Filter, X, FileText } from 'lucide-react';
import { useState, useEffect } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface Product {
    id: number;
    product_name: string;
}

interface ProductRate {
    id: number;
    product_id: number;
    product: string;
    purchase_price: number;
    sales_price: number;
    effective_date: string;
    status: number;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Product Rates',
        href: '/product-rates',
    },
];

interface ProductRatesProps {
    rates: {
        data: ProductRate[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    products: Product[];
    filters: {
        search?: string;
        status?: string;
        product_id?: number;
        start_date?: string;
        end_date?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function Index({ rates, products, filters }: ProductRatesProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-product-rate') || can('delete-product-rate');
    const canFilter = can('can-product-rate-filter');
    const canDownload = can('can-product-rate-download');
    
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingRate, setEditingRate] = useState<ProductRate | null>(null);
    const [deletingRate, setDeletingRate] = useState<ProductRate | null>(null);
    const [selectedRates, setSelectedRates] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [productId, setProductId] = useState(filters?.product_id?.toString() || '');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'effective_date');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);
    
    const { data, setData, post, put, processing, errors, reset } = useForm({
        product_id: '',
        purchase_price: '',
        sales_price: '',
        effective_date: '',
        status: 1
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingRate) {
            put(`/product-rates/${editingRate.id}`, {
                onSuccess: () => {
                    setEditingRate(null);
                    reset();
                }
            });
        } else {
            post('/product-rates', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                }
            });
        }
    };

    const handleEdit = (rate: ProductRate) => {
        setEditingRate(rate);
        setData({
            product_id: rate.product_id.toString(),
            purchase_price: rate.purchase_price ? rate.purchase_price.toString() : '',
            sales_price: rate.sales_price ? rate.sales_price.toString() : '',
            effective_date: rate.effective_date,
            status: rate.status ? 1 : 0
        });
    };

    const handleDelete = (rate: ProductRate) => {
        setDeletingRate(rate);
    };

    const confirmDelete = () => {
        if (deletingRate) {
            router.delete(`/product-rates/${deletingRate.id}`, {
                onSuccess: () => setDeletingRate(null)
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.post('/product-rates/bulk-delete', {
            ids: selectedRates
        }, {
            onSuccess: () => {
                setSelectedRates([]);
                setIsBulkDeleting(false);
            }
        });
    };

    const applyFilters = () => {
        router.get('/product-rates', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
            product_id: productId || undefined,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('all');
        setProductId('');
        setStartDate('');
        setEndDate('');
        router.get('/product-rates', {
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handleSort = (column: string) => {
        const newOrder = sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(newOrder);
        router.get('/product-rates', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
            product_id: productId || undefined,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: column,
            sort_order: newOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handlePageChange = (page: number) => {
        router.get('/product-rates', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
            product_id: productId || undefined,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
            page,
        }, { preserveState: true });
    };

    const toggleSelectAll = () => {
        if (selectedRates.length === rates.data.length) {
            setSelectedRates([]);
        } else {
            setSelectedRates(rates.data.map(rate => rate.id));
        }
    };

    const toggleSelectRate = (rateId: number) => {
        if (selectedRates.includes(rateId)) {
            setSelectedRates(selectedRates.filter(id => id !== rateId));
        } else {
            setSelectedRates([...selectedRates, rateId]);
        }
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
            <Head title="Product Rates" />
            
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Product Rates
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage product pricing history
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedRates.length > 0 && can('delete-product-rate') && (
                            <Button 
                                variant="destructive" 
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedRates.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (status !== 'all') params.append('status', status);
                                    if (productId) params.append('product_id', productId);
                                    if (startDate) params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    if (sortBy) params.append('sort_by', sortBy);
                                    if (sortOrder) params.append('sort_order', sortOrder);
                                    window.location.href = `/product-rates/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        {can('create-product-rate') && (
                            <Button
                                onClick={() => setIsCreateOpen(true)}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add Rate
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
                                <Label className="dark:text-gray-200">
                                    Search
                                </Label>
                                <Input
                                    placeholder="Search products..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Status
                                </Label>
                                <Select value={status} onValueChange={setStatus}>
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All status</SelectItem>
                                        <SelectItem value="1">Active</SelectItem>
                                        <SelectItem value="0">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Product
                                </Label>
                                <Select value={productId} onValueChange={setProductId}>
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All products" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {products.map((product) => (
                                            <SelectItem key={product.id} value={product.id.toString()}>
                                                {product.product_name}
                                            </SelectItem>
                                        ))}
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
                )}

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-4 text-left font-medium dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={selectedRates.length === rates.data.length && rates.data.length > 0}
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Product</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Purchase Price</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Sales Price</th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('effective_date')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Effective Date
                                                {sortBy === 'effective_date' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Status</th>
                                        {hasActionPermission && <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rates.data.length > 0 ? rates.data.map((rate) => (
                                        <tr
                                            key={rate.id}
                                            className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                        >
                                            <td className="p-4">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedRates.includes(rate.id)}
                                                    onChange={() => toggleSelectRate(rate.id)}
                                                    className="rounded border-gray-300 dark:border-gray-600"
                                                />
                                            </td>
                                            <td className="p-4 text-[13px] dark:text-white">{rate.product}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{rate.purchase_price || '-'}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{rate.sales_price || '-'}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{rate.effective_date}</td>
                                            <td className="p-4">
                                                <span className={`px-2 py-1 rounded text-xs ${
                                                    rate.status === 1 
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                                        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                }`}>
                                                    {rate.status === 1 ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            {hasActionPermission && (
                                            <td className="p-4">
                                                <div className="flex gap-2">
                                                    {can('update-product-rate') && (
                                                    <Button 
                                                        variant="ghost" 
                                                        size="sm"
                                                        onClick={() => handleEdit(rate)}
                                                        className="text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    )}
                                                    {can('delete-product-rate') && (
                                                    <Button 
                                                        variant="ghost" 
                                                        size="sm"
                                                        onClick={() => handleDelete(rate)}
                                                        className="text-red-600 hover:text-red-800"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                    )}
                                                </div>
                                            </td>
                                            )}
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td
                                                colSpan={hasActionPermission ? 7 : 6}
                                                className="p-8 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <DollarSign className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No product rates found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        
                        <Pagination
                            currentPage={rates.current_page}
                            lastPage={rates.last_page}
                            from={rates.from}
                            to={rates.to}
                            total={rates.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get('/product-rates', {
                                    search: search || undefined,
                                    status: status === 'all' ? undefined : status,
                                    product_id: productId || undefined,
                                    start_date: startDate || undefined,
                                    end_date: endDate || undefined,
                                    sort_by: sortBy,
                                    sort_order: sortOrder,
                                    per_page: newPerPage,
                                }, { preserveState: true });
                            }}
                        />
                    </CardContent>
                </Card>

                <FormModal
                    isOpen={isCreateOpen}
                    onClose={() => setIsCreateOpen(false)}
                    title="Create Product Rate"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                >
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="product_id" className="dark:text-gray-200">Product *</Label>
                            <Select value={data.product_id} onValueChange={(value) => setData('product_id', value)}>
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select product" />
                                </SelectTrigger>
                                <SelectContent>
                                    {products.map((product) => (
                                        <SelectItem key={product.id} value={product.id.toString()}>
                                            {product.product_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.product_id && <span className="text-red-500 text-sm">{errors.product_id}</span>}
                        </div>
                        <div>
                            <Label htmlFor="effective_date" className="dark:text-gray-200">Effective Date *</Label>
                            <Input
                                id="effective_date"
                                type="date"
                                value={data.effective_date}
                                onChange={(e) => setData('effective_date', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.effective_date && <span className="text-red-500 text-sm">{errors.effective_date}</span>}
                        </div>
                        <div>
                            <Label htmlFor="purchase_price" className="dark:text-gray-200">Purchase Price</Label>
                            <Input
                                id="purchase_price"
                                type="number"
                                step="0.01"
                                value={data.purchase_price}
                                onChange={(e) => setData('purchase_price', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                placeholder="0.00"
                            />
                            {errors.purchase_price && <span className="text-red-500 text-sm">{errors.purchase_price}</span>}
                        </div>
                        <div>
                            <Label htmlFor="sales_price" className="dark:text-gray-200">Sales Price</Label>
                            <Input
                                id="sales_price"
                                type="number"
                                step="0.01"
                                value={data.sales_price}
                                onChange={(e) => setData('sales_price', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                placeholder="0.00"
                            />
                            {errors.sales_price && <span className="text-red-500 text-sm">{errors.sales_price}</span>}
                        </div>
                        <div>
                            <Label htmlFor="status" className="dark:text-gray-200">Status</Label>
                            <Select value={data.status.toString()} onValueChange={(value) => setData('status', parseInt(value))}>
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div></div>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingRate}
                    onClose={() => setEditingRate(null)}
                    title="Edit Product Rate"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                >
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="edit-product_id" className="dark:text-gray-200">Product *</Label>
                            <Select value={data.product_id} onValueChange={(value) => setData('product_id', value)}>
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select product" />
                                </SelectTrigger>
                                <SelectContent>
                                    {products.map((product) => (
                                        <SelectItem key={product.id} value={product.id.toString()}>
                                            {product.product_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.product_id && <span className="text-red-500 text-sm">{errors.product_id}</span>}
                        </div>
                        <div>
                            <Label htmlFor="edit-effective_date" className="dark:text-gray-200">Effective Date *</Label>
                            <Input
                                id="edit-effective_date"
                                type="date"
                                value={data.effective_date}
                                onChange={(e) => setData('effective_date', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.effective_date && <span className="text-red-500 text-sm">{errors.effective_date}</span>}
                        </div>
                        <div>
                            <Label htmlFor="edit-purchase_price" className="dark:text-gray-200">Purchase Price</Label>
                            <Input
                                id="edit-purchase_price"
                                type="number"
                                step="0.01"
                                value={data.purchase_price}
                                onChange={(e) => setData('purchase_price', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.purchase_price && <span className="text-red-500 text-sm">{errors.purchase_price}</span>}
                        </div>
                        <div>
                            <Label htmlFor="edit-sales_price" className="dark:text-gray-200">Sales Price</Label>
                            <Input
                                id="edit-sales_price"
                                type="number"
                                step="0.01"
                                value={data.sales_price}
                                onChange={(e) => setData('sales_price', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.sales_price && <span className="text-red-500 text-sm">{errors.sales_price}</span>}
                        </div>
                        <div>
                            <Label htmlFor="edit-status" className="dark:text-gray-200">Status</Label>
                            <Select value={data.status.toString()} onValueChange={(value) => setData('status', parseInt(value))}>
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div></div>
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingRate}
                    onClose={() => setDeletingRate(null)}
                    onConfirm={confirmDelete}
                    title="Delete Product Rate"
                    message={`Are you sure you want to delete this product rate? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Product Rates"
                    message={`Are you sure you want to delete ${selectedRates.length} selected product rates? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}