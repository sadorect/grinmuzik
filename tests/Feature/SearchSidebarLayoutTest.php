<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchSidebarLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_page_uses_sidebar_shell(): void
    {
        $this->get(route('search'))
            ->assertOk()
            ->assertSee('data-public-shell="search-sidebar"', false)
            ->assertSee('aria-label="Search page sidebar"', false);
    }

    public function test_other_public_pages_keep_default_shell(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('data-public-shell="default"', false)
            ->assertDontSee('aria-label="Search page sidebar"', false);
    }
}
