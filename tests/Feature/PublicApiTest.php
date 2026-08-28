<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\ActorReference;
use Elibardev\NotificationOrchestrator\Channels\SkipReason;
use Elibardev\NotificationOrchestrator\Context\ContextTarget;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Contracts\ReferenceNormalizer;
use Elibardev\NotificationOrchestrator\Exceptions\InvalidActionException;
use Elibardev\NotificationOrchestrator\Exceptions\InvalidNotificationTypeException;
use Elibardev\NotificationOrchestrator\Exceptions\InvalidPayloadException;
use Elibardev\NotificationOrchestrator\Exceptions\MissingMessageException;
use Elibardev\NotificationOrchestrator\Exceptions\MissingRecipientsException;
use Elibardev\NotificationOrchestrator\Exceptions\MissingTitleException;
use Elibardev\NotificationOrchestrator\Facades\Notify;
use Elibardev\NotificationOrchestrator\NotificationAction;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\NotificationDispatcher;
use Elibardev\NotificationOrchestrator\NotificationOrchestrator;
use Elibardev\NotificationOrchestrator\NotificationSeverity;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Elibardev\NotificationOrchestrator\SubjectReference;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\TestNotifiable;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

class PublicApiTest extends TestCase
{
    public function test_generic_mqtt_targets_cannot_bypass_protocol_validation(): void
    {
        $this->expectException(InvalidPayloadException::class);
        ContextTarget::custom('mqtt', 'records/1', ['qos' => 2]);
    }

    public function test_unsafe_action_url_is_rejected_without_echoing_its_value(): void
    {
        $this->expectException(InvalidActionException::class);
        NotificationAction::navigate('open', 'Open', 'javascript:alert(1)');
    }

    public function test_custom_recipient_and_reference_normalizers_support_plain_objects(): void
    {
        $recipientNormalizer = new class implements RecipientNormalizer
        {
            public function normalize(object $recipient): RecipientIdentity
            {
                return new RecipientIdentity('custom', '1');
            }
        };
        $referenceNormalizer = new class implements ReferenceNormalizer
        {
            public function actor(object|string|int|null $value): ?ActorReference
            {
                if ($value === null) {
                    return null;
                }

                return new ActorReference('1', display: 'Explicit display');
            }

            public function subject(object|string|int|null $value): ?SubjectReference
            {
                if ($value === null) {
                    return null;
                }

                return new SubjectReference('custom', '1');
            }
        };
        app()->instance(RecipientNormalizer::class, $recipientNormalizer);
        app()->instance(ReferenceNormalizer::class, $referenceNormalizer);
        $fake = Notify::fake();
        $object = new \stdClass;
        Notify::make('x')->title('T')->message('M')->recipients($object)->actor($object)->subject($object)->send();
        Notify::assertSentTo($object, 'x');
        self::assertSame('Explicit display', $fake->recorded()[0]->payload->actor?->display);
        self::assertSame('custom', $fake->recorded()[0]->payload->subject?->type);
    }

    public function test_fluent_api_and_every_public_fake_assertion(): void
    {
        $fake = Notify::fake();
        Notify::assertNothingSent();
        $user = new TestNotifiable(['id' => 'uuid-one']);
        $other = new TestNotifiable(['id' => 'uuid-two']);
        config(['notification-orchestrator.features.broadcast' => true, 'notification-orchestrator.features.mqtt' => true, 'broadcasting.default' => 'log', 'notification-orchestrator.mqtt.host' => 'localhost']);
        $result = Notify::make(NotificationSeverity::SUCCESS)->title('Created')->message('Message')->severity('success')
            ->actor(12)->data(['keep' => 1, 'replace' => 0])->mergeData(['replace' => 2])
            ->action(NotificationAction::navigate('open', 'Open', '/records/1'))
            ->actions([NotificationAction::command('approve', 'Approve')])
            ->recipients([$user, $other])->except($other)->channels([])
            ->broadcastTo('records.1')->contextTo(ContextTarget::mqtt('records/1'))->send();
        Notify::assertSent(NotificationSeverity::SUCCESS, fn ($n) => $n->payload->data['replace'] === 2 && count($n->payload->actions) === 2);
        Notify::assertNotSent('other');
        Notify::assertSentTimes('success', 1);
        Notify::assertSentTo($user, 'success', fn ($n) => $n->payload->severity === NotificationSeverity::SUCCESS);
        Notify::assertNotSentTo($other, 'success');
        Notify::assertChannelPlanned($user, 'database');
        Notify::assertChannelPlanned($user, 'broadcast');
        Notify::assertChannelSkipped($user, 'mail', SkipReason::DISABLED);
        Notify::assertBroadcastTo('records.1');
        Notify::assertContextSent('mqtt', 'records/1');
        Notify::assertContextSent('mqtt', fn ($p) => $p->options['qos'] === 1 && $p->options['retain'] === false);
        self::assertSame(1, $result->recipientCount);
        self::assertSame(2, $result->contextDeliveryCount);
        self::assertSame($result->notificationId, $fake->recorded()[0]->payload->id);
        self::assertFalse((new \ReflectionClass(Notify::make('x')))->hasMethod('dispatch'));
    }

    public function test_injected_service_and_dispatcher_share_fake_without_facade_dependency(): void
    {
        $service = app(NotificationOrchestrator::class);
        $fake = $service->fake();
        app(NotificationDispatcher::class)->dispatch(new NotificationContext('x', 'T', 'M'), new TestNotifiable(['id' => 1]));
        $fake->assertSent('x');
        $service->fake()->assertNothingSent();
    }

    public function test_fake_is_not_installed_in_a_new_application(): void
    {
        Notify::fake();
        $this->refreshApplication();
        $this->expectException(\BadMethodCallException::class);
        Notify::assertNothingSent();
    }

    public function test_missing_type_is_rejected(): void
    {
        Notify::fake();
        $this->expectException(InvalidNotificationTypeException::class);
        Notify::make(' ');
    }

    public function test_missing_title_is_rejected(): void
    {
        Notify::fake();
        $this->expectException(MissingTitleException::class);
        Notify::make('x')->message('M')->recipients([])->send();
    }

    public function test_missing_message_is_rejected(): void
    {
        Notify::fake();
        $this->expectException(MissingMessageException::class);
        Notify::make('x')->title('T')->recipients([])->send();
    }

    public function test_missing_recipient_source_is_rejected_even_with_context(): void
    {
        Notify::fake();
        $this->expectException(MissingRecipientsException::class);
        Notify::make('x')->title('T')->message('M')->broadcastTo('record.1')->send();
    }

    public function test_empty_resolution_is_a_valid_zero_recipient_dispatch(): void
    {
        Notify::fake();
        $result = Notify::make('x')->title('T')->message('M')->recipients([])->send();
        self::assertSame(0, $result->recipientCount);
        Notify::assertSentTimes('x', 1);
    }

    public function test_duplicate_action_ids_are_rejected(): void
    {
        Notify::fake();
        $this->expectException(InvalidActionException::class);
        Notify::make('x')->title('T')->message('M')->recipients([])->actions([
            NotificationAction::command('same', 'A'), NotificationAction::command('same', 'B')])->send();
    }

    public function test_negative_assertion_fails_for_a_real_record(): void
    {
        Notify::fake();
        Notify::make('x')->title('T')->message('M')->recipients([])->send();
        $this->expectException(AssertionFailedError::class);
        Notify::assertNotSent('x');
    }
}
