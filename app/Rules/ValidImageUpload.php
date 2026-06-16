<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ValidImageUpload implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile && ! $value instanceof TemporaryUploadedFile) {
            $fail('The ' . $attribute . ' must be a valid uploaded file.');
            return;
        }

        $path = $value->getRealPath();

        if (! $path || ! file_exists($path)) {
            $fail('The ' . $attribute . ' could not be read.');
            return;
        }

        // Attempt to load the image data with GD — the same call Spatie uses internally.
        // This catches corrupt files that pass MIME checks but fail during processing.
        $imageData = @file_get_contents($path);

        if ($imageData === false) {
            $fail('The ' . $attribute . ' could not be read.');
            return;
        }

        if (@imagecreatefromstring($imageData) === false) {
            $fail('The ' . $attribute . ' is not a valid image. Please upload a JPG, PNG, or WebP file.');
        }
    }
}
