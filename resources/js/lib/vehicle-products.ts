export interface AssignedVehicleProduct {
    id: number;
    sort_order?: number;
    pivot?: {
        sort_order?: number;
    };
}

interface SelectableProduct {
    id: number;
}

export function getOrderedVehicleProducts<T extends SelectableProduct>(
    assignedProducts: AssignedVehicleProduct[] | undefined,
    products: T[],
): T[] {
    if (!assignedProducts) {
        return [];
    }

    const productsById = new Map(
        products.map((product) => [product.id, product]),
    );

    return [...assignedProducts]
        .sort((first, second) => {
            const firstOrder =
                first.sort_order ??
                first.pivot?.sort_order ??
                Number.MAX_SAFE_INTEGER;
            const secondOrder =
                second.sort_order ??
                second.pivot?.sort_order ??
                Number.MAX_SAFE_INTEGER;

            return firstOrder - secondOrder || first.id - second.id;
        })
        .map((assignedProduct) => productsById.get(assignedProduct.id))
        .filter((product): product is T => Boolean(product));
}
