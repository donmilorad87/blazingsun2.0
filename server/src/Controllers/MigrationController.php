<?php

declare(strict_types=1);

namespace BlazingSun\Api\Controllers;

use BlazingSun\Api\Database\MigrationRunner;
use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;

/** POST /api/migrations — called by the deploy job (no SSH on the host), protected by a bearer token. */
final readonly class MigrationController
{
    public function __construct(
        private MigrationRunner $runner,
        private string $token,
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

        return new JsonResponse(200, ['applied' => $this->runner->run()]);
    }
}
