import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pagination } from '@/components/ui/pagination';
import { DialogTrigger } from '@/components/ui/dialog';
import { FormModal } from '@/components/ui/form-modal';
import { DeleteModal } from '@/components/ui/delete-modal';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import { Plus, Search, Edit, Trash2, Key, ChevronUp, ChevronDown, Filter, X, FileText } from 'lucide-react';
import { useState, useEffect } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface Permission {
    id: number;
    name: string;
    description: string;
    module: string;
    roles_count: number;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Permissions',
        href: '/permissions',
    },
];

interface PermissionsProps {
    permissions: {
        data: Permission[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    filters: {
        search?: string;
        module?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function Permissions({ permissions, filters }: PermissionsProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-permission') || can('delete-permission');
    const canFilter = can('can-permission-filter');
    const canDownload = can('can-permission-download');
    
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingPermission, setEditingPermission] = useState<Permission | null>(null);
    const [deletingPermission, setDeletingPermission] = useState<Permission | null>(null);
    const [selectedPermissions, setSelectedPermissions] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [module, setModule] = useState(filters?.module || 'all');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'name');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'asc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);
    
    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        guard_name: 'web'
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingPermission) {
            put(`/permissions/${editingPermission.id}`, {
                onSuccess: () => {
                    setEditingPermission(null);
                    reset();
                }
            });
        } else {
            post('/permissions', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                }
            });
        }
    };

    const handleEdit = (permission: Permission) => {
        setEditingPermission(permission);
        setData({
            name: permission.name,
            guard_name: 'web'
        });
    };

    const handleDelete = (permission: Permission) => {
        setDeletingPermission(permission);
    };

    const confirmDelete = () => {
        if (deletingPermission) {
            router.delete(`/permissions/${deletingPermission.id}`, {
                onSuccess: () => setDeletingPermission(null)
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/permissions/bulk/delete', {
            data: { ids: selectedPermissions },
            onSuccess: () => {
                setSelectedPermissions([]);
                setIsBulkDeleting(false);
            }
        });
    };

    const applyFilters = () => {
        router.get('/permissions', {
            search: search || undefined,
            module: module === 'all' ? undefined : module,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setModule('all');
        setStartDate('');
        setEndDate('');
        router.get('/permissions', {
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handleSort = (column: string) => {
        const newOrder = sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(newOrder);
        router.get('/permissions', {
            search: search || undefined,
            module: module === 'all' ? undefined : module,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: column,
            sort_order: newOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handlePageChange = (page: number) => {
        router.get('/permissions', {
            search: search || undefined,
            module: module === 'all' ? undefined : module,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
            page,
        }, { preserveState: true });
    };

    const toggleSelectAll = () => {
        if (selectedPermissions.length === permissions.data.length) {
            setSelectedPermissions([]);
        } else {
            setSelectedPermissions(permissions.data.map(permission => permission.id));
        }
    };

    const toggleSelectPermission = (permissionId: number) => {
        if (selectedPermissions.includes(permissionId)) {
            setSelectedPermissions(selectedPermissions.filter(id => id !== permissionId));
        } else {
            setSelectedPermissions([...selectedPermissions, permissionId]);
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
            <Head title="Permissions" />
            
            <div className="p-6 space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold dark:text-white">Permissions</h1>
                        <p className="text-gray-600 dark:text-gray-400">Manage system permissions and access controls</p>
                    </div>
                    <div className="flex gap-2">
                        {selectedPermissions.length > 0 && can('delete-permission') && (
                            <Button 
                                variant="destructive" 
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="h-4 w-4 mr-2" />
                                Delete Selected ({selectedPermissions.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (module !== 'all') params.append('module', module);
                                    if (startDate) params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    if (sortBy) params.append('sort_by', sortBy);
                                    if (sortOrder) params.append('sort_order', sortOrder);
                                    window.location.href = `/permissions/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="h-4 w-4 mr-2" />
                                Download
                            </Button>
                        )}
                        {can('create-permission') && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="h-4 w-4 mr-2" />
                                Add Permission
                            </Button>
                        )}
                    </div>
                </div>

                {/* Filter Card */}
                {canFilter && (
                <Card className="dark:bg-gray-800 dark:border-gray-700">
                    <CardHeader>
                        <CardTitle className="dark:text-white flex items-center gap-2">
                            <Filter className="h-4 w-4" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div>
                                <Label className="text-sm dark:text-gray-200">Search</Label>
                                <Input
                                    placeholder="Search permissions..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="text-sm dark:text-gray-200">Module</Label>
                                <Select value={module} onValueChange={setModule}>
                                    <SelectTrigger className="dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <SelectValue placeholder="All modules" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All modules</SelectItem>
                                        <SelectItem value="users">Users</SelectItem>
                                        <SelectItem value="roles">Roles</SelectItem>
                                        <SelectItem value="permissions">Permissions</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="text-sm dark:text-gray-200">Start Date</Label>
                                <Input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="text-sm dark:text-gray-200">End Date</Label>
                                <Input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                />
                            </div>
                            <div className="flex items-end gap-2">
                                <Button onClick={applyFilters} className="px-4">
                                    Apply Filters
                                </Button>
                                <Button onClick={clearFilters} variant="outline" className="px-4">
                                    <X className="h-4 w-4 mr-2" />
                                    Clear
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                )}

                <Card className="dark:bg-gray-800 dark:border-gray-700">
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-gray-200 dark:border-gray-700">
                                        <th className="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={selectedPermissions.length === permissions.data.length && permissions.data.length > 0}
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300 cursor-pointer" onClick={() => handleSort('name')}>
                                            <div className="flex items-center gap-1">
                                                Name
                                                {sortBy === 'name' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                        <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300">Module</th>
                                        <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300">Description</th>
                                        <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300 cursor-pointer" onClick={() => handleSort('roles_count')}>
                                            <div className="flex items-center gap-1">
                                                Roles
                                                {sortBy === 'roles_count' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                        {hasActionPermission && <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {permissions.data.length > 0 ? permissions.data.map((permission) => (
                                        <tr key={permission.id} className="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                            <td className="py-3 px-4">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedPermissions.includes(permission.id)}
                                                    onChange={() => toggleSelectPermission(permission.id)}
                                                    className="rounded border-gray-300 dark:border-gray-600"
                                                />
                                            </td>
                                            <td className="py-3 px-4 text-[13px] font-medium text-gray-900 dark:text-gray-100">{permission.name}</td>
                                            <td className="py-3 px-4">
                                                <span className="px-2 py-1 text-[13px] font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                    {permission.module}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-[13px] text-gray-700 dark:text-gray-300">{permission.description}</td>
                                            <td className="py-3 px-4 text-[13px] text-gray-700 dark:text-gray-300">{permission.roles_count} roles</td>
                                            {hasActionPermission && (
                                            <td className="py-3 px-4">
                                                <div className="flex gap-2">
                                                    {can('update-permission') && (
                                                    <Button 
                                                        variant="ghost" 
                                                        size="sm"
                                                        onClick={() => handleEdit(permission)}
                                                        className="text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    )}
                                                    {can('delete-permission') && (
                                                    <Button 
                                                        variant="ghost" 
                                                        size="sm"
                                                        onClick={() => handleDelete(permission)}
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
                                            <td colSpan={hasActionPermission ? 6 : 5} className="py-12 text-center text-gray-500 dark:text-gray-400">
                                                <Key className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                                No permissions found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        
                        <Pagination
                            currentPage={permissions.current_page}
                            lastPage={permissions.last_page}
                            from={permissions.from}
                            to={permissions.to}
                            total={permissions.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get('/permissions', {
                                    search: search || undefined,
                                    module: module === 'all' ? undefined : module,
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
                    title="Create Permission"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                >
                    <div>
                        <Label htmlFor="name" className="dark:text-gray-200">Permission Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            placeholder="e.g., create users"
                        />
                        {errors.name && <span className="text-red-500 text-sm">{errors.name}</span>}
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingPermission}
                    onClose={() => setEditingPermission(null)}
                    title="Edit Permission"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                >
                    <div>
                        <Label htmlFor="edit-name" className="dark:text-gray-200">Permission Name</Label>
                        <Input
                            id="edit-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        />
                        {errors.name && <span className="text-red-500 text-sm">{errors.name}</span>}
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingPermission}
                    onClose={() => setDeletingPermission(null)}
                    onConfirm={confirmDelete}
                    title="Delete Permission"
                    message={`Are you sure you want to delete the permission "${deletingPermission?.name}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Permissions"
                    message={`Are you sure you want to delete ${selectedPermissions.length} selected permissions? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}