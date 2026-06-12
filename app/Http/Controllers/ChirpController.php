<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChirpRequest;
use App\Jobs\ExtractChirpAttachmentMetadata;
use App\Models\Chirp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChirpController extends Controller
{
    public function index(): Response
    {
        $chirps = Chirp::with('user')->latest()->get();

        return Inertia::render('chirps/index', [
            'chirps' => $chirps,
        ]);
    }

    public function store(StoreChirpRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $chirp = $request->user()->chirps()->create([
            'message' => (string) ($validated['message'] ?? ''),
        ]);

        $attachments = collect($request->file('attachments', []))
            ->map(function (UploadedFile $attachment, int $index) use ($chirp): array {
                $path = $attachment->store("chirps/{$chirp->id}");

                return [
                    'name' => $attachment->getClientOriginalName(),
                    'path' => $path,
                    'url' => route('chirps.attachments.show', [
                        'chirp' => $chirp,
                        'attachment' => $index,
                    ]),
                    'thumbnail_url' => route('chirps.attachments.thumbnail', [
                        'chirp' => $chirp,
                        'attachment' => $index,
                    ]),
                    'mime' => $attachment->getClientMimeType(),
                    'size' => $attachment->getSize(),
                ];
            })
            ->all();

        if ($attachments !== []) {
            $chirp->update(['attachments' => $attachments]);

            ExtractChirpAttachmentMetadata::dispatch($chirp->id);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Chirp posted!')]);

        return to_route('chirps.index');
    }

    public function update(StoreChirpRequest $request, Chirp $chirp): RedirectResponse
    {
        Gate::authorize('update', $chirp);

        $validated = $request->validated();

        $chirp->update(['message' => $validated['message']]);

        return to_route('chirps.index');
    }

    public function attachment(Chirp $chirp, int $attachment): StreamedResponse
    {
        $attachmentData = $this->attachmentData($chirp, $attachment);

        return $this->attachmentResponse($attachmentData);
    }

    public function attachmentThumbnail(Chirp $chirp, int $attachment): StreamedResponse
    {
        $attachmentData = $this->attachmentData($chirp, $attachment);

        return $this->attachmentResponse($attachmentData);
    }

    /**
     * @return array{name?: string, path?: string, mime?: string}
     */
    private function attachmentData(Chirp $chirp, int $attachment): array
    {
        $attachmentData = $chirp->attachments[$attachment] ?? null;

        abort_unless(is_array($attachmentData), 404);

        return $attachmentData;
    }

    /**
     * @param  array{name?: string, path?: string, mime?: string}  $attachmentData
     */
    private function attachmentResponse(array $attachmentData): StreamedResponse
    {
        $path = $attachmentData['path'] ?? null;

        abort_unless(is_string($path) && Storage::exists($path), 404);

        $name = is_string($attachmentData['name'] ?? null)
            ? $attachmentData['name']
            : basename($path);

        $mime = $attachmentData['mime'] ?? null;
        $headers = is_string($mime) ? ['Content-Type' => $mime] : [];

        return Storage::response($path, $name, $headers);
    }

    public function destroy(Request $request, Chirp $chirp): RedirectResponse
    {
        Gate::authorize('delete', $chirp);

        $chirp->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Chirp deleted.')]);

        return to_route('chirps.index');
    }
}
