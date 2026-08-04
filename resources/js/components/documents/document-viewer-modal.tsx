import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    FileText,
    ImageOff,
    Maximize2,
    Minimize2,
    RotateCcw,
    RotateCw,
    Scan,
    X,
    ZoomIn,
    ZoomOut,
} from 'lucide-react';
import {
    type ComponentType,
    type PointerEvent as ReactPointerEvent,
    type SVGProps,
    type WheelEvent,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';

export type ViewerDocumentKind = 'image' | 'pdf';

export interface ViewerDocument {
    id: string;
    title: string;
    url: string;
    kind: ViewerDocumentKind;
    fileName?: string;
}

interface DocumentViewerModalProps {
    open: boolean;
    documents: ViewerDocument[];
    initialDocumentId?: string | null;
    onOpenChange: (open: boolean) => void;
}

interface Point {
    x: number;
    y: number;
}

interface Dimensions {
    width: number;
    height: number;
}

interface ToolbarButtonProps {
    label: string;
    icon: ComponentType<SVGProps<SVGSVGElement>>;
    onClick: () => void;
    disabled?: boolean;
}

const MIN_SCALE = 0.25;
const MAX_SCALE = 5;
const ZOOM_STEP = 0.25;

const clampScale = (value: number) =>
    Math.min(MAX_SCALE, Math.max(MIN_SCALE, value));

const pointDistance = (first: Point, second: Point) =>
    Math.hypot(second.x - first.x, second.y - first.y);

const pointCenter = (first: Point, second: Point): Point => ({
    x: (first.x + second.x) / 2,
    y: (first.y + second.y) / 2,
});

function ToolbarButton({
    label,
    icon: Icon,
    onClick,
    disabled = false,
}: ToolbarButtonProps) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={onClick}
                    disabled={disabled}
                    aria-label={label}
                >
                    <Icon className="h-4 w-4" aria-hidden="true" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}

export function DocumentViewerModal({
    open,
    documents,
    initialDocumentId,
    onOpenChange,
}: DocumentViewerModalProps) {
    const availableDocuments = useMemo(
        () => documents.filter((document) => document.url.trim() !== ''),
        [documents],
    );
    const requestedInitialIndex = availableDocuments.findIndex(
        (document) => document.id === initialDocumentId,
    );
    const [activeIndex, setActiveIndex] = useState(
        requestedInitialIndex >= 0 ? requestedInitialIndex : 0,
    );
    const [scale, setScale] = useState(1);
    const [rotation, setRotation] = useState(0);
    const [pan, setPan] = useState<Point>({ x: 0, y: 0 });
    const [naturalSize, setNaturalSize] = useState<Dimensions>();
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(false);
    const [reloadVersion, setReloadVersion] = useState(0);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const viewerRef = useRef<HTMLDivElement>(null);
    const viewportRef = useRef<HTMLDivElement>(null);
    const pointersRef = useRef(new Map<number, Point>());
    const dragRef = useRef<{
        pointerId: number;
        origin: Point;
        pan: Point;
        pointerType: string;
    } | undefined>(undefined);
    const pinchRef = useRef<{
        distance: number;
        scale: number;
        center: Point;
        pan: Point;
    } | undefined>(undefined);
    const swipeRef = useRef<{
        pointerId: number;
        origin: Point;
        startedAt: number;
    } | undefined>(undefined);
    const scaleRef = useRef(scale);
    const panRef = useRef(pan);

    const activeDocument = availableDocuments[activeIndex];
    const isImage = activeDocument?.kind === 'image';
    const hasNavigation = availableDocuments.length > 1;

    useEffect(() => {
        scaleRef.current = scale;
    }, [scale]);

    useEffect(() => {
        panRef.current = pan;
    }, [pan]);

    const resetDocumentState = useCallback(() => {
        setScale(1);
        setRotation(0);
        setPan({ x: 0, y: 0 });
        setNaturalSize(undefined);
        setLoading(true);
        setLoadError(false);
        pointersRef.current.clear();
        dragRef.current = undefined;
        pinchRef.current = undefined;
        swipeRef.current = undefined;
    }, []);

    const goPrevious = useCallback(() => {
        if (availableDocuments.length < 2) return;
        resetDocumentState();
        setActiveIndex(
            (current) =>
                (current - 1 + availableDocuments.length) %
                availableDocuments.length,
        );
    }, [availableDocuments.length, resetDocumentState]);

    const goNext = useCallback(() => {
        if (availableDocuments.length < 2) return;
        resetDocumentState();
        setActiveIndex((current) => (current + 1) % availableDocuments.length);
    }, [availableDocuments.length, resetDocumentState]);

    const actualSize = useCallback(() => {
        setScale(1);
        setPan({ x: 0, y: 0 });
    }, []);

    const fitToScreen = useCallback(
        (dimensions = naturalSize, angle = rotation) => {
            const viewport = viewportRef.current;

            if (!viewport || !dimensions) {
                actualSize();
                return;
            }

            const quarterTurn = Math.abs(angle / 90) % 2 === 1;
            const width = quarterTurn ? dimensions.height : dimensions.width;
            const height = quarterTurn ? dimensions.width : dimensions.height;
            const horizontalScale = (viewport.clientWidth - 32) / width;
            const verticalScale = (viewport.clientHeight - 32) / height;

            setScale(clampScale(Math.min(horizontalScale, verticalScale, 1)));
            setPan({ x: 0, y: 0 });
        },
        [actualSize, naturalSize, rotation],
    );

    const resetView = useCallback(() => {
        setRotation(0);
        fitToScreen(naturalSize, 0);
    }, [fitToScreen, naturalSize]);

    const zoomBy = useCallback((amount: number) => {
        setScale((current) => clampScale(current + amount));
    }, []);

    const toggleFullscreen = useCallback(async () => {
        if (!document.fullscreenEnabled || !viewerRef.current) return;

        if (document.fullscreenElement) {
            await document.exitFullscreen();
        } else {
            await viewerRef.current.requestFullscreen();
        }
    }, []);

    useEffect(() => {
        if (!open || availableDocuments.length < 2) return;

        const adjacentIndexes = [
            (activeIndex - 1 + availableDocuments.length) %
                availableDocuments.length,
            (activeIndex + 1) % availableDocuments.length,
        ];

        adjacentIndexes.forEach((index) => {
            const document = availableDocuments[index];
            if (document.kind !== 'image') return;

            const image = new Image();
            image.decoding = 'async';
            image.src = document.url;
        });
    }, [activeIndex, availableDocuments, open]);

    useEffect(() => {
        const handleFullscreenChange = () =>
            setIsFullscreen(document.fullscreenElement === viewerRef.current);

        document.addEventListener('fullscreenchange', handleFullscreenChange);
        return () =>
            document.removeEventListener(
                'fullscreenchange',
                handleFullscreenChange,
            );
    }, []);

    useEffect(() => {
        if (!open) return;

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                if (document.fullscreenElement) {
                    void document.exitFullscreen();
                } else {
                    onOpenChange(false);
                }
                return;
            }

            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                goPrevious();
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                goNext();
            } else if (isImage && (event.key === '+' || event.key === '=')) {
                event.preventDefault();
                zoomBy(ZOOM_STEP);
            } else if (isImage && event.key === '-') {
                event.preventDefault();
                zoomBy(-ZOOM_STEP);
            } else if (isImage && event.key === '0') {
                event.preventDefault();
                resetView();
            } else if (event.key.toLowerCase() === 'f') {
                event.preventDefault();
                void toggleFullscreen();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [
        goNext,
        goPrevious,
        isImage,
        onOpenChange,
        open,
        resetView,
        toggleFullscreen,
        zoomBy,
    ]);

    const handleWheel = (event: WheelEvent<HTMLDivElement>) => {
        if (!isImage || loadError) return;
        event.preventDefault();
        zoomBy(event.deltaY < 0 ? ZOOM_STEP : -ZOOM_STEP);
    };

    const handlePointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (!isImage || loadError) return;

        event.currentTarget.setPointerCapture(event.pointerId);
        const point = { x: event.clientX, y: event.clientY };
        pointersRef.current.set(event.pointerId, point);

        if (pointersRef.current.size === 1) {
            dragRef.current = {
                pointerId: event.pointerId,
                origin: point,
                pan: panRef.current,
                pointerType: event.pointerType,
            };
            if (event.pointerType === 'touch') {
                swipeRef.current = {
                    pointerId: event.pointerId,
                    origin: point,
                    startedAt: Date.now(),
                };
            }
        } else if (pointersRef.current.size === 2) {
            const [first, second] = Array.from(pointersRef.current.values());
            pinchRef.current = {
                distance: pointDistance(first, second),
                scale: scaleRef.current,
                center: pointCenter(first, second),
                pan: panRef.current,
            };
            swipeRef.current = undefined;
        }
    };

    const handlePointerMove = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (!pointersRef.current.has(event.pointerId)) return;

        const point = { x: event.clientX, y: event.clientY };
        pointersRef.current.set(event.pointerId, point);

        if (pointersRef.current.size === 2 && pinchRef.current) {
            const [first, second] = Array.from(pointersRef.current.values());
            const currentCenter = pointCenter(first, second);
            const factor =
                pointDistance(first, second) / pinchRef.current.distance;

            setScale(clampScale(pinchRef.current.scale * factor));
            setPan({
                x:
                    pinchRef.current.pan.x +
                    currentCenter.x -
                    pinchRef.current.center.x,
                y:
                    pinchRef.current.pan.y +
                    currentCenter.y -
                    pinchRef.current.center.y,
            });
            return;
        }

        const drag = dragRef.current;
        if (!drag || drag.pointerId !== event.pointerId) return;

        if (drag.pointerType !== 'touch' || scaleRef.current > 1.05) {
            setPan({
                x: drag.pan.x + point.x - drag.origin.x,
                y: drag.pan.y + point.y - drag.origin.y,
            });
        }
    };

    const handlePointerEnd = (event: ReactPointerEvent<HTMLDivElement>) => {
        const swipe = swipeRef.current;
        if (
            swipe &&
            swipe.pointerId === event.pointerId &&
            scaleRef.current <= 1.05 &&
            Date.now() - swipe.startedAt < 700
        ) {
            const horizontalDistance = event.clientX - swipe.origin.x;
            const verticalDistance = Math.abs(event.clientY - swipe.origin.y);

            if (Math.abs(horizontalDistance) >= 60 && verticalDistance <= 50) {
                if (horizontalDistance > 0) {
                    goPrevious();
                } else {
                    goNext();
                }
            }
        }

        pointersRef.current.delete(event.pointerId);
        if (pointersRef.current.size < 2) pinchRef.current = undefined;
        if (dragRef.current?.pointerId === event.pointerId) {
            dragRef.current = undefined;
        }
        if (swipeRef.current?.pointerId === event.pointerId) {
            swipeRef.current = undefined;
        }
    };

    const imageControlsDisabled = !isImage || loading || loadError;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                movable={false}
                windowControls={false}
                className="flex h-[min(92dvh,900px)] w-[min(96vw,1280px)] max-w-none flex-col overflow-hidden rounded-lg p-0"
                onEscapeKeyDown={(event) => {
                    if (document.fullscreenElement) {
                        event.preventDefault();
                        void document.exitFullscreen();
                    }
                }}
            >
                <div
                    ref={viewerRef}
                    className="flex h-full min-h-0 flex-col bg-white dark:bg-gray-900"
                >
                    <header className="relative flex min-h-14 flex-col items-stretch gap-2 border-b border-gray-200 px-3 py-2 pr-12 sm:flex-row sm:items-center sm:px-4 sm:pr-12 dark:border-gray-700">
                        <div className="min-w-0 flex-1">
                            <DialogTitle className="truncate text-base">
                                {activeDocument?.title ?? 'Document Viewer'}
                            </DialogTitle>
                            <DialogDescription className="mb-0 truncate text-xs">
                                {activeDocument
                                    ? `${activeIndex + 1} of ${availableDocuments.length}`
                                    : 'No document available'}
                            </DialogDescription>
                        </div>

                        <div className="flex w-full items-center gap-1 overflow-x-auto sm:w-auto">
                            <ToolbarButton
                                label="Zoom out"
                                icon={ZoomOut}
                                onClick={() => zoomBy(-ZOOM_STEP)}
                                disabled={imageControlsDisabled}
                            />
                            <span
                                className="w-12 text-center text-xs text-gray-600 tabular-nums dark:text-gray-300"
                                aria-live="polite"
                            >
                                {Math.round(scale * 100)}%
                            </span>
                            <ToolbarButton
                                label="Zoom in"
                                icon={ZoomIn}
                                onClick={() => zoomBy(ZOOM_STEP)}
                                disabled={imageControlsDisabled}
                            />
                            <ToolbarButton
                                label="Fit to screen"
                                icon={Scan}
                                onClick={() => fitToScreen()}
                                disabled={imageControlsDisabled}
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={actualSize}
                                disabled={imageControlsDisabled}
                                aria-label="Actual size"
                                title="Actual size"
                                className="shrink-0 text-xs"
                            >
                                1:1
                            </Button>
                            <ToolbarButton
                                label="Rotate left"
                                icon={RotateCcw}
                                onClick={() =>
                                    setRotation((current) => current - 90)
                                }
                                disabled={imageControlsDisabled}
                            />
                            <ToolbarButton
                                label="Rotate right"
                                icon={RotateCw}
                                onClick={() =>
                                    setRotation((current) => current + 90)
                                }
                                disabled={imageControlsDisabled}
                            />
                            {document.fullscreenEnabled && (
                                <ToolbarButton
                                    label={
                                        isFullscreen
                                            ? 'Exit fullscreen'
                                            : 'Enter fullscreen'
                                    }
                                    icon={isFullscreen ? Minimize2 : Maximize2}
                                    onClick={() => void toggleFullscreen()}
                                />
                            )}
                        </div>
                        <div className="absolute top-2 right-2">
                            <ToolbarButton
                                label="Close viewer"
                                icon={X}
                                onClick={() => onOpenChange(false)}
                            />
                        </div>
                    </header>

                    <div
                        ref={viewportRef}
                        className="relative min-h-0 flex-1 touch-none overflow-hidden bg-gray-100 outline-none select-none dark:bg-gray-950"
                        onWheel={handleWheel}
                        onPointerDown={handlePointerDown}
                        onPointerMove={handlePointerMove}
                        onPointerUp={handlePointerEnd}
                        onPointerCancel={handlePointerEnd}
                        onDoubleClick={() => {
                            if (!imageControlsDisabled) {
                                if (scaleRef.current > 1.05) {
                                    actualSize();
                                } else {
                                    setScale(2);
                                }
                            }
                        }}
                        role="region"
                        aria-label="Document preview"
                    >
                        {!activeDocument ? (
                            <div className="flex h-full flex-col items-center justify-center gap-3 text-gray-500 dark:text-gray-400">
                                <FileText
                                    className="h-12 w-12"
                                    aria-hidden="true"
                                />
                                <p>No documents available.</p>
                            </div>
                        ) : loadError ? (
                            <div className="flex h-full flex-col items-center justify-center gap-3 px-6 text-center text-gray-500 dark:text-gray-400">
                                <ImageOff
                                    className="h-12 w-12"
                                    aria-hidden="true"
                                />
                                <p>This document could not be displayed.</p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        resetDocumentState();
                                        setReloadVersion(
                                            (current) => current + 1,
                                        );
                                    }}
                                >
                                    Try Again
                                </Button>
                            </div>
                        ) : activeDocument.kind === 'pdf' ? (
                            <>
                                {loading && (
                                    <div className="absolute inset-0 z-10 flex items-center justify-center bg-gray-100 dark:bg-gray-950">
                                        <Spinner size="lg" />
                                    </div>
                                )}
                                <iframe
                                    key={`${activeDocument.id}-${reloadVersion}`}
                                    src={activeDocument.url}
                                    title={activeDocument.title}
                                    className="h-full w-full border-0 bg-white"
                                    loading="lazy"
                                    onLoad={() => setLoading(false)}
                                />
                                <Button
                                    asChild
                                    variant="secondary"
                                    size="sm"
                                    className="absolute right-3 bottom-3 z-20"
                                >
                                    <a
                                        href={activeDocument.url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLink
                                            className="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                        Open PDF
                                    </a>
                                </Button>
                            </>
                        ) : (
                            <>
                                {loading && (
                                    <div className="absolute inset-0 z-10 flex items-center justify-center">
                                        <Spinner size="lg" />
                                    </div>
                                )}
                                <img
                                    key={`${activeDocument.id}-${reloadVersion}`}
                                    src={activeDocument.url}
                                    alt={activeDocument.title}
                                    draggable={false}
                                    decoding="async"
                                    className="absolute top-1/2 left-1/2 max-w-none cursor-grab active:cursor-grabbing"
                                    style={{
                                        transform: `translate(-50%, -50%) translate(${pan.x}px, ${pan.y}px) rotate(${rotation}deg) scale(${scale})`,
                                        transformOrigin: 'center',
                                        visibility: loading
                                            ? 'hidden'
                                            : 'visible',
                                    }}
                                    onLoad={(event) => {
                                        const dimensions = {
                                            width: event.currentTarget
                                                .naturalWidth,
                                            height: event.currentTarget
                                                .naturalHeight,
                                        };
                                        setNaturalSize(dimensions);
                                        setLoading(false);
                                        requestAnimationFrame(() =>
                                            fitToScreen(dimensions, 0),
                                        );
                                    }}
                                    onError={() => {
                                        setLoading(false);
                                        setLoadError(true);
                                    }}
                                />
                            </>
                        )}
                    </div>

                    <footer className="flex min-h-14 items-center justify-between gap-3 border-t border-gray-200 px-3 sm:px-4 dark:border-gray-700">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={goPrevious}
                            disabled={!hasNavigation}
                        >
                            <ChevronLeft
                                className="h-4 w-4"
                                aria-hidden="true"
                            />
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={goNext}
                            disabled={!hasNavigation}
                        >
                            Next
                            <ChevronRight
                                className="h-4 w-4"
                                aria-hidden="true"
                            />
                        </Button>
                    </footer>
                </div>
            </DialogContent>
        </Dialog>
    );
}
