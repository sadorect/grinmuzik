<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDarkModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_glass_layout_renders_dark_mode_hooks(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('grinmuzik-theme', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('color-scheme', false);
    }

    public function test_guest_layout_renders_dark_mode_hooks(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('grinmuzik-theme', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('color-scheme', false);
    }
}
