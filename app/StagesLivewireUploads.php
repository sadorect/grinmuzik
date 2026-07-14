<?php

namespace App;

use App\Support\UploadLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Symfony\Component\Process\Process;
use Throwable;

trait StagesLivewireUploads
{
    /**
     * Persist a Livewire upload onto a stable disk before Media Library imports it.
     *
     * @param  object  $model
     */
    protected function addStagedMedia(
        $model,
        TemporaryUploadedFile|UploadedFile $file,
        string $collection,
        string $fileName,
        ?string $errorField = null
    ): void
    {
        $extension = strtolower(
            $file->getClientOriginalExtension()
            ?: $file->extension()
            ?: pathinfo($fileName, PATHINFO_EXTENSION)
            ?: 'bin'
        );

        $errorKey = $errorField ?? $collection;
        $stagedPath = null;

        try {
            $stagedPath = $file->storeAs(
                'tmp/media-imports',
                Str::uuid()->toString().'.'.$extension,
                ['disk' => 'local']
            );

            // Keep Media Library's ceiling in lockstep with the admin-configurable
            // upload limits so raising them in site settings can't be silently
            // undercut by Spatie's static max_file_size config.
            config(['media-library.max_file_size' => max(
                UploadLimits::audioBytes(),
                UploadLimits::imageBytes(),
            )]);

            $model->addMediaFromDisk($stagedPath, 'local')
                ->usingFileName($fileName)
                ->toMediaCollection($collection);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (FileIsTooBig $exception) {
            throw ValidationException::withMessages([
                $errorKey => ['That file is larger than the allowed upload limit. Please choose a smaller file and try again.'],
            ]);
        } catch (FileCannotBeAdded $exception) {
            throw ValidationException::withMessages([
                $errorKey => ["We couldn't process that file for upload. Please check the file type and try again."],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                $errorKey => ["We couldn't finish uploading that file right now. Please try again in a moment."],
            ]);
        } finally {
            if ($stagedPath) {
                Storage::disk('local')->delete($stagedPath);
            }
        }
    }

    /**
     * Extract the duration in seconds from an uploaded audio file using ffprobe.
     * Returns 0 if ffprobe is unavailable or extraction fails.
     */
    protected function extractAudioDuration(TemporaryUploadedFile|UploadedFile $file): int
    {
        try {
            $path = $file->getRealPath();

            if (! $path || ! file_exists($path)) {
                return 0;
            }

            $process = new Process([
                'ffprobe', '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $path,
            ]);
            $process->setTimeout(15);
            $process->run();

            $output = trim($process->getOutput());

            return $output !== '' ? max(0, (int) round((float) $output)) : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
