import { router } from '@inertiajs/react';

interface ToggleSwitchProps {
    checked: boolean;
    onToggle: () => void;
    disabled?: boolean;
}

export function ToggleSwitch({ checked, onToggle, disabled = false }: ToggleSwitchProps) {
    return (
        <button
            onClick={onToggle}
            disabled={disabled}
            className={`
                relative inline-flex h-6 w-11 items-center rounded-full transition-colors
                ${checked 
                    ? 'bg-green-600 dark:bg-green-500' 
                    : 'bg-gray-200 dark:bg-gray-600'
                }
                ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
            `}
        >
            <span
                className={`
                    inline-block h-4 w-4 transform rounded-full bg-white transition-transform
                    ${checked ? 'translate-x-6' : 'translate-x-1'}
                `}
            />
        </button>
    );
}