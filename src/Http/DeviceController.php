<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Http;

use Elibardev\NotificationOrchestrator\Contracts\AuthenticatedNotifiableResolver;
use Elibardev\NotificationOrchestrator\Devices\DeviceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeviceController
{
    public function __construct(private DeviceRepository $devices, private AuthenticatedNotifiableResolver $owners) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['devices' => $this->devices->allFor($this->owners->resolve($request))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['driver' => 'required|string|max:64|regex:/^[a-z][a-z0-9._-]*$/', 'token' => 'required|string|max:4096', 'device_identifier' => 'nullable|uuid',
            'platform' => 'required|in:ios,android,web,desktop,unknown', 'name' => 'nullable|string|max:255']);

        return response()->json($this->devices->register($this->owners->resolve($request), $data));
    }

    public function update(Request $request, string $device): JsonResponse
    {
        $data = $request->validate(['enabled' => 'sometimes|boolean', 'name' => 'nullable|string|max:255']);
        if (array_key_exists('enabled', $data)) {
            $data['enabled'] = $request->boolean('enabled');
        }

        return response()->json($this->devices->update($this->owners->resolve($request), $device, $data));
    }

    public function destroy(Request $request, string $device): JsonResponse
    {
        $this->devices->disable($this->owners->resolve($request), $device);

        return response()->json(['disabled' => true]);
    }
}
