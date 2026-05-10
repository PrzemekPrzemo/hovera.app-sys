<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\Sync\MutationsRequest;
use App\Services\Sync\ChangeFeedService;
use App\Services\Sync\CursorCodec;
use App\Services\Sync\MutationApplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController
{
    public function __construct(
        private readonly ChangeFeedService $feed,
        private readonly MutationApplier $applier,
    ) {}

    public function changes(Request $request): JsonResponse
    {
        [, $sinceVersion] = CursorCodec::decode($request->query('since'));

        $entities = array_filter(explode(',', (string) $request->query('entities', '')));
        $limit = (int) $request->query('limit', config('sync.pull.default_limit', 200));
        $limit = max(1, min($limit, (int) config('sync.pull.max_limit', 500)));

        return new JsonResponse($this->feed->pull($sinceVersion, $entities, $limit));
    }

    public function mutations(MutationsRequest $request): JsonResponse
    {
        $membership = $request->attributes->get('tenant_membership');
        $results = $this->applier->apply($request->validated()['mutations'], $membership);

        return new JsonResponse(['results' => $results]);
    }
}
