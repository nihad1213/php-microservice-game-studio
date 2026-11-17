# Docker Configuration

This document describes the local Docker environment for **Game Studio**, including the PHP services, API gateway, MariaDB, phpMyAdmin, Redis, and the development/production image builds.

## 1. Architecture overview

The project uses Docker Compose to run several independently deployable PHP services.

| Component | Container image / build | Host access | Purpose |
|---|---|---:|---|
| `gateway` | `nginx:1.27-alpine` | `http://localhost:8080` | Reverse proxy entry point for the services |
| `developer-service` | Custom PHP 8.3 + Apache image | `http://localhost:8001` | Developer-related API |
| `team-service` | Custom PHP 8.3 + Apache image | `http://localhost:8002` | Team-related API |
| `game-service` | Custom PHP 8.3 + Apache image | `http://localhost:8003` | Game-related API |
| `sales-service` | Custom PHP 8.3 + Apache image | `http://localhost:8004` | Sales-related API; uses SQLite |
| `mariadb` | `mariadb:11` | Internal only | Relational database for developer, team, and game services |
| `phpmyadmin` | `phpmyadmin:5` | `http://localhost:8081` | Web interface for MariaDB |
| `redis` | `redis:7-alpine` | Internal only | Cache / temporary data store (when integrated by a service) |

All containers join the user-defined `backend` bridge network. Services can therefore communicate using their Compose service names, such as `mariadb`, `game-service`, and `redis`.

### Request flow

A client can call the Nginx gateway on port `8080`. Nginx forwards the request to the appropriate internal service according to the rules in `gateway/`.

The PHP services also publish individual host ports (`8001`–`8004`) for direct development and debugging access. In a production deployment, consider exposing only the gateway and keeping application containers private.

## 2. Shared PHP service configuration

The Compose file defines a YAML anchor named `php-service`:

```yaml
x-php-service: &php-service
  env_file: .env
  networks: [backend]
  restart: unless-stopped
```

Each PHP service merges this common configuration with:

```yaml
<<: *php-service
```

This avoids repeating the shared environment-file, network, and restart settings.

The `restart: unless-stopped` policy asks Docker to restart a container after an unexpected stop, unless it was explicitly stopped by an operator.

## 3. PHP application containers

The four PHP services are built from the same Dockerfile:

```yaml
build:
  context: .
  dockerfile: docker/php/Dockerfile
  target: dev
  args:
    SERVICE: developer-service
```

The `SERVICE` build argument selects the application directory used as the Apache document root:

```text
/app/services/<SERVICE>/public
```

For example, `developer-service` serves its public entry point from:

```text
/app/services/developer-service/public
```

### Development bind mounts

Each PHP service mounts its own source directory into the container:

```yaml
volumes:
  - ./services/developer-service:/app/services/developer-service
  - ./shared:/app/shared
```

This makes local code changes visible inside the running container without rebuilding the image. The shared directory is mounted into every PHP service so common code can be reused.

The sales service additionally mounts a named volume for its SQLite storage:

```yaml
- sales_data:/app/services/sales-service/storage
```

This keeps the SQLite database outside the container's writable layer so it can survive container recreation.

## 4. Database configuration

### MariaDB databases

MariaDB stores data in the named volume `mariadb_data`, mounted at `/var/lib/mysql`.

The initialization directory is mounted read-only:

```yaml
- ./docker/mariadb/init:/docker-entrypoint-initdb.d:ro
```

The SQL initialization script creates three databases and their application users:

| Database | User | Intended service |
|---|---|---|
| `developer_db` | `developer_svc` | `developer-service` |
| `team_db` | `team_svc` | `team-service` |
| `game_db` | `game_svc` | `game-service` |

Each application user receives privileges only on its own database. This follows the principle of least privilege and avoids using the MariaDB root account from application code.

The PHP containers connect to MariaDB using the Compose hostname `mariadb`, not `localhost`.

Example environment variables for `developer-service`:

```yaml
environment:
  DB_HOST: mariadb
  DB_NAME: developer_db
  DB_USER: developer_svc
  DB_PASS: ${DEVELOPER_DB_PASS}
```

The other two services use their corresponding database, username, and password variables.

### Important: keep credentials consistent

The supplied SQL initialization script currently contains literal passwords (`dev_change_me`, `team_change_me`, and `game_change_me`), while Compose reads passwords from `.env` (`DEVELOPER_DB_PASS`, `TEAM_DB_PASS`, and `GAME_DB_PASS`).

These values must match, otherwise the application cannot authenticate. For a safer setup, generate the SQL initialization from environment-aware configuration or use a controlled initialization process rather than committing real passwords to the repository.

Keep `.env` out of version control. Commit an `.env.example` containing variable names and placeholder values, never real credentials.

### MariaDB initialization runs only on first creation

The MariaDB image processes `/docker-entrypoint-initdb.d` scripts when it initializes an empty data directory. If `mariadb_data` already contains a database, changing the initialization SQL does not automatically recreate users or update passwords.

For an existing database, apply the required SQL manually or use a migration/administration process. **Do not remove `mariadb_data` casually**: deleting the volume deletes the persisted database contents.

### Health check and startup dependency

MariaDB has a health check using its bundled `healthcheck.sh` script. The PHP services declare:

```yaml
depends_on:
  mariadb:
    condition: service_healthy
```

This makes Compose wait for MariaDB to become healthy before starting those PHP containers. It is stronger than a basic `depends_on` dependency, which controls startup order but does not itself guarantee that the database is ready to accept connections.

## 5. Sales service and SQLite

Unlike the other three PHP services, `sales-service` does not use MariaDB in this Compose configuration. It receives:

```yaml
environment:
  SQLITE_PATH: /app/services/sales-service/storage/sales.sqlite
  GAME_SERVICE_URL: http://game-service
```

The SQLite database is stored in the `sales_data` named volume. The service can call the game service over the internal Docker network using `http://game-service`; it should not use `localhost` for this inter-container request.

The application must create the SQLite file and its parent directory when needed, and the PHP image/application must have the required PDO SQLite extension available. The current Dockerfile installs `pdo_mysql` but does **not** install `pdo_sqlite`; add it if the sales service uses PDO SQLite.

## 6. API gateway

The gateway uses the official Nginx Alpine image:

```yaml
image: nginx:1.27-alpine
ports:
  - "8080:80"
volumes:
  - "./gateway:/etc/nginx/conf.d:ro"
```

The local `gateway/` directory supplies Nginx virtual-host or location configuration. It is mounted read-only inside the container.

The gateway can reach application containers by their Compose service names on the `backend` network. For example, an upstream can target `http://developer-service:80`.

`depends_on` lists the four PHP services so Compose starts them before the gateway. This is a startup-order relationship, not an application-level readiness check. If the gateway must wait for application health, add health checks to the PHP services and use supported health-based dependency conditions.

## 7. phpMyAdmin

phpMyAdmin is connected to MariaDB through:

```yaml
environment:
  PMA_HOST: mariadb
```

Open the interface at:

```text
http://localhost:8081
```

Log in with a MariaDB account, for example one of the application users or the root account. The username and password are the credentials configured in MariaDB; phpMyAdmin does not define a separate database password in this Compose file.

For local development, the published port is convenient. Do not expose phpMyAdmin publicly in production without appropriate access controls.

## 8. Redis

Redis runs on the internal `backend` network and is reachable by the hostname `redis` from other containers.

The current Compose file does not publish a host port, configure authentication, mount a persistence volume, or show any service-specific Redis environment variables. Redis is therefore available internally, but it is not necessarily being used by the applications yet.

If Redis is intended for persistent data, configure an appropriate persistence strategy. If it is only a disposable cache, document that cached data may be lost when the container is recreated.

## 9. Dockerfile stages

The PHP Dockerfile defines three stages.

### `base`

- Uses `php:8.3-apache` by default (`PHP_VERSION` can override the version).
- Installs `unzip`, `git`, and `curl`.
- Installs the `pdo_mysql` and `opcache` PHP extensions.
- Enables Apache's `rewrite` and `headers` modules.
- Sets the Apache document root to the selected service's `public` directory.
- Configures Apache to allow access and use `index.php` as the fallback resource.

### `dev`

```dockerfile
FROM base AS dev
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
```

The development stage adds Composer. Source code is expected to be provided through Compose bind mounts, which makes it suitable for local development.

### `vendor`

This stage copies `shared/` and the selected service into a Composer image, then runs:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

It prepares production dependencies without development packages.

### `prod`

The production stage starts from `base`, copies the application and installed dependencies from `vendor`, switches to PHP's production configuration, creates the service storage directory, and assigns it to `www-data`.

It also defines a health check against `/health`. The application must actually expose that endpoint and return a successful HTTP status for the health check to pass.

**Build-target note:** Compose currently specifies `target: dev` for every PHP service. To run the production stage, change the target to `prod` in a production-specific Compose configuration or override.

## 10. Common commands

Run these commands from the directory containing `docker-compose.yml` (or `compose.yaml`).

```bash
# Build images and start containers in the background
docker compose up -d --build

# Show running containers
docker compose ps

# Follow logs for all services
docker compose logs -f

# Follow logs for one service
docker compose logs -f developer-service

# Open a shell in a PHP service
docker compose exec developer-service bash

# Run Composer inside a PHP service
docker compose exec developer-service composer install

# Stop and remove containers and the Compose network
docker compose down

# Stop containers and also delete named volumes (DESTRUCTIVE)
docker compose down -v
```

The final command deletes named volumes, including `mariadb_data` and `sales_data`. Use it only when you intentionally want to delete the persisted database files.

## 11. Configuration checks and follow-up improvements

Before relying on this setup, verify the following:

- [ ] `.env` contains `MARIADB_ROOT_PASSWORD`, `DEVELOPER_DB_PASS`, `TEAM_DB_PASS`, and `GAME_DB_PASS`.
- [ ] The SQL initialization credentials match the values used by the PHP services.
- [ ] `.env` is ignored by Git; `.env.example` contains placeholders only.
- [ ] The sales service has the PDO SQLite extension available. The current Dockerfile does not install it.
- [ ] The Nginx configuration in `gateway/` routes requests to the correct service names and ports.
- [ ] Each application implements the expected `/health` endpoint if the production health check is used.
- [ ] Production deployment uses the `prod` build target and does not unnecessarily publish each PHP service or phpMyAdmin to the host.
- [ ] Backups exist for important named volumes.

## 12. Design decisions

The current configuration uses:

- **One PHP/Apache image definition** with a service-specific build argument, reducing duplicated Dockerfile maintenance.
- **Separate databases and database users** for the developer, team, and game services, limiting accidental cross-service database access.
- **A shared Docker bridge network** for internal service-to-service communication.
- **Bind mounts in development** for fast code iteration.
- **Named volumes** for database and SQLite data that must survive container recreation.
- **Nginx as a gateway** to provide one main HTTP entry point.

As the project grows, keep this document focused on how the infrastructure is configured and operated. Put service-specific API behavior, database schemas, and business rules in the documentation belonging to each service.