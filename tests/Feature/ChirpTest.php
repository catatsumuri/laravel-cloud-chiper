<?php

use App\Jobs\ExtractChirpAttachmentMetadata;
use App\Models\Chirp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('chirps page requires authentication', function () {
    $this->get(route('chirps.index'))->assertRedirect(route('login'));
});

test('authenticated users can view the chirps page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('chirps.index'))->assertOk();
});

test('authenticated users can post a chirp', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('chirps.store'), ['message' => 'Hello, world!'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('chirps.index'));

    expect(Chirp::first())
        ->message->toBe('Hello, world!')
        ->user_id->toBe($user->id);
});

test('authenticated users can attach images to a chirp', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('photo.jpg');
    Queue::fake();

    $this->actingAs($user)
        ->post(route('chirps.store'), [
            'message' => 'See attached.',
            'attachments' => [$file],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('chirps.index'));

    $chirp = Chirp::first();
    expect($chirp)->not->toBeNull();

    $attachment = $chirp->attachments[0];

    expect($attachment['name'])->toBe('photo.jpg')
        ->and($attachment['url'])->toBe(route('chirps.attachments.show', [$chirp, 0]))
        ->and($attachment['thumbnail_url'])->toBe(route('chirps.attachments.thumbnail', [$chirp, 0]))
        ->and($attachment['mime'])->toBe('image/jpeg')
        ->and($attachment['size'])->toBe($file->getSize());

    Storage::disk('public')->assertExists($attachment['path']);

    Queue::assertPushed(
        ExtractChirpAttachmentMetadata::class,
        fn (ExtractChirpAttachmentMetadata $job): bool => $job->chirpId === $chirp->id,
    );
});

test('chirp attachment metadata is extracted by a queued job', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('photo.jpg', 640, 480);
    $path = $file->store('chirps/1', 'public');

    $chirp = Chirp::factory()->create([
        'user_id' => $user->id,
        'attachments' => [
            [
                'name' => 'photo.jpg',
                'path' => $path,
                'url' => route('chirps.attachments.show', ['chirp' => 1, 'attachment' => 0]),
                'thumbnail_url' => route('chirps.attachments.thumbnail', ['chirp' => 1, 'attachment' => 0]),
                'mime' => 'image/jpeg',
                'size' => $file->getSize(),
            ],
        ],
    ]);

    (new ExtractChirpAttachmentMetadata($chirp->id))->handle();

    $attachment = $chirp->fresh()->attachments[0];

    expect($attachment['metadata']['width'])->toBe(640)
        ->and($attachment['metadata']['height'])->toBe(480)
        ->and($attachment['metadata']['megapixels'])->toBe(0.31)
        ->and($attachment['metadata']['aspect_ratio'])->toBe(1.33)
        ->and($attachment['metadata']['orientation'])->toBe('landscape')
        ->and($attachment['metadata']['mime'])->toBe('image/jpeg')
        ->and($attachment['metadata']['file_size'])->toBe($file->getSize())
        ->and($attachment['metadata'])->toHaveKey('exif')
        ->and($attachment['metadata'])->toHaveKey('location');
});

test('authenticated users can post a chirp with only an image', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('photo.jpg');

    $this->actingAs($user)
        ->post(route('chirps.store'), [
            'message' => '',
            'attachments' => [$file],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('chirps.index'));

    $chirp = Chirp::first();
    expect($chirp)->not->toBeNull()
        ->and($chirp->message)->toBe('')
        ->and($chirp->attachments)->toHaveCount(1);
});

test('chirp attachments are served through an authenticated endpoint', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    Storage::disk('public')->put('chirps/1/photo.jpg', 'image');

    $chirp = Chirp::factory()->create([
        'attachments' => [
            [
                'name' => 'photo.jpg',
                'path' => 'chirps/1/photo.jpg',
                'url' => route('chirps.attachments.show', ['chirp' => 1, 'attachment' => 0]),
                'thumbnail_url' => route('chirps.attachments.thumbnail', ['chirp' => 1, 'attachment' => 0]),
                'mime' => 'image/jpeg',
                'size' => 5,
            ],
        ],
    ]);

    $this->get(route('chirps.attachments.show', [$chirp, 0]))
        ->assertRedirect(route('login'));

    $response = $this->actingAs($user)
        ->get(route('chirps.attachments.show', [$chirp, 0]))
        ->assertOk();

    expect($response->streamedContent())->toBe('image');
});

test('chirp attachment thumbnails are served through an authenticated endpoint', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    Storage::disk('public')->put('chirps/1/photo.jpg', 'image');

    $chirp = Chirp::factory()->create([
        'attachments' => [
            [
                'name' => 'photo.jpg',
                'path' => 'chirps/1/photo.jpg',
                'url' => route('chirps.attachments.show', ['chirp' => 1, 'attachment' => 0]),
                'thumbnail_url' => route('chirps.attachments.thumbnail', ['chirp' => 1, 'attachment' => 0]),
                'mime' => 'image/jpeg',
                'size' => 5,
            ],
        ],
    ]);

    $this->get(route('chirps.attachments.thumbnail', [$chirp, 0]))
        ->assertRedirect(route('login'));

    $response = $this->actingAs($user)
        ->get(route('chirps.attachments.thumbnail', [$chirp, 0]))
        ->assertOk();

    expect($response->streamedContent())->toBe('image');
});

test('missing chirp attachments return not found', function () {
    $user = User::factory()->create();
    $chirp = Chirp::factory()->create(['attachments' => null]);

    $this->actingAs($user)
        ->get(route('chirps.attachments.show', [$chirp, 0]))
        ->assertNotFound();
});

test('chirp attachments must be images', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('chirps.store'), [
            'message' => 'Bad attachment.',
            'attachments' => [
                UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ],
        ])
        ->assertSessionHasErrors('attachments.0');

    expect(Chirp::count())->toBe(0);
});

test('chirp message is required when no image is attached', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('chirps.store'), ['message' => ''])
        ->assertSessionHasErrors('message');
});

test('chirp message cannot exceed 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('chirps.store'), ['message' => str_repeat('a', 256)])
        ->assertSessionHasErrors('message');
});

test('users can update their own chirps', function () {
    $user = User::factory()->create();
    $chirp = Chirp::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('chirps.update', $chirp), ['message' => 'Updated message'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('chirps.index'));

    expect($chirp->fresh())->message->toBe('Updated message');
});

test('users cannot update chirps belonging to other users', function () {
    $user = User::factory()->create();
    $otherChirp = Chirp::factory()->create();

    $this->actingAs($user)
        ->patch(route('chirps.update', $otherChirp), ['message' => 'Hacked'])
        ->assertForbidden();

    expect($otherChirp->fresh())->message->not->toBe('Hacked');
});

test('updated chirp message is required', function () {
    $user = User::factory()->create();
    $chirp = Chirp::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('chirps.update', $chirp), ['message' => ''])
        ->assertSessionHasErrors('message');

    expect($chirp->fresh())->message->toBe($chirp->message);
});

test('updated chirp message cannot exceed 255 characters', function () {
    $user = User::factory()->create();
    $chirp = Chirp::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('chirps.update', $chirp), ['message' => str_repeat('a', 256)])
        ->assertSessionHasErrors('message');

    expect($chirp->fresh())->message->toBe($chirp->message);
});

test('users can delete their own chirps', function () {
    $user = User::factory()->create();
    $chirp = Chirp::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('chirps.destroy', $chirp))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('chirps.index'));

    expect(Chirp::find($chirp->id))->toBeNull();
});

test('deleting a chirp removes its attachments', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    Storage::disk('public')->put('chirps/1/photo.jpg', 'image');

    $chirp = Chirp::factory()->create([
        'user_id' => $user->id,
        'attachments' => [
            [
                'name' => 'photo.jpg',
                'path' => 'chirps/1/photo.jpg',
                'url' => route('chirps.attachments.show', ['chirp' => 1, 'attachment' => 0]),
                'thumbnail_url' => route('chirps.attachments.thumbnail', ['chirp' => 1, 'attachment' => 0]),
                'mime' => 'image/jpeg',
                'size' => 5,
            ],
        ],
    ]);

    $this->actingAs($user)
        ->delete(route('chirps.destroy', $chirp))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('chirps.index'));

    Storage::disk('public')->assertMissing('chirps/1/photo.jpg');
});

test('users cannot delete chirps belonging to other users', function () {
    $user = User::factory()->create();
    $otherChirp = Chirp::factory()->create();

    $this->actingAs($user)
        ->delete(route('chirps.destroy', $otherChirp))
        ->assertForbidden();

    expect(Chirp::find($otherChirp->id))->not->toBeNull();
});
