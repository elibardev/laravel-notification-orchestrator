<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Push;

use Elibardev\NotificationOrchestrator\NotificationPayload;

final readonly class PushMessage
{
    public function __construct(public NotificationPayload $payload, public ?string $notificationId = null) {}

    /** @return array<string,string> */
    public function data(): array
    {
        $data = ['schema' => $this->payload->schema, 'id' => $this->payload->id, 'type' => $this->payload->type,
            'actions' => json_encode(array_map(fn ($action) => $action->toArray(), $this->payload->actions), JSON_THROW_ON_ERROR)];
        if ($this->notificationId !== null) {
            $data['notification_id'] = $this->notificationId;
        }

        return $data;
    }
}
