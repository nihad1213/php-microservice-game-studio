<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\GameInput;
use App\Application\GameService;
use App\Domain\Game;
use App\Domain\GameFilter;
use App\Domain\GameStatus;
use App\Domain\Platform;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Studio\Core\Http\JsonResponder;
use Studio\Core\Http\ValidationFailed;

final class GameController
{
    public function __construct(
        private GameService $service,
        private JsonResponder $json,
    ) {}

    public function list(Request $request, Response $response): Response
    {
        $q       = $request->getQueryParams();
        $page    = max(1, (int) ($q['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($q['per_page'] ?? 20)));

        [$items, $total] = $this->service->list($page, $perPage, $this->filter($q));

        return $this->json->paginated(
            $response,
            array_map(fn (Game $g) => $g->toArray(), $items),
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
        $input = GameInput::fromArray((array) $request->getParsedBody());
        $game  = $this->service->create($input, $this->correlationId($request));

        return $this->json->created($response, $game->toArray(), "/api/v1/games/{$game->id}");
    }

    public function update(Request $request, Response $response, string $id): Response
    {
        $input = GameInput::fromArray((array) $request->getParsedBody());
        $game  = $this->service->update((int) $id, $input, $this->correlationId($request));

        return $this->json->ok($response, $game->toArray());
    }

    public function delete(Request $request, Response $response, string $id): Response
    {
        $this->service->delete((int) $id);

        return $this->json->noContent($response);
    }

    public function release(Request $request, Response $response, string $id): Response
    {
        $game = $this->service->release((int) $id, $this->correlationId($request));

        return $this->json->ok($response, $game->toArray());
    }

    public function retire(Request $request, Response $response, string $id): Response
    {
        $game = $this->service->retire((int) $id, $this->correlationId($request));

        return $this->json->ok($response, $game->toArray());
    }

    private function filter(array $q): GameFilter
    {
        $status = null;
        if (($q['status'] ?? '') !== '') {
            $status = GameStatus::tryFrom((string) $q['status'])
                ?? throw new ValidationFailed(['status' => 'unknown status']);
        }

        $platform = null;
        if (($q['platform'] ?? '') !== '') {
            $platform = Platform::tryFrom((string) $q['platform'])
                ?? throw new ValidationFailed(['platform' => 'unknown platform']);
        }

        $teamId = null;
        if (($q['team_id'] ?? '') !== '') {
            $teamId = filter_var($q['team_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($teamId === false) {
                throw new ValidationFailed(['team_id' => 'must be a positive integer']);
            }
        }

        $genre = ($q['genre'] ?? '') !== '' ? strtolower(trim((string) $q['genre'])) : null;

        return new GameFilter($status, $platform, $teamId, $genre);
    }

    private function correlationId(Request $request): ?string
    {
        $id = $request->getAttribute('correlation_id');

        return is_string($id) && $id !== '' ? $id : null;
    }
}