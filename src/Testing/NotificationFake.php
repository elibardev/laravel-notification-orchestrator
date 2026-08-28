<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Testing;

use BackedEnum;
use Closure;
use Elibardev\NotificationOrchestrator\Channels\ChannelPlanStatus;
use Elibardev\NotificationOrchestrator\Channels\SkipReason;
use Elibardev\NotificationOrchestrator\Contracts\DeliveryExecutor;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Planning\NotificationDispatchPlan;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Elibardev\NotificationOrchestrator\Support\Values;
use PHPUnit\Framework\Assert;

final class NotificationFake implements DeliveryExecutor
{
    public function __construct(private RecipientNormalizer $normalizer) {}

    /** @var list<RecordedNotification> */
    private array $notifications = [];

    public function execute(NotificationDispatchPlan $plan): void
    {
        $this->notifications[] = new RecordedNotification($plan->context, $plan->payload, $plan, $plan->result());
    }

    /** @return list<RecordedNotification> */
    public function recorded(): array
    {
        return $this->notifications;
    }

    /** @return list<RecordedNotification> */
    private function matching(string|BackedEnum $type, ?Closure $predicate = null): array
    {
        $type = Values::type($type);

        return array_values(array_filter($this->notifications, fn ($n) => $n->context->type === $type && ($predicate === null || $predicate($n))));
    }

    public function assertSent(string|BackedEnum $type, ?Closure $predicate = null): void
    {
        Assert::assertNotEmpty($this->matching($type, $predicate), 'Expected a matching logical notification.');
    }

    public function assertNotSent(string|BackedEnum $type, ?Closure $predicate = null): void
    {
        Assert::assertEmpty($this->matching($type, $predicate), 'Unexpected logical notification.');
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->notifications, 'Unexpected logical notifications.');
    }

    public function assertSentTimes(string|BackedEnum $type, int $times): void
    {
        Assert::assertCount($times, $this->matching($type));
    }

    private function identity(object $recipient): RecipientIdentity
    {
        return $this->normalizer->normalize($recipient);
    }

    public function assertSentTo(object $recipient, string|BackedEnum $type, ?Closure $predicate = null): void
    {
        $identity = $this->identity($recipient);
        Assert::assertTrue($this->hasRecipient($identity, $type, $predicate), 'Expected a matching recipient notification.');
    }

    public function assertNotSentTo(object $recipient, string|BackedEnum $type, ?Closure $predicate = null): void
    {
        Assert::assertFalse($this->hasRecipient($this->identity($recipient), $type, $predicate), 'Unexpected recipient notification.');
    }

    private function hasRecipient(RecipientIdentity $recipient, string|BackedEnum $type, ?Closure $predicate): bool
    {
        foreach ($this->matching($type, $predicate) as $notification) {
            foreach ($notification->plan->recipients as $plan) {
                if ($plan->recipient->key() === $recipient->key()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function assertChannelPlanned(object $recipient, string $channel): void
    {
        $this->assertChannel($recipient, $channel, ChannelPlanStatus::DELIVER);
    }

    public function assertChannelSkipped(object $recipient, string $channel, ?SkipReason $reason = null): void
    {
        $this->assertChannel($recipient, $channel, ChannelPlanStatus::SKIP, $reason);
    }

    private function assertChannel(object $recipient, string $channel, ChannelPlanStatus $status, ?SkipReason $reason = null): void
    {
        $identity = $this->identity($recipient);
        $found = false;
        foreach ($this->notifications as $notification) {
            foreach ($notification->plan->recipients as $plan) {
                if ($plan->recipient->key() !== $identity->key()) {
                    continue;
                }
                foreach ($plan->channels as $entry) {
                    if ($entry->channel === $channel && $entry->status === $status && ($reason === null || $entry->skipReason === $reason)) {
                        $found = true;
                        break 3;
                    }
                }
            }
        }
        Assert::assertTrue($found, 'Expected a matching recipient/channel plan.');
    }

    public function assertBroadcastTo(string $destination): void
    {
        $this->assertContextSent('broadcast', $destination);
    }

    public function assertContextSent(string $transport, string|Closure $destination): void
    {
        $found = false;
        foreach ($this->notifications as $notification) {
            foreach ($notification->plan->contexts as $plan) {
                if ($plan->transport === $transport && ($destination instanceof Closure ? $destination($plan) : $plan->destination === $destination)) {
                    $found = true;
                    break 2;
                }
            }
        }
        Assert::assertTrue($found, 'Expected a matching context plan.');
    }
}
