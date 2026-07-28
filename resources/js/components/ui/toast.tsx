import {
    AlertTriangle,
    CheckCircle,
    CircleAlert,
    Info,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

interface ToastProps {
    type: ToastType;
    message: string;
    isVisible: boolean;
    onClose: () => void;
    duration?: number;
}

export function Toast({
    type,
    message,
    isVisible,
    onClose,
    duration = 5000,
}: ToastProps) {
    useEffect(() => {
        if (!isVisible || duration <= 0) {
            return;
        }

        const timer = window.setTimeout(onClose, duration);
        return () => window.clearTimeout(timer);
    }, [duration, isVisible, onClose]);

    if (!isVisible) {
        return null;
    }

    const icons = {
        success: CheckCircle,
        error: CircleAlert,
        warning: AlertTriangle,
        info: Info,
    };

    const styles = {
        success:
            'border-green-200 bg-green-50 text-green-800 dark:border-green-700 dark:bg-green-950 dark:text-green-200',
        error:
            'border-red-200 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-950 dark:text-red-200',
        warning:
            'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200',
        info: 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-200',
    };

    const iconStyles = {
        success: 'text-green-500 dark:text-green-400',
        error: 'text-red-500 dark:text-red-400',
        warning: 'text-amber-500 dark:text-amber-400',
        info: 'text-sky-500 dark:text-sky-400',
    };

    const Icon = icons[type];
    const isUrgent = type === 'error' || type === 'warning';

    return (
        <div className="fixed right-4 bottom-4 left-4 z-[150] animate-in slide-in-from-bottom-2 sm:left-auto sm:w-full sm:max-w-md">
            <div
                className={`flex items-start gap-3 rounded-lg border p-4 shadow-lg ${styles[type]}`}
                role={isUrgent ? 'alert' : 'status'}
                aria-live={isUrgent ? 'assertive' : 'polite'}
                aria-atomic="true"
            >
                <Icon
                    className={`mt-0.5 h-5 w-5 shrink-0 ${iconStyles[type]}`}
                    aria-hidden="true"
                />
                <p className="min-w-0 flex-1 text-sm font-medium break-words">
                    {message}
                </p>
                <button
                    type="button"
                    onClick={onClose}
                    className="shrink-0 rounded p-1 transition-colors hover:bg-black/10 focus-visible:ring-2 focus-visible:ring-current focus-visible:outline-none"
                    aria-label="Dismiss notification"
                >
                    <X className="h-4 w-4" aria-hidden="true" />
                </button>
            </div>
        </div>
    );
}

export function useToast() {
    const [toast, setToast] = useState<{
        type: ToastType;
        message: string;
        isVisible: boolean;
    }>({
        type: 'success',
        message: '',
        isVisible: false,
    });

    const showToast = useCallback((type: ToastType, message: string) => {
        setToast({ type, message, isVisible: true });
    }, []);

    const hideToast = useCallback(() => {
        setToast((previous) => ({ ...previous, isVisible: false }));
    }, []);

    return {
        toast,
        showToast,
        hideToast,
        success: (message: string) => showToast('success', message),
        error: (message: string) => showToast('error', message),
        warning: (message: string) => showToast('warning', message),
        info: (message: string) => showToast('info', message),
    };
}
