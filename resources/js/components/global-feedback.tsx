import { FlashToast } from '@/components/flash-toast';
import { router } from '@inertiajs/react';
import { WifiOff } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export function GlobalFeedback() {
    const [isOnline, setIsOnline] = useState(() =>
        typeof navigator === 'undefined' ? true : navigator.onLine,
    );
    const [connectionRestored, setConnectionRestored] = useState(false);
    const [isNavigating, setIsNavigating] = useState(false);
    const wasOffline = useRef(!isOnline);

    useEffect(() => {
        const handleOffline = () => {
            wasOffline.current = true;
            setConnectionRestored(false);
            setIsOnline(false);
        };

        const handleOnline = () => {
            setIsOnline(true);

            if (wasOffline.current) {
                setConnectionRestored(true);
                wasOffline.current = false;
            }
        };

        window.addEventListener('offline', handleOffline);
        window.addEventListener('online', handleOnline);

        return () => {
            window.removeEventListener('offline', handleOffline);
            window.removeEventListener('online', handleOnline);
        };
    }, []);

    useEffect(() => {
        if (!connectionRestored) {
            return;
        }

        const timer = window.setTimeout(
            () => setConnectionRestored(false),
            3500,
        );

        return () => window.clearTimeout(timer);
    }, [connectionRestored]);

    useEffect(() => {
        const removeStartListener = router.on('start', () =>
            setIsNavigating(true),
        );
        const removeFinishListener = router.on('finish', () =>
            setIsNavigating(false),
        );

        return () => {
            removeStartListener();
            removeFinishListener();
        };
    }, []);

    return (
        <>
            <FlashToast />

            {isNavigating && (
                <div
                    className="fixed top-0 right-0 left-0 z-[170] h-1 overflow-hidden bg-indigo-100 dark:bg-indigo-950"
                    role="progressbar"
                    aria-label="Loading page"
                >
                    <div className="h-full w-1/3 animate-pulse bg-indigo-600" />
                </div>
            )}

            {!isOnline && (
                <div
                    className="fixed top-0 right-0 left-0 z-[160] flex min-h-10 items-center justify-center gap-2 bg-red-700 px-4 py-2 text-center text-sm font-medium text-white shadow"
                    role="alert"
                    aria-live="assertive"
                >
                    <WifiOff className="h-4 w-4 shrink-0" aria-hidden="true" />
                    You are offline. Check your connection before submitting
                    changes.
                </div>
            )}

            {connectionRestored && (
                <div
                    className="fixed top-3 left-1/2 z-[160] -translate-x-1/2 rounded-lg bg-green-700 px-4 py-2 text-sm font-medium text-white shadow"
                    role="status"
                    aria-live="polite"
                >
                    Connection restored.
                </div>
            )}

            <span className="sr-only" role="status" aria-live="polite">
                {isNavigating ? 'Loading page.' : 'Page loaded.'}
            </span>
        </>
    );
}
