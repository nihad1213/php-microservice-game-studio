# Team Service

This document describes the **team-service** of Game Studio: what it owns, how it is structured, its HTTP API, how it works together with developer-service, and how to run and extend it.

For the Docker, gateway, MariaDB and phpMyAdmin setup, see the Docker configuration document. For the shared error format and conventions used by every service, see the developer-service document. This file covers only what belongs to this service.

## 1. Purpose and responsibility

The team service is the single source of truth for **which teams exist and who belongs to them**. A team has a name, an optional description, an optional lead and a list of members. The developers themselves are owned by developer-service; this service only stores their ids.

| Item | Value |
|---|---|
| Service name | `team-service` |
| Stack | PHP 8.3, Slim 4, PHP-DI, PDO, Phinx, Respect\Validation, Guzzle |
| Database | MariaDB, database `team_db`, user `team_svc` |
| Direct host access | `http://localhost:8002` |
| Through the gateway | `http://localhost:8080/api/v1/teams` |
| Source folder | `services/team-service/` |
| Calls other services | developer-service (validates developers, fetches developer data) |
| Called by | game-service (checks that a team exists) |
| Publishes events | `team.member_added`, `team.member_removed` |

### Ownership rules

- Only this service reads or writes `team_db`. Other services never query its tables.
- `lead_developer_id` and `team_members.developer_id` refer to developers in developer-service. They are plain numbers, not foreign keys, and are validated through the developer-service API.
- Inside this service, `team_members.team_id` is a real foreign key to `teams.id`, because both tables are in the same database.

## 2. Folder structure

```text
services/team-service/
|-- composer.json
|-- phinx.php                      # migration / seed configuration
|-- public/index.php               # front controller (only web-visible folder)
|-- config/
|   |-- container.php              # dependency injection wiring
|   `-- routes.php                 # URL routes
|-- db/
|   |-- migrations/                # teams and team_members tables (Phinx)
|   `-- seeds/TeamSeeder.php       # 3 sample teams for development
`-- src/
    |-- Domain/                    # entities, contracts, business errors
    |-- Application/               # use cases and input validation
    |-- Infrastructure/            # PDO repository, developer-service HTTP client
    `-- Http/                      # controllers
```

## 3. Layers

Each layer only depends on the layers below it. The domain layer knows nothing about HTTP, PDO or Guzzle.

| Layer | Classes | Responsibility |
|---|---|---|
| Http | `TeamController`, `HealthController` | Read the request, call a use case, build the JSON response |
| Application | `TeamService`, `TeamInput`, `MemberInput` | Use cases, input validation, event publishing |
| Domain | `Team`, `TeamMember`, `TeamRepository`, `DeveloperDirectory`, `TeamNotFound`, `TeamNameTaken`, `MemberAlreadyInTeam`, `MemberNotFound`, `LeadCannotBeRemoved` | Entities, contracts, business errors |
| Infrastructure | `PdoTeamRepository`, `DeveloperClient` | SQL access, HTTP calls to developer-service |

`DeveloperDirectory` is an interface in the domain layer. `DeveloperClient` implements it over HTTP, so the application layer does not depend on Guzzle.

## 4. Data model

### Table `teams`

| Column | Type | Rules |
|---|---|---|
| `id` | unsigned integer, auto increment | Primary key |
| `name` | varchar(100) | Required, **unique** |
| `description` | text, nullable | Optional |
| `lead_developer_id` | unsigned integer, nullable | Developer in developer-service, no foreign key |
| `created_at` | timestamp | Set by the database |

### Table `team_members`

| Column | Type | Rules |
|---|---|---|
| `team_id` | unsigned integer | Foreign key to `teams.id`, **cascade on delete** |
| `developer_id` | unsigned integer | Developer in developer-service, no foreign key, indexed |
| `joined_at` | timestamp | Set by the database |

The primary key is the pair (`team_id`, `developer_id`). It is what prevents a developer from being added to the same team twice, even when two requests arrive at the same moment.

## 5. Business rules

| Rule | Result |
|---|---|
| Team names are unique | 409 on a duplicate |
| `lead_developer_id` must exist in developer-service | 422, or 503 when developer-service is unavailable |
| The lead is always a member | Registered in the same database transaction as the team change |
| The lead cannot be removed as a member | 409. Change the lead first |
| A developer can belong to a team only once | 409 |
| A member must exist in developer-service | 422 for an unknown developer |
| Deleting a team | Its memberships are deleted with it (foreign key cascade) |
| A developer can be in several teams | Allowed |

When the lead of a team is changed through `PUT`, the new lead is added as a member automatically. The previous lead stays a member and can be removed afterwards.

## 6. HTTP API

All routes are under `/api/v1/teams`. Request and response bodies are JSON. Send `Content-Type: application/json` on POST and PUT requests.

| Method and path | Description | Success | Errors |
|---|---|---|---|
| `GET /api/v1/teams` | Paginated list of teams | 200 | - |
| `POST /api/v1/teams` | Create a team | 201 + `Location` header | 409, 422, 503 |
| `GET /api/v1/teams/{id}` | Get one team | 200 | 404 |
| `PUT /api/v1/teams/{id}` | Replace a team (name required) | 200 | 404, 409, 422, 503 |
| `DELETE /api/v1/teams/{id}` | Delete a team and its memberships | 204 | 404 |
| `GET /api/v1/teams/{id}/members` | List members, optionally with developer data | 200 | 404, 422, 503 |
| `POST /api/v1/teams/{id}/members` | Add a developer to the team | 201 | 404, 409, 422, 503 |
| `DELETE /api/v1/teams/{id}/members/{developerId}` | Remove a member | 204 | 404, 409 |
| `GET /health` | Liveness check (not routed by the gateway) | 200 | - |

`GET /api/v1/teams/{id}` is also the endpoint game-service calls to check that a team exists.

### List query parameters

| Parameter | Default | Rules |
|---|---|---|
| `page` | `1` | Minimum 1 |
| `per_page` | `20` | Between 1 and 100 (values outside the range are corrected) |

### Team representation

```json
{
  "id": 4,
  "name": "Orbit Labs",
  "description": "Space games",
  "lead_developer_id": 2,
  "created_at": "2026-11-25T14:00:00+00:00"
}
```

Paginated lists use the same `data` / `meta` envelope as the other services.

### Create / update request body

```json
{
  "name": "Orbit Labs",
  "description": "Space games",
  "lead_developer_id": 2
}
```

`description` and `lead_developer_id` are optional and can be `null`. An empty description is stored as `null`.

### Validation rules

| Field | Rule |
|---|---|
| `name` | String, 1 to 100 characters after trimming |
| `description` | `null` or a string up to 1000 characters |
| `lead_developer_id` | `null` or a JSON integer, 1 or higher, and the developer must exist |
| `developer_id` (member) | JSON integer, 1 or higher, and the developer must exist |

### Members

`GET /api/v1/teams/{id}/members` returns the member ids with their join time:

```json
{
  "data": [
    { "developer_id": 2, "joined_at": "2026-11-25T14:00:00+00:00" },
    { "developer_id": 3, "joined_at": "2026-11-25T14:05:00+00:00" }
  ]
}
```

With `?expand=developers`, each entry also contains the developer's data fetched from developer-service:

```json
{
  "data": [
    {
      "developer_id": 2,
      "joined_at": "2026-11-25T14:00:00+00:00",
      "developer": { "id": 2, "first_name": "Ada", "last_name": "Lovelace", "role": "programmer", "...": "..." }
    }
  ]
}
```

- `developer` is `null` for a member whose developer was deleted in developer-service in the meantime.
- Any other value for `expand` returns 422.
- All developers are requested in parallel, so a team of 10 takes about as long as one request.

Adding a member returns 201 with the new membership and a `Location` header pointing to the members list:

```json
{ "developer_id": 3, "joined_at": "2026-11-25T14:05:00+00:00" }
```

### Example requests

```bash
# list, through the gateway
curl "localhost:8080/api/v1/teams"

# create a team with a lead (the lead becomes a member automatically)
curl -i -X POST localhost:8002/api/v1/teams -H 'Content-Type: application/json' \
  -d '{"name":"Orbit Labs","description":"Space games","lead_developer_id":2}'

# members
curl -i -X POST localhost:8002/api/v1/teams/1/members -H 'Content-Type: application/json' -d '{"developer_id":3}'
curl "localhost:8002/api/v1/teams/1/members?expand=developers"
curl -i -X DELETE localhost:8002/api/v1/teams/1/members/3

# update and delete
curl -i -X PUT localhost:8002/api/v1/teams/4 -H 'Content-Type: application/json' \
  -d '{"name":"Orbit Labs","description":"Space and sci-fi","lead_developer_id":2}'
curl -i -X DELETE localhost:8002/api/v1/teams/4
```

## 7. Error format

Errors use the same `application/problem+json` format as every other service, including the `X-Correlation-Id` header.

| Situation | Status | Raised by |
|---|---|---|
| Invalid or missing field, unknown `expand` value, unknown developer | 422 | `ValidationFailed` |
| Team name already used | 409 | `TeamNameTaken` |
| Developer already in the team | 409 | `MemberAlreadyInTeam` |
| Removing the team lead | 409 | `LeadCannotBeRemoved` |
| Team does not exist | 404 | `TeamNotFound` |
| Developer is not a member of the team | 404 | `MemberNotFound` |
| developer-service is unreachable or fails | 503 | `UpstreamUnavailable` |
| Anything unexpected | 500 | Error handler (details hidden when `APP_ENV=production`) |

Duplicates are detected by the database: the unique index on `teams.name` and the composite primary key on `team_members`. The repository turns the MariaDB duplicate-key error (code 1062) into the matching business error, so the result stays correct under concurrent requests.

## 8. Communication with developer-service

`DeveloperClient` implements the `DeveloperDirectory` interface and calls `GET {DEVELOPER_SERVICE_URL}/api/v1/developers/{id}`.

| Use | Method | When |
|---|---|---|
| Validate one developer | `exists()` | Creating a team with a lead, changing the lead, adding a member |
| Fetch many developers | `findMany()` | `GET .../members?expand=developers` (requests run in parallel) |

| Response from developer-service | Result in team-service |
|---|---|
| 2xx | Developer exists |
| 404 | `exists()` returns false, so 422. `findMany()` returns `null` for that developer |
| Timeout, connection error, 5xx, other 4xx | 503 Service Unavailable |

Details:

- Timeouts are short: 1 second to connect, 2 seconds in total.
- The `X-Correlation-Id` header is forwarded, so one request can be followed through both services.
- The lead is only validated when `lead_developer_id` actually changes.

## 9. Domain events

Adding or removing a member publishes an event after the database change has been saved.

| Type | Published when | Payload |
|---|---|---|
| `team.member_added` | A developer is added through the members endpoint | `team_id`, `developer_id` |
| `team.member_removed` | A member is removed through the members endpoint | `team_id`, `developer_id` |

The envelope (`id`, `type`, `version`, `source`, `correlation_id`, `occurred_at`, `payload`) is described in the game-service document.

- The event bus is bound to `LogEventBus`, so events are written to the JSON log as `domain_event` entries. Redis (Task 9) and Kafka (Task 10) replace it by changing one binding in `config/container.php`.
- A failing event bus is logged as a warning and does not fail the request.
- The automatic registration of a lead when a team is created or updated does **not** publish `team.member_added`.

## 10. Configuration

| Variable | Example | Purpose |
|---|---|---|
| `DB_HOST` | `mariadb` | MariaDB host name on the Docker network |
| `DB_NAME` | `team_db` | Database name |
| `DB_USER` | `team_svc` | Database user (access to this database only) |
| `DB_PASS` | from `TEAM_DB_PASS` in `.env` | Database password |
| `DEVELOPER_SERVICE_URL` | `http://developer-service` | Base URL for developer-service calls |
| `APP_ENV` | `local` / `production` | Production hides internal error details and enables the DI container cache |

The password in `.env` must match the one in `docker/mariadb/init/01-databases.sql`. That script only runs when the MariaDB volume is empty.

## 11. Migrations and running

### Migrations and seed data

Schema changes are managed with Phinx. There are two migrations: `CreateTeams` and `CreateTeamMembers` (which depends on `teams`).

```bash
docker compose exec team-service vendor/bin/phinx migrate -c phinx.php
docker compose exec team-service vendor/bin/phinx status -c phinx.php
docker compose exec team-service vendor/bin/phinx rollback -c phinx.php

# 3 sample teams with ids 1 to 3 (development only, the seeder empties both tables first)
docker compose exec team-service vendor/bin/phinx seed:run -c phinx.php -s TeamSeeder
```

Rules for migrations:

- Never edit a migration that has been applied or committed. Create a new one.
- The file name format is `YYYYMMDDHHMMSS_snake_case_name.php`, and the class name must be the same name in CamelCase. A mismatch makes Phinx stop with "Could not find class".
- This folder must only contain team-service migrations. Do not copy migrations from other services.
- `teams.id` is unsigned so that it matches `team_members.team_id`. Different column types cause a "Foreign key constraint is incorrectly formed" error.
- The seeded teams have ids 1 to 3, matching the team ids used by the game-service seed data. They have no lead and no members, so add those through the API.

### Running

```bash
docker compose up -d --build team-service
docker compose exec team-service composer install
docker compose exec team-service composer dump-autoload
docker compose logs -f team-service

curl -i localhost:8002/health
curl -i localhost:8080/api/v1/teams
```

### Observability

- Logs are JSON on stdout. Member events appear as `domain_event` entries.
- Every request carries an `X-Correlation-Id`, which is forwarded to developer-service. Search the logs of both services for the same id to follow a request.

## 12. Design decisions and limitations

### Design decisions

- **Only ids are stored for developers.** Names and roles stay in developer-service, so there is no copy to keep in sync. The `expand` option fetches them when needed.
- **Hexagonal boundary for developer-service.** The application layer depends on `DeveloperDirectory`, so the HTTP details stay in the infrastructure layer.
- **The lead invariant is enforced in one transaction**, so a team never exists with a lead who is not a member.
- **Duplicates are decided by the database**, not by a check-then-insert in PHP, which would be vulnerable to race conditions.
- **Parallel lookups for `expand`**, so response time does not grow with team size.
- **Event publishing never breaks a committed change.** Failures are logged, not thrown.

### Known limitations

- **Deleting a developer does not remove their memberships.** Team members can point to a developer that no longer exists (`expand` then shows `developer: null`). The planned fix is a `developer.deleted` event consumed by this service.
- **Deleting a team does not notify game-service.** Games can keep a `team_id` that no longer exists. A `team.deleted` event is the planned fix.
- **One request per developer for `expand`.** For very large teams, add a batch endpoint to developer-service (for example `GET /developers?ids=1,2,3`).
- **No transactional outbox.** A crash between the database commit and the event publish can lose an event.
- **No authentication yet.** All endpoints are open. JWT and an admin / reader split are planned in the security task.
- **No `/ready` endpoint**, `PATCH`, or search. Members are not paginated.
- **No OpenAPI file yet.** The API contract in this document is the reference until one is added to `docs/openapi/`.

## 13. Checklist

- [ ] `team_db` exists and the `team_svc` password matches `.env`.
- [ ] `phinx migrate` creates `teams` and `team_members`, and `TeamSeeder` inserts 3 teams.
- [ ] All eight endpoints work on `localhost:8002` and the team endpoints through `localhost:8080`.
- [ ] A duplicate team name returns 409 and an unknown lead returns 422.
- [ ] A new team's lead appears in its member list, and removing the lead returns 409.
- [ ] Adding the same developer twice returns 409, and an unknown developer returns 422.
- [ ] `?expand=developers` shows developer data.
- [ ] Stopping developer-service makes member and lead changes return 503, not a crash.
- [ ] Game-service creates a game only for an existing team (with `TEAM_CHECK` removed).
- [ ] One correlation id is visible in the logs of both services.