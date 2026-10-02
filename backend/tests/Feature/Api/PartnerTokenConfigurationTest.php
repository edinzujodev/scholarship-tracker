<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticatePartner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\BuildsApplications;
use Tests\Support\RefreshDoctrineSchema;
use Tests\TestCase;

final class PartnerTokenConfigurationTest extends TestCase
{
    use RefreshDoctrineSchema;
    use BuildsApplications;

    #[Test]
    public function each_demo_partner_has_its_own_default_token(): void
    {
        $this->assertSame(
            ['partner-demo' => 'demo-partner-token', 'partner-other' => 'other-partner-token'],
            config('partners.partners'),
        );
    }

    #[Test]
    public function a_changed_token_logs_in_as_that_partner_and_the_old_one_stops_working(): void
    {
        config(['partners.partners.partner-demo' => 'my-own-token']);
        $this->campaign();

        $this->getJson('/api/v1/campaigns', $this->authHeaders('my-own-token'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/campaigns', $this->authHeaders('demo-partner-token'))
            ->assertUnauthorized();
    }

    /** @param array<string, mixed> $partners */
    #[Test]
    #[DataProvider('invalidConfigurations')]
    public function a_misconfigured_token_fails_with_a_clear_message(array $partners, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        AuthenticatePartner::assertValidConfiguration($partners);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'empty token' => [['partner-demo' => '', 'partner-other' => 'b'], 'Partner "partner-demo" has no API token'];
        yield 'whitespace token' => [['partner-demo' => 'a', 'partner-other' => '  '], 'Partner "partner-other" has no API token'];
        yield 'missing token' => [['partner-demo' => null, 'partner-other' => 'b'], 'Partner "partner-demo" has no API token'];
        yield 'shared token' => [['partner-demo' => 'same', 'partner-other' => 'same'], 'share the same API token'];
    }

    #[Test]
    public function the_default_configuration_is_valid(): void
    {
        AuthenticatePartner::assertValidConfiguration((array) config('partners.partners'));

        $this->addToAssertionCount(1);
    }
}
