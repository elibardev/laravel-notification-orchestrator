<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Channels\ChannelDefinition;
use Elibardev\NotificationOrchestrator\Channels\ChannelKind;
use Elibardev\NotificationOrchestrator\Channels\ChannelRegistry;
use Elibardev\NotificationOrchestrator\Channels\HealthStatus;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\TestChannel;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Illuminate\Testing\PendingCommand;

class StatusTest extends TestCase
{
    private function channel(): TestChannel
    {
        $features = config('notification-orchestrator.features');
        config(['notification-orchestrator.features' => array_fill_keys(array_keys($features), false)]);
        $channel = new TestChannel;
        app(ChannelRegistry::class)->register(new ChannelDefinition('test', ChannelKind::OPTIONAL, 'fake', true, true, true, true), $channel, $channel);
        config(['notification-orchestrator.features.test' => true]);

        return $channel;
    }

    public function test_default_status_reports_unimplemented_modules_not_healthy_deliveries(): void
    {
        $command = $this->artisan('notifications:status');
        self::assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('database: INVALID')->expectsOutputToContain('Overall: INVALID')->assertExitCode(1)->run();
    }

    public function test_healthy_and_degraded_custom_channels_contribute_to_status(): void
    {
        $channel = $this->channel();
        $healthy = $this->artisan('notifications:status');
        self::assertInstanceOf(PendingCommand::class, $healthy);
        $healthy->expectsOutputToContain('test: HEALTHY')->expectsOutputToContain('Overall: HEALTHY')->assertExitCode(0)->run();
        $channel->state = HealthStatus::DEGRADED;
        $degraded = $this->artisan('notifications:status');
        self::assertInstanceOf(PendingCommand::class, $degraded);
        $degraded->expectsOutputToContain('Overall: DEGRADED')->assertExitCode(2)->run();
    }

    public function test_invalid_provider_messages_never_expose_exception_secrets(): void
    {
        $channel = $this->channel();
        $channel->valid = false;
        $command = $this->artisan('notifications:status');
        self::assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('configuration rejected')->doesntExpectOutputToContain('SECRET-CREDENTIAL')->assertExitCode(1)->run();
    }

    public function test_malformed_config_is_reported_without_preventing_diagnostics(): void
    {
        config(['notification-orchestrator.features' => null, 'notification-orchestrator.push.enabled' => 'SECRET-VALUE']);
        $command = $this->artisan('notifications:status');
        self::assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('features must be a map')->expectsOutputToContain('features.push')
            ->doesntExpectOutputToContain('SECRET-VALUE')->assertExitCode(1)->run();
    }
}
