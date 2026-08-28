<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Support;

use BackedEnum;
use Elibardev\NotificationOrchestrator\Exceptions\InvalidNotificationTypeException;
use Elibardev\NotificationOrchestrator\Exceptions\InvalidPayloadException;

final class Values
{
    public static function text(string $value, string $field): string
    {
        if (trim($value) === '') {
            throw new InvalidPayloadException($field.' must not be blank.');
        }

        return $value;
    }

    public static function type(string|BackedEnum $type): string
    {
        $value = $type instanceof BackedEnum ? (string) $type->value : $type;
        if (trim($value) === '') {
            throw new InvalidNotificationTypeException('Notification type is required.');
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    public static function data(array $value): array
    {
        self::check($value, 0);
        try {
            return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidPayloadException('Payload must contain valid JSON values.');
        }
    }

    private static function check(mixed $value, int $depth): void
    {
        if ($depth > 64 || is_object($value) || is_resource($value)) {
            throw new InvalidPayloadException('Use explicit scalar/array payload values, not objects or recursive data.');
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                self::check($item, $depth + 1);
            }
        }
    }

    public static function name(string $value): string
    {
        if (! preg_match('/^[a-z][a-z0-9._-]*$/D', $value)) {
            throw new InvalidPayloadException('Invalid registry name.');
        }

        return $value;
    }
}
