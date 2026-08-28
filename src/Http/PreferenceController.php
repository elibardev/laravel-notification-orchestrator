<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Http;

use Elibardev\NotificationOrchestrator\Channels\ChannelKind;
use Elibardev\NotificationOrchestrator\Channels\ChannelRegistry;
use Elibardev\NotificationOrchestrator\Contracts\AuthenticatedNotifiableResolver;
use Elibardev\NotificationOrchestrator\Contracts\PreferenceRepository;
use Elibardev\NotificationOrchestrator\Preferences\PreferenceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PreferenceController
{
    public function __construct(private AuthenticatedNotifiableResolver $owners, private PreferenceRepository $repository, private PreferenceResolver $resolver, private ChannelRegistry $channels) {}

    /** @return array{type:?string,channel:string} */
    private function key(Request $request): array
    {
        $data = $request->validate(['type' => 'nullable|string|min:1|max:255', 'channel' => 'required|string|max:255']);
        if (! $this->channels->has($data['channel']) || $this->channels->get($data['channel'])->definition->kind === ChannelKind::STRUCTURAL) {
            throw ValidationException::withMessages(['channel' => 'Choose a registered optional channel.']);
        }

        return ['type' => $data['type'] ?? null, 'channel' => $data['channel']];
    }

    public function show(Request $request): JsonResponse
    {
        $key = $this->key($request);
        $owner = $this->owners->resolve($request);

        return response()->json($key + ['configured' => $this->repository->get($owner, $key['type'], $key['channel'])] + $this->resolver->explain($owner, $key['type'], $key['channel']));
    }

    public function update(Request $request): JsonResponse
    {
        $key = $this->key($request);
        $data = $request->validate(['enabled' => 'required|boolean']);
        $this->repository->set($this->owners->resolve($request), $key['type'], $key['channel'], (bool) $data['enabled']);

        return $this->show($request);
    }

    public function destroy(Request $request): JsonResponse
    {
        $key = $this->key($request);
        $this->repository->delete($this->owners->resolve($request), $key['type'], $key['channel']);

        return $this->show($request);
    }
}
