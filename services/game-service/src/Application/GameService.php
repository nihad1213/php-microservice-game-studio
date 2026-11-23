<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Game;
use App\Domain\GameFilter;
use App\Domain\GameNotFound;
use App\Domain\GameRepository;
use App\Domain\GameStatus;
use App\Domain\InvalidTransition;
use App\Domain\TeamDirectory;
use Psr\Log\LoggerInterface;
use Studio\Core\Event\DomainEvent;
use Studio\Core\Event\EventBus;
use Studio\Core\Http\ValidationFailed;
use Studio\Core\Support\Clock;
use Throwable;

final class GameService
{
    public function __construct(
        private GameRepository $games,
        private TeamDirectory $teams,
        private EventBus $bus,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /** @return array{0: Game[], 1: int} items and total count */
    public function list(int $page, int $perPage, GameFilter $filter): array
    {
        return [
            $this->games->findAll($perPage, ($page - 1) * $perPage, $filter),
            $this->games->count($filter),
        ];
    }

    public function get(int $id): Game
    {
        return $this->games->find($id) ?? throw new GameNotFound($id);
    }

    public function create(GameInput $in, ?string $correlationId = null): Game
    {
        $this->assertTeamExists($in->teamId, $correlationId);

        return $this->games->insert(new Game(
            null, $in->title, $in->genre, $in->platform, $in->priceCents,
            GameStatus::InDevelopment, $in->teamId,
        ));
    }

    public function update(int $id, GameInput $in, ?string $correlationId = null): Game
    {
        $game = $this->get($id);

        if ($game->teamId !== $in->teamId) {
            $this->assertTeamExists($in->teamId, $correlationId);
        }

        $this->games->update(
            $game->withDetails($in->title, $in->genre, $in->platform, $in->priceCents, $in->teamId)
        );

        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $game = $this->get($id);

        if (!$game->canBeDeleted()) {
            throw InvalidTransition::cannot('delete', $game->status);
        }

        $this->games->delete($id);
    }

    public function release(int $id, ?string $correlationId = null): Game
    {
        $game = $this->get($id)->release($this->clock->now());   
        $this->games->update($game);                             

        $this->publish(new DomainEvent(                         
            type: 'game.released',
            payload: [
                'game_id'     => $game->id,
                'title'       => $game->title,
                'team_id'     => $game->teamId,
                'price_cents' => $game->priceCents,
                'released_at' => $game->releaseDate->format('Y-m-d'),
            ],
            source: 'game-service',
            correlationId: $correlationId,
        ));

        return $game;
    }

    public function retire(int $id, ?string $correlationId = null): Game
    {
        $game = $this->get($id)->retire();
        $this->games->update($game);

        $this->publish(new DomainEvent(
            type: 'game.retired',
            payload: ['game_id' => $game->id, 'title' => $game->title, 'team_id' => $game->teamId],
            source: 'game-service',
            correlationId: $correlationId,
        ));

        return $game;
    }

    private function assertTeamExists(int $teamId, ?string $correlationId): void
    {
        if (!$this->teams->exists($teamId, $correlationId)) {
            throw new ValidationFailed(['team_id' => 'team does not exist']);
        }
    }

    private function publish(DomainEvent $event): void
    {
        try {
            $this->bus->publish($event);
        } catch (Throwable $e) {
            $this->logger->warning('Event publish failed', ['type' => $event->type, 'error' => $e->getMessage()]);
        }
    }
}