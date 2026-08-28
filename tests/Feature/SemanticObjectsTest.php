<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Exceptions\InvalidPayloadException;
use Elibardev\NotificationOrchestrator\NotificationAction;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\NotificationPayload;
use Elibardev\NotificationOrchestrator\NotificationSeverity;
use Elibardev\NotificationOrchestrator\SubjectReference;
use Elibardev\NotificationOrchestrator\Support\DefaultReferenceNormalizer;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SemanticObjectsTest extends TestCase
{
    public function test_payload_has_canonical_defaults_and_json_shapes(): void
    {
        Carbon::setTestNow('2026-08-27T12:00:00Z');
        try {
            $payload = new NotificationPayload('logical-id', new NotificationContext('item.created', 'Title', 'Message'));
            $json = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
            self::assertSame('1.0', $json->schema);
            self::assertSame('logical-id', $json->id);
            self::assertSame('info', $json->severity);
            self::assertSame('2026-08-27T12:00:00.000000Z', $json->occurred_at);
            self::assertInstanceOf(\stdClass::class, $json->data);
            self::assertSame([], $json->actions);
            self::assertObjectNotHasProperty('read_at', $json);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_nested_values_and_dates_are_detached_from_mutable_input(): void
    {
        $value = 1;
        $date = new \DateTime('2026-01-01');
        $context = new NotificationContext(NotificationSeverity::INFO, 'Title', 'Message', occurredAt: $date,
            data: ['nested' => ['value' => &$value]], actions: [NotificationAction::command('open', 'Open')]);
        $value = 5;
        $date->modify('+1 year');
        self::assertSame('info', $context->type);
        self::assertSame(1, $context->data['nested']['value']);
        self::assertSame('2026', $context->occurredAt->format('Y'));
        self::assertSame('command', $context->actions[0]->type);
    }

    public function test_raw_models_are_not_serialized_into_data(): void
    {
        $this->expectException(InvalidPayloadException::class);
        new NotificationContext('x', 'T', 'M', data: ['user' => new class extends Model {}]);
    }

    public function test_subjects_require_explicit_semantic_types(): void
    {
        $normalizer = new DefaultReferenceNormalizer;
        self::assertSame('42', $normalizer->actor(42)?->id);
        $subject = new SubjectReference('record', '42');
        self::assertSame($subject, $normalizer->subject($subject));
        $this->expectException(InvalidPayloadException::class);
        $normalizer->subject(new class extends Model {});
    }
}
