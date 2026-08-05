import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useFocusFirstError } from '@/hooks/use-focus-first-error';
import { usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { ReactNode, useRef } from 'react';

interface FormModalProps {
    isOpen: boolean;
    onClose: () => void;
    title: string;
    onSubmit: (event: React.FormEvent) => void;
    processing: boolean;
    submitText: string;
    children: ReactNode;
    className?: string;
    description?: string;
    wide?: boolean;
    errors?: Record<string, string | undefined>;
    submitDisabled?: boolean;
    footerActions?: ReactNode;
}

export function FormModal({
    isOpen,
    onClose,
    title,
    onSubmit,
    processing,
    submitText,
    children,
    className,
    description,
    wide = false,
    errors = {},
    submitDisabled = false,
    footerActions,
}: FormModalProps) {
    const formRef = useRef<HTMLFormElement>(null);
    const pageErrors = usePage().props.errors as Record<
        string,
        string | undefined
    >;
    const validationErrors =
        Object.keys(errors).length > 0 ? errors : pageErrors;
    const defaultClassName = wide ? 'max-w-6xl' : 'max-w-2xl';
    const finalClassName = className || defaultClassName;

    useFocusFirstError(formRef, validationErrors, isOpen);

    const handleOpenChange = (open: boolean) => {
        if (!open && !processing) {
            onClose();
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleOpenChange}>
            <DialogContent
                className={`max-h-[calc(100dvh-2rem)] overflow-hidden dark:bg-gray-800 ${finalClassName} !flex !flex-col`}
                onEscapeKeyDown={(event) => {
                    if (processing) {
                        event.preventDefault();
                    }
                }}
                onPointerDownOutside={(event) => {
                    if (processing) {
                        event.preventDefault();
                    }
                }}
                aria-busy={processing}
            >
                <DialogHeader className="shrink-0 border-b pb-4 dark:border-gray-700">
                    <DialogTitle className="dark:text-white">
                        {title}
                    </DialogTitle>
                    <DialogDescription className={description ? '' : 'sr-only'}>
                        {description ||
                            `Complete the ${title.toLowerCase()} form.`}
                    </DialogDescription>
                </DialogHeader>
                <form
                    ref={formRef}
                    onSubmit={onSubmit}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <div className="flex-1 space-y-4 overflow-y-auto py-4 pr-2">
                        {children}
                    </div>
                    <div className="flex shrink-0 justify-end gap-2 border-t pt-4 dark:border-gray-700">
                        {footerActions}
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={onClose}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || submitDisabled}
                        >
                            {processing && (
                                <LoaderCircle
                                    className="h-4 w-4 animate-spin"
                                    aria-hidden="true"
                                />
                            )}
                            {processing ? `${submitText}...` : submitText}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
