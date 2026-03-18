<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaInstallPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_endpoint_exposes_install_metadata(): void
    {
        $response = $this->get(route('manifest'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8')
            ->assertSee('"name":"GrinMuzik"', false)
            ->assertSee('"display":"standalone"', false)
            ->assertSee('/pwa-icon.svg', false);
    }

    public function test_offline_page_is_available(): void
    {
        $this->get(route('offline'))
            ->assertOk()
            ->assertSee("You're offline", false)
            ->assertSee('Try again');
    }

    public function test_public_layout_renders_install_prompt_assets(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('Install GrinMuzik')
            ->assertSee('Install App');
    }
}
