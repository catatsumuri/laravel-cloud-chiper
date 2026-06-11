<?php

use App\Models\Chirp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('chirp message is required', function () {
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

test('users can delete their own chirps', function () {
    $user = User::factory()->create();
    $chirp = Chirp::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('chirps.destroy', $chirp))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('chirps.index'));

    expect(Chirp::find($chirp->id))->toBeNull();
});

test('users cannot delete chirps belonging to other users', function () {
    $user = User::factory()->create();
    $otherChirp = Chirp::factory()->create();

    $this->actingAs($user)
        ->delete(route('chirps.destroy', $otherChirp))
        ->assertForbidden();

    expect(Chirp::find($otherChirp->id))->not->toBeNull();
});
