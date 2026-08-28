<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Elibardev\NotificationOrchestrator\Exceptions\InvalidActionException;
use Elibardev\NotificationOrchestrator\Exceptions\MissingMessageException;
use Elibardev\NotificationOrchestrator\Exceptions\MissingTitleException;
use Elibardev\NotificationOrchestrator\Support\Values;
use Illuminate\Support\Carbon;

readonly class NotificationContext
{
    public string $type;

    public DateTimeImmutable $occurredAt;

    /** @var array<array-key,mixed> */
    public array $data;

    /** @var list<NotificationAction> */
    public array $actions;

    /**
     * @param  array<array-key,mixed>  $data
     * @param  iterable<NotificationAction>  $actions
     */
    public function __construct(string|BackedEnum $type, public string $title, public string $message,
        public NotificationSeverity $severity = NotificationSeverity::INFO, ?DateTimeInterface $occurredAt = null,
        public ?ActorReference $actor = null, public ?SubjectReference $subject = null, array $data = [], iterable $actions = [])
    {
        $this->type = Values::type($type);
        if (trim($title) === '') {
            throw new MissingTitleException('Notification title is required.');
        }
        if (trim($message) === '') {
            throw new MissingMessageException('Notification message is required.');
        }
        $this->occurredAt = DateTimeImmutable::createFromInterface($occurredAt ?? Carbon::now());
        $this->data = Values::data($data);
        $normalized = [];
        foreach ($actions as $action) {
            if (isset($normalized[$action->id])) {
                throw new InvalidActionException('Actions must have unique ids.');
            }
            $normalized[$action->id] = $action;
        }
        $this->actions = array_values($normalized);
    }
}
