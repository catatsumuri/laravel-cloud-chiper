import { Form, Head, usePage } from '@inertiajs/react';
import ChirpController from '@/actions/App/Http/Controllers/ChirpController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

export default function ChirpsIndex({ chirps }: { chirps: Chirp[] }) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Chirps" />

            <h1 className="sr-only">Chirps</h1>

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
                <Heading title="Chirps" description="Share what's on your mind" />

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
                        <Card key={chirp.id}>
                            <CardContent className="pt-6">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="space-y-1">
                                        <p className="text-sm font-medium">
                                            {chirp.user.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(chirp.created_at).toLocaleString()}
                                        </p>
                                        <p className="mt-2 text-sm">
                                            {chirp.message}
                                        </p>
                                    </div>

                                    {chirp.user.id === auth.user.id && (
                                        <Form
                                            {...ChirpController.destroy.form(chirp.id)}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Delete
                                                </Button>
                                            )}
                                        </Form>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
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
