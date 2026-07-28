import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ChevronLeft, ChevronRight } from 'lucide-react';

type PageItem = number | 'ellipsis';

interface PaginationProps {
    currentPage: number;
    lastPage: number;
    from: number;
    to: number;
    total: number;
    perPage: number;
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
}

export function Pagination({
    currentPage,
    lastPage,
    from,
    to,
    total,
    perPage,
    onPageChange,
    onPerPageChange,
}: PaginationProps) {
    if (lastPage <= 1) {
        return null;
    }

    const visiblePages = Array.from(
        new Set(
            [1, currentPage - 1, currentPage, currentPage + 1, lastPage].filter(
                (page) => page >= 1 && page <= lastPage,
            ),
        ),
    ).sort((a, b) => a - b);
    const pageItems: PageItem[] = [];

    visiblePages.forEach((page, index) => {
        const previousPage = visiblePages[index - 1];

        if (previousPage !== undefined && page - previousPage > 1) {
            pageItems.push('ellipsis');
        }

        pageItems.push(page);
    });

    return (
        <nav
            className="mt-4 flex flex-col gap-3 border-t border-gray-200 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700"
            aria-label="Table pagination"
        >
            <div className="flex flex-wrap items-center justify-between gap-3 sm:justify-start sm:gap-4">
                <div
                    className="text-sm text-gray-700 dark:text-gray-300"
                    aria-live="polite"
                >
                    Showing {from} to {to} of {total} results
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-sm text-gray-700 dark:text-gray-300">
                        Per page:
                    </span>
                    <Select
                        value={perPage.toString()}
                        onValueChange={(value) =>
                            onPerPageChange(Number(value))
                        }
                    >
                        <SelectTrigger
                            className="w-20 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            aria-label="Rows per page"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="10">10</SelectItem>
                            <SelectItem value="25">25</SelectItem>
                            <SelectItem value="50">50</SelectItem>
                            <SelectItem value="100">100</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="flex items-center justify-between gap-2 sm:justify-end">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage <= 1}
                    aria-label="Go to previous page"
                >
                    <ChevronLeft className="h-4 w-4" aria-hidden="true" />
                    <span className="hidden md:inline">Previous</span>
                </Button>

                <span className="text-sm text-gray-700 sm:hidden dark:text-gray-300">
                    Page {currentPage} of {lastPage}
                </span>

                <div className="hidden items-center gap-2 sm:flex">
                    {pageItems.map((item, index) =>
                        item === 'ellipsis' ? (
                            <span
                                key={`ellipsis-${index}`}
                                className="px-1 text-gray-500"
                                aria-hidden="true"
                            >
                                ...
                            </span>
                        ) : (
                            <Button
                                key={item}
                                variant={
                                    item === currentPage
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                                className="min-w-8"
                                onClick={() => onPageChange(item)}
                                aria-label={`Go to page ${item}`}
                                aria-current={
                                    item === currentPage ? 'page' : undefined
                                }
                            >
                                {item}
                            </Button>
                        ),
                    )}
                </div>

                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage >= lastPage}
                    aria-label="Go to next page"
                >
                    <span className="hidden md:inline">Next</span>
                    <ChevronRight className="h-4 w-4" aria-hidden="true" />
                </Button>
            </div>
        </nav>
    );
}
