import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
    ArrowDown,
    ArrowUp,
    GripVertical,
    Minus,
    Plus,
    Search,
} from 'lucide-react';
import { memo, useCallback, useMemo, useState } from 'react';

export interface AssignableProduct {
    id: number;
    name: string;
}

export interface ProductAssignment {
    product_id: number;
    sort_order: number;
}

interface VehicleProductSelectorProps {
    products: AssignableProduct[];
    value: ProductAssignment[];
    onChange: (assignments: ProductAssignment[]) => void;
    error?: string;
    disabled?: boolean;
    maxProducts?: number;
}

const AVAILABLE_CONTAINER = 'available-products';
const ASSIGNED_CONTAINER = 'assigned-products';

function sortableId(container: string, productId: number): string {
    return `${container}:${productId}`;
}

function productIdFromSortableId(id: string | number): number {
    return Number(String(id).split(':').at(-1));
}

function containerFromSortableId(id: string | number): string {
    const value = String(id);

    if (value === AVAILABLE_CONTAINER || value === ASSIGNED_CONTAINER) {
        return value;
    }

    return value.startsWith(`${ASSIGNED_CONTAINER}:`)
        ? ASSIGNED_CONTAINER
        : AVAILABLE_CONTAINER;
}

interface SortableProductProps {
    container: string;
    product: AssignableProduct;
    position?: number;
    disabled: boolean;
    onAdd?: () => void;
    onRemove?: () => void;
    onMoveUp?: () => void;
    onMoveDown?: () => void;
    canMoveUp?: boolean;
    canMoveDown?: boolean;
}

const SortableProduct = memo(function SortableProduct({
    container,
    product,
    position,
    disabled,
    onAdd,
    onRemove,
    onMoveUp,
    onMoveDown,
    canMoveUp = false,
    canMoveDown = false,
}: SortableProductProps) {
    const id = sortableId(container, product.id);
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id, disabled });

    return (
        <div
            ref={setNodeRef}
            style={{
                transform: CSS.Transform.toString(transform),
                transition,
            }}
            className={cn(
                'flex min-h-10 items-center gap-2 border-b bg-white px-2 py-1.5 last:border-b-0 dark:border-gray-700 dark:bg-gray-800',
                isDragging && 'z-10 opacity-60 shadow-md',
            )}
        >
            <button
                type="button"
                className="cursor-grab touch-none text-gray-400 outline-none hover:text-gray-700 focus-visible:ring-2 focus-visible:ring-blue-500 disabled:cursor-not-allowed dark:hover:text-gray-200"
                disabled={disabled}
                aria-label={`Drag ${product.name}`}
                {...attributes}
                {...listeners}
            >
                <GripVertical className="h-4 w-4" />
            </button>
            {position !== undefined && (
                <span className="w-5 shrink-0 text-right text-xs text-gray-500 dark:text-gray-400">
                    {position}.
                </span>
            )}
            <span className="min-w-0 flex-1 truncate text-sm text-gray-800 dark:text-gray-100">
                {product.name}
            </span>
            {onMoveUp && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7"
                    disabled={disabled || !canMoveUp}
                    onClick={onMoveUp}
                    title="Move up"
                    aria-label={`Move ${product.name} up`}
                >
                    <ArrowUp className="h-3.5 w-3.5" />
                </Button>
            )}
            {onMoveDown && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7"
                    disabled={disabled || !canMoveDown}
                    onClick={onMoveDown}
                    title="Move down"
                    aria-label={`Move ${product.name} down`}
                >
                    <ArrowDown className="h-3.5 w-3.5" />
                </Button>
            )}
            {onAdd && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7 text-green-600"
                    disabled={disabled}
                    onClick={onAdd}
                    title="Assign product"
                    aria-label={`Assign ${product.name}`}
                >
                    <Plus className="h-4 w-4" />
                </Button>
            )}
            {onRemove && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7 text-red-600"
                    disabled={disabled}
                    onClick={onRemove}
                    title="Remove product"
                    aria-label={`Remove ${product.name}`}
                >
                    <Minus className="h-4 w-4" />
                </Button>
            )}
        </div>
    );
});

function ProductPanel({
    id,
    children,
}: {
    id: string;
    children: React.ReactNode;
}) {
    const { isOver, setNodeRef } = useDroppable({ id });

    return (
        <div
            ref={setNodeRef}
            className={cn(
                'min-h-48 overflow-hidden border dark:border-gray-600',
                isOver && 'border-blue-500 ring-2 ring-blue-500/20',
            )}
        >
            {children}
        </div>
    );
}

export function VehicleProductSelector({
    products,
    value,
    onChange,
    error,
    disabled = false,
    maxProducts = 50,
}: VehicleProductSelectorProps) {
    const [search, setSearch] = useState('');
    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: { distance: 5 },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const productsById = useMemo(
        () => new Map(products.map((product) => [product.id, product])),
        [products],
    );
    const assignedIds = useMemo(
        () =>
            [...value]
                .sort((a, b) => a.sort_order - b.sort_order)
                .map((assignment) => assignment.product_id)
                .filter((productId) => productsById.has(productId)),
        [productsById, value],
    );
    const assignedSet = useMemo(() => new Set(assignedIds), [assignedIds]);
    const assignedProducts = useMemo(
        () =>
            assignedIds
                .map((productId) => productsById.get(productId))
                .filter((product): product is AssignableProduct => !!product),
        [assignedIds, productsById],
    );
    const availableProducts = useMemo(() => {
        const needle = search.trim().toLocaleLowerCase();

        return products
            .filter((product) => !assignedSet.has(product.id))
            .filter(
                (product) =>
                    needle === '' ||
                    product.name.toLocaleLowerCase().includes(needle),
            )
            .sort((a, b) => a.name.localeCompare(b.name));
    }, [assignedSet, products, search]);

    const emit = useCallback(
        (productIds: number[]) => {
            onChange(
                productIds.map((productId, index) => ({
                    product_id: productId,
                    sort_order: index + 1,
                })),
            );
        },
        [onChange],
    );

    const add = useCallback(
        (productId: number, index = assignedIds.length) => {
            if (
                disabled ||
                assignedSet.has(productId) ||
                assignedIds.length >= maxProducts
            ) {
                return;
            }

            const next = [...assignedIds];
            next.splice(index, 0, productId);
            emit(next);
        },
        [assignedIds, assignedSet, disabled, emit, maxProducts],
    );

    const remove = useCallback(
        (productId: number) => {
            if (!disabled) {
                emit(assignedIds.filter((id) => id !== productId));
            }
        },
        [assignedIds, disabled, emit],
    );

    const move = useCallback(
        (from: number, to: number) => {
            if (disabled || from === to || to < 0 || to >= assignedIds.length) {
                return;
            }

            emit(arrayMove(assignedIds, from, to));
        },
        [assignedIds, disabled, emit],
    );

    const handleDragEnd = useCallback(
        ({ active, over }: DragEndEvent) => {
            if (!over || disabled) {
                return;
            }

            const productId = productIdFromSortableId(active.id);
            const source = containerFromSortableId(active.id);
            const destination = containerFromSortableId(over.id);

            if (
                source === AVAILABLE_CONTAINER &&
                destination === ASSIGNED_CONTAINER
            ) {
                const overProductId =
                    String(over.id) === ASSIGNED_CONTAINER
                        ? null
                        : productIdFromSortableId(over.id);
                const targetIndex =
                    overProductId === null
                        ? assignedIds.length
                        : assignedIds.indexOf(overProductId);
                add(productId, Math.max(0, targetIndex));
                return;
            }

            if (
                source === ASSIGNED_CONTAINER &&
                destination === AVAILABLE_CONTAINER
            ) {
                remove(productId);
                return;
            }

            if (
                source === ASSIGNED_CONTAINER &&
                destination === ASSIGNED_CONTAINER
            ) {
                const oldIndex = assignedIds.indexOf(productId);
                const overProductId = productIdFromSortableId(over.id);
                const newIndex = assignedIds.indexOf(overProductId);

                if (oldIndex >= 0 && newIndex >= 0) {
                    move(oldIndex, newIndex);
                }
            }
        },
        [add, assignedIds, disabled, move, remove],
    );

    const atLimit = assignedIds.length >= maxProducts;

    return (
        <div>
            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <section aria-labelledby="available-products-title">
                        <div className="mb-2 flex items-center justify-between">
                            <h4
                                id="available-products-title"
                                className="text-sm font-medium text-gray-700 dark:text-gray-200"
                            >
                                Available Products
                            </h4>
                            <span className="text-xs text-gray-500">
                                {availableProducts.length}
                            </span>
                        </div>
                        <div className="relative mb-2">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-gray-400"
                                aria-hidden="true"
                            />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search products"
                                className="pl-8 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                disabled={disabled}
                            />
                        </div>
                        <ProductPanel id={AVAILABLE_CONTAINER}>
                            <div className="max-h-72 overflow-y-auto">
                                <SortableContext
                                    items={availableProducts.map((product) =>
                                        sortableId(
                                            AVAILABLE_CONTAINER,
                                            product.id,
                                        ),
                                    )}
                                    strategy={verticalListSortingStrategy}
                                >
                                    {availableProducts.length > 0 ? (
                                        availableProducts.map((product) => (
                                            <SortableProduct
                                                key={product.id}
                                                container={AVAILABLE_CONTAINER}
                                                product={product}
                                                disabled={disabled}
                                                onAdd={() => add(product.id)}
                                            />
                                        ))
                                    ) : (
                                        <p className="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                            {search
                                                ? 'No matching products found.'
                                                : 'All products are assigned.'}
                                        </p>
                                    )}
                                </SortableContext>
                            </div>
                        </ProductPanel>
                    </section>

                    <section aria-labelledby="assigned-products-title">
                        <div className="mb-2 flex items-center justify-between">
                            <h4
                                id="assigned-products-title"
                                className="text-sm font-medium text-gray-700 dark:text-gray-200"
                            >
                                Assigned Products
                            </h4>
                            <span className="text-xs text-gray-500">
                                {assignedProducts.length}/{maxProducts}
                            </span>
                        </div>
                        <div className="mb-2 flex h-10 items-center text-xs text-gray-500 dark:text-gray-400">
                            Drag to reorder or use the arrow controls.
                        </div>
                        <ProductPanel id={ASSIGNED_CONTAINER}>
                            <div className="max-h-72 overflow-y-auto">
                                <SortableContext
                                    items={assignedProducts.map((product) =>
                                        sortableId(
                                            ASSIGNED_CONTAINER,
                                            product.id,
                                        ),
                                    )}
                                    strategy={verticalListSortingStrategy}
                                >
                                    {assignedProducts.length > 0 ? (
                                        assignedProducts.map(
                                            (product, index) => (
                                                <SortableProduct
                                                    key={product.id}
                                                    container={
                                                        ASSIGNED_CONTAINER
                                                    }
                                                    product={product}
                                                    position={index + 1}
                                                    disabled={disabled}
                                                    onRemove={() =>
                                                        remove(product.id)
                                                    }
                                                    onMoveUp={() =>
                                                        move(index, index - 1)
                                                    }
                                                    onMoveDown={() =>
                                                        move(index, index + 1)
                                                    }
                                                    canMoveUp={index > 0}
                                                    canMoveDown={
                                                        index <
                                                        assignedProducts.length -
                                                            1
                                                    }
                                                />
                                            ),
                                        )
                                    ) : (
                                        <p className="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                            Drag products here to assign them.
                                        </p>
                                    )}
                                </SortableContext>
                            </div>
                        </ProductPanel>
                    </section>
                </div>
            </DndContext>
            {atLimit && (
                <p className="mt-1 text-xs text-amber-600 dark:text-amber-400">
                    Maximum {maxProducts} products can be assigned.
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}
