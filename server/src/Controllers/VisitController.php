<?php

declare(strict_types=1);

namespace BlazingSun\Api\Controllers;

use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;
use BlazingSun\Api\Models\PageVisit;

final readonly class VisitController
{
    public function __construct(private PageVisit $visits)
    {
    }

    /** POST /api/visits — counts this page load. */
    public function record(Request $request): JsonResponse
    {
        return new JsonResponse(200, ['total' => $this->visits->record()]);
    }
}
