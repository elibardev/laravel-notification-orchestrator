<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Context;

use Elibardev\NotificationOrchestrator\Exceptions\InvalidPayloadException;
use Elibardev\NotificationOrchestrator\Support\Values;

final readonly class ContextTarget
{
    /** @var array<array-key,mixed> */
    public array $options;

    /** @param array<array-key,mixed> $options */
    public function __construct(public string $transport, public string $destination, array $options = [])
    {
        Values::name($transport);
        Values::text($destination, 'Context destination');
        if ($transport === 'mqtt') {
            $options = array_replace(['qos' => 1, 'retain' => false], $options);
            if (! in_array($options['qos'], [0, 1], true) || ! is_bool($options['retain']) || strpbrk($destination, "+#\0") !== false) {
                throw new InvalidPayloadException('MQTT requires a publish topic, QoS 0 or 1 and boolean retain.');
            }
        }
        $this->options = Values::data($options);
    }

    public static function broadcast(string $channel): self
    {
        return new self('broadcast', $channel);
    }

    public static function mqtt(string $topic, int $qos = 1, bool $retain = false): self
    {
        return new self('mqtt', $topic, ['qos' => $qos, 'retain' => $retain]);
    }

    /** @param array<array-key,mixed> $options */
    public static function custom(string $transport, string $destination, array $options = []): self
    {
        return new self($transport, $destination, $options);
    }
}
