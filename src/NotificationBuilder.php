<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator;

use BackedEnum;
use DateTimeInterface;
use Elibardev\NotificationOrchestrator\Context\ContextTarget;
use Elibardev\NotificationOrchestrator\Contracts\ReferenceNormalizer;
use Elibardev\NotificationOrchestrator\Exceptions\InvalidPayloadException;
use Elibardev\NotificationOrchestrator\Support\Values;

final class NotificationBuilder
{
    private string $type;

    private string $title = '';

    private string $message = '';

    private NotificationSeverity $severity = NotificationSeverity::INFO;

    private ?\DateTimeImmutable $occurredAt = null;

    private ?ActorReference $actor = null;

    private ?SubjectReference $subject = null;

    /** @var array<array-key,mixed> */
    private array $data = [];

    /** @var list<NotificationAction> */
    private array $actions = [];

    /** @var list<mixed> */
    private array $recipients = [];

    /** @var list<mixed> */
    private array $exclusions = [];

    /** @var list<string>|null */
    private ?array $channels = null;

    /** @var list<ContextTarget> */
    private array $targets = [];

    public function __construct(private NotificationOrchestrator $orchestrator, private ReferenceNormalizer $references, string|BackedEnum $type)
    {
        $this->type = Values::type($type);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function severity(NotificationSeverity|string $severity): self
    {
        $this->severity = $severity instanceof NotificationSeverity ? $severity : (NotificationSeverity::tryFrom($severity) ?? throw new InvalidPayloadException('Invalid severity.'));

        return $this;
    }

    public function occurredAt(DateTimeInterface $date): self
    {
        $this->occurredAt = \DateTimeImmutable::createFromInterface($date);

        return $this;
    }

    public function actor(object|string|int|null $actor): self
    {
        $this->actor = $this->references->actor($actor);

        return $this;
    }

    public function subject(object|string|int|null $subject): self
    {
        $this->subject = $this->references->subject($subject);

        return $this;
    }

    /** @param array<array-key,mixed> $data */
    public function data(array $data): self
    {
        $this->data = Values::data($data);

        return $this;
    }

    /** @param array<array-key,mixed> $data */
    public function mergeData(array $data): self
    {
        return $this->data(array_replace($this->data, $data));
    }

    public function action(NotificationAction $action): self
    {
        $this->actions[] = $action;

        return $this;
    }

    /** @param iterable<NotificationAction> $actions */
    public function actions(iterable $actions): self
    {
        foreach ($actions as $action) {
            $this->action($action);
        }

        return $this;
    }

    public function recipients(mixed $source): self
    {
        $this->recipients[] = $source;

        return $this;
    }

    public function except(mixed $source): self
    {
        $this->exclusions[] = $source;

        return $this;
    }

    /** @param array<array-key,string> $channels */
    public function channels(array $channels): self
    {
        if (! array_is_list($channels)) {
            throw new InvalidPayloadException('Channels must be a list.');
        }
        foreach ($channels as $channel) {
            Values::name($channel);
        }
        $this->channels = array_values(array_unique($channels));

        return $this;
    }

    public function broadcastTo(string $channel): self
    {
        return $this->contextTo(ContextTarget::broadcast($channel));
    }

    public function contextTo(ContextTarget $target): self
    {
        $this->targets[] = $target;

        return $this;
    }

    public function send(): NotificationDispatchResult
    {
        return $this->orchestrator->send(new NotificationContext($this->type, $this->title, $this->message, $this->severity,
            $this->occurredAt, $this->actor, $this->subject, $this->data, $this->actions), $this->recipients, $this->exclusions, $this->channels, $this->targets);
    }
}
