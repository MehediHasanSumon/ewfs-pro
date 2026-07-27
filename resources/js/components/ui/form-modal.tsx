import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { ReactNode } from 'react';

interface FormModalProps {
    isOpen: boolean;
    onClose: () => void;
    title: string;
    onSubmit: (e: React.FormEvent) => void;
    processing: boolean;
    submitText: string;
    children: ReactNode;
    className?: string;
    description?: string;
    wide?: boolean;
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
    wide = false
}: FormModalProps) {
    const defaultClassName = wide ? 'max-w-6xl' : 'max-w-2xl';
    const finalClassName = className || defaultClassName;
    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className={`dark:bg-gray-800 ${finalClassName} !flex !flex-col overflow-hidden`}>
                <DialogHeader className="pb-4 border-b dark:border-gray-700 flex-shrink-0">
                    <DialogTitle className="dark:text-white">{title}</DialogTitle>
                    {description && (
                        <DialogDescription className="sr-only">
                            {description}
                        </DialogDescription>
                    )}
                </DialogHeader>
                <form onSubmit={onSubmit} className="flex flex-col flex-1 min-h-0">
                    <div className="flex-1 overflow-y-auto pr-2 space-y-4 py-4">
                        {children}
                    </div>
                    <div className="flex gap-2 justify-end pt-4 border-t dark:border-gray-700 flex-shrink-0">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? `${submitText}...` : submitText}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}