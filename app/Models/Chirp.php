<?php

namespace App\Models;

use Database\Factories\ChirpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property string $message
 * @property array<int, array{name: string, path: string, url: string, thumbnail_url?: string, mime: string|null, size: int, metadata?: array{width?: int, height?: int, megapixels?: float, aspect_ratio?: float, orientation?: string, mime?: string|null, image_type?: int|null, bits?: int|null, channels?: int|null, file_size?: int|null, exif?: array<string, int|float|string>, location?: array{latitude: float, longitude: float, altitude?: float}|null}}>|null $attachments
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['message', 'attachments'])]
class Chirp extends Model
{
    /** @use HasFactory<ChirpFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleted(function (Chirp $chirp): void {
            $paths = collect($chirp->attachments ?? [])
                ->pluck('path')
                ->filter()
                ->all();

            if ($paths !== []) {
                Storage::delete($paths);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachments' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
