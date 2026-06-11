<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChirpRequest;
use App\Models\Chirp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

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
        $request->user()->chirps()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Chirp posted!')]);

        return to_route('chirps.index');
    }

    public function update(StoreChirpRequest $request, Chirp $chirp): RedirectResponse
    {
        Gate::authorize('update', $chirp);

        $chirp->update($request->validated());

        return to_route('chirps.index');
    }

    public function destroy(Request $request, Chirp $chirp): RedirectResponse
    {
        Gate::authorize('delete', $chirp);

        $chirp->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Chirp deleted.')]);

        return to_route('chirps.index');
    }
}
