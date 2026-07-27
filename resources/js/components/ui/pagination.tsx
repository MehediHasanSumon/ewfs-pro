import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

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
    onPerPageChange
}: PaginationProps) {
    if (lastPage <= 1) return null;

    return (
        <div className="flex items-center justify-between mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div className="flex items-center gap-4">
                <div className="text-sm text-gray-700 dark:text-gray-300">
                    Showing {from} to {to} of {total} results
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-sm text-gray-700 dark:text-gray-300">Per page:</span>
                    <Select value={perPage.toString()} onValueChange={(value) => onPerPageChange(Number(value))}>
                        <SelectTrigger className="w-20 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
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
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage === 1}
                >
                    Previous
                </Button>
                {Array.from({ length: lastPage }, (_, i) => i + 1)
                    .filter(page => 
                        page === 1 || 
                        page === lastPage || 
                        Math.abs(page - currentPage) <= 2
                    )
                    .map((page, index, array) => (
                        <div key={page} className="flex items-center">
                            {index > 0 && array[index - 1] !== page - 1 && (
                                <span className="px-2 text-gray-500">...</span>
                            )}
                            <Button
                                variant={page === currentPage ? "default" : "outline"}
                                size="sm"
                                onClick={() => onPageChange(page)}
                            >
                                {page}
                            </Button>
                        </div>
                    ))
                }
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage === lastPage}
                >
                    Next
                </Button>
            </div>
        </div>
    );
}