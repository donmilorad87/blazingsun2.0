<?php

declare(strict_types=1);

namespace BlazingSun\Api\Controllers;

use BlazingSun\Api\Database\MigrationRunner;
use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;
use Closure;

/**
 * POST /api/migrations — called by the deploy job (no SSH on the host), protected by a bearer token.
 * After the migrations it runs $seed (the one-time CMS content seed), which returns whether it seeded now.
 */
final readonly class MigrationController
{
    /** @param Closure(): bool $seed */
    public function __construct(
        private MigrationRunner $runner,
        private string $token,
        private Closure $seed,
    ) {
    }

    public function run(Request $request): JsonResponse
    {
        if ($this->token === '') {
            return JsonResponse::error(404, 'Not found');
        }
        $given = $request->bearerToken();
        if ($given === null || !hash_equals($this->token, $given)) {
            return JsonResponse::error(401, 'Unauthorized');
        }

        $applied = $this->runner->run();

        return new JsonResponse(200, ['applied' => $applied, 'seeded' => ($this->seed)()]);
    }
}
