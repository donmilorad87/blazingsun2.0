<?php

declare(strict_types=1);

namespace BlazingSun\Api\Controllers;

use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;
use BlazingSun\Api\Models\PageVisit;

final readonly class HealthController
{
    public function __construct(private PageVisit $visits)
    {
    }

    /**
     * GET /api/health — used by the deploy smoke test; reads (never increments) the counter.
     * webp: whether this host's GD can write WebP (admin uploads fall back to PNG only when it can't).
     */
    public function show(Request $request): JsonResponse
    {
        return new JsonResponse(200, [
            'status' => 'ok',
            'database' => 'ok',
            'visits' => $this->visits->total(),
            'webp' => function_exists('imagewebp') && (imagetypes() & IMG_WEBP) !== 0,
        ]);
    }
}
