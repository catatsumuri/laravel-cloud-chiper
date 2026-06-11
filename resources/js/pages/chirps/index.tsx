import { Form, Head, usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';
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
import { index } from '@/routes/chirps';
import type { Auth } from '@/types';

type Chirp = {
    id: number;
    message: string;
    created_at: string;
    user: {
        id: number;
        name: string;
    };
};

type PageProps = {
    auth: Auth;
};

function ChirpItem({ chirp, isOwner }: { chirp: Chirp; isOwner: boolean }) {
    const [isEditing, setIsEditing] = useState(false);
    const form = useForm({ message: chirp.message });

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
                            <p className="mt-2 text-sm">{chirp.message}</p>
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
        </Card>
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

                <Form
                    {...ChirpController.store.form()}
                    options={{ preserveScroll: true, resetOnSuccess: true }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="message">Message</Label>
                                <Textarea
                                    id="message"
                                    name="message"
                                    placeholder="What's on your mind?"
                                    className="w-full"
                                />
                                <InputError message={errors.message} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                Chirp
                            </Button>
                        </>
                    )}
                </Form>

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
