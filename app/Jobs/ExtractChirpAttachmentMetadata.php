<?php

namespace App\Jobs;

use App\Models\Chirp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExtractChirpAttachmentMetadata implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [1, 5, 10];

    public function __construct(
        public int $chirpId,
    ) {}

    public function handle(): void
    {
        $chirp = Chirp::find($this->chirpId);

        if (! $chirp) {
            return;
        }

        if ($chirp->attachments === null || $chirp->attachments === []) {
            return;
        }

        $attachments = [];

        foreach ($chirp->attachments as $attachment) {
            $attachments[] = $this->attachmentWithMetadata($attachment);
        }

        $chirp->update(['attachments' => $attachments]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Failed to extract chirp attachment metadata.', [
            'chirp_id' => $this->chirpId,
            'exception' => $exception?->getMessage(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @return array<string, mixed>
     */
    private function attachmentWithMetadata(array $attachment): array
    {
        $path = $attachment['path'] ?? null;

        if (! is_string($path) || ! Storage::disk('public')->exists($path)) {
            return $attachment;
        }

        $absolutePath = Storage::disk('public')->path($path);
        $imageSize = @getimagesize($absolutePath);

        if ($imageSize === false) {
            return $attachment;
        }

        [$width, $height] = $imageSize;
        $fileSize = $this->integerValue(
            $attachment['size'] ?? Storage::disk('public')->size($path),
        );
        $exifData = $this->exifDataFor($absolutePath);

        return [
            ...$attachment,
            'metadata' => [
                ...$this->metadataFor($attachment),
                'width' => $width,
                'height' => $height,
                'megapixels' => round(($width * $height) / 1_000_000, 2),
                'aspect_ratio' => round($width / $height, 2),
                'orientation' => $this->orientationFor($width, $height),
                'mime' => $imageSize['mime'],
                'image_type' => $imageSize[2],
                'bits' => $imageSize['bits'] ?? null,
                'channels' => $imageSize['channels'] ?? null,
                'file_size' => $fileSize,
                'exif' => $this->exifFor($exifData),
                'location' => $this->locationFor($exifData),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @return array<string, mixed>
     */
    private function metadataFor(array $attachment): array
    {
        $metadata = $attachment['metadata'] ?? [];

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @return 'landscape'|'portrait'|'square'
     */
    private function orientationFor(int $width, int $height): string
    {
        if ($width === $height) {
            return 'square';
        }

        return $width > $height ? 'landscape' : 'portrait';
    }

    /**
     * @return array<string, mixed>
     */
    private function exifDataFor(string $path): array
    {
        if (! function_exists('exif_read_data')) {
            return [];
        }

        $data = @exif_read_data($path, 'IFD0,EXIF', true);

        if (! is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int|float|string>
     */
    private function exifFor(array $data): array
    {
        return array_filter([
            'make' => $this->stringValue($data['IFD0']['Make'] ?? null),
            'model' => $this->stringValue($data['IFD0']['Model'] ?? null),
            'software' => $this->stringValue($data['IFD0']['Software'] ?? null),
            'orientation' => $this->integerValue($data['IFD0']['Orientation'] ?? null),
            'taken_at' => $this->stringValue(
                $data['EXIF']['DateTimeOriginal'] ?? $data['IFD0']['DateTime'] ?? null,
            ),
            'exposure_time' => $this->stringValue($data['EXIF']['ExposureTime'] ?? null),
            'f_number' => $this->fractionValue($data['EXIF']['FNumber'] ?? null),
            'iso' => $this->integerValue($data['EXIF']['ISOSpeedRatings'] ?? null),
            'focal_length' => $this->fractionValue($data['EXIF']['FocalLength'] ?? null),
            'lens_model' => $this->stringValue($data['EXIF']['UndefinedTag:0xA434'] ?? null),
        ], fn (int|float|string|null $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{latitude: float, longitude: float, altitude?: float}|null
     */
    private function locationFor(array $data): ?array
    {
        $gps = $data['GPS'] ?? null;

        if (! is_array($gps)) {
            return null;
        }

        $latitude = $this->coordinateValue($gps['GPSLatitude'] ?? null, $gps['GPSLatitudeRef'] ?? null);
        $longitude = $this->coordinateValue($gps['GPSLongitude'] ?? null, $gps['GPSLongitudeRef'] ?? null);

        if ($latitude === null || $longitude === null) {
            return null;
        }

        $location = [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        $altitude = $this->fractionValue($gps['GPSAltitude'] ?? null);

        if (is_int($altitude) || is_float($altitude)) {
            $location['altitude'] = (float) $altitude;
        }

        return $location;
    }

    private function coordinateValue(mixed $coordinate, mixed $reference): ?float
    {
        if (! is_array($coordinate) || count($coordinate) < 3) {
            return null;
        }

        $degrees = $this->fractionValue($coordinate[0]);
        $minutes = $this->fractionValue($coordinate[1]);
        $seconds = $this->fractionValue($coordinate[2]);

        if (! is_numeric($degrees) || ! is_numeric($minutes) || ! is_numeric($seconds)) {
            return null;
        }

        $decimal = (float) $degrees + ((float) $minutes / 60) + ((float) $seconds / 3600);
        $reference = strtoupper((string) $reference);

        if ($reference === 'S' || $reference === 'W') {
            $decimal *= -1;
        }

        return round($decimal, 6);
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function fractionValue(mixed $value): int|float|string|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        if (! str_contains($value, '/')) {
            return trim($value);
        }

        [$numerator, $denominator] = array_map('trim', explode('/', $value, 2));

        if (! is_numeric($numerator) || ! is_numeric($denominator) || (float) $denominator === 0.0) {
            return trim($value);
        }

        return round((float) $numerator / (float) $denominator, 2);
    }
}
