# Developer Service

This document describes the **developer-service** of Game Studio: what it owns, how it is structured, its HTTP API, its database, and how to run and extend it.

For the Docker, gateway, MariaDB and phpMyAdmin setup, see the Docker configuration document. This file covers only what belongs to this service.

## 1. Purpose and responsibility

The developer service is the single source of truth for **people who work at the studio**: programmers, designers, artists, producers and QA engineers.

| Item | Value |
|---|---|
| Service name | `developer-service` |
| Stack | PHP 8.3, Slim 4, PHP-DI, PDO, Phinx, Respect\Validation |
| Database | MariaDB, database `developer_db`, user `developer_svc` |
| Direct host access | `http://localhost:8001` |
| Through the gateway | `http://localhost:8080/api/v1/developers` |
| Source folder | `services/developer-service/` |

### Ownership rules

- Only this service reads or writes `developer_db`. Other services must never query its tables.
- Other services that need developer data (for example team-service) call the HTTP API described below, or react to events once events are introduced.
- Ids that refer to a developer in another service's database are plain numbers, not foreign keys.

## 2. Folder structure

```text
services/developer-service/
|-- composer.json
|-- phinx.php                      # migration / seed configuration
|-- public/index.php               # front controller (only web-visible folder)
|-- config/
|   |-- container.php              # dependency injection wiring
|   `-- routes.php                 # URL routes
|-- db/
|   |-- migrations/                # schema changes (Phinx)
|   `-- seeds/DeveloperSeeder.php  # 30 fake developers for development
`-- src/
    |-- Domain/                    # business concepts, no framework code
    |-- Application/               # use cases and input validation
    |-- Infrastructure/            # PDO repository
    `-- Http/                      # controllers
```

Shared technical code (JSON responses, error handler, correlation id middleware, logger factory) lives outside the service in `shared/src` and is installed as the Composer package `studio/core`.

## 3. Layers

Each layer only depends on the layers below it. The domain layer knows nothing about HTTP or PDO.

| Layer | Classes | Responsibility |
|---|---|---|
| Http | `DeveloperController`, `HealthController` | Read the request, call a use case, build the JSON response |
| Application | `DeveloperService`, `DeveloperInput` | Use cases (list, get, create, update, delete) and request validation |
| Domain | `Developer`, `Role`, `DeveloperRepository`, `DeveloperNotFound`, `EmailAlreadyUsed` | Entity, allowed roles, repository contract, business errors |
| Infrastructure | `PdoDeveloperRepository` | SQL access to `developer_db` through PDO |

The repository is an interface in the domain layer. `config/container.php` binds it to the PDO implementation, so a different storage engine could be used without touching the use cases.

## 4. Data model

### Table `developers`

| Column | Type | Rules |
|---|---|---|
| `id` | integer, auto increment | Primary key, created by Phinx |
| `first_name` | varchar(100) | Required |
| `last_name` | varchar(100) | Required |
| `email` | varchar(190) | Required, **unique** index |
| `role` | varchar(30) | Required, indexed, one of the allowed roles |
| `hired_at` | date | Required |
| `created_at` | timestamp | Set by the database (`CURRENT_TIMESTAMP`) |

### Allowed roles

`programmer`, `designer`, `artist`, `producer`, `qa`

Roles are defined by the `App\Domain\Role` enum and stored as plain strings. Adding a role means adding an enum case; no migration is needed.

## 5. HTTP API

All routes are under `/api/v1/developers`. Request and response bodies are JSON. Send `Content-Type: application/json` on POST and PUT requests.

| Method and path | Description | Success | Errors |
|---|---|---|---|
| `GET /api/v1/developers` | Paginated list | 200 | 422 (invalid filter) |
| `POST /api/v1/developers` | Create a developer | 201 + `Location` header | 409, 422 |
| `GET /api/v1/developers/{id}` | Get one developer | 200 | 404 |
| `PUT /api/v1/developers/{id}` | Replace a developer (all fields required) | 200 | 404, 409, 422 |
| `DELETE /api/v1/developers/{id}` | Delete a developer | 204 | 404 |
| `GET /health` | Liveness check (not routed by the gateway) | 200 | - |

### List query parameters

| Parameter | Default | Rules |
|---|---|---|
| `page` | `1` | Minimum 1 |
| `per_page` | `20` | Between 1 and 100 (values outside the range are corrected) |
| `role` | none | Must be an allowed role, otherwise 422 |

### Developer representation

```json
{
  "id": 7,
  "first_name": "Ada",
  "last_name": "Lovelace",
  "email": "ada@studio.test",
  "role": "programmer",
  "hired_at": "2026-01-15",
  "created_at": "2026-11-21T10:15:00+00:00"
}
```

### Paginated list response

```json
{
  "data": [ { "id": 1, "first_name": "Ada", "...": "..." } ],
  "meta": { "current_page": 1, "per_page": 20, "total": 30, "last_page": 2 }
}
```

### Create / update request body

```json
{
  "first_name": "Ada",
  "last_name": "Lovelace",
  "email": "ada@studio.test",
  "role": "programmer",
  "hired_at": "2026-01-15"
}
```

### Validation rules

| Field | Rule |
|---|---|
| `first_name`, `last_name` | String, 1 to 100 characters after trimming |
| `email` | String, valid e-mail address, up to 190 characters. Stored in lowercase |
| `role` | One of the allowed roles |
| `hired_at` | String in the format `YYYY-MM-DD` |

`PUT` is a full replacement: every field is required, even if only one value changes.

### Example requests

```bash
# list, filtered, through the gateway
curl "localhost:8080/api/v1/developers?role=designer&per_page=5"

# create
curl -i -X POST localhost:8001/api/v1/developers \
  -H 'Content-Type: application/json' \
  -d '{"first_name":"Ada","last_name":"Lovelace","email":"ada@studio.test","role":"programmer","hired_at":"2026-01-15"}'

# get, update, delete
curl -i localhost:8001/api/v1/developers/1
curl -i -X PUT localhost:8001/api/v1/developers/1 -H 'Content-Type: application/json' \
  -d '{"first_name":"Ada","last_name":"Byron","email":"ada@studio.test","role":"producer","hired_at":"2026-01-15"}'
curl -i -X DELETE localhost:8001/api/v1/developers/1
```

## 6. Error format

Every error is returned as `application/problem+json` (RFC 7807). Responses also carry the `X-Correlation-Id` header.

```json
{
  "type": "about:blank",
  "title": "Unprocessable Entity",
  "status": 422,
  "detail": "Validation failed.",
  "instance": "/api/v1/developers",
  "errors": {
    "email": "missing or invalid",
    "role": "missing or invalid"
  }
}
```

| Situation | Status | Raised by |
|---|---|---|
| Invalid or missing field, unknown role filter | 422 | `ValidationFailed` |
| E-mail already used by another developer | 409 | `EmailAlreadyUsed` |
| Developer id does not exist | 404 | `DeveloperNotFound` |
| Unknown URL | 404 | Slim routing |
| Anything unexpected | 500 | Error handler (details hidden when `APP_ENV=production`) |

Messages of 4xx business errors are always shown. For 500 errors the message is only shown when the application is not in production mode.

### Duplicate e-mail protection

The unique index on `developers.email` is the real guard. The repository catches the database error (SQLSTATE `23000`) and turns it into `EmailAlreadyUsed`. This stays correct even when two requests with the same e-mail arrive at the same moment.

## 7. Configuration

The service reads its configuration from environment variables supplied by Docker Compose.

| Variable | Example | Purpose |
|---|---|---|
| `DB_HOST` | `mariadb` | MariaDB host name on the Docker network |
| `DB_NAME` | `developer_db` | Database name |
| `DB_USER` | `developer_svc` | Database user (access to this database only) |
| `DB_PASS` | from `DEVELOPER_DB_PASS` in `.env` | Database password |
| `APP_ENV` | `local` / `production` | Production hides internal error details and enables the DI container cache |

The password in `.env` must match the one in `docker/mariadb/init/01-databases.sql`. That script only runs when the MariaDB volume is empty.

## 8. Database migrations and seed data

Schema changes are managed with Phinx. Migrations live in `db/migrations`, and the history is stored in the `phinxlog` table of `developer_db`.

```bash
# apply all pending migrations
docker compose exec developer-service vendor/bin/phinx migrate -c phinx.php

# show migration status
docker compose exec developer-service vendor/bin/phinx status -c phinx.php

# undo the last migration
docker compose exec developer-service vendor/bin/phinx rollback -c phinx.php

# insert 30 fake developers (development only)
docker compose exec developer-service vendor/bin/phinx seed:run -c phinx.php -s DeveloperSeeder
```

Rules for migrations:

- Never edit a migration that has already been applied or committed. Create a new one.
- Keep the file name format `YYYYMMDDHHMMSS_snake_case_name.php`, with the class name in CamelCase.
- Seeders are for development data only and must not run in production.

## 9. Running the service

```bash
# start (from the project root)
docker compose up -d --build developer-service

# install PHP dependencies the first time or after changing composer.json
docker compose exec developer-service composer install

# regenerate the autoloader after adding classes or changing autoload settings
docker compose exec developer-service composer dump-autoload

# logs (one JSON object per line)
docker compose logs -f developer-service

# quick checks
curl -i localhost:8001/health
curl -i localhost:8080/api/v1/developers
```

The `/health` route is available on port 8001. The gateway only forwards `/api/v1/developers`, so use the direct port for health checks.

### Observability

- Logs are written as JSON to stdout, with the service name as the log channel.
- Every request gets an `X-Correlation-Id`. A valid UUID sent by the caller is kept, otherwise a new one is created. It is returned in the response headers, including error responses.
- Follow one request across services by searching the logs of all services for the same correlation id.

## 10. Design decisions

- **Thin controllers.** Controllers parse the request, call one use case and format the response. Business rules stay in the application and domain layers.
- **Prepared statements only.** All SQL uses bound parameters.
- **Validation at the edge.** `DeveloperInput` validates and normalises raw request data once, so the rest of the code works with typed values (`Role`, `DateTimeImmutable`).
- **Errors as exceptions.** Business errors extend `ProblemException` (from the shared package) and carry their own HTTP status, so controllers contain no error handling code.
- **E-mail is normalised** to lowercase and trimmed before it is stored.
- **Full-replacement `PUT`.** Partial updates (`PATCH`) are intentionally not offered yet.

## 11. Known limitations and planned work

- **No authentication yet.** All endpoints are open. JWT authentication and an admin / reader role split are planned in the security task.
- **No `/ready` endpoint.** `/health` only shows that the process is alive; it does not check the database.
- **Deleting a developer has no side effects elsewhere.** Team memberships that refer to the developer are not removed. The planned solution is for this service to publish a `developer.deleted` event that team-service consumes.
- **No events published yet.** The event bus is introduced in the Redis task.
- **No `PATCH`, sorting or text search** on the list endpoint.
- **No OpenAPI file yet.** The API contract in this document is the reference until one is added to `docs/openapi/`.

## 12. Checklist

- [ ] `developer_db` exists and the `developer_svc` password matches `.env`.
- [ ] `composer install` has been run and the `App\` namespace autoloads from `src/`.
- [ ] `phinx migrate` creates the `developers` table.
- [ ] All five endpoints work on `localhost:8001` and through `localhost:8080`.
- [ ] A duplicate e-mail returns 409 and invalid input returns 422 with field errors.
- [ ] An unknown id returns 404 as `application/problem+json`.
- [ ] Error responses contain the `X-Correlation-Id` header.