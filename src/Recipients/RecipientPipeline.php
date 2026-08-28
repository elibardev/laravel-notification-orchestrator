<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Recipients;

use Closure;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\RecipientFilter;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Contracts\RecipientResolver;
use Elibardev\NotificationOrchestrator\Exceptions\InvalidRecipientException;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Illuminate\Contracts\Container\Container;

final class RecipientPipeline
{
    public function __construct(private Container $container, private RecipientNormalizer $normalizer, private Configuration $config) {}

    /** @param list<mixed> $sources
     * @param  list<mixed>  $exclusions
     * @return array<string,object>
     */
    public function resolve(array $sources, array $exclusions, NotificationContext $context): array
    {
        $recipients = $this->collect($sources, $context);
        foreach ($this->collect($exclusions, $context) as $key => $unused) {
            unset($recipients[$key]);
        }
        foreach ($this->config->get('recipients.filters', []) as $class) {
            $filter = $this->container->make($class);
            if (! $filter instanceof RecipientFilter) {
                throw new InvalidRecipientException('Configured filter must implement RecipientFilter.');
            }
            $filtered = [];
            foreach ($filter->filter(array_values($recipients), $context) as $recipient) {
                $key = $this->normalizer->normalize($recipient)->key();
                // A filter may only suppress: it cannot reintroduce exclusions or new recipients.
                if (! isset($recipients[$key])) {
                    throw new InvalidRecipientException('Filters may only retain existing recipients.');
                }
                $filtered[$key] = $recipients[$key];
            }
            $recipients = $filtered;
        }

        return $recipients;
    }

    /** @param iterable<mixed> $sources
     * @return array<string,object>
     */
    private function collect(iterable $sources, NotificationContext $context, int $depth = 0): array
    {
        if ($depth > 32) {
            throw new InvalidRecipientException('Recipient sources are recursive.');
        }
        $result = [];
        foreach ($sources as $source) {
            if ($source instanceof Closure) {
                $source = $source($context);
            }
            if (is_string($source) && is_a($source, RecipientResolver::class, true)) {
                $source = $this->container->make($source);
            }
            if ($source instanceof RecipientResolver) {
                $source = $source->resolve($context);
            }
            if (is_iterable($source)) {
                $result += $this->collect($source, $context, $depth + 1);
            } elseif (is_object($source)) {
                $result[$this->normalizer->normalize($source)->key()] ??= $source;
            } else {
                throw new InvalidRecipientException('Unsupported recipient source.');
            }
        }

        return $result;
    }
}
