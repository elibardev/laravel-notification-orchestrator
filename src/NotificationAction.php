<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator;

use Elibardev\NotificationOrchestrator\Exceptions\InvalidActionException;
use Elibardev\NotificationOrchestrator\Support\Values;

final readonly class NotificationAction
{
    /** @var array<array-key, mixed> */
    public array $data;

    /** @param array<array-key, mixed> $data */
    public function __construct(public string $id, public string $type, public string $label, public ?string $url = null, array $data = [])
    {
        if (trim($id) === '' || trim($label) === '' || ! in_array($type, ['navigate', 'command'], true)) {
            throw new InvalidActionException('Action requires an id, label and supported type.');
        }
        if ($url !== null && (preg_match('/[\x00-\x20]/', $url) || (! str_starts_with($url, '/') && ! preg_match('~^https?://~i', $url)) || str_starts_with($url, '//') || str_contains($url, '\\'))) {
            throw new InvalidActionException('Action URL must be a relative path or HTTP(S) URL.');
        }
        $this->data = Values::data($data);
    }

    /** @param array<array-key, mixed> $data */
    public static function navigate(string $id, string $label, ?string $url = null, array $data = []): self
    {
        return new self($id, 'navigate', $label, $url, $data);
    }

    /** @param array<array-key, mixed> $data */
    public static function command(string $id, string $label, array $data = []): self
    {
        return new self($id, 'command', $label, null, $data);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'label' => $this->label, 'url' => $this->url, 'data' => (object) $this->data];
    }
}
