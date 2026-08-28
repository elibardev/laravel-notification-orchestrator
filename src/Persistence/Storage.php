<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Persistence;

use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Configuration\TableNameResolver;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

final class Storage
{
    public function __construct(private DatabaseManager $database, private Configuration $config, private TableNameResolver $names) {}

    public function connection(): Connection
    {
        return $this->database->connection($this->config->get('database.connection'));
    }

    public function table(string $name): Builder
    {
        return $this->connection()->table($this->names->for($name));
    }

    public function available(string $name): bool
    {
        return $this->connection()->getSchemaBuilder()->hasTable($this->names->for($name));
    }

    /** @param array<string,mixed> $values */
    public function insertIfAbsent(string $name, array $values): bool
    {
        try {
            $this->connection()->transaction(fn () => $this->table($name)->insert($values));

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
