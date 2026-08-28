<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator;

use Elibardev\NotificationOrchestrator\Channels\ChannelDefinition;
use Elibardev\NotificationOrchestrator\Channels\ChannelKind;
use Elibardev\NotificationOrchestrator\Channels\ChannelRegistry;
use Elibardev\NotificationOrchestrator\Channels\DatabaseChannel;
use Elibardev\NotificationOrchestrator\Channels\PersonalBroadcastChannel;
use Elibardev\NotificationOrchestrator\Configuration\CapabilityRegistry;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Configuration\ConfigurationMerger;
use Elibardev\NotificationOrchestrator\Configuration\RuntimeHealth;
use Elibardev\NotificationOrchestrator\Console\InstallCommand;
use Elibardev\NotificationOrchestrator\Console\PruneCommand;
use Elibardev\NotificationOrchestrator\Console\StatusCommand;
use Elibardev\NotificationOrchestrator\Context\BroadcastContextTransport;
use Elibardev\NotificationOrchestrator\Context\ContextTransportRegistry;
use Elibardev\NotificationOrchestrator\Context\MqttContextTransport;
use Elibardev\NotificationOrchestrator\Contracts\AuthenticatedNotifiableResolver;
use Elibardev\NotificationOrchestrator\Contracts\DeliveryExecutor;
use Elibardev\NotificationOrchestrator\Contracts\FcmAccessTokenProvider;
use Elibardev\NotificationOrchestrator\Contracts\IdGenerator;
use Elibardev\NotificationOrchestrator\Contracts\MqttDriver;
use Elibardev\NotificationOrchestrator\Contracts\NotificationRepository;
use Elibardev\NotificationOrchestrator\Contracts\PreferenceRepository;
use Elibardev\NotificationOrchestrator\Contracts\PushDestinationResolver;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Contracts\ReferenceNormalizer;
use Elibardev\NotificationOrchestrator\Events\InboxChanged;
use Elibardev\NotificationOrchestrator\Execution\AfterCommit;
use Elibardev\NotificationOrchestrator\Execution\NotificationExecutor;
use Elibardev\NotificationOrchestrator\Http\DefaultAuthenticatedNotifiableResolver;
use Elibardev\NotificationOrchestrator\Mail\MailChannel;
use Elibardev\NotificationOrchestrator\Mqtt\MqttChannel;
use Elibardev\NotificationOrchestrator\Mqtt\MqttClientFactory;
use Elibardev\NotificationOrchestrator\Mqtt\NativeMqttClientFactory;
use Elibardev\NotificationOrchestrator\Mqtt\PhpMqttDriver;
use Elibardev\NotificationOrchestrator\Persistence\DatabaseNotificationRepository;
use Elibardev\NotificationOrchestrator\Preferences\DatabasePreferenceRepository;
use Elibardev\NotificationOrchestrator\Preferences\InMemoryPreferenceRepository;
use Elibardev\NotificationOrchestrator\Push\FcmDriver;
use Elibardev\NotificationOrchestrator\Push\GoogleAccessTokenProvider;
use Elibardev\NotificationOrchestrator\Push\PushChannel;
use Elibardev\NotificationOrchestrator\Push\PushDriverRegistry;
use Elibardev\NotificationOrchestrator\Realtime\BroadcastInboxChange;
use Elibardev\NotificationOrchestrator\Support\UuidGenerator;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;

class NotificationOrchestratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FcmAccessTokenProvider::class, GoogleAccessTokenProvider::class);
        $this->app->bind(MqttClientFactory::class, NativeMqttClientFactory::class);
        $this->app->bind(MqttDriver::class, PhpMqttDriver::class);
        $this->app->bind(PushDestinationResolver::class, fn ($app) => $app->make($app->make(Configuration::class)->get('push.destination_resolver')));
        $this->app->singleton(PushDriverRegistry::class, function ($app) {
            $registry = new PushDriverRegistry($app);
            $registry->register('fcm', FcmDriver::class);

            return $registry;
        });
        $this->app->bind(NotificationRepository::class, DatabaseNotificationRepository::class);
        $this->app->bind(AuthenticatedNotifiableResolver::class, DefaultAuthenticatedNotifiableResolver::class);
        $this->app->singleton(NotificationOrchestrator::class);
        $this->app->bind(AfterCommit::class, fn ($app) => new AfterCommit(
            $app->make(DatabaseManager::class), $app->make('db.transactions'),
        ));
        $this->app->bind(DeliveryExecutor::class, NotificationExecutor::class);
        $this->app->singleton(CapabilityRegistry::class, function ($app) {
            $registry = new CapabilityRegistry($app->make(Configuration::class));
            foreach (['database', 'queue', 'broadcast', 'preferences', 'devices', 'push', 'mail', 'mqtt', 'delivery_tracking', 'presence', 'api', 'blade'] as $name) {
                $registry->register($name, true, $name === 'api' ? ['database'] : ($name === 'blade' ? ['api'] : []));
            }

            return $registry;
        });
        $this->app->bind(IdGenerator::class, UuidGenerator::class);
        foreach ([ReferenceNormalizer::class => 'references.normalizer',
            RecipientNormalizer::class => 'recipients.normalizer'] as $contract => $key) {
            $this->app->bind($contract, function ($app) use ($key) {
                $configuration = $app->make(Configuration::class);
                $configuration->validate();

                return $app->make($configuration->get($key));
            });
        }
        $this->app->singleton(ChannelRegistry::class, function ($app) {
            $registry = new ChannelRegistry($app, $app->make(Configuration::class), $app->make(CapabilityRegistry::class));
            foreach (['database' => [false, false], 'broadcast' => [true, true], 'mail' => [true, true], 'push' => [true, true], 'mqtt' => [true, true]] as $name => $flags) {
                $structural = in_array($name, ['database', 'broadcast'], true);
                $registry->register(new ChannelDefinition($name,
                    $structural ? ChannelKind::STRUCTURAL : ChannelKind::OPTIONAL,
                    $structural || $name === 'mail' ? 'laravel' : $name, ! $structural, $flags[0], $flags[1], true),
                    ['database' => DatabaseChannel::class, 'broadcast' => PersonalBroadcastChannel::class,
                        'mail' => MailChannel::class,
                        'push' => PushChannel::class,
                        'mqtt' => MqttChannel::class][$name],
                    destinationResolver: $app->make(Repository::class)->get('notification-orchestrator.channels.destinations', [])[$name] ?? null);
            }

            return $registry;
        });
        $this->app->singleton(ContextTransportRegistry::class, function ($app) {
            $registry = new ContextTransportRegistry($app, $app->make(Configuration::class), $app->make(CapabilityRegistry::class));
            $registry->register('broadcast', BroadcastContextTransport::class);
            $registry->register('mqtt', MqttContextTransport::class);

            return $registry;
        });
        $this->app->singleton(PreferenceRepository::class, fn ($app) => $app->make($app->make(Configuration::class)->enabled('preferences')
            ? DatabasePreferenceRepository::class : InMemoryPreferenceRepository::class));
        if (! ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached())) {
            $config = $this->app->make(Repository::class);
            $overrides = $config->get('notification-orchestrator', []);
            if (is_array($overrides)) {
                $config->set('notification-orchestrator', (new ConfigurationMerger)->merge(
                    require __DIR__.'/../config/notification-orchestrator.php', $overrides,
                ));
            }
        }
    }

    public function boot(): void
    {
        $config = $this->app->make(Configuration::class);
        if ($config->enabled('blade')) {
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'notifications');
        }
        if ($config->enabled('api') && $config->errors() === []) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }
        if ($config->enabled('devices') && $config->errors() === []) {
            $this->loadRoutesFrom(__DIR__.'/../routes/devices.php');
        }
        $this->app->make(Dispatcher::class)->listen(InboxChanged::class, BroadcastInboxChange::class);
        if ($config->enabled('broadcast')) {
            $this->app->booted(function () use ($config) {
                if ($config->errors() !== []) {
                    return;
                }
                try {
                    $this->app->make(BroadcastManager::class)->connection($config->get('broadcast.connection'))
                        ->channel($config->get('broadcast.personal_channel'), function (object $user, string $notifiable, string $id): bool {
                            $owner = $this->app->make(AuthenticatedNotifiableResolver::class)->resolve($this->app->make('request'));

                            return rawurlencode($owner->type) === $notifiable && rawurlencode($owner->id) === $id;
                        });
                } catch (\Throwable) { /* Strict validation and status report invalid enabled broadcasters safely. */
                }
            });
        }
        $this->app->booted(function () {
            if (! $this->app->runningInConsole()) {
                $this->app->make(Configuration::class)->validate();
                $this->app->make(ChannelRegistry::class)->validateEnabled();
                $this->app->make(ContextTransportRegistry::class)->validateEnabled();
                $this->app->make(CapabilityRegistry::class)->validate();
                $this->app->make(RuntimeHealth::class)->validate();
            }
        });
        $this->commands([StatusCommand::class, InstallCommand::class, PruneCommand::class]);
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/notifications')], 'notification-orchestrator-views');
            $this->publishes([__DIR__.'/../resources/js' => $this->app->publicPath('vendor/notification-orchestrator/js'), __DIR__.'/../resources/css' => $this->app->publicPath('vendor/notification-orchestrator/css')], 'notification-orchestrator-assets');
            $this->publishes([
                __DIR__.'/../config/notification-orchestrator.php' => $this->app->configPath('notification-orchestrator.php'),
            ], 'notification-orchestrator-config');
        }
    }
}
