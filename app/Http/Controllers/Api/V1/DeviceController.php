<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Push\DeviceTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController
{
    public function __construct(private readonly DeviceTokenService $devices) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(['ios', 'android'])],
            'token' => ['required', 'string', 'max:512'],
            'locale' => ['nullable', 'string', 'max:8'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'device_model' => ['nullable', 'string', 'max:64'],
        ]);

        $device = $this->devices->register($request->user(), $data);

        return new JsonResponse(['id' => $device->id, 'created' => $device->wasRecentlyCreated], 201);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $this->devices->unregister($request->user(), $token);

        return new JsonResponse(['ok' => true]);
    }
}
