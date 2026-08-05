import { DocumentViewerButton } from '@/components/documents/document-viewer-button';
import {
    DocumentViewerModal,
    type ViewerDocument,
} from '@/components/documents/document-viewer-modal';
import InputError from '@/components/input-error';
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
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/usePermission';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Edit,
    FileImage,
    FileText,
    Filter,
    FolderOpen,
    Image,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';

interface CompanySetting {
    id: number;
    company_name: string;
}

interface CompanyDocument {
    id: number;
    company_setting_id: number;
    document_name: string;
    document_type: 'image' | 'pdf';
    start_date: string | null;
    end_date: string | null;
    file_url: string | null;
    file_name: string;
    sort_order: number;
    remarks: string | null;
    status: boolean;
    created_at: string;
    updated_at: string;
}

interface PageProps {
    companySetting: CompanySetting;
    companyDocuments: {
        data: CompanyDocument[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    viewerDocuments: CompanyDocument[];
    filters: {
        search?: string;
        document_type?: string;
        status?: string;
        start_date?: string;
        end_date?: string;
        per_page?: number;
    };
    upload: {
        max_file_kb: number;
    };
}

interface MetadataValues {
    document_name: string;
    start_date: string;
    end_date: string;
    remarks: string;
    status: boolean;
}

const acceptedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'application/pdf',
];

export default function CompanyDocumentsIndex({
    companySetting,
    companyDocuments,
    viewerDocuments,
    filters,
    upload,
}: PageProps) {
    const { can } = usePermission();
    const canCreate = can('company-document-create');
    const canUpdate = can('company-document-update');
    const canDelete = can('company-document-delete');
    const baseUrl = `/company-settings/${companySetting.id}/documents`;
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingDocument, setEditingDocument] =
        useState<CompanyDocument | null>(null);
    const [deletingDocument, setDeletingDocument] =
        useState<CompanyDocument | null>(null);
    const [viewerOpen, setViewerOpen] = useState(false);
    const [initialDocumentId, setInitialDocumentId] = useState<string | null>(
        null,
    );
    const [search, setSearch] = useState(filters.search || '');
    const [documentType, setDocumentType] = useState(
        filters.document_type || 'all',
    );
    const [status, setStatus] = useState(filters.status || 'all');
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');
    const [perPage, setPerPage] = useState(filters.per_page || 10);
    const createForm = useForm({
        document_name: '',
        start_date: '',
        end_date: '',
        remarks: '',
        status: true,
        files: [] as File[],
    });
    const editForm = useForm({
        document_name: '',
        start_date: '',
        end_date: '',
        remarks: '',
        status: true,
        file: null as File | null,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Company Settings', href: '/company-settings' },
        {
            title: companySetting.company_name,
            href: `/company-settings/${companySetting.id}`,
        },
        { title: 'Company Documents', href: baseUrl },
    ];

    const viewerCollection = useMemo<ViewerDocument[]>(
        () =>
            viewerDocuments
                .filter((document) => Boolean(document.file_url))
                .map((document) => ({
                    id: viewerId(document.id),
                    title: document.document_name,
                    url: document.file_url as string,
                    kind: document.document_type,
                })),
        [viewerDocuments],
    );
    const viewerIds = useMemo(
        () => new Set(viewerCollection.map((document) => document.id)),
        [viewerCollection],
    );

    const visit = (
        overrides: Record<string, string | number | undefined> = {},
    ) => {
        router.get(
            baseUrl,
            {
                search: search || undefined,
                document_type:
                    documentType === 'all' ? undefined : documentType,
                status: status === 'all' ? undefined : status,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                per_page: perPage,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true },
        );
    };
    const clearFilters = () => {
        setSearch('');
        setDocumentType('all');
        setStatus('all');
        setStartDate('');
        setEndDate('');
        router.get(
            baseUrl,
            { per_page: perPage },
            { preserveState: true, preserveScroll: true },
        );
    };

    const openCreate = () => {
        createForm.reset();
        createForm.clearErrors();
        setIsCreateOpen(true);
    };

    const openEdit = (document: CompanyDocument) => {
        editForm.clearErrors();
        editForm.setData({
            document_name: document.document_name,
            start_date: document.start_date || '',
            end_date: document.end_date || '',
            remarks: document.remarks || '',
            status: document.status,
            file: null,
        });
        setEditingDocument(document);
    };

    const openViewer = (document: CompanyDocument) => {
        const id = viewerId(document.id);

        if (!viewerIds.has(id)) return;

        setInitialDocumentId(id);
        setViewerOpen(true);
    };

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        createForm.post(baseUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!editingDocument) return;

        editForm.transform((data) => ({ ...data, _method: 'put' }));
        editForm.post(`${baseUrl}/${editingDocument.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setEditingDocument(null);
                editForm.reset();
            },
        });
    };

    const validateFiles = (files: File[]): string | undefined => {
        if (files.some((file) => !acceptedMimeTypes.includes(file.type))) {
            return 'Select JPG, JPEG, PNG, WEBP, or PDF files only.';
        }

        if (files.some((file) => file.size > upload.max_file_kb * 1024)) {
            return `Each file must not exceed ${formatMegabytes(upload.max_file_kb)}.`;
        }

        return undefined;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`Company Documents - ${companySetting.company_name}`}
            />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Company Documents
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            {companySetting.company_name}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href={`/company-settings/${companySetting.id}`}>
                            <Button variant="secondary">
                                <ArrowLeft className="h-4 w-4" />
                                Company Details
                            </Button>
                        </Link>
                        {canCreate && (
                            <Button onClick={openCreate}>
                                <Plus className="h-4 w-4" />
                                Add Documents
                            </Button>
                        )}
                    </div>
                </div>

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 dark:text-white">
                            <Filter className="h-5 w-5" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-6">
                            <div>
                                <Label htmlFor="document-search">Search</Label>
                                <Input
                                    id="document-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Name, type, date..."
                                />
                            </div>
                            <div>
                                <Label>Document Type</Label>
                                <Select
                                    value={documentType}
                                    onValueChange={setDocumentType}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Types
                                        </SelectItem>
                                        <SelectItem value="image">
                                            Image
                                        </SelectItem>
                                        <SelectItem value="pdf">PDF</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Status</Label>
                                <Select
                                    value={status}
                                    onValueChange={setStatus}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Statuses
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
                            <div>
                                <Label htmlFor="filter-start-date">
                                    Start Date From
                                </Label>
                                <Input
                                    id="filter-start-date"
                                    type="date"
                                    value={startDate}
                                    onChange={(event) =>
                                        setStartDate(event.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <Label htmlFor="filter-end-date">
                                    End Date To
                                </Label>
                                <Input
                                    id="filter-end-date"
                                    type="date"
                                    value={endDate}
                                    onChange={(event) =>
                                        setEndDate(event.target.value)
                                    }
                                />
                            </div>
                            <div className="flex items-end gap-2">
                                <Button onClick={() => visit({ page: 1 })}>
                                    Apply
                                </Button>
                                <Button
                                    variant="secondary"
                                    onClick={clearFilters}
                                    aria-label="Clear filters"
                                    title="Clear filters"
                                >
                                    <X className="h-4 w-4" />
                                    Clear
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
                                        <th className="p-4 text-left text-[13px] font-medium">
                                            SL
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium">
                                            Preview
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium">
                                            Document Name
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium">
                                            Document Type
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium">
                                            Start Date
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium">
                                            End Date
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium">
                                            Status
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium">
                                            Created Date
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {companyDocuments.data.length > 0 ? (
                                        companyDocuments.data.map(
                                            (document, index) => (
                                                <tr
                                                    key={document.id}
                                                    className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                                >
                                                    <td className="p-4 text-[13px]">
                                                        {(companyDocuments.current_page -
                                                            1) *
                                                            companyDocuments.per_page +
                                                            index +
                                                            1}
                                                    </td>
                                                    <td className="p-4">
                                                        <DocumentPreview
                                                            document={document}
                                                        />
                                                    </td>
                                                    <td className="p-4 text-[13px] font-medium">
                                                        {document.document_name}
                                                    </td>
                                                    <td className="p-4 text-[13px] uppercase">
                                                        {document.document_type}
                                                    </td>
                                                    <td className="p-4 text-[13px]">
                                                        {formatDate(
                                                            document.start_date,
                                                        )}
                                                    </td>
                                                    <td className="p-4 text-[13px]">
                                                        {formatDate(
                                                            document.end_date,
                                                        )}
                                                    </td>
                                                    <td className="p-4">
                                                        <StatusBadge
                                                            active={
                                                                document.status
                                                            }
                                                        />
                                                    </td>
                                                    <td className="p-4 text-[13px]">
                                                        {formatDate(
                                                            document.created_at,
                                                        )}
                                                    </td>
                                                    <td className="p-4">
                                                        <div className="flex items-center gap-1">
                                                            <DocumentViewerButton
                                                                label="View"
                                                                icon={
                                                                    document.document_type ===
                                                                    'pdf'
                                                                        ? FileText
                                                                        : FileImage
                                                                }
                                                                available={Boolean(
                                                                    document.file_url,
                                                                )}
                                                                onClick={() =>
                                                                    openViewer(
                                                                        document,
                                                                    )
                                                                }
                                                            />
                                                            {canUpdate && (
                                                                <IconButton
                                                                    label="Edit document"
                                                                    onClick={() =>
                                                                        openEdit(
                                                                            document,
                                                                        )
                                                                    }
                                                                >
                                                                    <Edit className="h-4 w-4" />
                                                                </IconButton>
                                                            )}
                                                            {canDelete && (
                                                                <IconButton
                                                                    label="Delete document"
                                                                    destructive
                                                                    onClick={() =>
                                                                        setDeletingDocument(
                                                                            document,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </IconButton>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ),
                                        )
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={9}
                                                className="p-10 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <FolderOpen className="mx-auto mb-3 h-12 w-12" />
                                                No company documents found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={companyDocuments.current_page}
                            lastPage={companyDocuments.last_page}
                            from={companyDocuments.from}
                            to={companyDocuments.to}
                            total={companyDocuments.total}
                            perPage={perPage}
                            onPageChange={(page) => visit({ page })}
                            onPerPageChange={(value) => {
                                setPerPage(value);
                                visit({ per_page: value, page: 1 });
                            }}
                        />
                    </CardContent>
                </Card>
            </div>

            <FormModal
                isOpen={isCreateOpen}
                onClose={() => setIsCreateOpen(false)}
                title="Add Company Documents"
                description="Upload one or more company documents."
                onSubmit={submitCreate}
                processing={createForm.processing}
                submitText="Create"
                errors={createForm.errors}
            >
                <MetadataFields
                    data={createForm.data}
                    errors={createForm.errors}
                    onChange={(key, value) => createForm.setData(key, value)}
                />
                <div>
                    <Label htmlFor="company-document-files">
                        Files <span className="text-red-500">*</span>
                    </Label>
                    <Input
                        key={isCreateOpen ? 'create-open' : 'create-closed'}
                        id="company-document-files"
                        type="file"
                        multiple
                        required
                        accept=".jpg,.jpeg,.png,.webp,.pdf"
                        onChange={(event) => {
                            const files = Array.from(event.target.files || []);
                            const error = validateFiles(files);

                            if (error) {
                                createForm.setError('files', error);
                                createForm.setData('files', []);
                                event.target.value = '';
                                return;
                            }

                            createForm.clearErrors('files');
                            createForm.setData('files', files);
                        }}
                    />
                    {createForm.data.files.length > 0 && (
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {createForm.data.files.length} file
                            {createForm.data.files.length === 1 ? '' : 's'}{' '}
                            selected
                        </p>
                    )}
                    <InputError message={firstFileError(createForm.errors)} />
                </div>
            </FormModal>

            <FormModal
                isOpen={Boolean(editingDocument)}
                onClose={() => setEditingDocument(null)}
                title="Edit Company Document"
                description="Update document metadata or replace its file."
                onSubmit={submitEdit}
                processing={editForm.processing}
                submitText="Update"
                errors={editForm.errors}
            >
                <MetadataFields
                    data={editForm.data}
                    errors={editForm.errors}
                    onChange={(key, value) => editForm.setData(key, value)}
                />
                {editingDocument && (
                    <div>
                        <Label htmlFor="company-document-file">
                            Replacement File
                        </Label>
                        <Input
                            key={editingDocument.id}
                            id="company-document-file"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                            onChange={(event) => {
                                const file = event.target.files?.[0] || null;
                                const error = validateFiles(file ? [file] : []);

                                if (error) {
                                    editForm.setError('file', error);
                                    editForm.setData('file', null);
                                    event.target.value = '';
                                    return;
                                }

                                editForm.clearErrors('file');
                                editForm.setData('file', file);
                            }}
                        />
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Leave empty to keep the current{' '}
                            {editingDocument.document_type.toUpperCase()} file.
                        </p>
                        <InputError message={editForm.errors.file} />
                    </div>
                )}
            </FormModal>

            <DeleteModal
                isOpen={Boolean(deletingDocument)}
                onClose={() => setDeletingDocument(null)}
                onConfirm={() => {
                    if (!deletingDocument) return;

                    router.delete(`${baseUrl}/${deletingDocument.id}`, {
                        preserveScroll: true,
                        onSuccess: () => setDeletingDocument(null),
                    });
                }}
                title="Delete Company Document"
                message={`Are you sure you want to delete "${deletingDocument?.document_name}"? The stored file will also be removed.`}
            />

            {viewerOpen && (
                <DocumentViewerModal
                    open
                    documents={viewerCollection}
                    initialDocumentId={initialDocumentId}
                    onOpenChange={setViewerOpen}
                />
            )}
        </AppLayout>
    );
}

function MetadataFields({
    data,
    errors,
    onChange,
}: {
    data: MetadataValues;
    errors: Partial<Record<keyof MetadataValues, string>>;
    onChange: (key: keyof MetadataValues, value: string | boolean) => void;
}) {
    return (
        <>
            <div>
                <Label htmlFor="document-name">
                    Document Name <span className="text-red-500">*</span>
                </Label>
                <Input
                    id="document-name"
                    value={data.document_name}
                    required
                    maxLength={255}
                    error={Boolean(errors.document_name)}
                    onChange={(event) =>
                        onChange('document_name', event.target.value)
                    }
                />
                <InputError message={errors.document_name} />
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <Label htmlFor="document-start-date">Start Date</Label>
                    <Input
                        id="document-start-date"
                        type="date"
                        value={data.start_date}
                        error={Boolean(errors.start_date)}
                        onChange={(event) =>
                            onChange('start_date', event.target.value)
                        }
                    />
                    <InputError message={errors.start_date} />
                </div>
                <div>
                    <Label htmlFor="document-end-date">End Date</Label>
                    <Input
                        id="document-end-date"
                        type="date"
                        value={data.end_date}
                        min={data.start_date || undefined}
                        error={Boolean(errors.end_date)}
                        onChange={(event) =>
                            onChange('end_date', event.target.value)
                        }
                    />
                    <InputError message={errors.end_date} />
                </div>
            </div>
            <div>
                <Label htmlFor="document-remarks">Remarks</Label>
                <Textarea
                    id="document-remarks"
                    value={data.remarks}
                    maxLength={5000}
                    onChange={(event) =>
                        onChange('remarks', event.target.value)
                    }
                />
                <InputError message={errors.remarks} />
            </div>
            <label className="flex items-center gap-2 text-sm font-medium dark:text-gray-200">
                <input
                    type="checkbox"
                    checked={data.status}
                    onChange={(event) =>
                        onChange('status', event.target.checked)
                    }
                />
                Active
            </label>
        </>
    );
}

function DocumentPreview({ document }: { document: CompanyDocument }) {
    if (document.document_type === 'image' && document.file_url) {
        return (
            <img
                src={document.file_url}
                alt=""
                loading="lazy"
                decoding="async"
                className="h-10 w-12 rounded object-cover"
            />
        );
    }

    return document.document_type === 'pdf' ? (
        <FileText className="h-8 w-8 text-red-500" aria-label="PDF document" />
    ) : (
        <Image className="h-8 w-8 text-gray-400" aria-label="Image document" />
    );
}

function StatusBadge({ active }: { active: boolean }) {
    return (
        <span
            className={`inline-flex rounded px-2 py-1 text-xs ${
                active
                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
            }`}
        >
            {active ? 'Active' : 'Inactive'}
        </span>
    );
}

function IconButton({
    label,
    destructive = false,
    onClick,
    children,
}: {
    label: string;
    destructive?: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={onClick}
            aria-label={label}
            title={label}
            className={destructive ? 'text-red-600 hover:text-red-700' : ''}
        >
            {children}
        </Button>
    );
}

function viewerId(id: number): string {
    return `company-document-${id}`;
}

function formatDate(date: string | null): string {
    if (!date) return '-';

    return new Date(`${date}T00:00:00`).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatMegabytes(kilobytes: number): string {
    const megabytes = kilobytes / 1024;

    return `${Number.isInteger(megabytes) ? megabytes : megabytes.toFixed(1)} MB`;
}

function firstFileError(errors: Record<string, string>): string | undefined {
    return Object.entries(errors).find(
        ([key]) => key === 'files' || key.startsWith('files.'),
    )?.[1];
}
