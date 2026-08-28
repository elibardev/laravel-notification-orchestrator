<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator;

use Elibardev\NotificationOrchestrator\Support\Values;

final readonly class NotificationPayload extends NotificationContext implements \JsonSerializable
{
    public string $schema;

    public function __construct(public string $id, NotificationContext $context)
    {
        Values::text($id, 'Notification id');
        $this->schema = '1.0';
        parent::__construct($context->type, $context->title, $context->message, $context->severity,
            $context->occurredAt, $context->actor, $context->subject, $context->data, $context->actions);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['schema' => $this->schema, 'id' => $this->id, 'type' => $this->type, 'title' => $this->title,
            'message' => $this->message, 'severity' => $this->severity->value,
            'occurred_at' => $this->occurredAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s.u\\Z'),
            'actor' => $this->actor?->toArray(), 'subject' => $this->subject?->toArray(),
            'data' => (object) $this->data, 'actions' => array_map(fn (NotificationAction $a) => $a->toArray(), $this->actions)];
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
