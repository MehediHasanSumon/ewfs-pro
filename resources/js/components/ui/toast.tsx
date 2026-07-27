import { CheckCircle, XCircle, AlertTriangle, X } from 'lucide-react';
import { useState, useEffect } from 'react';

interface ToastProps {
    type: 'success' | 'error' | 'warning';
    message: string;
    isVisible: boolean;
    onClose: () => void;
    duration?: number;
}

export function Toast({ type, message, isVisible, onClose, duration = 5000 }: ToastProps) {
    useEffect(() => {
        if (isVisible && duration > 0) {
            const timer = setTimeout(onClose, duration);
            return () => clearTimeout(timer);
        }
    }, [isVisible, duration, onClose]);

    if (!isVisible) return null;

    const icons = {
        success: CheckCircle,
        error: XCircle,
        warning: AlertTriangle,
    };

    const styles = {
        success: 'bg-green-50 border-green-200 text-green-800 dark:bg-green-900 dark:border-green-700 dark:text-green-200',
        error: 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900 dark:border-red-700 dark:text-red-200',
        warning: 'bg-yellow-50 border-yellow-200 text-yellow-800 dark:bg-yellow-900 dark:border-yellow-700 dark:text-yellow-200',
    };

    const iconStyles = {
        success: 'text-green-500 dark:text-green-400',
        error: 'text-red-500 dark:text-red-400',
        warning: 'text-yellow-500 dark:text-yellow-400',
    };

    const Icon = icons[type];

    return (
        <div className="fixed bottom-4 right-4 z-[150] animate-in slide-in-from-bottom-2">
            <div className={`flex items-center gap-3 p-4 border rounded-lg shadow-lg min-w-[300px] ${styles[type]}`}>
                <Icon className={`h-5 w-5 flex-shrink-0 ${iconStyles[type]}`} />
                <p className="text-sm font-medium flex-1">{message}</p>
                <button
                    onClick={onClose}
                    className="flex-shrink-0 p-1 hover:bg-black/10 rounded transition-colors"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}

export function useToast() {
    const [toast, setToast] = useState<{
        type: 'success' | 'error' | 'warning';
        message: string;
        isVisible: boolean;
    }>({
        type: 'success',
        message: '',
        isVisible: false,
    });

    const showToast = (type: 'success' | 'error' | 'warning', message: string) => {
        setToast({ type, message, isVisible: true });
    };

    const hideToast = () => {
        setToast(prev => ({ ...prev, isVisible: false }));
    };

    return {
        toast,
        showToast,
        hideToast,
        success: (message: string) => showToast('success', message),
        error: (message: string) => showToast('error', message),
        warning: (message: string) => showToast('warning', message),
    };
}