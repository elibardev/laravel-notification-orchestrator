<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Persistence;

final readonly class NotificationQuery
{
    /** @var array{string,string}|null */
    public ?array $before;

    public function __construct(public int $limit = 20, public string $state = 'all', public ?string $type = null, public ?string $cursor = null)
    {
        if ($limit < 1 || $limit > 100 || ! in_array($state, ['all', 'read', 'unread'], true)) {
            throw new \InvalidArgumentException('Invalid notification query.');
        }
        $before = $cursor === null ? null : json_decode(base64_decode($cursor, true) ?: '', true);
        if ($cursor !== null && (! is_array($before) || count($before) !== 2 || ! isset($before[0], $before[1]) || ! is_string($before[0]) || ! is_string($before[1]))) {
            throw new \InvalidArgumentException('Invalid notification cursor.');
        }
        $this->before = $before === null ? null : [$before[0], $before[1]];
    }
}
