<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Configuration;

use Elibardev\NotificationOrchestrator\Exceptions\ConfigurationException;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Encryption\Encrypter;

final class RuntimeHealth
{
    public function __construct(private Configuration $config, private CapabilityRegistry $capabilities, private Storage $storage, private Repository $application, private DatabaseManager $database) {}

    /** @return list<string> */
    public function errors(): array
    {
        $errors = [];
        if ($this->config->enabled('devices')) {
            $key = $this->application->get('app.key');
            $cipher = $this->application->get('app.cipher', 'AES-256-CBC');
            $decoded = is_string($key) && str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
            if (! is_string($decoded) || ! is_string($cipher) || ! Encrypter::supported($decoded, $cipher)) {
                $errors[] = 'devices: a valid application encryption key and cipher are required.';
            }
        }
        foreach ($this->capabilities->all() as $name => $definition) {
            if ($this->config->enabled($name) && ! $definition['implemented']) {
                $errors[] = $name.': module not implemented.';
            }
        }
        foreach (['database' => 'notifications', 'preferences' => 'preferences', 'delivery_tracking' => 'deliveries', 'devices' => 'devices'] as $feature => $table) {
            if (! $this->config->enabled($feature)) {
                continue;
            }
            try {
                if (! $this->storage->available($table)) {
                    $errors[] = $feature.': required table missing; run notifications:install and migrate.';
                }
            } catch (\Throwable) {
                $errors[] = $feature.': storage unavailable.';
            }
        }
        if ($this->config->enabled('queue')) {
            try {
                $name = $this->config->get('queue.connection') ?? $this->application->get('queue.default');
                $queue = $this->application->get('queue.connections.'.$name);
                if (! is_array($queue) || ! isset($queue['driver'])) {
                    $errors[] = 'queue: configured connection unavailable.';
                } elseif ($queue['driver'] === 'database' && ! $this->database->connection($queue['connection'] ?? null)->getSchemaBuilder()->hasTable($queue['table'] ?? 'jobs')) {
                    $errors[] = 'queue: application jobs table missing.';
                }
            } catch (\Throwable) {
                $errors[] = 'queue: infrastructure unavailable.';
            }
        }

        return $errors;
    }

    public function validate(): void
    {
        $this->config->validate();
        $this->capabilities->validate();
        if ($errors = $this->errors()) {
            throw new ConfigurationException(implode(' ', $errors));
        }
    }
}
