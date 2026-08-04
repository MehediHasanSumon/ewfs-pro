import { Button } from '@/components/ui/button';
import { FileText } from 'lucide-react';
import type { ComponentType, SVGProps } from 'react';

interface DocumentViewerButtonProps {
    label: string;
    available: boolean;
    onClick: () => void;
    icon?: ComponentType<SVGProps<SVGSVGElement>>;
}

export function DocumentViewerButton({
    label,
    available,
    onClick,
    icon: Icon = FileText,
}: DocumentViewerButtonProps) {
    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={onClick}
            disabled={!available}
            aria-label={available ? label : `${label} unavailable`}
        >
            <Icon className="h-4 w-4" aria-hidden="true" />
            {label}
        </Button>
    );
}
