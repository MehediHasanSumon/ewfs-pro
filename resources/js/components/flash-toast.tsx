import { Toast, useToast } from '@/components/ui/toast';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

export function FlashToast() {
    const { flash } = usePage<SharedData>().props;
    const { toast, showToast, hideToast } = useToast();

    useEffect(() => {
        if (flash.error) {
            showToast('error', flash.error);
            return;
        }

        if (flash.warning) {
            showToast('warning', flash.warning);
            return;
        }

        if (flash.success) {
            showToast('success', flash.success);
            return;
        }

        if (flash.info) {
            showToast('info', flash.info);
        }
    }, [flash.error, flash.info, flash.success, flash.warning, showToast]);

    return (
        <Toast
            type={toast.type}
            message={toast.message}
            isVisible={toast.isVisible}
            onClose={hideToast}
        />
    );
}
