import { Badge } from '@/components/ui/badge';
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
import { Edit, FileText, Filter, Plus, Trash2, X, Car } from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface Customer {
    id: number;
    name: string;
}

interface Product {
    id: number;
    name: string;
    product_name: string;
}

interface Vehicle {
    id: number;
    customer_id: number;
    vehicle_type?: string;
    vehicle_name?: string;
    vehicle_number?: string;
    reg_date?: string;
    status: boolean;
    customer?: Customer;
    products?: Product[];
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Vehicles',
        href: '/vehicles',
    },
];

interface VehiclesProps {
    vehicles: {
        data: Vehicle[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    customers: Customer[];
    products: Product[];
    filters: {
        search?: string;
        customer?: string;
        status?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function Vehicles({ vehicles, customers = [], products = [], filters }: VehiclesProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-vehicle') || can('delete-vehicle');
    const canFilter = can('can-vehicle-filter');
    const canDownload = can('can-vehicle-download');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingVehicle, setEditingVehicle] = useState<Vehicle | null>(null);
    const [deletingVehicle, setDeletingVehicle] = useState<Vehicle | null>(null);
    const [selectedVehicles, setSelectedVehicles] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [customer, setCustomer] = useState(filters?.customer || 'all');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        customer_id: '',
        product_ids: [] as string[],
        vehicle_type: '',
        vehicle_name: '',
        vehicle_number: '',
        reg_date: '',
        status: true
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingVehicle) {
            put(`/vehicles/${editingVehicle.id}`, {
                onSuccess: () => {
                    setEditingVehicle(null);
                    reset();
                },
            });
        } else {
            post('/vehicles', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (vehicle: Vehicle) => {
        setEditingVehicle(vehicle);
        setData({
            customer_id: vehicle.customer_id.toString(),
            product_ids: vehicle.products?.map(p => p.id.toString()) || [],
            vehicle_type: vehicle.vehicle_type || '',
            vehicle_name: vehicle.vehicle_name || '',
            vehicle_number: vehicle.vehicle_number || '',
            reg_date: vehicle.reg_date || '',
            status: vehicle.status
        });
    };

    const handleDelete = (vehicle: Vehicle) => {
        setDeletingVehicle(vehicle);
    };

    const confirmDelete = () => {
        if (deletingVehicle) {
            router.delete(`/vehicles/${deletingVehicle.id}`, {
                onSuccess: () => setDeletingVehicle(null),
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/vehicles/bulk/delete', {
            data: { ids: selectedVehicles },
            onSuccess: () => {
                setSelectedVehicles([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const toggleSelectAll = () => {
        if (selectedVehicles.length === vehicles.data.length) {
            setSelectedVehicles([]);
        } else {
            setSelectedVehicles(vehicles.data.map((vehicle) => vehicle.id));
        }
    };

    const toggleSelectVehicle = (vehicleId: number) => {
        if (selectedVehicles.includes(vehicleId)) {
            setSelectedVehicles(selectedVehicles.filter((id) => id !== vehicleId));
        } else {
            setSelectedVehicles([...selectedVehicles, vehicleId]);
        }
    };

    const applyFilters = () => {
        router.get(
            '/vehicles',
            {
                search: search || undefined,
                customer: customer === 'all' ? undefined : customer,
                status: status === 'all' ? undefined : status,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setCustomer('all');
        setStatus('all');
        router.get(
            '/vehicles',
            {
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/vehicles',
            {
                search: search || undefined,
                customer: customer === 'all' ? undefined : customer,
                status: status === 'all' ? undefined : status,
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
            <Head title="Vehicles" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Vehicles</h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage vehicle information
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedVehicles.length > 0 && can('delete-vehicle') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedVehicles.length})
                            </Button>
                        )}
                        {canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (search) params.append('search', search);
                                if (customer !== 'all') params.append('customer', customer);
                                if (status !== 'all') params.append('status', status);
                                window.location.href = `/vehicles/download-pdf?${params.toString()}`;
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                        )}
                        {can('create-vehicle') && (
                        <Button onClick={() => {
                            setIsCreateOpen(true);
                            reset();
                        }}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Vehicle
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
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-5">
                            <div>
                                <Label className="dark:text-gray-200">Search</Label>
                                <Input
                                    placeholder="Search vehicles..."
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
                                        router.get(
                                            '/vehicles',
                                            {
                                                search: search || undefined,
                                                customer: value === 'all' ? undefined : value,
                                                status: status === 'all' ? undefined : status,
                                                per_page: perPage,
                                            },
                                            { preserveState: true },
                                        );
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All customers" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All customers</SelectItem>
                                        {customers.map((c) => (
                                            <SelectItem key={c.id} value={c.id.toString()}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Status</Label>
                                <Select
                                    value={status}
                                    onValueChange={(value) => {
                                        setStatus(value);
                                        router.get(
                                            '/vehicles',
                                            {
                                                search: search || undefined,
                                                customer: customer === 'all' ? undefined : customer,
                                                status: value === 'all' ? undefined : value,
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
                                                    selectedVehicles.length ===
                                                    vehicles.data.length &&
                                                    vehicles.data.length > 0
                                                }
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            SL
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Customer
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Vehicle Name
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Vehicle Number
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Type
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Products
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
                                    {vehicles.data.length > 0 ? (
                                        vehicles.data.map((vehicle, index) => (
                                            <tr
                                                key={vehicle.id}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedVehicles.includes(
                                                            vehicle.id,
                                                        )}
                                                        onChange={() =>
                                                            toggleSelectVehicle(
                                                                vehicle.id,
                                                            )
                                                        }
                                                        className="rounded border-gray-300 dark:border-gray-600"
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {(vehicles.current_page - 1) * vehicles.per_page + index + 1}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {vehicle.customer?.name || 'N/A'}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {vehicle.vehicle_name || 'N/A'}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {vehicle.vehicle_number || 'N/A'}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {vehicle.vehicle_type || 'N/A'}
                                                </td>
                                                <td className="p-4">
                                                    {vehicle.products && vehicle.products.length > 0 ? (
                                                        <div className="flex flex-wrap gap-1">
                                                            {vehicle.products.map(p => (
                                                                <Badge key={p.id} variant="secondary">
                                                                    {p.product_name}
                                                                </Badge>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <span className="text-[13px] dark:text-gray-300">N/A</span>
                                                    )}
                                                </td>
                                                <td className="p-4">
                                                    <Badge variant={vehicle.status ? 'success' : 'destructive'}>
                                                        {vehicle.status ? 'Active' : 'Inactive'}
                                                    </Badge>
                                                </td>
                                                {hasActionPermission && (
                                                <td className="p-4">
                                                    <div className="flex gap-2">
                                                        {can('update-vehicle') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleEdit(vehicle)}
                                                            className="text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                        )}
                                                        {can('delete-vehicle') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(vehicle)}
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
                                                colSpan={hasActionPermission ? 9 : 8}
                                                className="p-8 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <Car className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No vehicles found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={vehicles.current_page}
                            lastPage={vehicles.last_page}
                            from={vehicles.from}
                            to={vehicles.to}
                            total={vehicles.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get(
                                    '/vehicles',
                                    {
                                        search: search || undefined,
                                        customer: customer === 'all' ? undefined : customer,
                                        status: status === 'all' ? undefined : status,
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
                    title="Create Vehicle"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                >
                    <div>
                        <Label className="dark:text-gray-200">Customer *</Label>
                        <Select
                            value={data.customer_id}
                            onValueChange={(value) => setData('customer_id', value)}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select Customer" />
                            </SelectTrigger>
                            <SelectContent>
                                {customers.map((customer) => (
                                    <SelectItem key={customer.id} value={customer.id.toString()}>
                                        {customer.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.customer_id && <span className="text-sm text-red-500">{errors.customer_id}</span>}
                    </div>

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
                                        checked={data.product_ids.includes(product.id.toString())}
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                setData('product_ids', [...data.product_ids, product.id.toString()]);
                                            } else {
                                                setData('product_ids', data.product_ids.filter(id => id !== product.id.toString()));
                                            }
                                        }}
                                        className="rounded border-gray-300 dark:border-gray-600"
                                    />
                                    <span className="text-sm dark:text-white">{product.name}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div>
                        <Label className="dark:text-gray-200">Status</Label>
                        <Select
                            value={data.status ? 'active' : 'inactive'}
                            onValueChange={(value) => setData('status', value === 'active')}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingVehicle}
                    onClose={() => {
                        setEditingVehicle(null);
                        reset();
                    }}
                    title="Edit Vehicle"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                >
                    <div>
                        <Label className="dark:text-gray-200">Customer *</Label>
                        <Select
                            value={data.customer_id}
                            onValueChange={(value) => setData('customer_id', value)}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select Customer" />
                            </SelectTrigger>
                            <SelectContent>
                                {customers.map((customer) => (
                                    <SelectItem key={customer.id} value={customer.id.toString()}>
                                        {customer.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.customer_id && <span className="text-sm text-red-500">{errors.customer_id}</span>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="edit-vehicle_type" className="dark:text-gray-200">Vehicle Type</Label>
                            <Input
                                id="edit-vehicle_type"
                                value={data.vehicle_type}
                                onChange={(e) => setData('vehicle_type', e.target.value)}
                                placeholder="e.g., Car, Truck"
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-vehicle_name" className="dark:text-gray-200">Vehicle Name</Label>
                            <Input
                                id="edit-vehicle_name"
                                value={data.vehicle_name}
                                onChange={(e) => setData('vehicle_name', e.target.value)}
                                placeholder="e.g., Toyota Corolla"
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="edit-vehicle_number" className="dark:text-gray-200">Vehicle Number</Label>
                            <Input
                                id="edit-vehicle_number"
                                value={data.vehicle_number}
                                onChange={(e) => setData('vehicle_number', e.target.value)}
                                placeholder="e.g., ABC-1234"
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-reg_date" className="dark:text-gray-200">Registration Date</Label>
                            <Input
                                id="edit-reg_date"
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
                                        checked={data.product_ids.includes(product.id.toString())}
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                setData('product_ids', [...data.product_ids, product.id.toString()]);
                                            } else {
                                                setData('product_ids', data.product_ids.filter(id => id !== product.id.toString()));
                                            }
                                        }}
                                        className="rounded border-gray-300 dark:border-gray-600"
                                    />
                                    <span className="text-sm dark:text-white">{product.name}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div>
                        <Label className="dark:text-gray-200">Status</Label>
                        <Select
                            value={data.status ? 'active' : 'inactive'}
                            onValueChange={(value) => setData('status', value === 'active')}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingVehicle}
                    onClose={() => setDeletingVehicle(null)}
                    onConfirm={confirmDelete}
                    title="Delete Vehicle"
                    message={`Are you sure you want to delete the vehicle "${deletingVehicle?.vehicle_name || deletingVehicle?.vehicle_number}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Vehicles"
                    message={`Are you sure you want to delete ${selectedVehicles.length} selected vehicles? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}