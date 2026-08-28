<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Devices;

use Elibardev\NotificationOrchestrator\Exceptions\DeliveryExecutionException;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Elibardev\NotificationOrchestrator\Push\PushDestination;
use Elibardev\NotificationOrchestrator\Push\PushResult;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DeviceRepository
{
    public function __construct(private Storage $storage, private Encrypter $encryption) {}

    private function owned(RecipientIdentity $owner): Builder
    {
        return $this->storage->table('devices')->where('notifiable_type', $owner->type)->where('notifiable_id', $owner->id);
    }

    /** @param array<string,mixed> $attributes
     * @return array<string,mixed> */
    public function register(RecipientIdentity $owner, #[\SensitiveParameter] array $attributes): array
    {
        $driver = $attributes['driver'] ?? null;
        $token = $attributes['token'] ?? null;
        $installation = $attributes['device_identifier'] ?? null;
        if (! is_string($driver) || ! preg_match('/^[a-z][a-z0-9._-]{0,63}$/D', $driver) || ! is_string($token) || trim($token) === '' || strlen($token) > 4096
            || ($installation !== null && (! is_string($installation) || ! Str::isUuid($installation)))) {
            throw new \InvalidArgumentException('Invalid device driver, token or installation UUID.');
        }
        if (! in_array($attributes['platform'] ?? 'unknown', ['ios', 'android', 'web', 'desktop', 'unknown'], true)
            || (isset($attributes['name']) && (! is_string($attributes['name']) || mb_strlen($attributes['name']) > 255))) {
            throw new \InvalidArgumentException('Invalid device platform or name.');
        }
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return $this->storage->connection()->transaction(function () use ($owner, $attributes, $driver, $token, $installation) {
                    $hash = hash('sha256', $token);
                    $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
                    $matches = $this->storage->table('devices')->where('driver', $driver)->where(function (Builder $q) use ($hash, $installation) {
                        $q->where('token_hash', $hash);
                        if ($installation !== null) {
                            $q->orWhere('device_identifier', $installation);
                        }
                    })->orderBy('id')->lockForUpdate()->get();
                    $current = $matches->firstWhere('token_hash', $hash) ?? $matches->first();
                    foreach ($matches as $match) {
                        if ($current !== null && $match->id !== $current->id) {
                            $this->storage->table('devices')->where('id', $match->id)->update(['device_identifier' => null, 'enabled' => false, 'invalidated_at' => $now, 'updated_at' => $now]);
                        }
                    }
                    $values = ['notifiable_type' => $owner->type, 'notifiable_id' => $owner->id, 'driver' => $driver, 'token' => $this->encryption->encrypt($token),
                        'token_hash' => $hash, 'device_identifier' => $installation, 'platform' => $attributes['platform'] ?? 'unknown', 'name' => $attributes['name'] ?? null,
                        'enabled' => true, 'last_used_at' => $now, 'invalidated_at' => null, 'updated_at' => $now];
                    if ($current === null) {
                        $id = (string) Str::uuid();
                        $this->storage->table('devices')->insert(['id' => $id, 'created_at' => $now] + $values);
                    } else {
                        $id = $current->id;
                        $this->storage->table('devices')->where('id', $id)->update($values);
                    }

                    return $this->findFor($owner, $id);
                }, 3);
            } catch (UniqueConstraintViolationException) {
                if ($attempt === 2) {
                    throw new \RuntimeException('Concurrent device registration could not be completed.');
                }
            }
        }
        throw new \LogicException('Device registration failed.');
    }

    /** @return array<string,mixed> */
    public function findFor(RecipientIdentity $owner, string $id): array
    {
        $row = $this->owned($owner)->where('id', $id)->first() ?? throw new NotFoundHttpException;

        return $this->project($row);
    }

    /** @return array<string,mixed> */
    private function project(\stdClass $row): array
    {
        return ['id' => $row->id, 'driver' => $row->driver, 'platform' => $row->platform, 'device_identifier' => $row->device_identifier,
            'name' => $row->name, 'enabled' => (bool) $row->enabled, 'invalidated_at' => $row->invalidated_at, 'last_used_at' => $row->last_used_at];
    }

    /** @return list<array<string,mixed>> */
    public function allFor(RecipientIdentity $owner): array
    {
        return array_values($this->owned($owner)->orderBy('id')->get()->map(fn ($row) => $this->project($row))->all());
    }

    /** @param array<string,mixed> $attributes
     * @return array<string,mixed> */
    public function update(RecipientIdentity $owner, string $id, array $attributes): array
    {
        $this->findFor($owner, $id);
        $values = array_intersect_key($attributes, array_flip(['name', 'enabled']));
        if ((isset($values['name']) && (! is_string($values['name']) || mb_strlen($values['name']) > 255))
            || (array_key_exists('enabled', $values) && ! is_bool($values['enabled']))) {
            throw new \InvalidArgumentException('Invalid device name or enabled state.');
        }
        if ($values !== []) {
            $this->owned($owner)->where('id', $id)->update($values + ['updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        }

        return $this->findFor($owner, $id);
    }

    public function disable(RecipientIdentity $owner, string $id): void
    {
        $this->findFor($owner, $id);
        $this->owned($owner)->where('id', $id)->update(['enabled' => false, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
    }

    /** @return iterable<PushDestination> */
    public function destinations(RecipientIdentity $owner): iterable
    {
        foreach ($this->owned($owner)->where('enabled', true)->whereNull('invalidated_at')->orderBy('id')->cursor() as $row) {
            yield new PushDestination($this->encryption->decrypt($row->token), $row->driver, $row->platform, $row->id, $row->name);
        }
    }

    /** @param callable():PushResult $send */
    public function deliverCurrent(RecipientIdentity $owner, PushDestination $destination, callable $send): PushResult
    {
        return $this->storage->connection()->transaction(function () use ($owner, $destination, $send) {
            $row = $this->owned($owner)->where('id', $destination->deviceId)->where('driver', $destination->driver)
                ->where('token_hash', hash('sha256', $destination->token))->where('enabled', true)->whereNull('invalidated_at')->lockForUpdate()->first();
            if ($row === null) {
                throw new DeliveryExecutionException;
            }
            $result = $send();
            if ($result->invalidDestination) {
                $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
                $this->storage->table('devices')->where('id', $row->id)->update(['enabled' => false, 'invalidated_at' => $now, 'updated_at' => $now]);
            }

            return $result;
        });
    }
}
