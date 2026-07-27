import React, { useState, useEffect } from 'react';
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
    Settings as SettingsIcon,
    X,
} from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';

interface SMSConfig {
    id: number;
    url: string;
    api_key: string;
    sender_id: string;
    status: boolean;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'SMS Config',
        href: '/sms-configs',
    },
];

interface SMSConfigProps {
    configs: {
        data: SMSConfig[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    filters: {
        search?: string;
        status?: string;
        start_date?: string;
        end_date?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function SMSConfig({ configs, filters }: SMSConfigProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-s-m-s-setting') || can('delete-s-m-s-setting');
    const canFilter = can('can-s-m-s-setting-filter');
    const canDownload = can('can-s-m-s-setting-download');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingConfig, setEditingConfig] = useState<SMSConfig | null>(null);
    const [deletingConfig, setDeletingConfig] = useState<SMSConfig | null>(null);
    const [selectedConfigs, setSelectedConfigs] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'created_at');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        url: '',
        api_key: '',
        sender_id: '',
        status: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingConfig) {
            put(`/sms-configs/${editingConfig.id}`, {
                onSuccess: () => {
                    setEditingConfig(null);
                    reset();
                },
            });
        } else {
            post('/sms-configs', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (config: SMSConfig) => {
        setEditingConfig(config);
        setData({
            url: config.url,
            api_key: config.api_key,
            sender_id: config.sender_id,
            status: config.status,
        });
    };

    const handleDelete = (config: SMSConfig) => {
        setDeletingConfig(config);
    };

    const confirmDelete = () => {
        if (deletingConfig) {
            router.delete(`/sms-configs/${deletingConfig.id}`, {
                onSuccess: () => setDeletingConfig(null),
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/sms-configs/bulk/delete', {
            data: { ids: selectedConfigs },
            onSuccess: () => {
                setSelectedConfigs([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const applyFilters = () => {
        router.get(
            '/sms-configs',
            {
                search: search || undefined,
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
        setStatus('all');
        setStartDate('');
        setEndDate('');
        router.get(
            '/sms-configs',
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
            '/sms-configs',
            {
                search: search || undefined,
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
            '/sms-configs',
            {
                search: search || undefined,
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
        if (selectedConfigs.length === configs.data.length) {
            setSelectedConfigs([]);
        } else {
            setSelectedConfigs(configs.data.map((config) => config.id));
        }
    };

    const toggleSelectConfig = (configId: number) => {
        if (selectedConfigs.includes(configId)) {
            setSelectedConfigs(selectedConfigs.filter((id) => id !== configId));
        } else {
            setSelectedConfigs([...selectedConfigs, configId]);
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
            <Head title="SMS Config" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            SMS Configuration
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage SMS provider settings and configurations
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedConfigs.length > 0 && can('delete-s-m-s-setting') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedConfigs.length})
                            </Button>
                        )}
                        {canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (search) params.append('search', search);
                                if (status !== 'all') params.append('status', status);
                                if (startDate) params.append('start_date', startDate);
                                if (endDate) params.append('end_date', endDate);
                                if (sortBy) params.append('sort_by', sortBy);
                                if (sortOrder) params.append('sort_order', sortOrder);
                                window.location.href = `/sms-configs/download-pdf?${params.toString()}`;
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                        )}
                        {can('create-s-m-s-setting') && (
                        <Button onClick={() => setIsCreateOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Config
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
                                <Label className="dark:text-gray-200">
                                    Search
                                </Label>
                                <Input
                                    placeholder="Search configs..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
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
                                            '/sms-configs',
                                            {
                                                search: search || undefined,
                                                status: value === 'all' ? undefined : value,
                                                start_date: startDate || undefined,
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
                                        <SelectItem value="all">All status</SelectItem>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
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
                                <Button onClick={applyFilters} className="px-4">
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
                                                    selectedConfigs.length === configs.data.length &&
                                                    configs.data.length > 0
                                                }
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('url')}
                                        >
                                            <div className="flex items-center gap-1">
                                                URL
                                                {sortBy === 'url' &&
                                                    (sortOrder === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ))}
                                            </div>
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            API Key
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Sender ID
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
                                    {configs.data.length > 0 ? (
                                        configs.data.map((config) => (
                                            <tr
                                                key={config.id}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedConfigs.includes(config.id)}
                                                        onChange={() => toggleSelectConfig(config.id)}
                                                        className="rounded border-gray-300 dark:border-gray-600"
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {config.url}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {config.api_key}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {config.sender_id}
                                                </td>
                                                <td className="p-4">
                                                    <span className={`px-2 py-1 rounded text-xs ${
                                                        config.status
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                    }`}>
                                                        {config.status ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                {hasActionPermission && (
                                                <td className="p-4">
                                                    <div className="flex gap-2">
                                                        {can('update-s-m-s-setting') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleEdit(config)}
                                                            className="text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                        )}
                                                        {can('delete-s-m-s-setting') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(config)}
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
                                                colSpan={hasActionPermission ? 6 : 5}
                                                className="p-8 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <SettingsIcon className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No SMS configurations found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={configs.current_page}
                            lastPage={configs.last_page}
                            from={configs.from}
                            to={configs.to}
                            total={configs.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get(
                                    '/sms-configs',
                                    {
                                        search: search || undefined,
                                        status: status === 'all' ? undefined : status,
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
                    title="Create SMS Config"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                    wide={true}
                >
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="url" className="dark:text-gray-200">
                                URL
                            </Label>
                            <Input
                                id="url"
                                value={data.url}
                                onChange={(e) => setData('url', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.url && (
                                <span className="text-sm text-red-500">{errors.url}</span>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="api_key" className="dark:text-gray-200">
                                API Key
                            </Label>
                            <Input
                                id="api_key"
                                value={data.api_key}
                                onChange={(e) => setData('api_key', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.api_key && (
                                <span className="text-sm text-red-500">{errors.api_key}</span>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="sender_id" className="dark:text-gray-200">
                                Sender ID
                            </Label>
                            <Input
                                id="sender_id"
                                value={data.sender_id}
                                onChange={(e) => setData('sender_id', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.sender_id && (
                                <span className="text-sm text-red-500">{errors.sender_id}</span>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="status" className="dark:text-gray-200">
                                Status
                            </Label>
                            <Select
                                value={data.status ? 'true' : 'false'}
                                onValueChange={(value) => setData('status', value === 'true')}
                            >
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="true">Active</SelectItem>
                                    <SelectItem value="false">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.status && (
                                <span className="text-sm text-red-500">{errors.status}</span>
                            )}
                        </div>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingConfig}
                    onClose={() => setEditingConfig(null)}
                    title="Edit SMS Config"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                    wide={true}
                >
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="edit-url" className="dark:text-gray-200">
                                URL
                            </Label>
                            <Input
                                id="edit-url"
                                value={data.url}
                                onChange={(e) => setData('url', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.url && (
                                <span className="text-sm text-red-500">{errors.url}</span>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="edit-api_key" className="dark:text-gray-200">
                                API Key
                            </Label>
                            <Input
                                id="edit-api_key"
                                value={data.api_key}
                                onChange={(e) => setData('api_key', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.api_key && (
                                <span className="text-sm text-red-500">{errors.api_key}</span>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="edit-sender_id" className="dark:text-gray-200">
                                Sender ID
                            </Label>
                            <Input
                                id="edit-sender_id"
                                value={data.sender_id}
                                onChange={(e) => setData('sender_id', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.sender_id && (
                                <span className="text-sm text-red-500">{errors.sender_id}</span>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="edit-status" className="dark:text-gray-200">
                                Status
                            </Label>
                            <Select
                                value={data.status ? 'true' : 'false'}
                                onValueChange={(value) => setData('status', value === 'true')}
                            >
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="true">Active</SelectItem>
                                    <SelectItem value="false">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.status && (
                                <span className="text-sm text-red-500">{errors.status}</span>
                            )}
                        </div>
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingConfig}
                    onClose={() => setDeletingConfig(null)}
                    onConfirm={confirmDelete}
                    title="Delete SMS Config"
                    message={`Are you sure you want to delete the SMS config "${deletingConfig?.url}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Configs"
                    message={`Are you sure you want to delete ${selectedConfigs.length} selected configs? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}