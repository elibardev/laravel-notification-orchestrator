<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Support;

use Elibardev\NotificationOrchestrator\ActorReference;
use Elibardev\NotificationOrchestrator\Contracts\ReferenceNormalizer;
use Elibardev\NotificationOrchestrator\Exceptions\InvalidPayloadException;
use Elibardev\NotificationOrchestrator\SubjectReference;
use Illuminate\Database\Eloquent\Model;

final class DefaultReferenceNormalizer implements ReferenceNormalizer
{
    public function actor(object|string|int|null $value): ?ActorReference
    {
        if ($value === null || $value instanceof ActorReference) {
            return $value;
        }
        if (is_string($value) || is_int($value)) {
            return new ActorReference((string) $value);
        }
        if ($value instanceof Model && $value->getKey() !== null) {
            $alias = $value->getMorphClass();

            return new ActorReference((string) $value->getKey(), $alias === $value::class ? null : $alias);
        }
        throw new InvalidPayloadException('Bind ReferenceNormalizer for custom actor objects.');
    }

    public function subject(object|string|int|null $value): ?SubjectReference
    {
        if ($value === null || $value instanceof SubjectReference) {
            return $value;
        }
        if ($value instanceof Model && $value->getKey() !== null && $value->getMorphClass() !== $value::class) {
            return new SubjectReference($value->getMorphClass(), (string) $value->getKey());
        }
        throw new InvalidPayloadException('Provide SubjectReference, a morph-mapped model, or a custom ReferenceNormalizer.');
    }
}
