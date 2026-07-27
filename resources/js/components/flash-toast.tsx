import { Toast, useToast } from '@/components/ui/toast';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

export function FlashToast() {
    const { flash } = usePage().props as { flash: { success?: string; error?: string; warning?: string } };
    const { toast, success, error, warning, hideToast } = useToast();

    useEffect(() => {
        if (flash.success) {
            success(flash.success);
        }
        if (flash.error) {
            error(flash.error);
        }
        if (flash.warning) {
            warning(flash.warning);
        }
    }, [flash]);

    return (
        <Toast
            type={toast.type}
            message={toast.message}
            isVisible={toast.isVisible}
            onClose={hideToast}
        />
    );
}