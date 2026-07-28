import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { AlertTriangle, LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

interface DeleteModalProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void | Promise<void>;
    title: string;
    message: string;
    processing?: boolean;
}

export function DeleteModal({
    isOpen,
    onClose,
    onConfirm,
    title,
    message,
    processing = false,
}: DeleteModalProps) {
    const [confirming, setConfirming] = useState(false);
    const isBusy = processing || confirming;

    useEffect(() => {
        if (!isOpen) {
            setConfirming(false);
        }
    }, [isOpen]);

    const handleOpenChange = (open: boolean) => {
        if (!open && !isBusy) {
            onClose();
        }
    };

    const handleConfirm = () => {
        if (isBusy) {
            return;
        }

        setConfirming(true);
        const result = onConfirm();

        if (result instanceof Promise) {
            void result.finally(() => setConfirming(false));
            return;
        }

        window.setTimeout(() => setConfirming(false), 1000);
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleOpenChange}>
            <DialogContent
                className="dark:bg-gray-800"
                onEscapeKeyDown={(event) => {
                    if (isBusy) {
                        event.preventDefault();
                    }
                }}
                onPointerDownOutside={(event) => {
                    if (isBusy) {
                        event.preventDefault();
                    }
                }}
                aria-busy={isBusy}
            >
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 dark:text-white">
                        <AlertTriangle
                            className="h-5 w-5 text-red-500"
                            aria-hidden="true"
                        />
                        {title}
                    </DialogTitle>
                    <DialogDescription>{message}</DialogDescription>
                </DialogHeader>
                <div className="flex justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={isBusy}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={handleConfirm}
                        disabled={isBusy}
                    >
                        {isBusy && (
                            <LoaderCircle
                                className="h-4 w-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        {isBusy ? 'Deleting...' : 'Delete'}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
