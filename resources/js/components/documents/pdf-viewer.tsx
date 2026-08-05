import type { ViewerDocument } from '@/components/documents/document-viewer-modal';
import { lazy, type ReactNode, Suspense, useEffect, useState } from 'react';

const DocumentViewerModal = lazy(() =>
    import('@/components/documents/document-viewer-modal').then((module) => ({
        default: module.DocumentViewerModal,
    })),
);

interface PdfViewerRequest {
    url: string;
    title?: string;
    fileName?: string;
}

interface PdfViewerProviderProps {
    children: ReactNode;
}

const PDF_VIEWER_EVENT = 'erp:open-pdf-viewer';

const labelFromUrl = (url: string) => {
    try {
        const parsed = new URL(url, window.location.origin);
        const segments = parsed.pathname.split('/').filter(Boolean);
        const endpoint = segments.at(-1) || 'pdf';
        let resource =
            endpoint === 'pdf' || endpoint === 'download-pdf'
                ? segments.at(-2)
                : endpoint;

        if (resource && /^\d+$/.test(resource)) {
            resource = segments.at(-3);
        }

        if (!resource || /^\d+$/.test(resource)) {
            return 'PDF Document';
        }

        return resource
            .replace(/-pdf$/i, '')
            .replace(/[-_]+/g, ' ')
            .replace(/\b\w/g, (letter) => letter.toUpperCase());
    } catch {
        return 'PDF Document';
    }
};

export function openPdfViewer(url: string, title?: string, fileName?: string) {
    window.dispatchEvent(
        new CustomEvent<PdfViewerRequest>(PDF_VIEWER_EVENT, {
            detail: { url, title, fileName },
        }),
    );
}

export function PdfViewerProvider({ children }: PdfViewerProviderProps) {
    const [request, setRequest] = useState<PdfViewerRequest | null>(null);

    useEffect(() => {
        const handleOpen = (event: Event) => {
            const detail = (event as CustomEvent<PdfViewerRequest>).detail;

            if (!detail?.url) return;

            setRequest(detail);
        };

        window.addEventListener(PDF_VIEWER_EVENT, handleOpen);

        return () => window.removeEventListener(PDF_VIEWER_EVENT, handleOpen);
    }, []);

    const document: ViewerDocument | null = request
        ? {
              id: request.url,
              title: request.title || labelFromUrl(request.url),
              url: request.url,
              kind: 'pdf',
              fileName: request.fileName,
          }
        : null;

    return (
        <>
            {children}
            {document && (
                <Suspense fallback={null}>
                    <DocumentViewerModal
                        open
                        documents={[document]}
                        initialDocumentId={document.id}
                        onOpenChange={(open) => {
                            if (!open) setRequest(null);
                        }}
                    />
                </Suspense>
            )}
        </>
    );
}
