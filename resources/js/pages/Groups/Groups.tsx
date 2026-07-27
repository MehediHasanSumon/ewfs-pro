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
import { usePermission } from '@/hooks/usePermission';
import {
    Building,
    ChevronDown,
    ChevronUp,
    Edit,
    FileText,
    Filter,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface Group {
    id: number;
    code: string;
    name: string;
    parents: string;
    status: number;
    parent_name?: string;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Groups', href: '/groups' },
];

interface GroupsProps {
    groups: {
        data: Group[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    masterGroups: Record<string, string>;
    filters: {
        search?: string;
        master_group?: string;
        status?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function Groups({
    groups,
    masterGroups = {},
    filters,
}: GroupsProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-account') || can('delete-account');
    const canFilter = can('can-group-filter');
    const canDownload = can('can-group-download');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingGroup, setEditingGroup] = useState<Group | null>(null);
    const [deletingGroup, setDeletingGroup] = useState<Group | null>(null);
    const [selectedGroups, setSelectedGroups] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [masterGroupsList, setMasterGroupsList] = useState<Group[]>([]);
    const [subGroups, setSubGroups] = useState<Group[]>([]);
    const [search, setSearch] = useState(filters?.search || '');
    const [masterGroup, setMasterGroup] = useState(
        filters?.master_group || 'all',
    );
    const [status, setStatus] = useState(filters?.status || 'all');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'created_at');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: '',
        parents: '',
        name: '',
    });

    useEffect(() => {
        const masters = Object.entries(masterGroups).map(
            ([code, name]) => ({
                id: 0,
                code: code,
                name: name,
                parents: 'ROOT',
                status: 1,
                created_at: '',
            }),
        );
        setMasterGroupsList(masters);
    }, [masterGroups]);

    useEffect(() => {
        if (data.code) {
            fetch(`/groups/get-parentchild/${data.code}`)
                .then((response) => response.json())
                .then((responseData) => {
                    const subs = Object.entries(responseData).map(
                        ([code, name]) => ({
                            id: 0,
                            code: code,
                            name: name as string,
                            parents: '',
                            status: 1,
                            created_at: '',
                        }),
                    );
                    setSubGroups(subs);
                })
                .catch(() => setSubGroups([]));
        } else {
            setSubGroups([]);
        }
    }, [data.code]);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (filters?.search || '')) {
                applyFilters();
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [search]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingGroup) {
            put(`/groups/${editingGroup.id}`, {
                onSuccess: () => {
                    setEditingGroup(null);
                    reset();
                },
            });
        } else {
            post('/groups', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (group: Group) => {
        setEditingGroup(group);
        setData({
            code: group.parents || '',
            parents: group.parents || '',
            name: group.name,
        });
    };

    const confirmDelete = () => {
        if (deletingGroup) {
            router.delete(`/groups/${deletingGroup.id}`, {
                onSuccess: () => setDeletingGroup(null),
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/groups/bulk/delete', {
            data: { ids: selectedGroups },
            onSuccess: () => {
                setSelectedGroups([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const applyFilters = () => {
        router.get(
            '/groups',
            {
                search: search || undefined,
                master_group: masterGroup === 'all' ? undefined : masterGroup,
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
        setMasterGroup('all');
        setStatus('all');
        router.get(
            '/groups',
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
            '/groups',
            {
                search: search || undefined,
                master_group: masterGroup === 'all' ? undefined : masterGroup,
                status: status === 'all' ? undefined : status,
                sort_by: column,
                sort_order: newOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const toggleSelectAll = () => {
        if (selectedGroups.length === groups.data.length) {
            setSelectedGroups([]);
        } else {
            setSelectedGroups(groups.data.map((group) => group.id));
        }
    };

    const toggleSelectGroup = (groupId: number) => {
        if (selectedGroups.includes(groupId)) {
            setSelectedGroups(selectedGroups.filter((id) => id !== groupId));
        } else {
            setSelectedGroups([...selectedGroups, groupId]);
        }
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/groups',
            {
                search: search || undefined,
                master_group: masterGroup === 'all' ? undefined : masterGroup,
                status: status === 'all' ? undefined : status,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
                page,
            },
            { preserveState: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Groups" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Groups
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage groups and their hierarchy
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedGroups.length > 0 && can('delete-account') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedGroups.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (masterGroup !== 'all')
                                        params.append('master_group', masterGroup);
                                    if (status !== 'all')
                                        params.append('status', status);
                                    params.append('sort_by', sortBy);
                                    params.append('sort_order', sortOrder);
                                    window.location.href = `/groups/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        {can('create-account') && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Group
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
                                <Label className="dark:text-gray-200">
                                    Search
                                </Label>
                                <Input
                                    placeholder="Search groups..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Master Group
                                </Label>
                                <Select
                                    value={masterGroup}
                                    onValueChange={(value) => {
                                        setMasterGroup(value);
                                        router.get(
                                            '/groups',
                                            {
                                                search: search || undefined,
                                                master_group:
                                                    value === 'all'
                                                        ? undefined
                                                        : value,
                                                status:
                                                    status === 'all'
                                                        ? undefined
                                                        : status,
                                                sort_by: sortBy,
                                                sort_order: sortOrder,
                                                per_page: perPage,
                                            },
                                            { preserveState: true },
                                        );
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All groups" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All groups
                                        </SelectItem>
                                        {Object.entries(masterGroups).map(
                                            ([code, name]) => (
                                                <SelectItem
                                                    key={code}
                                                    value={code}
                                                >
                                                    {name}
                                                </SelectItem>
                                            ),
                                        )}
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
                                            '/groups',
                                            {
                                                search: search || undefined,
                                                master_group:
                                                    masterGroup === 'all'
                                                        ? undefined
                                                        : masterGroup,
                                                status:
                                                    value === 'all'
                                                        ? undefined
                                                        : value,
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
                                        <SelectItem value="1">
                                            Active
                                        </SelectItem>
                                        <SelectItem value="0">
                                            Inactive
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
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
                                                    selectedGroups.length ===
                                                        groups.data.length &&
                                                    groups.data.length > 0
                                                }
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('id')}
                                        >
                                            <div className="flex items-center gap-1">
                                                ID
                                                {sortBy === 'id' &&
                                                    (sortOrder === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ))}
                                            </div>
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() =>
                                                handleSort('code')
                                            }
                                        >
                                            <div className="flex items-center gap-1">
                                                Code
                                                {sortBy === 'code' &&
                                                    (sortOrder === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ))}
                                            </div>
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() =>
                                                handleSort('name')
                                            }
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
                                            Parent Group
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
                                    {groups.data.length > 0 ? (
                                        groups.data.map((group) => (
                                            <tr
                                                key={group.id}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedGroups.includes(
                                                            group.id,
                                                        )}
                                                        onChange={() =>
                                                            toggleSelectGroup(
                                                                group.id,
                                                            )
                                                        }
                                                        className="rounded border-gray-300 dark:border-gray-600"
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {group.id}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {group.code}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {group.name}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {group.parent_name ||
                                                        group.parents}
                                                </td>
                                                <td className="p-4">
                                                    <span className={`px-2 py-1 rounded text-xs ${
                                                        group.status 
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                    }`}>
                                                        {group.status ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                {hasActionPermission && (
                                                    <td className="p-4">
                                                        <div className="flex gap-2">
                                                            {can('update-account') && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        handleEdit(
                                                                            group,
                                                                        )
                                                                    }
                                                                    className="text-indigo-600 hover:text-indigo-800"
                                                                >
                                                                    <Edit className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {can('delete-account') && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setDeletingGroup(
                                                                            group,
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
                                                <Building className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No groups found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={groups.current_page}
                            lastPage={groups.last_page}
                            from={groups.from}
                            to={groups.to}
                            total={groups.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get(
                                    '/groups',
                                    {
                                        search: search || undefined,
                                        master_group:
                                            masterGroup === 'all'
                                                ? undefined
                                                : masterGroup,
                                        status:
                                            status === 'all'
                                                ? undefined
                                                : status,
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
                    title="Create Group"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                >
                    <div>
                        <Label
                            htmlFor="code"
                            className="dark:text-gray-200"
                        >
                            Select Master Group
                        </Label>
                        <Select
                            value={data.code}
                            onValueChange={(value) =>
                                setData('code', value)
                            }
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="--Select Master Group--" />
                            </SelectTrigger>
                            <SelectContent>
                                {masterGroupsList.map((group) => (
                                    <SelectItem
                                        key={group.code}
                                        value={group.code}
                                    >
                                        {group.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.code && (
                            <span className="text-sm text-red-500">
                                {errors.code}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label
                            htmlFor="parents"
                            className="dark:text-gray-200"
                        >
                            Under Main Group
                        </Label>
                        <Select
                            value={data.parents}
                            onValueChange={(value) =>
                                setData('parents', value)
                            }
                            disabled={!data.code}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="--Under Main Group--" />
                            </SelectTrigger>
                            <SelectContent>
                                {subGroups.map((group) => (
                                    <SelectItem
                                        key={group.code}
                                        value={group.code}
                                    >
                                        {group.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.parents && (
                            <span className="text-sm text-red-500">
                                {errors.parents}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label
                            htmlFor="name"
                            className="dark:text-gray-200"
                        >
                            Group Name
                        </Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) =>
                                setData('name', e.target.value)
                            }
                            placeholder="Group Name"
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.name && (
                            <span className="text-sm text-red-500">
                                {errors.name}
                            </span>
                        )}
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingGroup}
                    onClose={() => setEditingGroup(null)}
                    title="Edit Group"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                >
                    <div>
                        <Label
                            htmlFor="edit_code"
                            className="dark:text-gray-200"
                        >
                            Select Master Group
                        </Label>
                        <Select
                            value={data.code}
                            onValueChange={(value) =>
                                setData('code', value)
                            }
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="--Select Master Group--" />
                            </SelectTrigger>
                            <SelectContent>
                                {masterGroupsList.map((group) => (
                                    <SelectItem
                                        key={group.code}
                                        value={group.code}
                                    >
                                        {group.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.code && (
                            <span className="text-sm text-red-500">
                                {errors.code}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label
                            htmlFor="edit_parents"
                            className="dark:text-gray-200"
                        >
                            Under Main Group
                        </Label>
                        <Select
                            value={data.parents}
                            onValueChange={(value) =>
                                setData('parents', value)
                            }
                            disabled={!data.code}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="--Under Main Group--" />
                            </SelectTrigger>
                            <SelectContent>
                                {subGroups.map((group) => (
                                    <SelectItem
                                        key={group.code}
                                        value={group.code}
                                    >
                                        {group.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.parents && (
                            <span className="text-sm text-red-500">
                                {errors.parents}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label
                            htmlFor="edit_name"
                            className="dark:text-gray-200"
                        >
                            Group Name
                        </Label>
                        <Input
                            id="edit_name"
                            value={data.name}
                            onChange={(e) =>
                                setData('name', e.target.value)
                            }
                            placeholder="Group Name"
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.name && (
                            <span className="text-sm text-red-500">
                                {errors.name}
                            </span>
                        )}
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingGroup}
                    onClose={() => setDeletingGroup(null)}
                    onConfirm={confirmDelete}
                    title="Delete Group"
                    message={`Are you sure you want to delete the group "${deletingGroup?.name}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Groups"
                    message={`Are you sure you want to delete ${selectedGroups.length} selected groups? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}