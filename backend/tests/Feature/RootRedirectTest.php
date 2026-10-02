<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RootRedirectTest extends TestCase
{
    #[Test]
    public function the_root_redirects_to_the_partner_portal_instead_of_a_framework_page(): void
    {
        $this->get('/')
            ->assertStatus(302)
            ->assertRedirect(config('app.frontend_url'))
            ->assertDontSee('Laravel');
    }

    #[Test]
    public function the_health_check_still_answers(): void
    {
        $this->get('/up')->assertOk();
    }
}
