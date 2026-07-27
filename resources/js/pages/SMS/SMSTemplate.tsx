import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteModal } from '@/components/ui/delete-modal';
import { FormModal } from '@/components/ui/form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
    MessageSquare,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface SMSTemplate {
    id: number;
    title: string;
    type: string;
    message: string;
    status: boolean;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'SMS Templates',
        href: '/sms-templates',
    },
];

interface SMSTemplateProps {
    smsTemplates: {
        data: SMSTemplate[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    filters: {
        search?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function SMSTemplate({ smsTemplates, filters }: SMSTemplateProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-s-m-s-template') || can('delete-s-m-s-template');
    const canFilter = can('can-s-m-s-template-filter');
    const canDownload = can('can-s-m-s-template-download');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingTemplate, setEditingTemplate] = useState<SMSTemplate | null>(null);
    const [deletingTemplate, setDeletingTemplate] = useState<SMSTemplate | null>(null);
    const [selectedTemplates, setSelectedTemplates] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'created_at');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        title: '',
        type: '',
        message: '',
        status: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingTemplate) {
            put(`/sms-templates/${editingTemplate.id}`, {
                onSuccess: () => {
                    setEditingTemplate(null);
                    reset();
                },
            });
        } else {
            post('/sms-templates', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (template: SMSTemplate) => {
        setEditingTemplate(template);
        setData({
            title: template.title,
            type: template.type,
            message: template.message,
            status: template.status,
        });
    };

    const handleDelete = (template: SMSTemplate) => {
        setDeletingTemplate(template);
    };

    const confirmDelete = () => {
        if (deletingTemplate) {
            router.delete(`/sms-templates/${deletingTemplate.id}`, {
                onSuccess: () => setDeletingTemplate(null),
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/sms-templates/bulk/delete', {
            data: { ids: selectedTemplates },
            onSuccess: () => {
                setSelectedTemplates([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const applyFilters = () => {
        router.get(
            '/sms-templates',
            {
                search: search || undefined,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            '/sms-templates',
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
            '/sms-templates',
            {
                search: search || undefined,
                sort_by: column,
                sort_order: newOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/sms-templates',
            {
                search: search || undefined,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
                page,
            },
            { preserveState: true },
        );
    };

    const toggleSelectAll = () => {
        if (selectedTemplates.length === smsTemplates.data.length) {
            setSelectedTemplates([]);
        } else {
            setSelectedTemplates(smsTemplates.data.map((template) => template.id));
        }
    };

    const toggleSelectTemplate = (templateId: number) => {
        if (selectedTemplates.includes(templateId)) {
            setSelectedTemplates(selectedTemplates.filter((id) => id !== templateId));
        } else {
            setSelectedTemplates([...selectedTemplates, templateId]);
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
            <Head title="SMS Templates" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            SMS Templates
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage SMS message templates
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedTemplates.length > 0 && can('delete-s-m-s-template') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedTemplates.length})
                            </Button>
                        )}
                        {canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (search) params.append('search', search);
                                if (sortBy) params.append('sort_by', sortBy);
                                if (sortOrder) params.append('sort_order', sortOrder);
                                window.location.href = `/sms-templates/download-pdf?${params.toString()}`;
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                        )}
                        {can('create-s-m-s-template') && (
                        <Button onClick={() => setIsCreateOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Template
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
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <Label className="dark:text-gray-200">
                                    Search
                                </Label>
                                <Input
                                    placeholder="Search templates..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
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
                                                    selectedTemplates.length ===
                                                    smsTemplates.data.length &&
                                                    smsTemplates.data.length > 0
                                                }
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('title')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Title
                                                {sortBy === 'title' &&
                                                    (sortOrder === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ))}
                                            </div>
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('type')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Type
                                                {sortBy === 'type' &&
                                                    (sortOrder === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ))}
                                            </div>
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Message
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('status')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Status
                                                {sortBy === 'status' &&
                                                    (sortOrder === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ))}
                                            </div>
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('created_at')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Created At
                                                {sortBy === 'created_at' &&
                                                    (sortOrder === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ))}
                                            </div>
                                        </th>
                                        {hasActionPermission && (
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Actions
                                        </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {smsTemplates.data.length > 0 ? (
                                        smsTemplates.data.map((template) => (
                                            <tr
                                                key={template.id}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedTemplates.includes(
                                                            template.id,
                                                        )}
                                                        onChange={() =>
                                                            toggleSelectTemplate(
                                                                template.id,
                                                            )
                                                        }
                                                        className="rounded border-gray-300 dark:border-gray-600"
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {template.title}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {template.type}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300 max-w-xs truncate">
                                                    {template.message}
                                                </td>
                                                <td className="p-4">
                                                    <span className={`px-2 py-1 rounded text-xs ${template.status
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                        }`}>
                                                        {template.status ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {new Date(template.created_at).toLocaleDateString()}
                                                </td>
                                                {hasActionPermission && (
                                                <td className="p-4">
                                                    <div className="flex gap-2">
                                                        {can('update-s-m-s-template') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleEdit(template)
                                                            }
                                                            className="text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                        )}
                                                        {can('delete-s-m-s-template') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    template,
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
                                                <MessageSquare className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No SMS templates found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={smsTemplates.current_page}
                            lastPage={smsTemplates.last_page}
                            from={smsTemplates.from}
                            to={smsTemplates.to}
                            total={smsTemplates.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get(
                                    '/sms-templates',
                                    {
                                        search: search || undefined,
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
                    title="Create SMS Template"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                >
                    <div>
                        <Label htmlFor="title" className="dark:text-gray-200">
                            Title
                        </Label>
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.title && (
                            <span className="text-sm text-red-500">
                                {errors.title}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="type" className="dark:text-gray-200">
                            Type
                        </Label>
                        <Select
                            value={data.type}
                            onValueChange={(value) => setData('type', value)}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="welcome">Welcome</SelectItem>
                                <SelectItem value="notification">Notification</SelectItem>
                                <SelectItem value="reminder">Reminder</SelectItem>
                                <SelectItem value="promotional">Promotional</SelectItem>
                                <SelectItem value="alert">Alert</SelectItem>
                            </SelectContent>
                        </Select>
                        {errors.type && (
                            <span className="text-sm text-red-500">
                                {errors.type}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="message" className="dark:text-gray-200">
                            Message
                        </Label>
                        <Textarea
                            id="message"
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            rows={4}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            placeholder="Enter your SMS message here..."
                        />
                        <div className="mt-2">
                            <div className="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                Click variables to insert:
                            </div>
                            <div className="flex flex-wrap gap-1">
                                {[
                                    'customer_name', 'total_payment', 'total_due', 'account_number',
                                    'customer_mobile', 'customer_email', 'total_cradit', 'security_deposit'
                                ].map((variable) => (
                                    <button
                                        key={variable}
                                        type="button"
                                        onClick={() => {
                                            const textarea = document.getElementById('message') as HTMLTextAreaElement;
                                            const cursorPos = textarea.selectionStart;
                                            const textBefore = data.message.substring(0, cursorPos);
                                            const textAfter = data.message.substring(cursorPos);
                                            const newText = textBefore + `{{${variable}}}` + textAfter;
                                            setData('message', newText);
                                            setTimeout(() => {
                                                textarea.focus();
                                                textarea.setSelectionRange(cursorPos + variable.length + 4, cursorPos + variable.length + 4);
                                            }, 0);
                                        }}
                                        className="px-2 py-1 text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors cursor-pointer"
                                    >
                                        {variable}
                                    </button>
                                ))}
                            </div>
                        </div>
                        {errors.message && (
                            <span className="text-sm text-red-500">
                                {errors.message}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label className="dark:text-gray-200">
                            Status
                        </Label>
                        <Select
                            value={data.status ? 'active' : 'inactive'}
                            onValueChange={(value) => setData('status', value === 'active')}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingTemplate}
                    onClose={() => setEditingTemplate(null)}
                    title="Edit SMS Template"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                >
                    <div>
                        <Label htmlFor="edit-title" className="dark:text-gray-200">
                            Title
                        </Label>
                        <Input
                            id="edit-title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.title && (
                            <span className="text-sm text-red-500">
                                {errors.title}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="edit-type" className="dark:text-gray-200">
                            Type
                        </Label>
                        <Select
                            value={data.type}
                            onValueChange={(value) => setData('type', value)}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="welcome">Welcome</SelectItem>
                                <SelectItem value="notification">Notification</SelectItem>
                                <SelectItem value="reminder">Reminder</SelectItem>
                                <SelectItem value="promotional">Promotional</SelectItem>
                                <SelectItem value="alert">Alert</SelectItem>
                            </SelectContent>
                        </Select>
                        {errors.type && (
                            <span className="text-sm text-red-500">
                                {errors.type}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="edit-message" className="dark:text-gray-200">
                            Message
                        </Label>
                        <Textarea
                            id="edit-message"
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            rows={4}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            placeholder="Enter your SMS message here..."
                        />
                        <div className="mt-2">
                            <div className="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                Click variables to insert:
                            </div>
                            <div className="flex flex-wrap gap-1">
                                {[
                                    'customer_name', 'total_payment', 'total_due', 'account_number',
                                    'customer_mobile', 'customer_email', 'total_cradit', 'security_deposit'
                                ].map((variable) => (
                                    <button
                                        key={variable}
                                        type="button"
                                        onClick={() => {
                                            const textarea = document.getElementById('edit-message') as HTMLTextAreaElement;
                                            const cursorPos = textarea.selectionStart;
                                            const textBefore = data.message.substring(0, cursorPos);
                                            const textAfter = data.message.substring(cursorPos);
                                            const newText = textBefore + `{{${variable}}}` + textAfter;
                                            setData('message', newText);
                                            setTimeout(() => {
                                                textarea.focus();
                                                textarea.setSelectionRange(cursorPos + variable.length + 4, cursorPos + variable.length + 4);
                                            }, 0);
                                        }}
                                        className="px-2 py-1 text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors cursor-pointer"
                                    >
                                        {variable}
                                    </button>
                                ))}
                            </div>
                        </div>
                        {errors.message && (
                            <span className="text-sm text-red-500">
                                {errors.message}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label className="dark:text-gray-200">
                            Status
                        </Label>
                        <Select
                            value={data.status ? 'active' : 'inactive'}
                            onValueChange={(value) => setData('status', value === 'active')}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingTemplate}
                    onClose={() => setDeletingTemplate(null)}
                    onConfirm={confirmDelete}
                    title="Delete SMS Template"
                    message={`Are you sure you want to delete the template "${deletingTemplate?.title}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Templates"
                    message={`Are you sure you want to delete ${selectedTemplates.length} selected templates? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}