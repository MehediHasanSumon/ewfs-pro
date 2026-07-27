import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Pagination } from '@/components/ui/pagination';
import { FormModal } from '@/components/ui/form-modal';
import { DeleteModal } from '@/components/ui/delete-modal';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import { Plus, Edit, Trash2, Package, ChevronUp, ChevronDown, Filter, X, FileText } from 'lucide-react';
import { useState, useEffect } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface Product {
    id: number;
    category_id?: number;
    unit_id?: number;
    product_code: string;
    product_name: string;
    product_slug?: string;
    country_Of_origin?: string;
    category: string;
    unit: string;
    purchase_price?: number;
    sales_price?: number;
    remarks?: string;
    status: number;
    created_at: string;
}

interface Category {
    id: number;
    name: string;
}

interface Unit {
    id: number;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Products',
        href: '/products',
    },
];

interface ProductsProps {
    products: {
        data: Product[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    categories: Category[];
    units: Unit[];
    filters: {
        search?: string;
        status?: string;
        category_id?: string;
        unit_id?: string;
        start_date?: string;
        end_date?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function Products({ products, categories, units, filters }: ProductsProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-product') || can('delete-product');
    const canFilter = can('can-product-filter');
    const canDownload = can('can-product-download');
    
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState<Product | null>(null);
    const [deletingProduct, setDeletingProduct] = useState<Product | null>(null);
    const [selectedProducts, setSelectedProducts] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [categoryId, setCategoryId] = useState(filters?.category_id || '');
    const [unitId, setUnitId] = useState(filters?.unit_id || '');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'product_name');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'asc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);
    
    const { data, setData, post, put, processing, errors, reset } = useForm({
        category_id: '',
        unit_id: '',
        product_code: '',
        product_name: '',
        product_slug: '',
        country_Of_origin: '',

        remarks: '',
        status: 1
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingProduct) {
            put(`/products/${editingProduct.id}`, {
                onSuccess: () => {
                    setEditingProduct(null);
                    reset();
                }
            });
        } else {
            post('/products', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                }
            });
        }
    };

    const handleEdit = (product: Product) => {
        setEditingProduct(product);
        setData({
            category_id: product.category_id ? product.category_id.toString() : '',
            unit_id: product.unit_id ? product.unit_id.toString() : '',
            product_code: product.product_code || '',
            product_name: product.product_name || '',
            product_slug: product.product_slug || '',
            country_Of_origin: product.country_Of_origin || '',

            remarks: product.remarks || '',
            status: product.status || 1
        });
    };

    const handleDelete = (product: Product) => {
        setDeletingProduct(product);
    };

    const confirmDelete = () => {
        if (deletingProduct) {
            router.delete(`/products/${deletingProduct.id}`, {
                onSuccess: () => setDeletingProduct(null)
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/products/bulk/delete', {
            data: { ids: selectedProducts },
            onSuccess: () => {
                setSelectedProducts([]);
                setIsBulkDeleting(false);
            }
        });
    };

    const applyFilters = () => {
        router.get('/products', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
            category_id: categoryId || undefined,
            unit_id: unitId || undefined,
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
        setCategoryId('');
        setUnitId('');
        setStartDate('');
        setEndDate('');
        router.get('/products', {
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handleSort = (column: string) => {
        const newOrder = sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(newOrder);
        router.get('/products', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
            category_id: categoryId || undefined,
            unit_id: unitId || undefined,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: column,
            sort_order: newOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handlePageChange = (page: number) => {
        router.get('/products', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
            category_id: categoryId || undefined,
            unit_id: unitId || undefined,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
            page,
        }, { preserveState: true });
    };

    const toggleSelectAll = () => {
        if (selectedProducts.length === products.data.length) {
            setSelectedProducts([]);
        } else {
            setSelectedProducts(products.data.map(product => product.id));
        }
    };

    const toggleSelectProduct = (productId: number) => {
        if (selectedProducts.includes(productId)) {
            setSelectedProducts(selectedProducts.filter(id => id !== productId));
        } else {
            setSelectedProducts([...selectedProducts, productId]);
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
            <Head title="Products" />
            
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Products
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage product inventory
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedProducts.length > 0 && can('delete-product') && (
                            <Button 
                                variant="destructive" 
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedProducts.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (status !== 'all') params.append('status', status);
                                    if (categoryId) params.append('category_id', categoryId);
                                    if (unitId) params.append('unit_id', unitId);
                                    if (startDate) params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    if (sortBy) params.append('sort_by', sortBy);
                                    if (sortOrder) params.append('sort_order', sortOrder);
                                    window.location.href = `/products/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        {can('create-product') && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Product
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
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-7">
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
                                    Category
                                </Label>
                                <Select value={categoryId} onValueChange={setCategoryId}>
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All categories" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem key={category.id} value={category.id.toString()}>
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Unit
                                </Label>
                                <Select value={unitId} onValueChange={setUnitId}>
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All units" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {units.map((unit) => (
                                            <SelectItem key={unit.id} value={unit.id.toString()}>
                                                {unit.name}
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
                                                checked={selectedProducts.length === products.data.length && products.data.length > 0}
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Code</th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('product_name')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Product Name
                                                {sortBy === 'product_name' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Category</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Unit</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Purchase Price</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Sales Price</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Status</th>
                                        {hasActionPermission && <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {products.data.length > 0 ? products.data.map((product) => (
                                        <tr
                                            key={product.id}
                                            className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                        >
                                            <td className="p-4">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedProducts.includes(product.id)}
                                                    onChange={() => toggleSelectProduct(product.id)}
                                                    className="rounded border-gray-300 dark:border-gray-600"
                                                />
                                            </td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{product.product_code || '-'}</td>
                                            <td className="p-4 text-[13px] dark:text-white">{product.product_name}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{product.category || '-'}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{product.unit || '-'}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{product.purchase_price || '-'}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{product.sales_price || '-'}</td>
                                            <td className="p-4">
                                                <span className={`px-2 py-1 rounded text-xs ${
                                                    product.status === 1 
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                                        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                }`}>
                                                    {product.status === 1 ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            {hasActionPermission && (
                                            <td className="p-4">
                                                <div className="flex gap-2">
                                                    {can('update-product') && (
                                                    <Button 
                                                        variant="ghost" 
                                                        size="sm"
                                                        onClick={() => handleEdit(product)}
                                                        className="text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    )}
                                                    {can('delete-product') && (
                                                    <Button 
                                                        variant="ghost" 
                                                        size="sm"
                                                        onClick={() => handleDelete(product)}
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
                                                colSpan={hasActionPermission ? 9 : 8}
                                                className="p-8 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <Package className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No products found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        
                        <Pagination
                            currentPage={products.current_page}
                            lastPage={products.last_page}
                            from={products.from}
                            to={products.to}
                            total={products.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get('/products', {
                                    search: search || undefined,
                                    status: status === 'all' ? undefined : status,
                                    category_id: categoryId || undefined,
                                    unit_id: unitId || undefined,
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
                    title="Create Product"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                >
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="category_id" className="dark:text-gray-200">Category *</Label>
                            <Select value={data.category_id} onValueChange={(value) => setData('category_id', value)}>
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((category) => (
                                        <SelectItem key={category.id} value={category.id.toString()}>
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.category_id && <span className="text-red-500 text-sm">{errors.category_id}</span>}
                        </div>
                        <div>
                            <Label htmlFor="unit_id" className="dark:text-gray-200">Unit *</Label>
                            <Select value={data.unit_id} onValueChange={(value) => setData('unit_id', value)}>
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select unit" />
                                </SelectTrigger>
                                <SelectContent>
                                    {units.map((unit) => (
                                        <SelectItem key={unit.id} value={unit.id.toString()}>
                                            {unit.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.unit_id && <span className="text-red-500 text-sm">{errors.unit_id}</span>}
                        </div>
                        <div>
                            <Label htmlFor="product_code" className="dark:text-gray-200">Product Code</Label>
                            <Input
                                id="product_code"
                                value={data.product_code}
                                onChange={(e) => setData('product_code', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                placeholder="e.g., PRD001"
                            />
                            {errors.product_code && <span className="text-red-500 text-sm">{errors.product_code}</span>}
                        </div>
                        <div>
                            <Label htmlFor="product_name" className="dark:text-gray-200">Product Name *</Label>
                            <Input
                                id="product_name"
                                value={data.product_name}
                                onChange={(e) => setData('product_name', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                placeholder="e.g., Laptop"
                            />
                            {errors.product_name && <span className="text-red-500 text-sm">{errors.product_name}</span>}
                        </div>

                        <div>
                            <Label htmlFor="country_Of_origin" className="dark:text-gray-200">Country of Origin</Label>
                            <Input
                                id="country_Of_origin"
                                value={data.country_Of_origin}
                                onChange={(e) => setData('country_Of_origin', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                placeholder="e.g., Bangladesh"
                            />
                            {errors.country_Of_origin && <span className="text-red-500 text-sm">{errors.country_Of_origin}</span>}
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
                    </div>
                    <div className="col-span-2">
                        <Label htmlFor="remarks" className="dark:text-gray-200">Remarks</Label>
                        <Textarea
                            id="remarks"
                            value={data.remarks}
                            onChange={(e) => setData('remarks', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            placeholder="Additional notes..."
                            rows={3}
                        />
                        {errors.remarks && <span className="text-red-500 text-sm">{errors.remarks}</span>}
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingProduct}
                    onClose={() => setEditingProduct(null)}
                    title="Edit Product"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                >
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="edit-category_id" className="dark:text-gray-200">Category *</Label>
                            <Select value={data.category_id} onValueChange={(value) => setData('category_id', value)}>
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((category) => (
                                        <SelectItem key={category.id} value={category.id.toString()}>
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.category_id && <span className="text-red-500 text-sm">{errors.category_id}</span>}
                        </div>
                        <div>
                            <Label htmlFor="edit-unit_id" className="dark:text-gray-200">Unit *</Label>
                            <Select value={data.unit_id} onValueChange={(value) => setData('unit_id', value)}>
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select unit" />
                                </SelectTrigger>
                                <SelectContent>
                                    {units.map((unit) => (
                                        <SelectItem key={unit.id} value={unit.id.toString()}>
                                            {unit.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.unit_id && <span className="text-red-500 text-sm">{errors.unit_id}</span>}
                        </div>
                        <div>
                            <Label htmlFor="edit-product_code" className="dark:text-gray-200">Product Code</Label>
                            <Input
                                id="edit-product_code"
                                value={data.product_code}
                                onChange={(e) => setData('product_code', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.product_code && <span className="text-red-500 text-sm">{errors.product_code}</span>}
                        </div>
                        <div>
                            <Label htmlFor="edit-product_name" className="dark:text-gray-200">Product Name *</Label>
                            <Input
                                id="edit-product_name"
                                value={data.product_name}
                                onChange={(e) => setData('product_name', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.product_name && <span className="text-red-500 text-sm">{errors.product_name}</span>}
                        </div>

                        <div>
                            <Label htmlFor="edit-country_Of_origin" className="dark:text-gray-200">Country of Origin</Label>
                            <Input
                                id="edit-country_Of_origin"
                                value={data.country_Of_origin}
                                onChange={(e) => setData('country_Of_origin', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.country_Of_origin && <span className="text-red-500 text-sm">{errors.country_Of_origin}</span>}
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
                    </div>
                    <div className="col-span-2">
                        <Label htmlFor="edit-remarks" className="dark:text-gray-200">Remarks</Label>
                        <Textarea
                            id="edit-remarks"
                            value={data.remarks}
                            onChange={(e) => setData('remarks', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            rows={3}
                        />
                        {errors.remarks && <span className="text-red-500 text-sm">{errors.remarks}</span>}
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingProduct}
                    onClose={() => setDeletingProduct(null)}
                    onConfirm={confirmDelete}
                    title="Delete Product"
                    message={`Are you sure you want to delete the product "${deletingProduct?.product_name}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Products"
                    message={`Are you sure you want to delete ${selectedProducts.length} selected products? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}