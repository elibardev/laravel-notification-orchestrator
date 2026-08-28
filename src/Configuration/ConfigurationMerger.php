<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Configuration;

final class ConfigurationMerger
{
    /**
     * @param  array<string,mixed>  $defaults
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    public function merge(array $defaults, array $overrides, string $path = ''): array
    {
        foreach ($overrides as $key => $value) {
            $child = ltrim($path.'.'.$key, '.');
            $list = in_array($child, ['api.middleware', 'channels.defaults', 'recipients.filters'], true)
                || preg_match('/^channels\.types\..+$/D', $child)
                || (is_array($defaults[$key] ?? null) && $defaults[$key] !== [] && array_is_list($defaults[$key]));
            if (is_array($value) && ! $list && (! array_is_list($value) || $value === [])
                && is_array($defaults[$key] ?? null)) {
                $defaults[$key] = $this->merge($defaults[$key], $value, $child);
            } else {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}
