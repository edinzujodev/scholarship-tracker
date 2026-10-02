<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\AuthenticatePartner;
use App\Actions\ChangeApplicationStatus;
use App\Audit\AuditTrail;
use App\Doctrine\Types\EncryptedStringType;
use App\Audit\DoctrineAuditTrail;
use App\Logging\QueryStats;
use App\Repositories\ApplicationRepository;
use App\Security\Pii\BlindIndex;
use App\Security\Pii\CloudKmsKeyProvider;
use App\Security\Pii\FieldCipher;
use App\Security\Pii\KeyProvider;
use App\Security\Pii\LocalKeyProvider;
use App\Repositories\CampaignRepository;
use App\Repositories\Doctrine\DoctrineApplicationRepository;
use App\Repositories\Doctrine\DoctrineCampaignRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CampaignRepository::class => DoctrineCampaignRepository::class,
        ApplicationRepository::class => DoctrineApplicationRepository::class,
        AuditTrail::class => DoctrineAuditTrail::class,
    ];

    /** @var array<class-string, class-string> */
    public array $singletons = [
        // One per request lifecycle; reset by AssignRequestId.
        QueryStats::class => QueryStats::class,
    ];

    public function register(): void
    {
        // Personal-data encryption keys: Cloud KMS in production, local otherwise.
        $this->app->singleton(KeyProvider::class, static function (Application $app): KeyProvider {
            $config = $app->make(ConfigRepository::class);

            return match ($config->get('pii.driver')) {
                'cloud_kms' => new CloudKmsKeyProvider(
                    $app->make(HttpFactory::class),
                    (string) $config->get('pii.cloud_kms.key_name'),
                    (string) $config->get('pii.cloud_kms.wrapped_keys'),
                    (string) $config->get('pii.cloud_kms.wrapped_index_key'),
                    (string) $config->get('pii.cloud_kms.endpoint'),
                    (string) $config->get('pii.cloud_kms.access_token'),
                    (string) $config->get('pii.cloud_kms.metadata_token_url'),
                ),
                'local' => new LocalKeyProvider(
                    $app->environment(),
                    (string) $config->get('app.key'),
                    (string) $config->get('pii.local.keys'),
                    (string) $config->get('pii.local.index_key'),
                ),
                default => throw new \RuntimeException('PII_KEY_DRIVER must be "cloud_kms" or "local".'),
            };
        });
        $this->app->singleton(FieldCipher::class, static fn (Application $app) => new FieldCipher($app->make(KeyProvider::class)->keys()));
        $this->app->singleton(BlindIndex::class, static fn (Application $app) => new BlindIndex($app->make(KeyProvider::class)->keys()));

        // The status-change action logs to the dedicated audit channel.
        $this->app->when(ChangeApplicationStatus::class)
            ->needs(LoggerInterface::class)
            ->give(static fn () => Log::channel('audit'));
    }

    public function boot(): void
    {
        // Doctrine creates its types itself, so the encrypted column type
        // resolves the cipher lazily (keys are only fetched when first needed).
        EncryptedStringType::resolveCipherUsing(fn (): FieldCipher => $this->app->make(FieldCipher::class));

        // A bad PARTNER_*_TOKEN in .env stops the app with a clear message.
        AuthenticatePartner::assertValidConfiguration((array) config('partners.partners', []));

        // Laravel's middleware priority runs throttling before partner.auth, so
        // the partner id is not resolved yet. Key by a hash of the bearer token
        // (never the raw token), falling back to the client IP.
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(120)->by(
            $request->bearerToken() !== null
                ? 'token:'.hash('sha256', $request->bearerToken())
                : 'ip:'.$request->ip(),
        ));
    }
}
