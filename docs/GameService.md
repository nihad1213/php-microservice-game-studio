# Game Service

This document describes the **game-service** of Game Studio: what it owns, how it is structured, its HTTP API, the game life cycle, the events it publishes, and how to run and extend it.

For the Docker, gateway, MariaDB and phpMyAdmin setup, see the Docker configuration document. For the shared error format and conventions used by every service, see the developer-service document. This file covers only what belongs to this service.

## 1. Purpose and responsibility

The game service is the single source of truth for the **video games the studio builds and releases**. It manages each game's details and its life cycle from development to release to retirement, and it announces life-cycle changes as domain events.

| Item | Value |
|---|---|
| Service name | `game-service` |
| Stack | PHP 8.3, Slim 4, PHP-DI, PDO, Phinx, Respect\Validation, Guzzle |
| Database | MariaDB, database `game_db`, user `game_svc` |
| Direct host access | `http://localhost:8003` |
| Through the gateway | `http://localhost:8080/api/v1/games` |
| Source folder | `services/game-service/` |
| Calls other services | team-service (validates `team_id`) |
| Publishes events | `game.released`, `game.retired` |

### Ownership rules

- Only this service reads or writes `game_db`. Other services never query its tables.
- `team_id` refers to a team in team-service. It is a plain number, not a foreign key, and is validated through the team-service API.
- Other services that need game data (for example sales-service) call the HTTP API below or react to the events in section 7.

## 2. Folder structure

```text
services/game-service/
|-- composer.json
|-- phinx.php                      # migration / seed configuration
|-- public/index.php               # front controller (only web-visible folder)
|-- config/
|   |-- container.php              # dependency injection wiring
|   `-- routes.php                 # URL routes
|-- db/
|   |-- migrations/                # schema changes (Phinx)
|   `-- seeds/GameSeeder.php       # 10 sample games for development
`-- src/
    |-- Domain/                    # entity, life-cycle rules, enums, contracts
    |-- Application/               # use cases and input validation
    |-- Infrastructure/            # PDO repository, team-service HTTP client
    `-- Http/                      # controllers
```

Shared technical code (JSON responses, error handler, correlation id middleware, logger, event bus interface, clock) lives in `shared/src` and is installed as the Composer package `studio/core`.

## 3. Layers

Each layer only depends on the layers below it. The domain layer knows nothing about HTTP, PDO or Guzzle.

| Layer | Classes | Responsibility |
|---|---|---|
| Http | `GameController`, `HealthController` | Read the request, call a use case, build the JSON response |
| Application | `GameService`, `GameInput` | Use cases (list, get, create, update, delete, release, retire), input validation, event publishing |
| Domain | `Game`, `GameStatus`, `Platform`, `GameFilter`, `GameRepository`, `TeamDirectory`, `GameNotFound`, `InvalidTransition`, `GameAlreadyExists` | Entity with life-cycle rules, enums, contracts, business errors |
| Infrastructure | `PdoGameRepository`, `TeamClient`, `AllowAllTeamDirectory` | SQL access, HTTP calls to team-service, temporary stand-in |

The life-cycle rules live in the `Game` entity itself (`release()`, `retire()`, `withDetails()`, `canBeDeleted()`), so they cannot be bypassed by controllers or SQL.

## 4. Data model

### Table `games`

| Column | Type | Rules |
|---|---|---|
| `id` | integer, auto increment | Primary key |
| `title` | varchar(200) | Required |
| `genre` | varchar(50) | Required, stored in lowercase |
| `platform` | varchar(30) | Required, one of the allowed platforms |
| `price_cents` | unsigned integer | Price in cents, never a decimal number |
| `status` | varchar(20) | `in_development` by default, set by the service |
| `team_id` | unsigned integer | Team in team-service, no foreign key |
| `release_date` | date, nullable | Set when the game is released |
| `created_at` | timestamp | Set by the database |

Indexes: **unique** on (`title`, `platform`), plus single-column indexes on `status`, `team_id` and `genre`.

The same title can exist on several platforms (for example Star Forge for PC and for PlayStation), but not twice on the same platform.

### Allowed values

| Field | Values |
|---|---|
| `platform` | `pc`, `playstation`, `xbox`, `switch`, `mobile` |
| `status` | `in_development`, `released`, `retired` |

Both are PHP enums (`Platform`, `GameStatus`) stored as plain strings. Adding a value means adding an enum case; no migration is needed.

## 5. Game life cycle

```text
   create                 POST /release            POST /retire
  ---------> in_development ------------> released ------------> retired
                    |                                               
                    `-- may be edited and deleted        retired games are read-only
```

| Action | Allowed from | Result | Otherwise |
|---|---|---|---|
| Create | - | `in_development` | - |
| Edit (PUT) | `in_development`, `released` | details changed, status unchanged | 409 when retired |
| Release | `in_development` | `released`, `release_date` = today (UTC) | 409 |
| Retire | `released` | `retired` | 409 |
| Delete | `in_development` | game removed | 409 (released and retired games are kept as history) |

The status can never be set directly through PUT. It only changes through the release and retire actions.

## 6. HTTP API

All routes are under `/api/v1/games`. Request and response bodies are JSON. Send `Content-Type: application/json` on POST and PUT requests.

| Method and path | Description | Success | Errors |
|---|---|---|---|
| `GET /api/v1/games` | Paginated, filterable list | 200 | 422 (invalid filter) |
| `POST /api/v1/games` | Create a game | 201 + `Location` header | 409, 422, 503 |
| `GET /api/v1/games/{id}` | Get one game | 200 | 404 |
| `PUT /api/v1/games/{id}` | Replace the game's details (all fields required) | 200 | 404, 409, 422, 503 |
| `DELETE /api/v1/games/{id}` | Delete a game in development | 204 | 404, 409 |
| `POST /api/v1/games/{id}/release` | Release the game, publishes `game.released` | 200 | 404, 409 |
| `POST /api/v1/games/{id}/retire` | Retire the game, publishes `game.retired` | 200 | 404, 409 |
| `GET /health` | Liveness check (not routed by the gateway) | 200 | - |

### List query parameters

| Parameter | Default | Rules |
|---|---|---|
| `page` | `1` | Minimum 1 |
| `per_page` | `20` | Between 1 and 100 (values outside the range are corrected) |
| `status` | none | Must be an allowed status, otherwise 422 |
| `platform` | none | Must be an allowed platform, otherwise 422 |
| `team_id` | none | Positive integer, otherwise 422 |
| `genre` | none | Matched in lowercase |

Filters can be combined, for example `?status=released&platform=pc&team_id=1`.

### Game representation

```json
{
  "id": 1,
  "title": "Star Forge",
  "genre": "rpg",
  "platform": "pc",
  "price_cents": 3999,
  "status": "released",
  "team_id": 1,
  "release_date": "2025-03-14",
  "created_at": "2026-11-23T09:30:00+00:00"
}
```

Paginated lists use the same `data` / `meta` envelope as the developer service.

### Create / update request body

```json
{
  "title": "Moon Harbor",
  "genre": "Adventure",
  "platform": "pc",
  "price_cents": 2999,
  "team_id": 1
}
```

### Validation rules

| Field | Rule |
|---|---|
| `title` | String, 1 to 200 characters after trimming |
| `genre` | String, 1 to 50 characters. Stored in lowercase |
| `platform` | One of the allowed platforms |
| `price_cents` | JSON **integer** between 0 and 1,000,000 (`"free"` or `19.99` are rejected) |
| `team_id` | JSON integer, 1 or higher, and the team must exist in team-service |

`PUT` is a full replacement of these five fields. `status`, `release_date` and `created_at` are not accepted from the client.

### Example requests

```bash
# list with filters, through the gateway
curl "localhost:8080/api/v1/games?status=released&platform=pc"

# create
curl -i -X POST localhost:8003/api/v1/games -H 'Content-Type: application/json' \
  -d '{"title":"Moon Harbor","genre":"adventure","platform":"pc","price_cents":2999,"team_id":1}'

# life cycle
curl -i -X POST localhost:8003/api/v1/games/6/release
curl -i -X POST localhost:8003/api/v1/games/6/retire

# update and delete
curl -i -X PUT localhost:8003/api/v1/games/6 -H 'Content-Type: application/json' \
  -d '{"title":"Skyward","genre":"adventure","platform":"xbox","price_cents":4999,"team_id":1}'
curl -i -X DELETE localhost:8003/api/v1/games/7
```

## 7. Domain events

Releasing or retiring a game publishes an event after the database change has been saved. Events describe facts in the past tense.

### Event envelope

```json
{
  "id": "0b8f2c1e-6d0e-4c53-9a2f-3b2e0a7d1c11",
  "type": "game.released",
  "version": 1,
  "source": "game-service",
  "correlation_id": "f6b2c3a4-...",
  "occurred_at": "2026-11-23T10:15:00+00:00",
  "payload": { }
}
```

### Events published

| Type | Published when | Payload |
|---|---|---|
| `game.released` | A game is released | `game_id`, `title`, `team_id`, `price_cents`, `released_at` (Y-m-d) |
| `game.retired` | A game is retired | `game_id`, `title`, `team_id` |

### Delivery

- The event bus is bound to `LogEventBus`, which writes each event to the JSON log. Search for `domain_event` in `docker compose logs game-service`.
- The binding in `config/container.php` is the only place to change when the Redis (Task 9) or Kafka (Task 10) implementation is added. Business code does not change.
- A failing event bus is logged as a warning and does **not** fail the release or retire request, because the database change is already committed.
- Events are published after the commit but not atomically with it. If the process crashes in between, an event can be lost. The transactional outbox pattern is the planned fix (see section 12).

## 8. Communication with team-service

When a game is created, or its `team_id` changes, the service checks that the team exists by calling `GET {TEAM_SERVICE_URL}/api/v1/teams/{id}` through `TeamClient`, an implementation of the domain interface `TeamDirectory`.

| Response from team-service | Result in game-service |
|---|---|
| 2xx | Team exists, request continues |
| 404 | 422 with `errors.team_id = "team does not exist"` |
| Timeout, connection error, 5xx, other 4xx | 503 Service Unavailable |

Details:

- Timeouts are short: 1 second to connect, 2 seconds in total.
- The `X-Correlation-Id` header is forwarded, so one request can be followed through both services.
- Updating a game without changing `team_id` does not call team-service.

### Temporary switch: `TEAM_CHECK`

While team-service had no endpoints, the check was disabled with `TEAM_CHECK: "false"`, which binds `AllowAllTeamDirectory`. Remove that line from the `game-service` block in `docker-compose.yml` and recreate the container once team-service is working:

```bash
docker compose up -d game-service
```

A plain `docker compose restart` does not apply environment changes.

## 9. Configuration

| Variable | Example | Purpose |
|---|---|---|
| `DB_HOST` | `mariadb` | MariaDB host name on the Docker network |
| `DB_NAME` | `game_db` | Database name |
| `DB_USER` | `game_svc` | Database user (access to this database only) |
| `DB_PASS` | from `GAME_DB_PASS` in `.env` | Database password |
| `TEAM_SERVICE_URL` | `http://team-service` | Base URL for team-service calls |
| `TEAM_CHECK` | `false` | Temporary: disables the team check. Remove in normal operation |
| `APP_ENV` | `local` / `production` | Production hides internal error details and enables the DI container cache |

The password in `.env` must match the one in `docker/mariadb/init/01-databases.sql`. That script only runs when the MariaDB volume is empty.

## 10. Database migrations and seed data

Schema changes are managed with Phinx. Migrations live in `db/migrations`; the history is stored in the `phinxlog` table of `game_db`.

```bash
docker compose exec game-service vendor/bin/phinx migrate -c phinx.php
docker compose exec game-service vendor/bin/phinx status -c phinx.php
docker compose exec game-service vendor/bin/phinx rollback -c phinx.php

# 10 sample games (development only; the seeder empties the games table first)
docker compose exec game-service vendor/bin/phinx seed:run -c phinx.php -s GameSeeder
```

Rules for migrations:

- Never edit a migration that has been applied or committed. Create a new one.
- The file name format is `YYYYMMDDHHMMSS_snake_case_name.php`, and the class name must be the same name in CamelCase (`create_games` and `CreateGames`). A mismatch makes Phinx stop with "Could not find class".
- This folder must only contain game-service migrations. Do not copy migrations from other services.
- The seeder refers to team ids 1 to 3, which match the team-service seed data.

## 11. Running

```bash
docker compose up -d --build game-service
docker compose exec game-service composer install
docker compose exec game-service composer dump-autoload
docker compose logs -f game-service

curl -i localhost:8003/health
curl -i localhost:8080/api/v1/games
```

### Observability

- Logs are JSON on stdout. Release and retire events appear as `domain_event` entries.
- Every request carries an `X-Correlation-Id`, which is also forwarded to team-service.

## 12. Design decisions and limitations

### Design decisions

- **Rules inside the entity.** The life cycle lives in `Game`, not in controllers or SQL, so it cannot be bypassed.
- **Status is not client input.** It only changes through explicit business actions (`release`, `retire`).
- **Hexagonal boundary for team-service.** The application layer depends on the `TeamDirectory` interface, so the temporary stand-in needs no network and the HTTP client can be swapped.
- **Event publishing never breaks a committed change.** Failures are logged, not thrown.
- **Prices are integer cents** to avoid floating point errors, and release dates use UTC.
- **Released and retired games cannot be deleted**, so sales history always refers to a game that still exists.

### Known limitations

- **No authentication yet.** All endpoints are open. JWT and an admin / reader split are planned in the security task.
- **Events are only logged.** Real delivery arrives with Redis Streams (Task 9) and optionally Kafka (Task 10).
- **No transactional outbox.** A crash between the database commit and the event publish can lose an event.
- **Deleting a team does not notify this service.** Games can keep a `team_id` that no longer exists. A `team.deleted` event is the planned fix.
- **No `/ready` endpoint**, `PATCH`, sorting, or text search.
- **No OpenAPI file yet.** The API contract in this document is the reference until one is added to `docs/openapi/`.

## 13. Checklist

- [ ] `game_db` exists and the `game_svc` password matches `.env`.
- [ ] `phinx migrate` creates `games`, and `GameSeeder` inserts 10 rows.
- [ ] All seven endpoints work on `localhost:8003` and through `localhost:8080`.
- [ ] Releasing a game twice returns 409, and a retired game cannot be edited.
- [ ] Deleting a released game returns 409, deleting a game in development returns 204.
- [ ] A duplicate title on the same platform returns 409.
- [ ] `game.released` appears in the log with `id`, `type`, `occurred_at` and `payload`.
- [ ] An unknown team returns 422 and a stopped team-service returns 503 (with `TEAM_CHECK` removed).