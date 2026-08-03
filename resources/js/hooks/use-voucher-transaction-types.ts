import { useCallback, useRef, useState } from 'react';

export interface VoucherTransactionTypeOption {
    id: number;
    name: string;
    voucher_category_id: number;
    voucher_type: string;
}

interface OptionsResponse {
    data: VoucherTransactionTypeOption[];
}

export function useVoucherTransactionTypes(
    voucherType: string,
    optionsUrl: string,
) {
    const [transactionTypes, setTransactionTypes] = useState<
        VoucherTransactionTypeOption[]
    >([]);
    const [loadingTransactionTypes, setLoadingTransactionTypes] =
        useState(false);
    const requestSequence = useRef(0);

    const clearTransactionTypes = useCallback(() => {
        requestSequence.current += 1;
        setTransactionTypes([]);
        setLoadingTransactionTypes(false);
    }, []);

    const loadTransactionTypes = useCallback(
        async (categoryId: string, selectedId?: string) => {
            if (!categoryId) {
                clearTransactionTypes();
                return;
            }

            const sequence = requestSequence.current + 1;
            requestSequence.current = sequence;
            setLoadingTransactionTypes(true);

            const params = new URLSearchParams({
                category_id: categoryId,
                voucher_type: voucherType,
            });

            if (selectedId) {
                params.set('selected_id', selectedId);
            }

            try {
                const response = await fetch(
                    `${optionsUrl}?${params.toString()}`,
                    {
                        headers: {
                            Accept: 'application/json',
                        },
                    },
                );

                if (!response.ok) {
                    throw new Error('Unable to load voucher transaction types.');
                }

                const payload = (await response.json()) as OptionsResponse;

                if (requestSequence.current === sequence) {
                    setTransactionTypes(
                        payload.data.filter(
                            (transactionType) =>
                                transactionType.voucher_category_id.toString() ===
                                    categoryId &&
                                transactionType.voucher_type === voucherType,
                        ),
                    );
                }
            } catch {
                if (requestSequence.current === sequence) {
                    setTransactionTypes([]);
                }
            } finally {
                if (requestSequence.current === sequence) {
                    setLoadingTransactionTypes(false);
                }
            }
        },
        [clearTransactionTypes, optionsUrl, voucherType],
    );

    return {
        transactionTypes,
        loadingTransactionTypes,
        loadTransactionTypes,
        clearTransactionTypes,
    };
}
