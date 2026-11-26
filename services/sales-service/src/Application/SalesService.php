<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\GameDirectory;
use App\Domain\Sale;
use App\Domain\SaleFilter;
use App\Domain\SaleNotFound;
use App\Domain\SaleRepository;
use Psr\Log\LoggerInterface;
use Studio\Core\Event\DomainEvent;
use Studio\Core\Event\EventBus;
use Studio\Core\Http\ValidationFailed;
use Throwable;

final class SalesService
{
    public function __construct(
        private SaleRepository $sales,
        private GameDirectory $games,
        private EventBus $bus,
        private LoggerInterface $logger,
    ) {}

    /** @return array{0: Sale[], 1: int} items and total count */
    public function list(int $page, int $perPage, SaleFilter $filter): array
    {
        return [
            $this->sales->findAll($perPage, ($page - 1) * $perPage, $filter),
            $this->sales->count($filter),
        ];
    }

    public function get(int $id): Sale
    {
        return $this->sales->find($id) ?? throw new SaleNotFound($id);
    }

    public function record(SaleInput $in, ?string $correlationId = null): Sale
    {
        $problem = $this->sellableProblem($in->gameId, $correlationId);
        if ($problem !== null) {
            throw new ValidationFailed(['game_id' => $problem]);
        }

        $sale = $this->sales->insert($this->toSale($in));

        $this->publish(new DomainEvent(
            type: 'sale.recorded',
            payload: [
                'sale_id'          => $sale->id,
                'game_id'          => $sale->gameId,
                'units'            => $sale->units,
                'unit_price_cents' => $sale->unitPriceCents,
                'currency'         => $sale->currency->value,
                'region'           => $sale->region->value,
                'platform'         => $sale->platform->value,
                'sold_at'          => $sale->soldAt->format(DATE_ATOM),
            ],
            source: 'sales-service',
            correlationId: $correlationId,
        ));

        return $sale;
    }

    /** All-or-nothing: if any item is invalid or any game is not sellable, nothing is saved. */
    public function recordBatch(BatchInput $batch, ?string $correlationId = null): int
    {
        $checked = [];                                       // each game is checked only once
        $errors  = [];

        foreach ($batch->items as $i => $item) {
            if (!array_key_exists($item->gameId, $checked)) {
                $checked[$item->gameId] = $this->sellableProblem($item->gameId, $correlationId);
            }
            if ($checked[$item->gameId] !== null) {
                $errors["items.{$i}.game_id"] = $checked[$item->gameId];
            }
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        $count = $this->sales->insertMany(array_map($this->toSale(...), $batch->items));

        $this->publish(new DomainEvent(
            type: 'sales.batch_recorded',
            payload: ['count' => $count, 'game_ids' => array_keys($checked)],
            source: 'sales-service',
            correlationId: $correlationId,
        ));

        return $count;
    }

    /** @return string|null why the game cannot be sold, or null when it can */
    private function sellableProblem(int $gameId, ?string $correlationId): ?string
    {
        $status = $this->games->status($gameId, $correlationId);

        return match (true) {
            $status === null       => 'game does not exist',
            $status !== 'released' => "game is {$status}, only released games can be sold",
            default                => null,
        };
    }

    private function toSale(SaleInput $in): Sale
    {
        return new Sale(
            null, $in->gameId, $in->soldAt, $in->units, $in->unitPriceCents,
            $in->currency, $in->region, $in->platform,
        );
    }

    /** A broken event bus must not make an already committed sale fail. */
    private function publish(DomainEvent $event): void
    {
        try {
            $this->bus->publish($event);
        } catch (Throwable $e) {
            $this->logger->warning('Event publish failed', ['type' => $event->type, 'error' => $e->getMessage()]);
        }
    }
}