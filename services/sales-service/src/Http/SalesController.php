<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\BatchInput;
use App\Application\DateParser;
use App\Application\ReportService;
use App\Application\SaleInput;
use App\Application\SalesService;
use App\Domain\Platform;
use App\Domain\Region;
use App\Domain\Sale;
use App\Domain\SaleFilter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Studio\Core\Http\JsonResponder;
use Studio\Core\Http\ValidationFailed;
use Studio\Core\Support\Clock;

final class SalesController
{
    public function __construct(
        private SalesService $sales,
        private ReportService $reports,
        private JsonResponder $json,
        private Clock $clock,
    ) {}

    public function list(Request $request, Response $response): Response
    {
        $q       = $request->getQueryParams();
        $page    = max(1, (int) ($q['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($q['per_page'] ?? 20)));

        [$items, $total] = $this->sales->list($page, $perPage, $this->filter($q));

        return $this->json->paginated(
            $response,
            array_map(fn (Sale $s) => $s->toArray(), $items),
            $page,
            $perPage,
            $total,
        );
    }

    public function get(Request $request, Response $response, string $id): Response
    {
        return $this->json->ok($response, $this->sales->get((int) $id)->toArray());
    }

    public function create(Request $request, Response $response): Response
    {
        $input = SaleInput::fromArray((array) $request->getParsedBody(), $this->clock->now());
        $sale  = $this->sales->record($input, $this->correlationId($request));

        return $this->json->created($response, $sale->toArray(), "/api/v1/sales/{$sale->id}");
    }

    public function batch(Request $request, Response $response): Response
    {
        $batch    = BatchInput::fromArray((array) $request->getParsedBody(), $this->clock->now());
        $inserted = $this->sales->recordBatch($batch, $this->correlationId($request));

        return $this->json->created($response, ['inserted' => $inserted]);
    }

    public function reportByGame(Request $request, Response $response): Response
    {
        return $this->json->ok($response, ['data' => $this->reports->byGame($this->filter($request->getQueryParams()))]);
    }

    public function reportByRegion(Request $request, Response $response): Response
    {
        return $this->json->ok($response, ['data' => $this->reports->byRegion($this->filter($request->getQueryParams()))]);
    }

    public function reportMonthly(Request $request, Response $response): Response
    {
        return $this->json->ok($response, ['data' => $this->reports->monthly($this->filter($request->getQueryParams()))]);
    }

    /** The same filters work on the list and on every report. */
    private function filter(array $q): SaleFilter
    {
        $errors = [];

        $gameId = null;
        if (($q['game_id'] ?? '') !== '') {
            $gameId = filter_var($q['game_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($gameId === false) {
                $errors['game_id'] = 'must be a positive integer';
                $gameId = null;
            }
        }

        $from = null;
        if (($q['from'] ?? '') !== '') {
            $from = DateParser::parse($q['from']);
            if ($from === null) {
                $errors['from'] = 'must be a date (2026-11-01) or a date and time (2026-11-01T10:00:00Z)';
            }
        }

        $to = null;
        if (($q['to'] ?? '') !== '') {
            $to = DateParser::parse($q['to']);
            if ($to === null) {
                $errors['to'] = 'must be a date (2026-11-30) or a date and time (2026-11-30T23:59:59Z)';
            } elseif (DateParser::isDateOnly((string) $q['to'])) {
                $to = $to->setTime(23, 59, 59);                 // a plain date includes the whole day
            }
        }
        if ($from !== null && $to !== null && $from > $to) {
            $errors['from'] = 'must not be after "to"';
        }

        $region = null;
        if (($q['region'] ?? '') !== '') {
            $region = Region::tryFrom((string) $q['region']);
            if ($region === null) {
                $errors['region'] = 'unknown region';
            }
        }

        $platform = null;
        if (($q['platform'] ?? '') !== '') {
            $platform = Platform::tryFrom((string) $q['platform']);
            if ($platform === null) {
                $errors['platform'] = 'unknown platform';
            }
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new SaleFilter($gameId, $from, $to, $region, $platform);
    }

    private function correlationId(Request $request): ?string
    {
        $id = $request->getAttribute('correlation_id');

        return is_string($id) && $id !== '' ? $id : null;
    }
}