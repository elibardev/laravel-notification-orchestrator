<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Configuration;

use Elibardev\NotificationOrchestrator\Exceptions\ConfigurationException;

final class TableNameResolver
{
    public function __construct(private Configuration $config) {}

    public function for(string $name): string
    {
        $this->config->validate();
        if (! in_array($name, ['notifications', 'preferences', 'devices', 'deliveries'], true)) {
            throw new ConfigurationException('Unknown logical table.');
        }

        return $this->config->get('database.tables.'.$name) ?? $this->config->get('database.table_prefix').$name;
    }
}
