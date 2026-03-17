<?php

namespace Tests;

use App\Support\MediaLibrary\OrganizedPathGenerator;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $compiledViewsPath = storage_path('framework/views/testing-' . Str::uuid());
        File::ensureDirectoryExists($compiledViewsPath);

        config()->set('media-storage.public_disk', 'public');
        config()->set('media-storage.private_disk', 'local');
        config()->set('media-library.disk_name', 'public');
        config()->set('media-library.path_generator', OrganizedPathGenerator::class);
        config()->set('view.compiled', $compiledViewsPath);
    }
}
