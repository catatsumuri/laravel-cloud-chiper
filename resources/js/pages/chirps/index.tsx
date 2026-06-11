import { Form, Head, usePage, useForm } from '@inertiajs/react';
import { Paperclip, Upload, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import ChirpController from '@/actions/App/Http/Controllers/ChirpController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { index } from '@/routes/chirps';
import type { Auth } from '@/types';

type Chirp = {
    id: number;
    message: string;
    attachments: ChirpAttachment[] | null;
    created_at: string;
    user: {
        id: number;
        name: string;
    };
};

type PageProps = {
    auth: Auth;
};

type ChirpAttachment = {
    name: string;
    path: string;
    url: string;
    thumbnail_url?: string;
    mime: string | null;
    size: number;
    metadata?: {
        width?: number;
        height?: number;
        megapixels?: number;
        aspect_ratio?: number;
        orientation?: string;
        mime?: string | null;
        image_type?: number | null;
        bits?: number | null;
        channels?: number | null;
        file_size?: number | null;
        location?: {
            latitude: number;
            longitude: number;
            altitude?: number;
        } | null;
        exif?: {
            make?: string;
            model?: string;
            software?: string;
            orientation?: number;
            taken_at?: string;
            exposure_time?: string;
            f_number?: number | string;
            iso?: number;
            focal_length?: number | string;
            lens_model?: string;
        };
    };
};

function formatFileSize(bytes: number) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function formatImageDimensions(attachment: ChirpAttachment) {
    const width = attachment.metadata?.width;
    const height = attachment.metadata?.height;

    if (!width || !height) {
        return null;
    }

    return `${width}x${height}`;
}

function formatCamera(attachment: ChirpAttachment) {
    const make = attachment.metadata?.exif?.make;
    const model = attachment.metadata?.exif?.model;

    return [make, model].filter(Boolean).join(' ') || null;
}

function formatLocation(attachment: ChirpAttachment) {
    const location = attachment.metadata?.location;

    if (!location) {
        return null;
    }

    return `${location.latitude.toFixed(6)}, ${location.longitude.toFixed(6)}`;
}

function openStreetMapUrl(attachment: ChirpAttachment) {
    const location = attachment.metadata?.location;

    if (!location) {
        return null;
    }

    return `https://www.openstreetmap.org/?mlat=${location.latitude}&mlon=${location.longitude}#map=16/${location.latitude}/${location.longitude}`;
}

function openStreetMapEmbedUrl(attachment: ChirpAttachment) {
    const location = attachment.metadata?.location;

    if (!location) {
        return null;
    }

    const offset = 0.01;
    const left = location.longitude - offset;
    const right = location.longitude + offset;
    const bottom = location.latitude - offset;
    const top = location.latitude + offset;

    return `https://www.openstreetmap.org/export/embed.html?bbox=${left},${bottom},${right},${top}&layer=mapnik&marker=${location.latitude},${location.longitude}`;
}

function imageMetadataRows(attachment: ChirpAttachment) {
    const metadata = attachment.metadata;
    const exif = metadata?.exif;

    if (!metadata) {
        return [];
    }

    const rows: Array<[string, string | null | undefined]> = [
        ['Dimensions', formatImageDimensions(attachment)],
        ['File size', formatFileSize(metadata.file_size ?? attachment.size)],
        ['MIME', metadata.mime ?? attachment.mime],
        ['Orientation', metadata.orientation],
        [
            'Megapixels',
            metadata.megapixels ? `${metadata.megapixels} MP` : null,
        ],
        [
            'Aspect ratio',
            metadata.aspect_ratio ? `${metadata.aspect_ratio}:1` : null,
        ],
        ['Color depth', metadata.bits ? `${metadata.bits} bit` : null],
        ['Channels', metadata.channels?.toString()],
        ['Camera', formatCamera(attachment)],
        ['Location', formatLocation(attachment)],
        [
            'Altitude',
            metadata.location?.altitude !== undefined
                ? `${metadata.location.altitude} m`
                : null,
        ],
        ['Taken at', exif?.taken_at],
        ['Lens', exif?.lens_model],
        ['Exposure', exif?.exposure_time],
        ['Aperture', exif?.f_number ? `f/${exif.f_number}` : null],
        ['ISO', exif?.iso?.toString()],
        ['Focal length', exif?.focal_length ? `${exif.focal_length} mm` : null],
        ['Software', exif?.software],
    ];

    return rows.filter(
        (row): row is [string, string] =>
            row[1] !== null && row[1] !== undefined && row[1] !== '',
    );
}

function ChirpItem({ chirp, isOwner }: { chirp: Chirp; isOwner: boolean }) {
    const [isEditing, setIsEditing] = useState(false);
    const [selectedAttachment, setSelectedAttachment] =
        useState<ChirpAttachment | null>(null);
    const [selectedAttachmentTab, setSelectedAttachmentTab] = useState<
        'image' | 'details'
    >('image');
    const form = useForm({ message: chirp.message });
    const selectedAttachmentDimensions = selectedAttachment
        ? formatImageDimensions(selectedAttachment)
        : null;
    const selectedAttachmentMetadataRows = selectedAttachment
        ? imageMetadataRows(selectedAttachment)
        : [];
    const selectedAttachmentMapUrl = selectedAttachment
        ? openStreetMapUrl(selectedAttachment)
        : null;
    const selectedAttachmentMapEmbedUrl = selectedAttachment
        ? openStreetMapEmbedUrl(selectedAttachment)
        : null;

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.patch(ChirpController.update.url(chirp.id), {
            preserveScroll: true,
            onSuccess: () => setIsEditing(false),
        });
    }

    function handleCancel() {
        setIsEditing(false);
        form.reset();
        form.clearErrors();
    }

    return (
        <Card>
            <CardContent className="pt-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex-1 space-y-1">
                        <p className="text-sm font-medium">{chirp.user.name}</p>
                        <p className="text-xs text-muted-foreground">
                            {new Date(chirp.created_at).toLocaleString()}
                        </p>

                        {isEditing ? (
                            <form
                                onSubmit={handleSubmit}
                                className="mt-2 space-y-2"
                            >
                                <Textarea
                                    value={form.data.message}
                                    onChange={(e) =>
                                        form.setData('message', e.target.value)
                                    }
                                    className="w-full"
                                    autoFocus
                                />
                                <InputError message={form.errors.message} />
                                <div className="flex gap-2">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={form.processing}
                                    >
                                        Save
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        onClick={handleCancel}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            <>
                                <p className="mt-2 text-sm">{chirp.message}</p>
                                {chirp.attachments &&
                                    chirp.attachments.length > 0 && (
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {chirp.attachments.map(
                                                (attachment) => {
                                                    const dimensions =
                                                        formatImageDimensions(
                                                            attachment,
                                                        );

                                                    return (
                                                        <button
                                                            type="button"
                                                            key={
                                                                attachment.path
                                                            }
                                                            onClick={() => {
                                                                setSelectedAttachment(
                                                                    attachment,
                                                                );
                                                                setSelectedAttachmentTab(
                                                                    'image',
                                                                );
                                                            }}
                                                            className="group block w-24 overflow-hidden rounded-md border bg-background text-left transition-colors hover:bg-accent sm:w-28"
                                                        >
                                                            <img
                                                                src={
                                                                    attachment.thumbnail_url ??
                                                                    attachment.url
                                                                }
                                                                alt={
                                                                    attachment.name
                                                                }
                                                                className="aspect-square w-full object-cover"
                                                                loading="lazy"
                                                            />
                                                            <span className="flex min-w-0 flex-col gap-0.5 px-1.5 py-1 text-[11px] text-muted-foreground group-hover:text-accent-foreground">
                                                                <span className="flex min-w-0 items-center gap-1">
                                                                    <Paperclip className="size-3.5 shrink-0" />
                                                                    <span className="truncate">
                                                                        {
                                                                            attachment.name
                                                                        }
                                                                    </span>
                                                                </span>
                                                                {dimensions && (
                                                                    <span className="truncate">
                                                                        {
                                                                            dimensions
                                                                        }
                                                                    </span>
                                                                )}
                                                            </span>
                                                        </button>
                                                    );
                                                },
                                            )}
                                        </div>
                                    )}
                            </>
                        )}
                    </div>

                    {isOwner && !isEditing && (
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setIsEditing(true)}
                            >
                                Edit
                            </Button>

                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="destructive" size="sm">
                                        Delete
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>
                                        Delete this chirp?
                                    </DialogTitle>
                                    <DialogDescription>
                                        This action cannot be undone.
                                    </DialogDescription>
                                    <Form
                                        {...ChirpController.destroy.form(
                                            chirp.id,
                                        )}
                                    >
                                        {({ processing }) => (
                                            <DialogFooter className="gap-2">
                                                <DialogClose asChild>
                                                    <Button variant="secondary">
                                                        Cancel
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    variant="destructive"
                                                    disabled={processing}
                                                    asChild
                                                >
                                                    <button type="submit">
                                                        Delete
                                                    </button>
                                                </Button>
                                            </DialogFooter>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    )}
                </div>
            </CardContent>

            <Dialog
                open={selectedAttachment !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedAttachment(null);
                        setSelectedAttachmentTab('image');
                    }
                }}
            >
                <DialogContent className="max-h-[calc(100vh-2rem)] max-w-[calc(100vw-2rem)] gap-3 overflow-hidden p-3 sm:max-w-5xl">
                    <DialogTitle className="sr-only">
                        {selectedAttachment?.name ?? 'Attached image'}
                    </DialogTitle>
                    {selectedAttachment && (
                        <>
                            <div className="flex items-center justify-between gap-3">
                                <div className="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setSelectedAttachmentTab('image')
                                        }
                                        className={cn(
                                            'rounded-md px-3.5 py-1.5 text-sm transition-colors',
                                            selectedAttachmentTab === 'image'
                                                ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                                                : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                                        )}
                                    >
                                        Image
                                    </button>
                                    {selectedAttachment.metadata && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setSelectedAttachmentTab(
                                                    'details',
                                                )
                                            }
                                            className={cn(
                                                'rounded-md px-3.5 py-1.5 text-sm transition-colors',
                                                selectedAttachmentTab ===
                                                    'details'
                                                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                                                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                                            )}
                                        >
                                            Details
                                        </button>
                                    )}
                                </div>
                                <a
                                    href={selectedAttachment.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="shrink-0 text-xs text-primary underline-offset-4 hover:underline"
                                >
                                    Open image
                                </a>
                            </div>
                            {selectedAttachmentTab === 'image' ? (
                                <div className="flex min-h-0 items-center justify-center overflow-auto rounded-md bg-black">
                                    <img
                                        src={selectedAttachment.url}
                                        alt={selectedAttachment.name}
                                        className="max-h-[calc(100vh-12rem)] max-w-full object-contain"
                                    />
                                </div>
                            ) : (
                                <div
                                    className={
                                        selectedAttachmentMapEmbedUrl
                                            ? 'grid min-h-0 gap-4 overflow-auto lg:grid-cols-[minmax(0,1fr)_minmax(18rem,24rem)]'
                                            : 'grid min-h-0 gap-4 overflow-auto'
                                    }
                                >
                                    <dl className="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-2 text-sm">
                                        {selectedAttachmentMetadataRows.map(
                                            ([label, value]) => (
                                                <div
                                                    key={label}
                                                    className="contents"
                                                >
                                                    <dt className="text-muted-foreground">
                                                        {label}
                                                    </dt>
                                                    <dd className="min-w-0 truncate">
                                                        {value}
                                                    </dd>
                                                </div>
                                            ),
                                        )}
                                    </dl>
                                    {selectedAttachmentMapEmbedUrl &&
                                        selectedAttachmentMapUrl && (
                                            <div className="overflow-hidden rounded-md border">
                                                <iframe
                                                    title="Photo location"
                                                    src={
                                                        selectedAttachmentMapEmbedUrl
                                                    }
                                                    className="h-72 w-full"
                                                    loading="lazy"
                                                />
                                                <a
                                                    href={
                                                        selectedAttachmentMapUrl
                                                    }
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="block border-t px-3 py-2 text-xs text-primary underline-offset-4 hover:underline"
                                                >
                                                    Open pinned location
                                                </a>
                                            </div>
                                        )}
                                </div>
                            )}
                            <div className="flex flex-col gap-2 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                                <span className="truncate">
                                    {selectedAttachment.name}
                                    {selectedAttachmentDimensions
                                        ? ` - ${selectedAttachmentDimensions}`
                                        : ''}
                                </span>
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </Card>
    );
}

function ChirpComposer() {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);
    const form = useForm<{
        message: string;
        attachments: File[];
    }>({
        message: '',
        attachments: [],
    });

    const attachmentErrors = Object.entries(form.errors)
        .filter(
            ([key]) => key === 'attachments' || key.startsWith('attachments.'),
        )
        .map(([, message]) => message);
    const attachmentPreviews = useMemo(
        () =>
            form.data.attachments.map((file) => ({
                file,
                url: URL.createObjectURL(file),
            })),
        [form.data.attachments],
    );

    useEffect(() => {
        return () => {
            attachmentPreviews.forEach((preview) => {
                URL.revokeObjectURL(preview.url);
            });
        };
    }, [attachmentPreviews]);

    function appendFiles(files: FileList | File[]) {
        form.setData(
            'attachments',
            [...form.data.attachments, ...Array.from(files)].slice(0, 4),
        );
    }

    function clearFiles() {
        form.setData('attachments', []);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }

    function removeFile(fileToRemove: File) {
        form.setData(
            'attachments',
            form.data.attachments.filter((file) => file !== fileToRemove),
        );
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        form.post(ChirpController.store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                clearFiles();
            },
        });
    }

    function handleDrop(e: React.DragEvent<HTMLElement>) {
        e.preventDefault();
        setIsDragging(false);
        appendFiles(e.dataTransfer.files);
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="message">Message</Label>
                <Textarea
                    id="message"
                    name="message"
                    value={form.data.message}
                    onChange={(e) => form.setData('message', e.target.value)}
                    onDragEnter={(e) => {
                        e.preventDefault();
                        setIsDragging(true);
                    }}
                    onDragOver={(e) => e.preventDefault()}
                    onDragLeave={(e) => {
                        if (
                            !(e.relatedTarget instanceof Node) ||
                            !e.currentTarget.contains(e.relatedTarget)
                        ) {
                            setIsDragging(false);
                        }
                    }}
                    onDrop={handleDrop}
                    placeholder="What's on your mind?"
                    className={`w-full transition-colors ${
                        isDragging
                            ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                            : ''
                    }`}
                />
                <InputError message={form.errors.message} />
            </div>

            <div className="rounded-md border border-dashed border-border bg-muted/20 p-4">
                <input
                    ref={fileInputRef}
                    type="file"
                    name="attachments[]"
                    accept="image/*"
                    multiple
                    className="sr-only"
                    onChange={(e) => appendFiles(e.currentTarget.files ?? [])}
                />
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3 text-sm text-muted-foreground">
                        <Upload className="size-4 shrink-0" />
                        <span>
                            Drop images into the message field or choose images.
                        </span>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => fileInputRef.current?.click()}
                    >
                        Choose images
                    </Button>
                </div>

                {form.data.attachments.length > 0 && (
                    <div className="mt-3 space-y-3">
                        <div
                            onDragEnter={(e) => {
                                e.preventDefault();
                                setIsDragging(true);
                            }}
                            onDragOver={(e) => e.preventDefault()}
                            onDragLeave={(e) => {
                                if (
                                    !(e.relatedTarget instanceof Node) ||
                                    !e.currentTarget.contains(e.relatedTarget)
                                ) {
                                    setIsDragging(false);
                                }
                            }}
                            onDrop={handleDrop}
                            className={`flex flex-wrap gap-2 rounded-md transition-colors ${
                                isDragging
                                    ? 'bg-primary/5 ring-2 ring-primary/20'
                                    : ''
                            }`}
                        >
                            {attachmentPreviews.map(({ file, url }) => (
                                <figure
                                    key={`${file.name}-${file.size}-${file.lastModified}`}
                                    className="group relative w-24 overflow-hidden rounded-md border bg-background sm:w-28"
                                >
                                    <img
                                        src={url}
                                        alt={file.name}
                                        className="aspect-square w-full object-cover"
                                    />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="icon"
                                        onClick={() => removeFile(file)}
                                        aria-label={`Remove ${file.name}`}
                                        className="absolute top-1 right-1 size-6 opacity-95 shadow-sm sm:opacity-0 sm:transition-opacity sm:group-hover:opacity-100 sm:focus-visible:opacity-100"
                                    >
                                        <X className="size-3.5" />
                                    </Button>
                                    <figcaption className="flex min-w-0 items-center gap-1 px-1.5 py-1 text-[11px]">
                                        <Paperclip className="size-3.5 shrink-0 text-muted-foreground" />
                                        <span className="truncate">
                                            {file.name}
                                        </span>
                                        <span className="shrink-0 text-muted-foreground">
                                            {formatFileSize(file.size)}
                                        </span>
                                    </figcaption>
                                </figure>
                            ))}
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={clearFiles}
                        >
                            <X className="size-3.5" />
                            Clear
                        </Button>
                    </div>
                )}

                {attachmentErrors.map((error) => (
                    <InputError key={error} message={error} className="mt-2" />
                ))}
            </div>

            {form.progress && (
                <progress
                    value={form.progress.percentage}
                    max="100"
                    className="h-2 w-full"
                >
                    {form.progress.percentage}%
                </progress>
            )}

            <Button type="submit" disabled={form.processing}>
                Chirp
            </Button>
        </form>
    );
}

export default function ChirpsIndex({ chirps }: { chirps: Chirp[] }) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Chirps" />

            <h1 className="sr-only">Chirps</h1>

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
                <Heading
                    title="Chirps"
                    description="Share what's on your mind"
                />

                <ChirpComposer />

                <div className="space-y-4">
                    {chirps.map((chirp) => (
                        <ChirpItem
                            key={chirp.id}
                            chirp={chirp}
                            isOwner={chirp.user.id === auth.user.id}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

ChirpsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Chirps',
            href: index(),
        },
    ],
};
