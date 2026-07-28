import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Clock } from 'lucide-react';
import {
    type ChangeEvent,
    type KeyboardEvent,
    useEffect,
    useId,
    useRef,
    useState,
} from 'react';

interface TimeParts {
    hour: string;
    minute: string;
    period: 'AM' | 'PM';
}

interface TimePickerProps {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    error?: boolean;
    className?: string;
    'aria-describedby'?: string;
}

const hours = Array.from({ length: 12 }, (_, index) =>
    String(index + 1).padStart(2, '0'),
);
const minutes = Array.from({ length: 60 }, (_, index) =>
    String(index).padStart(2, '0'),
);

function parseTime(value: string): TimeParts | null {
    const trimmedValue = value.trim();
    const twelveHourMatch = trimmedValue.match(
        /^(0?[1-9]|1[0-2]):([0-5]\d)\s*([AaPp][Mm])$/,
    );

    if (twelveHourMatch) {
        return {
            hour: twelveHourMatch[1].padStart(2, '0'),
            minute: twelveHourMatch[2],
            period: twelveHourMatch[3].toUpperCase() as 'AM' | 'PM',
        };
    }

    const twentyFourHourMatch = trimmedValue.match(
        /^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/,
    );

    if (!twentyFourHourMatch) {
        return null;
    }

    const hour = Number(twentyFourHourMatch[1]);

    return {
        hour: String(hour % 12 || 12).padStart(2, '0'),
        minute: twentyFourHourMatch[2],
        period: hour >= 12 ? 'PM' : 'AM',
    };
}

export function formatTimeForDisplay(value: string): string {
    const parsed = parseTime(value);

    return parsed
        ? `${parsed.hour}:${parsed.minute} ${parsed.period}`
        : value;
}

export function formatTimeForStorage(value: string): string | null {
    const parsed = parseTime(value);

    if (!parsed) {
        return null;
    }

    let hour = Number(parsed.hour) % 12;

    if (parsed.period === 'PM') {
        hour += 12;
    }

    return `${String(hour).padStart(2, '0')}:${parsed.minute}:00`;
}

export function TimePicker({
    id,
    value,
    onChange,
    placeholder = 'hh:mm AM/PM',
    disabled = false,
    error = false,
    className,
    'aria-describedby': ariaDescribedBy,
}: TimePickerProps) {
    const generatedId = useId();
    const inputId = id || generatedId;
    const containerRef = useRef<HTMLDivElement>(null);
    const [isOpen, setIsOpen] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [draft, setDraft] = useState('');
    const [pickerTime, setPickerTime] = useState<TimeParts>(
        () =>
            parseTime(value) || {
                hour: '12',
                minute: '00',
                period: 'AM',
            },
    );
    const displayValue = isEditing ? draft : formatTimeForDisplay(value);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const closeOnOutsideClick = (event: MouseEvent | TouchEvent) => {
            if (
                containerRef.current &&
                !containerRef.current.contains(event.target as Node)
            ) {
                setIsOpen(false);
            }
        };

        document.addEventListener('mousedown', closeOnOutsideClick);
        document.addEventListener('touchstart', closeOnOutsideClick);

        return () => {
            document.removeEventListener('mousedown', closeOnOutsideClick);
            document.removeEventListener('touchstart', closeOnOutsideClick);
        };
    }, [isOpen]);

    const openPicker = (sourceValue = displayValue) => {
        if (disabled) {
            return;
        }

        setPickerTime(
            parseTime(sourceValue || value) || {
                hour: '12',
                minute: '00',
                period: 'AM',
            },
        );
        setIsOpen(true);
    };

    const handleInputChange = (event: ChangeEvent<HTMLInputElement>) => {
        const nextDraft = event.target.value;
        const storageValue = formatTimeForStorage(nextDraft);

        setIsEditing(true);
        setDraft(nextDraft);
        onChange(storageValue || '');
    };

    const handleBlur = () => {
        setIsEditing(false);
        const storageValue = formatTimeForStorage(draft);

        if (storageValue) {
            setDraft(formatTimeForDisplay(storageValue));
            onChange(storageValue);
        }
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            openPicker();
        }

        if (event.key === 'Escape') {
            setIsOpen(false);
        }
    };

    const applyPickerTime = () => {
        const storageValue = formatTimeForStorage(
            `${pickerTime.hour}:${pickerTime.minute} ${pickerTime.period}`,
        );

        if (!storageValue) {
            return;
        }

        onChange(storageValue);
        setDraft(formatTimeForDisplay(storageValue));
        setIsEditing(false);
        setIsOpen(false);
    };

    return (
        <div ref={containerRef} className={cn('relative', className)}>
            <Input
                id={inputId}
                type="text"
                inputMode="text"
                autoComplete="off"
                value={displayValue}
                onFocus={() => {
                    const formattedValue = formatTimeForDisplay(value);

                    setDraft(formattedValue);
                    setIsEditing(true);
                    openPicker(formattedValue);
                }}
                onChange={handleInputChange}
                onBlur={handleBlur}
                onKeyDown={handleKeyDown}
                placeholder={placeholder}
                disabled={disabled}
                error={error}
                aria-invalid={error}
                aria-describedby={ariaDescribedBy}
                aria-haspopup="dialog"
                aria-expanded={isOpen}
                className="pr-9 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            />
            <button
                type="button"
                onMouseDown={(event) => event.preventDefault()}
                onClick={() => (isOpen ? setIsOpen(false) : openPicker())}
                disabled={disabled}
                aria-label={`Choose time for ${inputId}`}
                aria-expanded={isOpen}
                className="absolute top-1/2 right-1 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:text-gray-400 dark:hover:bg-gray-600 dark:hover:text-gray-200"
            >
                <Clock className="h-4 w-4" aria-hidden="true" />
            </button>

            {isOpen && (
                <div
                    role="dialog"
                    aria-label="Choose time"
                    className="absolute z-[160] mt-1 w-full min-w-[15rem] rounded-md border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-600 dark:bg-gray-700"
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            setIsOpen(false);
                        }
                    }}
                >
                    <div className="grid grid-cols-3 gap-2">
                        <label className="sr-only" htmlFor={`${inputId}-hour`}>
                            Hour
                        </label>
                        <select
                            id={`${inputId}-hour`}
                            value={pickerTime.hour}
                            onChange={(event) =>
                                setPickerTime((current) => ({
                                    ...current,
                                    hour: event.target.value,
                                }))
                            }
                            className="w-full rounded-md border border-gray-300 bg-white px-2 py-1.5 text-[13px] text-gray-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-900"
                        >
                            {hours.map((hour) => (
                                <option key={hour} value={hour}>
                                    {hour}
                                </option>
                            ))}
                        </select>

                        <label className="sr-only" htmlFor={`${inputId}-minute`}>
                            Minute
                        </label>
                        <select
                            id={`${inputId}-minute`}
                            value={pickerTime.minute}
                            onChange={(event) =>
                                setPickerTime((current) => ({
                                    ...current,
                                    minute: event.target.value,
                                }))
                            }
                            className="w-full rounded-md border border-gray-300 bg-white px-2 py-1.5 text-[13px] text-gray-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-900"
                        >
                            {minutes.map((minute) => (
                                <option key={minute} value={minute}>
                                    {minute}
                                </option>
                            ))}
                        </select>

                        <label className="sr-only" htmlFor={`${inputId}-period`}>
                            AM or PM
                        </label>
                        <select
                            id={`${inputId}-period`}
                            value={pickerTime.period}
                            onChange={(event) =>
                                setPickerTime((current) => ({
                                    ...current,
                                    period: event.target.value as 'AM' | 'PM',
                                }))
                            }
                            className="w-full rounded-md border border-gray-300 bg-white px-2 py-1.5 text-[13px] text-gray-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-900"
                        >
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>

                    <button
                        type="button"
                        onClick={applyPickerTime}
                        className="mt-3 w-full rounded-md bg-indigo-600 px-3 py-1.5 text-[13px] font-medium text-white transition-colors hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-offset-gray-700"
                    >
                        Set time
                    </button>
                </div>
            )}
        </div>
    );
}
