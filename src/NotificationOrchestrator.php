<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator;

use BackedEnum;
use Elibardev\NotificationOrchestrator\Context\ContextTarget;
use Elibardev\NotificationOrchestrator\Contracts\DeliveryExecutor;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Contracts\ReferenceNormalizer;
use Elibardev\NotificationOrchestrator\Execution\AfterCommit;
use Elibardev\NotificationOrchestrator\Planning\DeliveryPlanner;
use Elibardev\NotificationOrchestrator\Testing\NotificationFake;

/** @mixin NotificationFake */
class NotificationOrchestrator
{
    public function __construct(private DeliveryPlanner $planner, private AfterCommit $boundary,
        private ReferenceNormalizer $references, private DeliveryExecutor $executor, private RecipientNormalizer $normalizer) {}

    public function make(string|BackedEnum $type): NotificationBuilder
    {
        return new NotificationBuilder($this, $this->references, $type);
    }

    public function fake(): NotificationFake
    {
        return $this->executor = new NotificationFake($this->normalizer);
    }

    /** @param list<mixed> $sources
     * @param  list<mixed>  $exclusions
     * @param  list<string>|null  $channels
     * @param  list<ContextTarget>  $targets
     */
    public function send(NotificationContext $context, array $sources, array $exclusions = [], ?array $channels = null, array $targets = []): NotificationDispatchResult
    {
        $executor = $this->executor;
        $plan = $this->planner->plan($context, $sources, $exclusions, $channels, $targets, $executor instanceof NotificationFake);
        $result = $plan->result();
        $this->boundary->run(static fn () => $executor->execute($plan));

        return $result;
    }

    /** @param list<mixed> $arguments */
    public function __call(string $name, array $arguments): mixed
    {
        if (! $this->executor instanceof NotificationFake || ! in_array($name, [
            'assertSent', 'assertNotSent', 'assertNothingSent', 'assertSentTimes', 'assertSentTo', 'assertNotSentTo',
            'assertChannelPlanned', 'assertChannelSkipped', 'assertBroadcastTo', 'assertContextSent', 'recorded',
        ], true)) {
            throw new \BadMethodCallException('Call Notify::fake() before using testing assertions.');
        }

        return $this->executor->$name(...$arguments);
    }
}
