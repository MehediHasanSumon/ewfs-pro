import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { User } from 'lucide-react';
import { useState } from 'react';

interface EmployeeProfileImageProps {
    employeeName: string;
    src?: string | null;
    onView?: () => void;
}

export function EmployeeProfileImage({
    employeeName,
    src,
    onView,
}: EmployeeProfileImageProps) {
    const [failedSrc, setFailedSrc] = useState<string | null>(null);
    const loadFailed = Boolean(src && failedSrc === src);

    const canView = Boolean(src && !loadFailed && onView);

    return (
        <button
            type="button"
            onClick={onView}
            disabled={!canView}
            className="shrink-0 rounded-lg text-left outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-default"
            aria-label={
                canView
                    ? `View ${employeeName} profile image`
                    : `${employeeName} profile image unavailable`
            }
        >
            <Avatar className="h-32 w-28 rounded-lg border border-gray-200 bg-gray-100 sm:h-36 sm:w-32 dark:border-gray-700 dark:bg-gray-700">
                {src && (
                    <AvatarImage
                        src={src}
                        alt={`${employeeName} profile`}
                        loading="lazy"
                        decoding="async"
                        className="rounded-lg object-cover"
                        onLoadingStatusChange={(status) =>
                            setFailedSrc(status === 'error' ? src : null)
                        }
                    />
                )}
                <AvatarFallback
                    delayMs={src ? 250 : 0}
                    className="rounded-lg bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-300"
                >
                    <User className="h-12 w-12" aria-hidden="true" />
                </AvatarFallback>
            </Avatar>
        </button>
    );
}
