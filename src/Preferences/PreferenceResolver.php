<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Preferences;

use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\PreferenceRepository;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;

final class PreferenceResolver
{
    public function __construct(private PreferenceRepository $repository, private Configuration $config) {}

    public function enabled(RecipientIdentity $recipient, string $type, string $channel): bool
    {
        return $this->explain($recipient, $type, $channel)['effective'];
    }

    /** @return array{effective:bool,source:string} */
    public function explain(RecipientIdentity $recipient, ?string $type, string $channel): array
    {
        if ($this->config->enabled('preferences')) {
            if ($type !== null && ($value = $this->repository->get($recipient, $type, $channel)) !== null) {
                return ['effective' => $value, 'source' => 'user_type'];
            }
            if (($value = $this->repository->get($recipient, null, $channel)) !== null) {
                return ['effective' => $value, 'source' => 'user_global'];
            }
        }
        $types = $this->config->get('preferences.types', []);

        if ($type !== null && isset($types[$type][$channel])) {
            return ['effective' => $types[$type][$channel], 'source' => 'type_default'];
        }
        $defaults = $this->config->get('preferences.defaults', []);
        if (isset($defaults[$channel])) {
            return ['effective' => $defaults[$channel], 'source' => 'global_default'];
        }

        return ['effective' => $this->config->get('preferences.default', true), 'source' => 'package_default'];
    }
}
