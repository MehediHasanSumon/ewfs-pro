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
import { Plus, Edit, Trash2, Shield, ChevronUp, ChevronDown, Filter, X, FileText } from 'lucide-react';
import { useState, useEffect } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface Role {
    id: number;
    name: string;
    description: string;
    permissions_count: number;
    users_count: number;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Roles',
        href: '/roles',
    },
];

interface RolesProps {
    roles: {
        data: Role[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    permissions: Permission[];
    filters: {
        search?: string;
        start_date?: string;
        end_date?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function Roles({ roles, permissions = [], filters }: RolesProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-role') || can('delete-role');
    const canFilter = can('can-role-filter');
    const canDownload = can('can-role-download');
    
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<Role | null>(null);
    const [deletingRole, setDeletingRole] = useState<Role | null>(null);
    const [selectedRoles, setSelectedRoles] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'name');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'asc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);
    
    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        permissions: [] as number[]
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingRole) {
            put(`/roles/${editingRole.id}`, {
                onSuccess: () => {
                    setEditingRole(null);
                    reset();
                }
            });
        } else {
            post('/roles', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                }
            });
        }
    };

    const handleEdit = async (role: Role) => {
        setEditingRole(role);
        try {
            const response = await fetch(`/roles/${role.id}/edit`);
            const data = await response.json();
            setData({
                name: role.name,
                permissions: data.rolePermissions || []
            });
        } catch (error) {
            setData({
                name: role.name,
                permissions: []
            });
        }
    };

    const handleDelete = (role: Role) => {
        setDeletingRole(role);
    };

    const confirmDelete = () => {
        if (deletingRole) {
            router.delete(`/roles/${deletingRole.id}`, {
                onSuccess: () => setDeletingRole(null)
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/roles/bulk/delete', {
            data: { ids: selectedRoles },
            onSuccess: () => {
                setSelectedRoles([]);
                setIsBulkDeleting(false);
            }
        });
    };

    const applyFilters = () => {
        router.get('/roles', {
            search: search || undefined,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStartDate('');
        setEndDate('');
        router.get('/roles', {
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handleSort = (column: string) => {
        const newOrder = sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(newOrder);
        router.get('/roles', {
            search: search || undefined,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: column,
            sort_order: newOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handlePageChange = (page: number) => {
        router.get('/roles', {
            search: search || undefined,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
            page,
        }, { preserveState: true });
    };

    const toggleSelectAll = () => {
        if (selectedRoles.length === roles.data.length) {
            setSelectedRoles([]);
        } else {
            setSelectedRoles(roles.data.map(role => role.id));
        }
    };

    const toggleSelectRole = (roleId: number) => {
        if (selectedRoles.includes(roleId)) {
            setSelectedRoles(selectedRoles.filter(id => id !== roleId));
        } else {
            setSelectedRoles([...selectedRoles, roleId]);
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
            <Head title="Roles" />
            
            <div className="p-6 space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Roles</h1>
                        <p className="text-gray-600 dark:text-gray-400">Manage user roles and access levels</p>
                    </div>
                    <div className="flex gap-2">
                        {selectedRoles.length > 0 && can('delete-role') && (
                            <Button 
                                variant="destructive" 
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="h-4 w-4 mr-2" />
                                Delete Selected ({selectedRoles.length})
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
                                    window.location.href = `/roles/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="h-4 w-4 mr-2" />
                                Download
                            </Button>
                        )}
                        {can('create-role') && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="h-4 w-4 mr-2" />
                                Add Role
                            </Button>
                        )}
                    </div>
                </div>

                {/* Filter Card */}
                {canFilter && (
                <Card className="dark:bg-gray-800 dark:border-gray-700">
                    <CardHeader>
                        <CardTitle className="dark:text-white flex items-center gap-2">
                            <Filter className="h-5 w-5" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <Label className="dark:text-gray-200">Search</Label>
                                <Input
                                    placeholder="Search roles..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                />
                            </div>

                            <div>
                                <Label className="dark:text-gray-200">Start Date</Label>
                                <Input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">End Date</Label>
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
                                                checked={selectedRoles.length === roles.data.length && roles.data.length > 0}
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
                                        <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300">Description</th>
                                        <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300 cursor-pointer" onClick={() => handleSort('permissions_count')}>
                                            <div className="flex items-center gap-1">
                                                Permissions
                                                {sortBy === 'permissions_count' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                        <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300 cursor-pointer" onClick={() => handleSort('users_count')}>
                                            <div className="flex items-center gap-1">
                                                Users
                                                {sortBy === 'users_count' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                        {hasActionPermission && <th className="text-left py-3 px-4 text-[13px] font-semibold text-gray-700 dark:text-gray-300">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {roles.data.length > 0 ? roles.data.map((role) => (
                                        <tr key={role.id} className="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                            <td className="py-3 px-4">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedRoles.includes(role.id)}
                                                    onChange={() => toggleSelectRole(role.id)}
                                                    className="rounded border-gray-300 dark:border-gray-600"
                                                />
                                            </td>
                                            <td className="py-3 px-4 text-[13px] font-medium text-gray-900 dark:text-gray-100">{role.name}</td>
                                            <td className="py-3 px-4 text-[13px] text-gray-700 dark:text-gray-300">{role.description}</td>
                                            <td className="py-3 px-4 text-[13px] text-gray-700 dark:text-gray-300">{role.permissions_count} permissions</td>
                                            <td className="py-3 px-4 text-[13px] text-gray-700 dark:text-gray-300">{role.users_count} users</td>
                                            {hasActionPermission && (
                                            <td className="py-3 px-4">
                                                <div className="flex gap-2">
                                                    {can('update-role') && (
                                                    <Button 
                                                        variant="ghost" 
                                                        size="sm"
                                                        onClick={() => handleEdit(role)}
                                                        className="text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    )}
                                                    {can('delete-role') && (
                                                    <Button 
                                                        variant="ghost" 
                                                        size="sm"
                                                        onClick={() => handleDelete(role)}
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
                                                <Shield className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                                No roles found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        
                        <Pagination
                            currentPage={roles.current_page}
                            lastPage={roles.last_page}
                            from={roles.from}
                            to={roles.to}
                            total={roles.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get('/roles', {
                                    search: search || undefined,
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
                    title="Create Role"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                >
                    <div>
                        <Label htmlFor="name" className="dark:text-gray-200">Role Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            placeholder="e.g., admin"
                        />
                        {errors.name && <span className="text-red-500 text-sm">{errors.name}</span>}
                    </div>
                    <div>
                        <Label className="dark:text-gray-200">Permissions</Label>
                        <div className="mt-2 max-h-48 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-md p-3 dark:bg-gray-700">
                            {permissions.map((permission) => (
                                <label key={permission.id} className="flex items-center space-x-2 mb-2">
                                    <input
                                        type="checkbox"
                                        checked={data.permissions.includes(permission.id)}
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                setData('permissions', [...data.permissions, permission.id]);
                                            } else {
                                                setData('permissions', data.permissions.filter(id => id !== permission.id));
                                            }
                                        }}
                                        className="rounded border-gray-300 dark:border-gray-600"
                                    />
                                    <span className="text-sm dark:text-gray-300">{permission.name}</span>
                                </label>
                            ))}
                        </div>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingRole}
                    onClose={() => setEditingRole(null)}
                    title="Edit Role"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                >
                    <div>
                        <Label htmlFor="edit-name" className="dark:text-gray-200">Role Name</Label>
                        <Input
                            id="edit-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        />
                        {errors.name && <span className="text-red-500 text-sm">{errors.name}</span>}
                    </div>
                    <div>
                        <Label className="dark:text-gray-200">Permissions</Label>
                        <div className="mt-2 max-h-48 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-md p-3 dark:bg-gray-700">
                            {permissions.map((permission) => (
                                <label key={permission.id} className="flex items-center space-x-2 mb-2">
                                    <input
                                        type="checkbox"
                                        checked={data.permissions.includes(permission.id)}
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                setData('permissions', [...data.permissions, permission.id]);
                                            } else {
                                                setData('permissions', data.permissions.filter(id => id !== permission.id));
                                            }
                                        }}
                                        className="rounded border-gray-300 dark:border-gray-600"
                                    />
                                    <span className="text-sm dark:text-gray-300">{permission.name}</span>
                                </label>
                            ))}
                        </div>
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingRole}
                    onClose={() => setDeletingRole(null)}
                    onConfirm={confirmDelete}
                    title="Delete Role"
                    message={`Are you sure you want to delete the role "${deletingRole?.name}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Roles"
                    message={`Are you sure you want to delete ${selectedRoles.length} selected roles? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}