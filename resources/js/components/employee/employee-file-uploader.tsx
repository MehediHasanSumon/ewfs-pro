import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FileText, Image, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface EmployeeFileUploaderProps {
    id: string;
    label: string;
    value: File | null;
    existingUrl?: string | null;
    existingPath?: string | null;
    accept: string;
    acceptedTypes: string[];
    maxSizeKb: number;
    error?: string;
    removeExisting?: boolean;
    allowPdf?: boolean;
    onChange: (file: File | null) => void;
    onRemove: () => void;
}

function EmployeeFileUploader({
    id,
    label,
    value,
    existingUrl,
    existingPath,
    accept,
    acceptedTypes,
    maxSizeKb,
    error,
    removeExisting = false,
    allowPdf = false,
    onChange,
    onRemove,
}: EmployeeFileUploaderProps) {
    const [localError, setLocalError] = useState<string>();
    const [inputVersion, setInputVersion] = useState(0);
    const previewUrl = useMemo(
        () => (value ? URL.createObjectURL(value) : null),
        [value],
    );

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const validateAndSelect = (file: File | null): boolean => {
        setLocalError(undefined);

        if (!file) {
            onChange(null);
            return true;
        }

        if (!acceptedTypes.includes(file.type)) {
            setLocalError(
                `Select a supported ${allowPdf ? 'image or PDF' : 'image'} file.`,
            );
            return false;
        }

        if (file.size > maxSizeKb * 1024) {
            setLocalError(
                `File size must not exceed ${(maxSizeKb / 1024).toFixed(maxSizeKb % 1024 === 0 ? 0 : 1)} MB.`,
            );
            return false;
        }

        onChange(file);

        return true;
    };

    const visibleUrl = value
        ? previewUrl
        : !removeExisting
          ? (existingUrl ?? null)
          : null;
    const visibleName =
        value?.name ??
        (!removeExisting && existingPath
            ? existingPath.split('/').pop()
            : undefined);
    const isPdf =
        value?.type === 'application/pdf' ||
        (!value && Boolean(existingPath?.toLowerCase().endsWith('.pdf')));
    const hasFile = Boolean(value || (!removeExisting && existingPath));

    return (
        <div>
            <Label htmlFor={id} className="dark:text-gray-200">
                {label}
            </Label>
            <Input
                key={inputVersion}
                id={id}
                type="file"
                accept={accept}
                className="mt-1 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                error={Boolean(error || localError)}
                aria-describedby={
                    error || localError ? `${id}-error` : undefined
                }
                onChange={(event) => {
                    if (!validateAndSelect(event.target.files?.[0] ?? null)) {
                        event.target.value = '';
                    }
                }}
            />

            {hasFile && (
                <div className="mt-2 flex min-w-0 items-center gap-3 rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                    {visibleUrl && !isPdf ? (
                        <img
                            src={visibleUrl}
                            alt={`${label} preview`}
                            className="h-14 w-16 shrink-0 rounded object-contain"
                        />
                    ) : isPdf ? (
                        <FileText
                            className="h-10 w-10 shrink-0 text-red-500"
                            aria-hidden="true"
                        />
                    ) : (
                        <Image
                            className="h-10 w-10 shrink-0 text-gray-400"
                            aria-hidden="true"
                        />
                    )}
                    <span className="min-w-0 flex-1 truncate text-xs text-gray-600 dark:text-gray-300">
                        {visibleName}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => {
                            setLocalError(undefined);
                            setInputVersion((current) => current + 1);
                            onRemove();
                        }}
                        title={`Remove ${label}`}
                        aria-label={`Remove ${label}`}
                    >
                        <X className="h-4 w-4" aria-hidden="true" />
                    </Button>
                </div>
            )}
            <InputError id={`${id}-error`} message={localError ?? error} />
        </div>
    );
}

type WrapperProps = Omit<
    EmployeeFileUploaderProps,
    'accept' | 'acceptedTypes' | 'allowPdf'
>;

const imageTypes = ['image/jpeg', 'image/png', 'image/webp'];

export function EmployeeImageUploader(props: WrapperProps) {
    return (
        <EmployeeFileUploader
            {...props}
            accept=".jpg,.jpeg,.png,.webp"
            acceptedTypes={imageTypes}
        />
    );
}

export function EmployeeSignatureUploader(props: WrapperProps) {
    return (
        <EmployeeFileUploader
            {...props}
            accept=".jpg,.jpeg,.png,.webp"
            acceptedTypes={imageTypes}
        />
    );
}

export function EmployeeNidUploader(props: WrapperProps) {
    return (
        <EmployeeFileUploader
            {...props}
            accept=".jpg,.jpeg,.png,.webp,.pdf"
            acceptedTypes={[...imageTypes, 'application/pdf']}
            allowPdf
        />
    );
}
