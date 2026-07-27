import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteModal } from '@/components/ui/delete-modal';
import { FormModal } from '@/components/ui/form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { SearchableSelect } from '@/components/ui/searchable-select';
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

interface WhiteSale {
    id: number;
    sale_date: string;
    sale_time: string;
    invoice_no: string;
    mobile_no?: string;
    company_name?: string;
    proprietor_name?: string;
    shift: { id: number; name: string };
    products: {
        id: number;
        product: { product_name: string };
        category: { name: string };
        unit: { name: string };
        quantity: number;
        sales_price: number;
        amount: number;
    }[];
    total_amount: number;
    remarks: string;
    status: number;
    created_at: string;
}

interface Product {
    id: number;
    product_name: string;
    product_code: string;
    unit: { name: string };
    category?: { name: string };
    purchase_price?: number;
    activeRate?: { sales_price: number };
    rates?: { sales_price: number }[];
}

interface Shift {
    id: number;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'White Sales',
        href: '/white-sales',
    },
];

interface WhiteSalesProps {
    whiteSales: {
        data: WhiteSale[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    products: Product[];
    shifts: Shift[];
    filters: {
        search?: string;
        start_date?: string;
        end_date?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function WhiteSales({ whiteSales, products = [], shifts = [], filters = {} }: WhiteSalesProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-white-sale') || can('delete-white-sale');
    const canFilter = can('can-white-sale-filter');
    const canDownload = can('can-white-sale-download');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingWhiteSale, setEditingWhiteSale] = useState<WhiteSale | null>(null);
    const [deletingWhiteSale, setDeletingWhiteSale] = useState<WhiteSale | null>(null);
    const [selectedWhiteSales, setSelectedWhiteSales] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters.search || '');
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');
    const [sortBy, setSortBy] = useState(filters.sort_by || 'created_at');
    const [sortOrder, setSortOrder] = useState(filters.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters.per_page || 10);

    const [data, setDataState] = useState({
        shift_id: '',
        mobile_no: '',
        company_name: '',
        proprietor_name: '',
        products: [
            {
                product: '',
                category: '',
                purchase_price: '',
                unit: '',
                quantity: '',
                amount: '',
            }
        ],
        total_amount: '',
        remarks: '',
        status: 1,
    });

    type DataType = typeof data;

    const setData = (key: string | DataType, value?: string | number | DataType['products']) => {
        if (typeof key === 'string') {
            setDataState(prev => ({ ...prev, [key]: value }));
        } else {
            setDataState(key);
        }
    };

    const reset = () => {
        setDataState({
            shift_id: '',
            mobile_no: '',
            company_name: '',
            proprietor_name: '',
            products: [
                {
                    product: '',
                    category: '',
                    purchase_price: '',
                    unit: '',
                    quantity: '',
                    amount: '',
                }
            ],
            total_amount: '',
            remarks: '',
            status: 1,
        });
    };

    const [processing, setProcessing] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const validProducts = data.products.filter(p => p.product && p.quantity !== '' && p.amount !== '');
        if (validProducts.length === 0) {
            alert('Please add at least one product to cart');
            return;
        }

        if (!data.mobile_no || !data.company_name) {
            alert('Please fill all required fields');
            return;
        }

        const submitData = {
            ...data,
            products: validProducts,
            total_amount: validProducts.reduce((sum, p) => sum + parseFloat(p.amount || '0'), 0)
        };

        if (editingWhiteSale) {
            router.put(`/white-sales/${editingWhiteSale.id}`, submitData, {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    setEditingWhiteSale(null);
                    reset();
                },
            });
        } else {
            setProcessing(true);
            router.post('/white-sales', submitData, {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                    setProcessing(false);
                },
                onError: () => {
                    setProcessing(false);
                },
            });
        }
    };

    const handleEdit = (whiteSale: WhiteSale) => {
        setEditingWhiteSale(whiteSale);
        setData({
            shift_id: whiteSale.shift?.id?.toString() || '',
            mobile_no: whiteSale.mobile_no || '',
            company_name: whiteSale.company_name || '',
            proprietor_name: whiteSale.proprietor_name || '',
            products: [
                {
                    product: '',
                    category: '',
                    purchase_price: '',
                    unit: '',
                    quantity: '',
                    amount: '',
                },
                ...whiteSale.products.map(p => ({
                    product: p.product?.product_name || '',
                    category: p.category?.name || '',
                    purchase_price: p.sales_price?.toString() || '',
                    unit: p.unit?.name || '',
                    quantity: p.quantity?.toString() || '',
                    amount: p.amount?.toString() || '',
                }))
            ],
            total_amount: whiteSale.total_amount.toString(),
            remarks: whiteSale.remarks || '',
            status: whiteSale.status,
        });
        setIsCreateOpen(true);
    };

    const handleDelete = (whiteSale: WhiteSale) => {
        setDeletingWhiteSale(whiteSale);
    };

    const confirmDelete = () => {
        if (deletingWhiteSale) {
            router.delete(`/white-sales/${deletingWhiteSale.id}`, {
                onSuccess: () => setDeletingWhiteSale(null),
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/white-sales/bulk/delete', {
            data: { ids: selectedWhiteSales },
            onSuccess: () => {
                setSelectedWhiteSales([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const applyFilters = () => {
        router.get(
            '/white-sales',
            {
                search: search || undefined,
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
        setStartDate('');
        setEndDate('');
        router.get(
            '/white-sales',
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
            '/white-sales',
            {
                search: search || undefined,
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
            '/white-sales',
            {
                search: search || undefined,
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
        if (selectedWhiteSales.length === whiteSales.data.length) {
            setSelectedWhiteSales([]);
        } else {
            setSelectedWhiteSales(whiteSales.data.map((sale) => sale.id));
        }
    };

    const toggleSelectWhiteSale = (saleId: number) => {
        if (selectedWhiteSales.includes(saleId)) {
            setSelectedWhiteSales(selectedWhiteSales.filter((id) => id !== saleId));
        } else {
            setSelectedWhiteSales([...selectedWhiteSales, saleId]);
        }
    };

    const addProduct = () => {
        const firstProduct = data.products[0];
        if (!firstProduct.product || firstProduct.quantity === '' || firstProduct.purchase_price === '') {
            alert('Please fill all required fields');
            return;
        }

        const newProducts = [
            {
                product: '',
                category: '',
                purchase_price: '',
                unit: '',
                quantity: '',
                amount: '',
            },
            ...data.products
        ];

        setData({
            ...data,
            products: newProducts
        });
    };

    const removeProduct = (index: number) => {
        const newProducts = data.products.filter((_, i) => i !== index);
        setData('products', newProducts);
    };

    const updateProduct = (index: number, field: string, value: string) => {
        setDataState((prevData) => {
            const newProducts = [...prevData.products];
            newProducts[index] = { ...newProducts[index], [field]: value };

            // Auto-fill when product is selected
            if (field === 'product') {
                const selectedProduct = products.find(p => p.product_name === value);
                if (selectedProduct) {
                    newProducts[index].category = selectedProduct.category?.name || '';
                    newProducts[index].unit = selectedProduct.unit?.name || '';
                    newProducts[index].purchase_price = selectedProduct.activeRate?.sales_price?.toString() || selectedProduct.rates?.[0]?.sales_price?.toString() || selectedProduct.purchase_price?.toString() || '';
                }
            }

            if (field === 'quantity' || field === 'purchase_price') {
                const quantity = parseFloat(newProducts[index].quantity) || 0;
                const price = parseFloat(newProducts[index].purchase_price) || 0;
                newProducts[index].amount = (quantity * price).toFixed(2);
            }

            return {
                ...prevData,
                products: newProducts
            };
        });
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
            <Head title="White Sales" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            White Sales
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage white sales and product transactions
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedWhiteSales.length > 0 && can('delete-white-sale') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedWhiteSales.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (startDate) params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    if (sortBy) params.append('sort_by', sortBy);
                                    if (sortOrder) params.append('sort_order', sortOrder);
                                    window.location.href = `/white-sales/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        {can('create-white-sale') && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add White Sale
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
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <div>
                                    <Label className="dark:text-gray-200">Search</Label>
                                    <Input
                                        placeholder="Search white sales..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
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
                                                checked={selectedWhiteSales.length === whiteSales.data.length && whiteSales.data.length > 0}
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
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Time</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Invoice No</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Shift</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Products</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Total Amount</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Status</th>
                                        {hasActionPermission && <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {whiteSales.data.length > 0 ? (
                                        whiteSales.data.map((sale) => (
                                            <tr key={sale.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedWhiteSales.includes(sale.id)}
                                                        onChange={() => toggleSelectWhiteSale(sale.id)}
                                                        className="rounded border-gray-300 dark:border-gray-600"
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">{new Date(sale.sale_date).toLocaleDateString('en-GB')}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{sale.sale_time}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{sale.invoice_no}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{sale.shift?.name}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{sale.products.length} items</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{sale.total_amount.toLocaleString()}</td>
                                                <td className="p-4">
                                                    <span className={`rounded px-2 py-1 text-xs font-medium ${sale.status === 1
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                        }`}>
                                                        {sale.status === 1 ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                {hasActionPermission && (
                                                    <td className="p-4">
                                                        <div className="flex gap-2">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => window.open(`/white-sales/${sale.id}/pdf`, '_blank')}
                                                                className="text-blue-600 hover:text-blue-800"
                                                                title="Download PDF"
                                                            >
                                                                <FileText className="h-4 w-4" />
                                                            </Button>
                                                            {can('update-white-sale') && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleEdit(sale)}
                                                                    className="text-indigo-600 hover:text-indigo-800"
                                                                >
                                                                    <Edit className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {can('delete-white-sale') && (
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
                                            <td colSpan={hasActionPermission ? 9 : 8} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                <ShoppingCart className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No white sales found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={whiteSales.current_page}
                            lastPage={whiteSales.last_page}
                            from={whiteSales.from}
                            to={whiteSales.to}
                            total={whiteSales.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                applyFilters();
                            }}
                        />
                    </CardContent>
                </Card>

                <FormModal
                    isOpen={isCreateOpen}
                    onClose={() => {
                        setIsCreateOpen(false);
                        setEditingWhiteSale(null);
                        reset();
                    }}
                    title={editingWhiteSale ? "Update White Sale" : "Create White Sale"}
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText={editingWhiteSale ? "Update White Sale" : "Create White Sale"}
                    className="max-w-[65vw]"
                >
                    <div className="space-y-4">
                        <div className="grid grid-cols-4 gap-4">
                            <div>
                                <Label className="text-sm font-medium dark:text-gray-200">
                                    Shift <span className="text-red-500">*</span>
                                </Label>
                                <Select value={data.shift_id} onValueChange={(value) => setData('shift_id', value)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select shift" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {shifts.map((shift) => (
                                            <SelectItem key={shift.id} value={shift.id.toString()}>
                                                {shift.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="text-sm font-medium dark:text-gray-200">
                                    Mobile No <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    value={data.mobile_no}
                                    onChange={(e) => {
                                        const mobile = e.target.value;
                                        setData('mobile_no', mobile);

                                        if (mobile.length >= 11) {
                                            fetch(`/white-sales/customer/${mobile}`)
                                                .then(res => res.json())
                                                .then(customer => {
                                                    if (customer) {
                                                        setData('company_name', customer.company_name || '');
                                                        setData('proprietor_name', customer.proprietor_name || '');
                                                    }
                                                })
                                                .catch(() => { });
                                        }
                                    }}
                                    placeholder="Enter mobile number"
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="text-sm font-medium dark:text-gray-200">
                                    Company Name <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    value={data.company_name}
                                    onChange={(e) => setData('company_name', e.target.value)}
                                    placeholder="Enter company name"
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="text-sm font-medium dark:text-gray-200">Proprietor Name</Label>
                                <Input
                                    value={data.proprietor_name}
                                    onChange={(e) => setData('proprietor_name', e.target.value)}
                                    placeholder="Enter proprietor name"
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-5 gap-4">
                            <div>
                                <Label className="text-sm font-medium dark:text-gray-200">
                                    Product <span className="text-red-500">*</span>
                                </Label>
                                <SearchableSelect
                                    options={products.map(p => ({ value: p.product_name, label: p.product_name, subtitle: p.product_code }))}
                                    value={data.products[0]?.product || ''}
                                    onValueChange={(value) => updateProduct(0, 'product', value)}
                                    placeholder="Select product"
                                    searchPlaceholder="Search products..."
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="text-sm font-medium dark:text-gray-200">
                                    Sales Price <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={data.products[0]?.purchase_price || ''}
                                    onChange={(e) => updateProduct(0, 'purchase_price', e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="text-sm font-medium dark:text-gray-200">Unit</Label>
                                <Input
                                    value={data.products[0]?.unit || ''}
                                    readOnly
                                    className="bg-gray-100 dark:border-gray-600 dark:bg-gray-600 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="text-sm font-medium dark:text-gray-200">
                                    Quantity <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={data.products[0]?.quantity || ''}
                                    onChange={(e) => updateProduct(0, 'quantity', e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="text-sm font-medium dark:text-gray-200">Amount</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={data.products[0]?.amount || ''}
                                    onChange={(e) => updateProduct(0, 'amount', e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-12 gap-4">
                            <div className="col-span-10">
                                <Label className="text-sm font-medium dark:text-gray-200">Remarks</Label>
                                <Input
                                    value={data.remarks}
                                    onChange={(e) => setData('remarks', e.target.value)}
                                    placeholder="Enter any remarks"
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div className="col-span-2 flex flex-col justify-end">
                                <Button
                                    type="button"
                                    onClick={addProduct}
                                    className="bg-blue-600 hover:bg-blue-700"
                                >
                                    <Plus className="h-4 w-4 mr-1" />
                                    Add to Cart
                                </Button>
                            </div>
                        </div>

                        {data.products.slice(1).filter(p => p.product).length > 0 && (
                            <div className="mt-6">
                                <table className="w-full border border-gray-300 dark:border-gray-600">
                                    <thead className="bg-gray-100 dark:bg-gray-700">
                                        <tr>
                                            <th className="p-2 text-left text-sm font-medium dark:text-gray-200">SL</th>
                                            <th className="p-2 text-left text-sm font-medium dark:text-gray-200">Product</th>
                                            <th className="p-2 text-left text-sm font-medium dark:text-gray-200">Sales Price</th>
                                            <th className="p-2 text-left text-sm font-medium dark:text-gray-200">Unit</th>
                                            <th className="p-2 text-left text-sm font-medium dark:text-gray-200">Quantity</th>
                                            <th className="p-2 text-left text-sm font-medium dark:text-gray-200">Amount</th>
                                            <th className="p-2 text-left text-sm font-medium dark:text-gray-200">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.products.slice(1).filter(p => p.product).map((product, index) => {
                                            const actualIndex = index + 1;
                                            return (
                                                <tr key={actualIndex} className="border-t dark:border-gray-600">
                                                    <td className="p-2 text-sm dark:text-white">{index + 1}</td>
                                                    <td className="p-2 text-sm dark:text-white">{product.product}</td>
                                                    <td className="p-2 text-sm dark:text-white">{parseFloat(product.purchase_price).toLocaleString()}</td>
                                                    <td className="p-2 text-sm dark:text-white">{product.unit}</td>
                                                    <td className="p-2 text-sm dark:text-white">{parseFloat(product.quantity).toLocaleString()}</td>
                                                    <td className="p-2 text-sm dark:text-white">{parseFloat(product.amount).toLocaleString()}</td>
                                                    <td className="p-2">
                                                        <div className="flex gap-2">
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => {
                                                                    const editProduct = data.products[actualIndex];
                                                                    const newProducts = data.products.filter((_, i) => i !== actualIndex);
                                                                    newProducts[0] = editProduct;
                                                                    setData('products', newProducts);
                                                                }}
                                                                className="text-indigo-600 hover:text-indigo-800"
                                                            >
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="destructive"
                                                                size="sm"
                                                                onClick={() => removeProduct(actualIndex)}
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingWhiteSale}
                    onClose={() => setDeletingWhiteSale(null)}
                    onConfirm={confirmDelete}
                    title="Delete White Sale"
                    message={`Are you sure you want to delete this white sale? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected White Sales"
                    message={`Are you sure you want to delete ${selectedWhiteSales.length} selected white sales? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}