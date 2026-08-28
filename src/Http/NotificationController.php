<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Http;

use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\AuthenticatedNotifiableResolver;
use Elibardev\NotificationOrchestrator\Contracts\NotificationRepository;
use Elibardev\NotificationOrchestrator\Persistence\NotificationQuery;
use Elibardev\NotificationOrchestrator\Realtime\PersonalChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class NotificationController
{
    public function __construct(private AuthenticatedNotifiableResolver $owners, private NotificationRepository $repository, private Configuration $config, private PersonalChannel $channels) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['limit' => 'sometimes|integer|min:1|max:100', 'state' => 'sometimes|in:all,read,unread', 'type' => 'nullable|string|max:255', 'cursor' => 'nullable|string|max:1024']);
        try {
            $query = new NotificationQuery((int) ($validated['limit'] ?? 20), $validated['state'] ?? 'all', $validated['type'] ?? null, $validated['cursor'] ?? null);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['cursor' => 'Invalid notification cursor.']);
        }

        return response()->json($this->repository->paginateFor($this->owners->resolve($request), $query)->toArray());
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $response = $this->index($request);
        $data = $response->getData(true);
        $data['realtime'] = ['enabled' => $this->config->enabled('broadcast'), 'channel' => $this->config->enabled('broadcast') ? $this->channels->name($this->owners->resolve($request)) : null,
            'auth_endpoint' => url($this->config->get('api.prefix').'/broadcasting/auth')];

        return response()->json($data);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['meta' => ['unread_count' => $this->repository->unreadCount($this->owners->resolve($request))]]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        return response()->json($this->repository->markRead($this->owners->resolve($request), $notification)->toArray());
    }

    public function unread(Request $request, string $notification): JsonResponse
    {
        return response()->json($this->repository->markUnread($this->owners->resolve($request), $notification)->toArray());
    }

    public function readAll(Request $request): JsonResponse
    {
        return response()->json($this->repository->markAllRead($this->owners->resolve($request))->toArray());
    }
}
