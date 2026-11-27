# Sales Service

This document describes the **sales-service** of Game Studio: what it owns, how it is structured, its HTTP API and reports, how it uses SQLite, how it works together with game-service and Redis, and how to run and extend it.

For the Docker, gateway, MariaDB and phpMyAdmin setup, see the Docker configuration document. For the shared error format and conventions used by every service, see the developer-service document. This file covers only what belongs to this service.

## 1. Purpose and responsibility

The sales service is the single source of truth for **what the studio has sold**. It records individual sales and batches of sales, and produces revenue reports by game, by region and by month. It is the only service that uses **SQLite** instead of MariaDB.

| Item | Value |
|---|---|
| Service name | `sales-service` |
| Stack | PHP 8.3, Slim 4, PHP-DI, PDO (SQLite), Phinx, Respect\Validation, Guzzle, Predis |
| Database | SQLite file `storage/sales.sqlite` on the Docker volume `sales_data` |
| Direct host access | `http://localhost:8004` |
| Through the gateway | `http://localhost:8080/api/v1/sales` |
| Source folder | `services/sales-service/` |
| Calls other services | game-service (checks that a game is released) |
| Uses | Redis (60 second cache of game status) |
| Publishes events | `sale.recorded`, `sales.batch_recorded` |

### Ownership rules

- Only this service reads or writes `sales.sqlite`. Other services never open the file.
- `game_id` refers to a game in game-service. It is a plain number, not a foreign key, and is validated through the game-service API.
- Other services that need sales data call the HTTP API below or react to the events in section 8.

## 2. Folder structure

```text
services/sales-service/
|-- composer.json
|-- phinx.php                      # migration / seed configuration (SQLite adapter)
|-- public/index.php               # front controller (only web-visible folder)
|-- config/
|   |-- container.php              # dependency injection wiring, SQLite PRAGMAs
|   `-- routes.php                 # URL routes
|-- db/
|   |-- migrations/                # schema changes (Phinx)
|   `-- seeds/SalesSeeder.php      # 5,000 sample sales for development
|-- storage/                       # sales.sqlite lives here (Docker volume)
`-- src/
    |-- Domain/                    # entity, filters, enums, contracts
    |-- Application/               # use cases, input validation, date parsing
    |-- Infrastructure/            # SQLite repository and reports, game-service client, Redis cache
    `-- Http/                      # controller
```

Shared technical code (JSON responses, error handler, correlation id middleware, logger, event bus interface, clock) lives in `shared/src` and is installed as the Composer package `studio/core`.

## 3. Layers

Each layer only depends on the layers below it. The domain layer knows nothing about HTTP, SQLite, Guzzle or Redis.

| Layer | Classes | Responsibility |
|---|---|---|
| Http | `SalesController`, `HealthController` | Read the request, parse filters, call a use case, build the JSON response |
| Application | `SalesService`, `ReportService`, `SaleInput`, `BatchInput`, `DateParser` | Use cases, validation, event publishing |
| Domain | `Sale`, `SaleFilter`, `Region`, `Platform`, `Currency`, `SaleRepository`, `SalesReport`, `GameDirectory`, `SaleNotFound` | Entity, enums, contracts, business error |
| Infrastructure | `SqliteSalesRepository`, `SqliteSalesReport`, `SaleQuery`, `GameClient`, `CachedGameDirectory` | SQL access, report queries, HTTP calls to game-service, Redis cache |

Writes go through the `SaleRepository`. Reports go through a separate `SalesReport` interface, because reporting queries are read-only aggregations and do not need the entity layer. Both the list endpoint and all reports share one filter builder (`SaleQuery`), so the same filters behave the same everywhere.

## 4. Data model

### Table `sales`

| Column | Type | Rules |
|---|---|---|
| `id` | integer, auto increment | Primary key |
| `game_id` | integer | Game in game-service, no foreign key |
| `sold_at` | datetime | UTC text in the format `YYYY-MM-DD HH:MM:SS` |
| `units` | integer | Number of copies, 1 to 100,000 |
| `unit_price_cents` | integer | Price per copy in cents, 0 to 1,000,000 |
| `currency` | varchar(3) | `USD` by default |
| `region` | varchar(20) | One of the allowed regions |
| `platform` | varchar(20) | One of the allowed platforms |
| `created_at` | datetime | Set by the database |

Indexes: (`game_id`, `sold_at`), `sold_at`, and `region`.

The total of a sale is `units * unit_price_cents`. It is calculated, not stored, and returned as `total_cents`.

### Allowed values

| Field | Values |
|---|---|
| `region` | `north_america`, `europe`, `asia`, `south_america`, `oceania`, `africa` |
| `platform` | `pc`, `playstation`, `xbox`, `switch`, `mobile` |
| `currency` | `USD`, `EUR`, `GBP` |

All three are PHP enums stored as plain strings. Adding a value means adding an enum case; no migration is needed.

### Conventions

- **Money is stored as integer cents**, never as a decimal number.
- **Dates are stored in UTC.** SQLite has no date type, so the ISO text format sorts and compares correctly. Dates sent with an offset (`+02:00`) are converted to UTC.
- **Sales are append-only.** There is no update or delete endpoint.

## 5. Business rules

| Rule | Result |
|---|---|
| Only `released` games can be sold | 422 for games in development, retired or unknown games |
| game-service unavailable | 503 (unless the game status is cached) |
| `units` is an integer from 1 to 100,000 | 422 otherwise |
| `unit_price_cents` is an integer from 0 to 1,000,000 | 422 otherwise (`19.99` is rejected) |
| `sold_at` is optional | Default is the current time (UTC) |
| `sold_at` must not be in the future | 422 |
| `currency` defaults to `USD` | Reports never add different currencies together |
| Batch of 1 to 500 items | One transaction: all sales are saved or none |
| Retired games | Can still be sold for up to 60 seconds, until the cached status expires |

## 6. HTTP API

All routes are under `/api/v1/sales`. Request and response bodies are JSON. Send `Content-Type: application/json` on POST requests.

| Method and path | Description | Success | Errors |
|---|---|---|---|
| `GET /api/v1/sales` | Paginated, filterable list (newest first) | 200 | 422 (invalid filter) |
| `POST /api/v1/sales` | Record one sale | 201 + `Location` header | 422, 503 |
| `POST /api/v1/sales/batch` | Record up to 500 sales in one transaction | 201 | 422, 503 |
| `GET /api/v1/sales/{id}` | Get one sale | 200 | 404 |
| `GET /api/v1/sales/reports/by-game` | Units and revenue per game | 200 | 422 |
| `GET /api/v1/sales/reports/by-region` | Units and revenue per region | 200 | 422 |
| `GET /api/v1/sales/reports/monthly` | Units and revenue per month | 200 | 422 |
| `GET /health` | Liveness check (not routed by the gateway) | 200 | - |

### Filters (list and all three reports)

| Parameter | Default | Rules |
|---|---|---|
| `game_id` | none | Positive integer |
| `from` | none | Date (`2026-11-01`) or date and time (`2026-11-01T10:00:00Z`) |
| `to` | none | Same formats. A plain date includes the whole day (up to 23:59:59) |
| `region` | none | Must be an allowed region |
| `platform` | none | Must be an allowed platform |
| `page` | `1` | List only. Minimum 1 |
| `per_page` | `20` | List only. Between 1 and 100 |

Invalid values return 422 with one error per field, and `from` after `to` is rejected. Filters can be combined.

In URLs, write a time zone offset as `%2B02:00` or use `Z`. A raw `+` in a query string is read as a space.

### Sale representation

```json
{
  "id": 5001,
  "game_id": 1,
  "sold_at": "2026-11-26T10:15:00+00:00",
  "units": 2,
  "unit_price_cents": 3999,
  "total_cents": 7998,
  "currency": "USD",
  "region": "europe",
  "platform": "pc",
  "created_at": "2026-11-26T10:15:01+00:00"
}
```

Paginated lists use the same `data` / `meta` envelope as the other services.

### Record a sale

```json
{
  "game_id": 1,
  "units": 2,
  "unit_price_cents": 3999,
  "currency": "USD",
  "region": "europe",
  "platform": "pc",
  "sold_at": "2026-11-26T10:15:00Z"
}
```

`currency` and `sold_at` are optional.

### Batch import

```json
{
  "items": [
    { "game_id": 1, "units": 1, "unit_price_cents": 3999, "region": "asia", "platform": "pc" },
    { "game_id": 3, "units": 3, "unit_price_cents": 2499, "region": "europe", "platform": "pc" }
  ]
}
```

Response: `201` with `{ "inserted": 2 }`.

- The list must contain between 1 and 500 items.
- Every item is validated first. If any item is invalid, or any game is not sellable, **nothing is saved**.
- Errors are named by position, for example `items.3.units` and `items.7.game_id`. At most 50 validation errors are returned.
- Each game is checked once per batch, no matter how many items refer to it.
- Inserting 500 rows in one transaction is dramatically faster in SQLite than 500 separate inserts.

### Reports

All reports accept the filters above and return `{ "data": [ ... ] }`. Every row contains `sales_count`, `units` and `revenue_cents` (the sum of `units * unit_price_cents`).

| Report | Grouped by | Sorted by |
|---|---|---|
| `/reports/by-game` | `game_id` and `currency` | Revenue, highest first |
| `/reports/by-region` | `region` and `currency` | Revenue, highest first |
| `/reports/monthly` | `month` (`YYYY-MM`) and `currency` | Month, oldest first |

```json
{
  "data": [
    { "game_id": 1, "currency": "USD", "sales_count": 812, "units": 934, "revenue_cents": 2150340 },
    { "game_id": 1, "currency": "EUR", "sales_count": 201, "units": 230, "revenue_cents": 512400 }
  ]
}
```

**Reports are always grouped by currency.** Amounts in different currencies are never added together, because that would need exchange rates. A game sold in two currencies therefore appears in two rows.

### Example requests

```bash
# record a sale
curl -i -X POST localhost:8004/api/v1/sales -H 'Content-Type: application/json' \
  -d '{"game_id":1,"units":2,"unit_price_cents":3999,"region":"europe","platform":"pc"}'

# list with filters, through the gateway
curl "localhost:8080/api/v1/sales?game_id=1&region=europe&from=2026-01-01&to=2026-06-30&per_page=5"

# reports
curl "localhost:8004/api/v1/sales/reports/by-game"
curl "localhost:8004/api/v1/sales/reports/by-region?game_id=1"
curl "localhost:8004/api/v1/sales/reports/monthly?game_id=1&from=2026-01-01"
```

## 7. Error format

Errors use the same `application/problem+json` format as every other service, including the `X-Correlation-Id` header.

| Situation | Status | Raised by |
|---|---|---|
| Invalid or missing field, invalid filter, bad date, future `sold_at` | 422 | `ValidationFailed` |
| Game does not exist, or is not released | 422 (`errors.game_id`) | `ValidationFailed` |
| Sale id does not exist | 404 | `SaleNotFound` |
| game-service is unreachable or fails | 503 | `UpstreamUnavailable` |
| Anything unexpected | 500 | Error handler (details hidden when `APP_ENV=production`) |

## 8. Communication with game-service and Redis

Before a sale is recorded, the service asks game-service for the game's status through the domain interface `GameDirectory`. The implementation is built from two classes:

- `GameClient` calls `GET {GAME_SERVICE_URL}/api/v1/games/{id}` over HTTP.
- `CachedGameDirectory` wraps it and keeps the status in Redis.

| Response from game-service | Result in sales-service |
|---|---|
| 200 with status `released` | The sale is recorded |
| 200 with another status | 422, the game is not released |
| 404 | 422, the game does not exist |
| Timeout, connection error, 5xx, other 4xx, broken response | 503 Service Unavailable |

Details:

- Timeouts are short: 1 second to connect, 2 seconds in total. The `X-Correlation-Id` header is forwarded.
- **Cache:** the key is `sales:game:{id}:status`, the value is the status text, and the lifetime is 60 seconds. "Game not found" is never cached.
- **Redis is only an optimisation.** If Redis is down, reads and writes of the cache are logged as warnings and the service falls back to one HTTP call per sale. Redis timeouts are 0.5 seconds, so a dead Redis does not slow requests noticeably.
- During a cached period, a game that has just been retired can still be sold. This is a deliberate trade-off between freshness and load on game-service.

## 9. Domain events

Events are published after the sale has been saved.

| Type | Published when | Payload |
|---|---|---|
| `sale.recorded` | A single sale is recorded | `sale_id`, `game_id`, `units`, `unit_price_cents`, `currency`, `region`, `platform`, `sold_at` |
| `sales.batch_recorded` | A batch is recorded | `count`, `game_ids` |

The envelope (`id`, `type`, `version`, `source`, `correlation_id`, `occurred_at`, `payload`) is described in the game-service document.

- The event bus is bound to `LogEventBus`, so events are written to the JSON log as `domain_event` entries. Redis (Task 9) and Kafka (Task 10) replace it by changing one binding in `config/container.php`.
- A failing event bus is logged as a warning and does not fail the request.
- A batch publishes one summary event, not one event per sale.

## 10. SQLite

### Connection settings

`config/container.php` runs these PRAGMAs on every connection:

| PRAGMA | Value | Why |
|---|---|---|
| `journal_mode` | `WAL` | Readers do not block the writer, much better concurrency |
| `synchronous` | `NORMAL` | Safe with WAL and much faster |
| `foreign_keys` | `ON` | Foreign keys are off by default in SQLite |
| `busy_timeout` | `5000` | Wait up to 5 seconds when the database is locked instead of failing at once |

Batches use `BEGIN IMMEDIATE`, which takes the write lock at the start, so a batch fails fast rather than failing half way.

### Files and volume

- The database is `storage/sales.sqlite` inside the container, stored on the named volume `sales_data`. It survives `docker compose down` and container rebuilds, but is deleted by `docker compose down -v`.
- In WAL mode, SQLite also creates `sales.sqlite-wal` and `sales.sqlite-shm` next to the database. They belong together and must stay in the same folder.
- The file does not appear in the project folder on your computer, because it lives in the Docker volume.

### Permissions

Apache runs PHP as `www-data`. The `storage` folder and the database file must be writable by `www-data`, and the **whole folder** must be writable because SQLite writes the `-wal` and `-shm` files there.

```bash
docker compose exec sales-service chown -R www-data:www-data /app/services/sales-service/storage
```

Always run Phinx as `www-data` (`docker compose exec -u www-data ...`). A database file created by root causes `attempt to write a readonly database`.

### Trade-offs

- **One writer at a time.** Run exactly **one replica** of sales-service. Several containers sharing one SQLite file are not supported.
- **Not for very high write volume.** For this workload (appending sales, reading reports) SQLite is a good fit. If sales grow beyond a single instance, move this service to MariaDB or PostgreSQL. Only the container wiring and the SQL dialect change, because data access sits behind the `SaleRepository` and `SalesReport` interfaces.
- **No phpMyAdmin.** phpMyAdmin cannot open SQLite. Use the `sqlite3` command line, DB Browser for SQLite, or an optional Adminer / sqlite-web container.
- **Backups.** Copy the database with SQLite's backup command, not with a plain file copy while the service is writing:

```bash
docker compose exec -u www-data sales-service php -r '
  $p = new PDO("sqlite:" . getenv("SQLITE_PATH"));
  $p->exec("VACUUM INTO \"/app/services/sales-service/storage/backup.sqlite\"");'
docker compose cp sales-service:/app/services/sales-service/storage/backup.sqlite ./backup.sqlite
```

## 11. Configuration

| Variable | Example | Purpose |
|---|---|---|
| `SQLITE_PATH` | `/app/services/sales-service/storage/sales.sqlite` | Location of the database file |
| `GAME_SERVICE_URL` | `http://game-service` | Base URL for game-service calls |
| `REDIS_HOST` | `redis` | Redis host name for the status cache |
| `APP_ENV` | `local` / `production` | Production hides internal error details and enables the DI container cache |

There are no database credentials, because SQLite has no users. Access is controlled by file permissions.

## 12. Migrations, seed data and running

Schema changes are managed with Phinx (SQLite adapter). The history is stored in the `phinxlog` table of the database file.

```bash
# all Phinx commands run as www-data
docker compose exec -u www-data sales-service vendor/bin/phinx migrate -c phinx.php
docker compose exec -u www-data sales-service vendor/bin/phinx status -c phinx.php
docker compose exec -u www-data sales-service vendor/bin/phinx rollback -c phinx.php

# 5,000 sample sales over the last 18 months (development only, the seeder empties the table first)
docker compose exec -u www-data sales-service vendor/bin/phinx seed:run -c phinx.php -s SalesSeeder
```

Rules for migrations:

- Never edit a migration that has been applied or committed. Create a new one.
- The file name format is `YYYYMMDDHHMMSS_snake_case_name.php`, and the class name must be the same name in CamelCase. A mismatch makes Phinx stop with "Could not find class".
- This folder must only contain sales-service migrations. Do not copy migrations from other services.
- The seeder refers to games 1, 2, 3, 4 and 10, which must be `released` in the game-service seed data. The seeder uses a fixed random seed, so it creates the same data every time.

### Running

```bash
docker compose up -d --build sales-service
docker compose exec sales-service composer install
docker compose exec sales-service composer dump-autoload
docker compose logs -f sales-service

curl -i localhost:8004/health
curl -i localhost:8080/api/v1/sales
```

Look inside the database:

```bash
docker compose exec -u www-data sales-service php -r '
  $p = new PDO("sqlite:" . getenv("SQLITE_PATH"));
  echo $p->query("SELECT COUNT(*) FROM sales")->fetchColumn(), PHP_EOL;'
```

### Observability

- Logs are JSON on stdout. Sale events appear as `domain_event` entries, and cache problems as warnings.
- Every request carries an `X-Correlation-Id`, which is also forwarded to game-service.

### Troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| `attempt to write a readonly database` | Folder or file owned by root. Run the `chown` command from section 10 and use `-u www-data` for Phinx |
| `database is locked` | More than one writer. Keep one replica and make sure nothing else has the file open |
| `unable to open database file` | The `sales_data` volume is not mounted, or `SQLITE_PATH` is wrong. Check `docker compose exec sales-service env \| grep SQLITE` |
| Every sale returns 503 | game-service is down or `GAME_SERVICE_URL` is wrong. Test `curl localhost:8003/api/v1/games/1` |
| `Class "Predis\Client" not found` | `predis/predis` is missing. Run `composer install` |
| Phinx creates the file in the wrong place | `phinx.php` must read `SQLITE_PATH` and the name must not include `.sqlite` |

## 13. Design decisions and limitations

### Design decisions

- **Reports are separate from the entity layer.** Aggregation queries are read-only and go straight to SQL through the `SalesReport` interface.
- **One filter builder** serves the list and every report, so filters cannot drift apart.
- **Reports group by currency**, so amounts in different currencies are never mixed.
- **Batch writes are one transaction**, which is both faster and all-or-nothing.
- **The game check is cached but never required to be fresh.** A short cache protects game-service during imports, and a dead Redis never blocks sales.
- **Money in integer cents and dates in UTC**, to avoid floating point errors and time zone mistakes.
- **Append-only ledger.** Sales are never changed or deleted, so reports stay reproducible.

### Known limitations

- **Corrections are not supported.** There is no update, delete or refund. A compensating record (for example a negative sale) is not built yet.
- **Reports contain `game_id` only**, not game titles. Titles would need a call to game-service or a local copy fed by `game.released` events.
- **No currency conversion.** Totals across currencies must be calculated by the client.
- **Single writer.** SQLite means one replica of this service.
- **Events are only logged.** Real delivery arrives with Redis Streams (Task 9) and optionally Kafka (Task 10). There is no transactional outbox, so a crash between the commit and the publish can lose an event.
- **A retired game can still be sold for up to 60 seconds** because of the status cache.
- **No authentication yet.** All endpoints are open. JWT and an admin / reader split are planned in the security task.
- **No `/ready` endpoint**, no export, and no OpenAPI file yet.

## 14. Checklist

- [ ] `sales.sqlite` is created by the migration and survives `docker compose down` and `up`.
- [ ] `SalesSeeder` inserts 5,000 rows.
- [ ] A sale of a released game returns 201 with `total_cents`.
- [ ] Selling a game that is in development, retired or unknown returns 422.
- [ ] Stopping game-service makes sales return 503, not a crash.
- [ ] A batch with one invalid item saves nothing, and a valid batch of 500 items is inserted in well under a second.
- [ ] The three reports return correct numbers, and one matches a raw SQL query.
- [ ] The key `sales:game:{id}:status` appears in Redis, and sales still work when Redis is stopped.
- [ ] `sale.recorded` appears in the log.
- [ ] One correlation id is visible in the logs of sales-service and game-service.