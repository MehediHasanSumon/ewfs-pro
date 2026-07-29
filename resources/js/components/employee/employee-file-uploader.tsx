import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { FileText, Image, Upload, X } from 'lucide-react';
import {
    type DragEvent,
    type KeyboardEvent,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';

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
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [localError, setLocalError] = useState<string>();
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

    const validateAndSelect = (file: File | null) => {
        setLocalError(undefined);

        if (!file) {
            onChange(null);
            return;
        }

        if (!acceptedTypes.includes(file.type)) {
            setLocalError(
                `Select a supported ${allowPdf ? 'image or PDF' : 'image'} file.`,
            );
            return;
        }

        if (file.size > maxSizeKb * 1024) {
            setLocalError(
                `File size must not exceed ${(maxSizeKb / 1024).toFixed(maxSizeKb % 1024 === 0 ? 0 : 1)} MB.`,
            );
            return;
        }

        onChange(file);
    };

    const handleDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setIsDragging(false);
        validateAndSelect(event.dataTransfer.files?.[0] ?? null);
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            inputRef.current?.click();
        }
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
            <div
                role="button"
                tabIndex={0}
                onClick={() => inputRef.current?.click()}
                onKeyDown={handleKeyDown}
                onDragEnter={(event) => {
                    event.preventDefault();
                    setIsDragging(true);
                }}
                onDragOver={(event) => event.preventDefault()}
                onDragLeave={() => setIsDragging(false)}
                onDrop={handleDrop}
                className={cn(
                    'mt-1 flex min-h-32 cursor-pointer items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white p-3 text-center transition-colors dark:border-gray-600 dark:bg-gray-700',
                    isDragging &&
                        'border-indigo-500 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/30',
                    (error || localError) &&
                        'border-red-500 dark:border-red-400',
                )}
                aria-describedby={
                    error || localError ? `${id}-error` : undefined
                }
            >
                <input
                    ref={inputRef}
                    id={id}
                    type="file"
                    accept={accept}
                    className="sr-only"
                    onChange={(event) => {
                        validateAndSelect(event.target.files?.[0] ?? null);
                        event.target.value = '';
                    }}
                />

                {hasFile ? (
                    <div className="flex min-w-0 flex-col items-center gap-2">
                        {visibleUrl && !isPdf ? (
                            <img
                                src={visibleUrl}
                                alt={`${label} preview`}
                                className="h-16 w-20 rounded object-contain"
                            />
                        ) : isPdf ? (
                            <FileText
                                className="h-12 w-12 text-red-500"
                                aria-hidden="true"
                            />
                        ) : (
                            <Image
                                className="h-12 w-12 text-gray-400"
                                aria-hidden="true"
                            />
                        )}
                        <span className="max-w-full truncate text-xs text-gray-600 dark:text-gray-300">
                            {visibleName}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={(event) => {
                                event.stopPropagation();
                                setLocalError(undefined);
                                onRemove();
                            }}
                        >
                            <X className="h-4 w-4" aria-hidden="true" />
                            Remove
                        </Button>
                    </div>
                ) : (
                    <Upload
                        className="h-8 w-8 text-gray-400"
                        aria-label={`Upload ${label}`}
                    />
                )}
            </div>
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
