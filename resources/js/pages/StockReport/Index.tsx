import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ChevronDown, ChevronUp, FileText, Filter, Package, X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Stock {
    id: number;
    product: {
        id: number;
        product_name: string;
        product_code: string;
        unit: { name: string };
        category: { name: string };
    };
    current_stock: number;
    available_stock: number;
    created_at: string;
}

interface Category {
    id: number;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Today Stock Report', href: '/stock-report' },
];

interface StockReportProps {
    stocks: {
        data: Stock[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    categories: Category[];
    filters: {
        search?: string;
        category?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function StockReport({ stocks, categories = [], filters = {} }: StockReportProps) {
    const [search, setSearch] = useState(filters.search || '');
    const [category, setCategory] = useState(filters.category || 'all');
    const [sortBy, setSortBy] = useState(filters.sort_by || 'created_at');
    const [sortOrder, setSortOrder] = useState(filters.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters.per_page || 10);

    const applyFilters = () => {
        router.get('/stock-report', {
            search: search || undefined,
            category: category === 'all' ? undefined : category,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setCategory('all');
        router.get('/stock-report', {
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handleSort = (column: string) => {
        const newOrder = sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(newOrder);
        router.get('/stock-report', {
            search: search || undefined,
            category: category === 'all' ? undefined : category,
            sort_by: column,
            sort_order: newOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handlePageChange = (page: number) => {
        router.get('/stock-report', {
            search: search || undefined,
            category: category === 'all' ? undefined : category,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
            page,
        }, { preserveState: true });
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
            <Head title="Today Stock Report" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Today Stock Report</h1>
                        <p className="text-gray-600 dark:text-gray-400">View current stock levels</p>
                    </div>
                    <Button
                        variant="success"
                        onClick={() => {
                            const params = new URLSearchParams();
                            if (search) params.append('search', search);
                            if (category !== 'all') params.append('category', category);
                            params.append('sort_by', sortBy);
                            params.append('sort_order', sortOrder);
                            window.location.href = `/stock-report/download-pdf?${params.toString()}`;
                        }}
                    >
                        <FileText className="mr-2 h-4 w-4" />Download
                    </Button>
                </div>

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
                                    placeholder="Search products..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Category</Label>
                                <Select value={category} onValueChange={(value) => { setCategory(value); applyFilters(); }}>
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All categories" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All categories</SelectItem>
                                        {categories.map((cat) => (
                                            <SelectItem key={cat.id} value={cat.id.toString()}>{cat.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end gap-2 md:col-span-2">
                                <Button onClick={applyFilters} className="px-4">Apply Filters</Button>
                                <Button onClick={clearFilters} variant="secondary" className="px-4">
                                    <X className="mr-2 h-4 w-4" />Clear
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Product Code</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Product Name</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Category</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Unit</th>
                                        <th className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300" onClick={() => handleSort('current_stock')}>
                                            <div className="flex items-center gap-1">
                                                Current Stock
                                                {sortBy === 'current_stock' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                        <th className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300" onClick={() => handleSort('available_stock')}>
                                            <div className="flex items-center gap-1">
                                                Available Stock
                                                {sortBy === 'available_stock' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {stocks.data.length > 0 ? (
                                        stocks.data.map((stock) => (
                                            <tr key={stock.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                <td className="p-4 text-[13px] dark:text-white">{stock.product.product_code}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{stock.product.product_name}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{stock.product.category.name}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{stock.product.unit.name}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{stock.current_stock.toLocaleString()}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{stock.available_stock.toLocaleString()}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={6} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                <Package className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No stock found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={stocks.current_page}
                            lastPage={stocks.last_page}
                            from={stocks.from}
                            to={stocks.to}
                            total={stocks.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => { setPerPage(newPerPage); applyFilters(); }}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
