<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\MemberInput;
use App\Application\TeamInput;
use App\Application\TeamService;
use App\Domain\Team;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Studio\Core\Http\JsonResponder;
use Studio\Core\Http\ValidationFailed;

final class TeamController
{
    public function __construct(
        private TeamService $service,
        private JsonResponder $json,
    ) {}

    public function list(Request $request, Response $response): Response
    {
        $q       = $request->getQueryParams();
        $page    = max(1, (int) ($q['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($q['per_page'] ?? 20)));

        [$items, $total] = $this->service->list($page, $perPage);

        return $this->json->paginated(
            $response,
            array_map(fn (Team $t) => $t->toArray(), $items),
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
        $input = TeamInput::fromArray((array) $request->getParsedBody());
        $team  = $this->service->create($input, $this->correlationId($request));

        return $this->json->created($response, $team->toArray(), "/api/v1/teams/{$team->id}");
    }

    public function update(Request $request, Response $response, string $id): Response
    {
        $input = TeamInput::fromArray((array) $request->getParsedBody());
        $team  = $this->service->update((int) $id, $input, $this->correlationId($request));

        return $this->json->ok($response, $team->toArray());
    }

    public function delete(Request $request, Response $response, string $id): Response
    {
        $this->service->delete((int) $id);

        return $this->json->noContent($response);
    }

    public function members(Request $request, Response $response, string $id): Response
    {
        $expand = (string) ($request->getQueryParams()['expand'] ?? '');
        if ($expand !== '' && $expand !== 'developers') {
            throw new ValidationFailed(['expand' => 'only "developers" is supported']);
        }

        $rows = $this->service->members((int) $id, $expand === 'developers', $this->correlationId($request));

        return $this->json->ok($response, ['data' => $rows]);
    }

    public function addMember(Request $request, Response $response, string $id): Response
    {
        $input  = MemberInput::fromArray((array) $request->getParsedBody());
        $member = $this->service->addMember((int) $id, $input->developerId, $this->correlationId($request));

        return $this->json->created($response, $member->toArray(), "/api/v1/teams/{$id}/members");
    }

    public function removeMember(Request $request, Response $response, string $id, string $developerId): Response
    {
        $this->service->removeMember((int) $id, (int) $developerId, $this->correlationId($request));

        return $this->json->noContent($response);
    }

    private function correlationId(Request $request): ?string
    {
        $id = $request->getAttribute('correlation_id');

        return is_string($id) && $id !== '' ? $id : null;
    }
}