<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\DeveloperInput;
use App\Application\DeveloperService;
use App\Domain\Developer;
use App\Domain\Role;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Studio\Core\Http\JsonResponder;
use Studio\Core\Http\ValidationFailed;

final class DeveloperController
{
    public function __construct(
        private DeveloperService $service,
        private JsonResponder $json,
    ) {}

    public function list(Request $request, Response $response): Response
    {
        $q       = $request->getQueryParams();
        $page    = max(1, (int) ($q['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($q['per_page'] ?? 20)));

        $role = null;
        if (isset($q['role']) && $q['role'] !== '') {
            $role = Role::tryFrom((string) $q['role'])
                ?? throw new ValidationFailed(['role' => 'unknown role']);
        }

        [$items, $total] = $this->service->list($page, $perPage, $role);

        return $this->json->paginated(
            $response,
            array_map(fn (Developer $d) => $d->toArray(), $items),
            $page,
            $perPage,
            $total,
        );
    }

    public function get(Request $request, Response $response, string $id): Response
    {
        return $this->json->ok($response, $this->service->get((int) $id)->toArray());
    }

    public function create(Request $request, Response $response): Response
    {
        $input     = DeveloperInput::fromArray((array) $request->getParsedBody());
        $developer = $this->service->create($input);

        return $this->json->created(
            $response,
            $developer->toArray(),
            "/api/v1/developers/{$developer->id}",
        );
    }

    public function update(Request $request, Response $response, string $id): Response
    {
        $input = DeveloperInput::fromArray((array) $request->getParsedBody());

        return $this->json->ok($response, $this->service->update((int) $id, $input)->toArray());
    }

    public function delete(Request $request, Response $response, string $id): Response
    {
        $this->service->delete((int) $id);

        return $this->json->noContent($response);
    }
}