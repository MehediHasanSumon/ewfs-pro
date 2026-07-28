import { type RefObject, useEffect } from 'react';

export function useFocusFirstError(
    containerRef: RefObject<HTMLElement | null>,
    errors: Record<string, string | undefined>,
    active = true,
) {
    useEffect(() => {
        if (!active) {
            return;
        }

        const firstErrorName = Object.keys(errors).find((field) =>
            Boolean(errors[field]),
        );

        if (!firstErrorName) {
            return;
        }

        const container = containerRef.current;
        const invalidField =
            container?.querySelector<HTMLElement>('[aria-invalid="true"]') ??
            Array.from(
                container?.querySelectorAll<HTMLElement>('[name]') ?? [],
            ).find(
                (element) =>
                    element.getAttribute('name') === firstErrorName,
            ) ??
            Array.from(
                container?.querySelectorAll<HTMLElement>('[id]') ?? [],
            ).find((element) => {
                const id = element.getAttribute('id');
                return id === firstErrorName || id === `edit-${firstErrorName}`;
            });

        invalidField?.focus();
        invalidField?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, [active, containerRef, errors]);
}
