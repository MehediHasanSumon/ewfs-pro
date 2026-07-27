import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteModal } from '@/components/ui/delete-modal';
import { FormModal } from '@/components/ui/form-modal';
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
import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronUp,
    Edit,
    FileText,
    Filter,
    Plus,
    Trash2,
    Users as UsersIcon,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface User {
    id: number;
    name: string;
    email: string;
    roles: string[];
    email_verified: boolean;
    banned: boolean;
    created_at: string;
}

interface Role {
    id: number;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Users',
        href: '/users',
    },
];

interface UsersProps {
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    roles: Role[];
    filters: {
        search?: string;
        role?: string;
        status?: string;
        start_date?: string;
        end_date?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function Users({ users, roles = [], filters }: UsersProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-user') || can('delete-user');
    const canFilter = can('can-user-filter');
    const canDownload = can('can-user-download');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [deletingUser, setDeletingUser] = useState<User | null>(null);
    const [selectedUsers, setSelectedUsers] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [role, setRole] = useState(filters?.role || 'all');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'created_at');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        roles: [] as number[],
        email_verified: false,
        banned: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingUser) {
            put(`/users/${editingUser.id}`, {
                onSuccess: () => {
                    setEditingUser(null);
                    reset();
                },
            });
        } else {
            post('/users', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = async (user: User) => {
        setEditingUser(user);
        try {
            const response = await fetch(`/users/${user.id}/edit`);
            const data = await response.json();
            setData({
                name: user.name,
                email: user.email,
                password: '',
                roles: data.userRoles || [],
                email_verified: user.email_verified,
                banned: user.banned,
            });
        } catch {
            setData({
                name: user.name,
                email: user.email,
                password: '',
                roles: [],
                email_verified: user.email_verified,
                banned: user.banned,
            });
        }
    };

    const handleDelete = (user: User) => {
        setDeletingUser(user);
    };

    const confirmDelete = () => {
        if (deletingUser) {
            router.delete(`/users/${deletingUser.id}`, {
                onSuccess: () => setDeletingUser(null),
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/users/bulk/delete', {
            data: { ids: selectedUsers },
            onSuccess: () => {
                setSelectedUsers([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const applyFilters = () => {
        router.get(
            '/users',
            {
                search: search || undefined,
                role: role === 'all' ? undefined : role,
                status: status === 'all' ? undefined : status,
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
        setRole('all');
        setStatus('all');
        setStartDate('');
        setEndDate('');
        router.get(
            '/users',
            {
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handleSort = (column: string) => {
        const newOrder =
            sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(newOrder);
        router.get(
            '/users',
            {
                search: search || undefined,
                role: role === 'all' ? undefined : role,
                status: status === 'all' ? undefined : status,
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
            '/users',
            {
                search: search || undefined,
                role: role === 'all' ? undefined : role,
                status: status === 'all' ? undefined : status,
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
        if (selectedUsers.length === users.data.length) {
            setSelectedUsers([]);
        } else {
            setSelectedUsers(users.data.map((user) => user.id));
        }
    };

    const toggleSelectUser = (userId: number) => {
        if (selectedUsers.includes(userId)) {
            setSelectedUsers(selectedUsers.filter((id) => id !== userId));
        } else {
            setSelectedUsers([...selectedUsers, userId]);
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
            <Head title="Users" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Users
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage system users and their access
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedUsers.length > 0 && can('delete-user') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedUsers.length})
                            </Button>
                        )}
                        {canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (search) params.append('search', search);
                                if (role !== 'all') params.append('role', role);
                                if (status !== 'all')
                                    params.append('status', status);
                                if (startDate)
                                    params.append('start_date', startDate);
                                if (endDate) params.append('end_date', endDate);
                                if (sortBy) params.append('sort_by', sortBy);
                                if (sortOrder)
                                    params.append('sort_order', sortOrder);
                                window.location.href = `/users/download-pdf?${params.toString()}`;
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                        )}
                        {can('create-user') && (
                        <Button onClick={() => setIsCreateOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add User
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
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-6">
                            <div>
                                <Label className="dark:text-gray-200">
                                    Search
                                </Label>
                                <Input
                                    placeholder="Search users..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Role
                                </Label>
                                <Select
                                    value={role}
                                    onValueChange={(value) => {
                                        setRole(value);
                                        router.get(
                                            '/users',
                                            {
                                                search: search || undefined,
                                                role:
                                                    value === 'all'
                                                        ? undefined
                                                        : value,
                                                status:
                                                    status === 'all'
                                                        ? undefined
                                                        : status,
                                                start_date:
                                                    startDate || undefined,
                                                end_date: endDate || undefined,
                                                sort_by: sortBy,
                                                sort_order: sortOrder,
                                                per_page: perPage,
                                            },
                                            { preserveState: true },
                                        );
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All roles" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All roles
                                        </SelectItem>
                                        {roles.map((r) => (
                                            <SelectItem
                                                key={r.id}
                                                value={r.name}
                                            >
                                                {r.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Status
                                </Label>
                                <Select
                                    value={status}
                                    onValueChange={(value) => {
                                        setStatus(value);
                                        router.get(
                                            '/users',
                                            {
                                                search: search || undefined,
                                                role:
                                                    role === 'all'
                                                        ? undefined
                                                        : role,
                                                status:
                                                    value === 'all'
                                                        ? undefined
                                                        : value,
                                                start_date:
                                                    startDate || undefined,
                                                end_date: endDate || undefined,
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
                                        <SelectItem value="all">
                                            All status
                                        </SelectItem>
                                        <SelectItem value="verified">
                                            Verified
                                        </SelectItem>
                                        <SelectItem value="unverified">
                                            Unverified
                                        </SelectItem>
                                        <SelectItem value="active">
                                            Active
                                        </SelectItem>
                                        <SelectItem value="banned">
                                            Banned
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
                                    onChange={(e) =>
                                        setStartDate(e.target.value)
                                    }
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
                                                checked={
                                                    selectedUsers.length ===
                                                    users.data.length &&
                                                    users.data.length > 0
                                                }
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
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
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('email')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Email
                                                {sortBy === 'email' &&
                                                    (sortOrder === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ))}
                                            </div>
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Role
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Verified
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Banned
                                        </th>
                                        {hasActionPermission && (
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Actions
                                        </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.length > 0 ? (
                                        users.data.map((user) => (
                                            <tr
                                                key={user.id}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedUsers.includes(
                                                            user.id,
                                                        )}
                                                        onChange={() =>
                                                            toggleSelectUser(
                                                                user.id,
                                                            )
                                                        }
                                                        className="rounded border-gray-300 dark:border-gray-600"
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {user.name}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {user.email}
                                                </td>
                                                <td className="p-4">
                                                    <div className="flex flex-wrap gap-1">
                                                        {user.roles.length >
                                                            0 ? (
                                                            user.roles.map(
                                                                (
                                                                    role,
                                                                    index,
                                                                ) => (
                                                                    <span
                                                                        key={
                                                                            index
                                                                        }
                                                                        className="rounded bg-blue-100 px-1.5 py-0.5 text-[11px] text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                                                    >
                                                                        {role}
                                                                    </span>
                                                                ),
                                                            )
                                                        ) : (
                                                            <span className="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                                No Role
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="p-4">
                                                    <span className={`px-2 py-1 rounded text-xs ${user.email_verified
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                        }`}>
                                                        {user.email_verified ? 'Verified' : 'Unverified'}
                                                    </span>
                                                </td>
                                                <td className="p-4">
                                                    <span className={`px-2 py-1 rounded text-xs ${user.banned
                                                            ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                            : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                        }`}>
                                                        {user.banned ? 'Banned' : 'Active'}
                                                    </span>
                                                </td>
                                                {hasActionPermission && (
                                                <td className="p-4">
                                                    <div className="flex gap-2">
                                                        {can('update-user') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleEdit(user)
                                                            }
                                                            className="text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                        )}
                                                        {can('delete-user') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    user,
                                                                )
                                                            }
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
                                                colSpan={hasActionPermission ? 7 : 6}
                                                className="p-8 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <UsersIcon className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No users found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={users.current_page}
                            lastPage={users.last_page}
                            from={users.from}
                            to={users.to}
                            total={users.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get(
                                    '/users',
                                    {
                                        search: search || undefined,
                                        role: role === 'all' ? undefined : role,
                                        status:
                                            status === 'all'
                                                ? undefined
                                                : status,
                                        start_date: startDate || undefined,
                                        end_date: endDate || undefined,
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
                    onClose={() => setIsCreateOpen(false)}
                    title="Create User"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                >
                    <div>
                        <Label htmlFor="name" className="dark:text-gray-200">
                            Name
                        </Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.name && (
                            <span className="text-sm text-red-500">
                                {errors.name}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="email" className="dark:text-gray-200">
                            Email
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.email && (
                            <span className="text-sm text-red-500">
                                {errors.email}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label
                            htmlFor="password"
                            className="dark:text-gray-200"
                        >
                            Password
                        </Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.password && (
                            <span className="text-sm text-red-500">
                                {errors.password}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label className="dark:text-gray-200">Roles</Label>
                        <div className="mt-2 max-h-32 overflow-y-auto rounded-md border border-gray-300 p-3 dark:border-gray-600 dark:bg-gray-700">
                            {roles.map((role) => (
                                <label
                                    key={role.id}
                                    className="mb-2 flex items-center space-x-2"
                                >
                                    <input
                                        type="checkbox"
                                        checked={data.roles.includes(role.id)}
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                setData('roles', [
                                                    ...data.roles,
                                                    role.id,
                                                ]);
                                            } else {
                                                setData(
                                                    'roles',
                                                    data.roles.filter(
                                                        (id) => id !== role.id,
                                                    ),
                                                );
                                            }
                                        }}
                                        className="rounded border-gray-300 dark:border-gray-600"
                                    />
                                    <span className="text-sm dark:text-gray-300">
                                        {role.name}
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>
                    <div>
                        <label className="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                checked={data.email_verified}
                                onChange={(e) => setData('email_verified', e.target.checked)}
                                className="rounded border-gray-300 dark:border-gray-600"
                            />
                            <span className="text-sm dark:text-gray-300">Email Verified</span>
                        </label>
                    </div>
                    <div>
                        <label className="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                checked={data.banned}
                                onChange={(e) => setData('banned', e.target.checked)}
                                className="rounded border-gray-300 dark:border-gray-600"
                            />
                            <span className="text-sm dark:text-gray-300">Banned</span>
                        </label>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingUser}
                    onClose={() => setEditingUser(null)}
                    title="Edit User"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                >
                    <div>
                        <Label
                            htmlFor="edit-name"
                            className="dark:text-gray-200"
                        >
                            Name
                        </Label>
                        <Input
                            id="edit-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.name && (
                            <span className="text-sm text-red-500">
                                {errors.name}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label
                            htmlFor="edit-email"
                            className="dark:text-gray-200"
                        >
                            Email
                        </Label>
                        <Input
                            id="edit-email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.email && (
                            <span className="text-sm text-red-500">
                                {errors.email}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label
                            htmlFor="edit-password"
                            className="dark:text-gray-200"
                        >
                            Password (leave blank to keep current)
                        </Label>
                        <Input
                            id="edit-password"
                            type="password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.password && (
                            <span className="text-sm text-red-500">
                                {errors.password}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label className="dark:text-gray-200">Roles</Label>
                        <div className="mt-2 max-h-32 overflow-y-auto rounded-md border border-gray-300 p-3 dark:border-gray-600 dark:bg-gray-700">
                            {roles.map((role) => (
                                <label
                                    key={role.id}
                                    className="mb-2 flex items-center space-x-2"
                                >
                                    <input
                                        type="checkbox"
                                        checked={data.roles.includes(role.id)}
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                setData('roles', [
                                                    ...data.roles,
                                                    role.id,
                                                ]);
                                            } else {
                                                setData(
                                                    'roles',
                                                    data.roles.filter(
                                                        (id) => id !== role.id,
                                                    ),
                                                );
                                            }
                                        }}
                                        className="rounded border-gray-300 dark:border-gray-600"
                                    />
                                    <span className="text-sm dark:text-gray-300">
                                        {role.name}
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>
                    <div>
                        <label className="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                checked={data.email_verified}
                                onChange={(e) => setData('email_verified', e.target.checked)}
                                className="rounded border-gray-300 dark:border-gray-600"
                            />
                            <span className="text-sm dark:text-gray-300">Email Verified</span>
                        </label>
                    </div>
                    <div>
                        <label className="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                checked={data.banned}
                                onChange={(e) => setData('banned', e.target.checked)}
                                className="rounded border-gray-300 dark:border-gray-600"
                            />
                            <span className="text-sm dark:text-gray-300">Banned</span>
                        </label>
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingUser}
                    onClose={() => setDeletingUser(null)}
                    onConfirm={confirmDelete}
                    title="Delete User"
                    message={`Are you sure you want to delete the user "${deletingUser?.name}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Users"
                    message={`Are you sure you want to delete ${selectedUsers.length} selected users? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}
