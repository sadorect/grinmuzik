<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PricingPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pricing_page_now_presents_creator_access_copy(): void
    {
        $this->get(route('pricing'))
            ->assertOk()
            ->assertSee('Creator Access')
            ->assertSee('Artist Launch Flow')
            ->assertSee('Start as an Artist')
            ->assertDontSee('Pricing and Donations')
            ->assertDontSee('View Stripe Pricing')
            ->assertDontSee('Platform Cut');
    }
}
