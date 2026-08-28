<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Configuration;

use Elibardev\NotificationOrchestrator\Exceptions\ConfigurationException;
use Elibardev\NotificationOrchestrator\Support\Values;

final class CapabilityRegistry
{
    /** @var array<string,array{implemented:bool,requires:list<string>}> */
    private array $definitions = [];

    public function __construct(private Configuration $config) {}

    /** @param list<string> $requires */
    public function register(string $name, bool $implemented = false, array $requires = []): void
    {
        Values::name($name);
        if (isset($this->definitions[$name])) {
            throw new ConfigurationException('Capability already registered.');
        }
        $this->definitions[$name] = ['implemented' => $implemented, 'requires' => $requires];
    }

    public function enabled(string $name): bool
    {
        return $this->config->enabled($name);
    }

    /** @return array<string,array{implemented:bool,requires:list<string>}> */
    public function all(): array
    {
        return $this->definitions;
    }

    /** @return list<string> */
    public function errors(): array
    {
        $errors = [];
        foreach ((array) $this->config->get('features', []) as $name => $enabled) {
            if (! isset($this->definitions[$name])) {
                $errors[] = 'An unregistered feature is configured.';

                continue;
            }
            if ($enabled !== true) {
                continue;
            }
            foreach ($this->definitions[$name]['requires'] as $required) {
                if (! $this->enabled($required)) {
                    $errors[] = 'features.'.$name.' requires features.'.$required.'.';
                }
            }
        }

        return $errors;
    }

    public function validate(): void
    {
        if ($errors = $this->errors()) {
            throw new ConfigurationException(implode(' ', $errors));
        }
    }
}
