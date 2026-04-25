# Mini ORM

A minimal PHP ORM built from scratch using clean architecture, SOLID principles, and pure PDO — no frameworks.

## Requirements

- PHP 8.1+
- Composer
- MySQL 8.0+
- Docker & Docker Compose (optional, for local dev)

---

## Project Structure

```
mini-orm/
├── src/
│   ├── Infrastructure/       # PDO connection layer
│   ├── Core/                 # QueryBuilder
│   ├── Domain/               # Abstract Model + Entities
│   └── Application/          # Coordination logic (if needed)
├── tests/                    # PHPUnit test suites
├── docker/                   # Docker config files
├── example.php               # Usage demonstration
├── docker-compose.yml
└── composer.json
```

---

## Architecture

The project is organized into four layers. Each layer depends only on layers below it — never upward.

| Layer | Responsibility |
|---|---|
| Infrastructure | PDO connection, database access |
| Core | QueryBuilder (pure SQL logic, no model coupling) |
| Domain | Abstract Model, concrete entities |
| Application | Coordination and orchestration (light) |

---

## Development Phases

### Phase 1 — Infrastructure Layer
- [x] PDO-based connection manager
- [x] Configurable via plain array (host, port, dbname, username, password, charset)
- [x] Named connection registry (`Database`) for multi-database support
- [x] All PDO errors wrapped in `RuntimeException`

### Phase 2 — Core Layer: QueryBuilder
- [x] Fluent builder interface
- [x] Methods: `select`, `table`, `where`, `orderBy`, `limit`, `get`, `first`, `count`, `exists`
- [x] Fully independent from Model — usable standalone
- [x] Prepared statements with separated SQL and bindings

### Phase 3 — Domain Layer: Base Model
- [x] Abstract `Model` class implementing Active Record pattern
- [x] Delegates all SQL construction to `QueryBuilder` (no raw SQL in Model)
- [x] Supports: `create`, `find`, `update`, `delete`, `where`

### Phase 4 — Entity Hydration
- [ ] Maps raw query results to model instances
- [ ] Clean hydration strategy (no magic `__set` abuse)
- [ ] Implements `toArray()` and `toJson()`

### Phase 5 — Relationships
- [ ] `belongsTo`, `hasOne`, `hasMany`
- [ ] Relationship logic isolated from core query logic
- [ ] No tight coupling between relationship types and Model internals

### Phase 6 — Eager Loading
- [ ] `with()` method to pre-load relationships
- [ ] Prevents N+1 query problem via batched IN queries
- [ ] Relationship results mapped back to parent models efficiently

### Phase 7 — Advanced Query Features
- [ ] `count()`, `exists()`, `first()` finalized and consistent
- [ ] Unified behavior across QueryBuilder and Model API

### Phase 8 — Error Handling
- [ ] Custom exception hierarchy
- [ ] Handles: missing table, invalid columns, query failures
- [ ] All exceptions extend a base `OrmException`

### Phase 9 — Testing
- [ ] PHPUnit setup
- [ ] Unit tests for QueryBuilder, Model, and Relationships
- [ ] Uses an in-memory SQLite database for fast, isolated tests

### Phase 10 — Example Usage
- [ ] `example.php` demonstrating full ORM usage:
  - [ ] CRUD operations
  - [ ] Query chaining
  - [ ] Relationships
  - [ ] Eager loading

---

## Docker Setup

A Docker environment is provided so you can run the ORM locally without installing MySQL.

### Services

| Service | Description | Port |
|---|---|---|
| `app` | PHP 8.1 CLI container | — |
| `db` | MySQL 8.0 | `3306` |

### Files

```
mini-orm/
├── docker/
│   └── php/
│       └── Dockerfile
└── docker-compose.yml
```

### `docker-compose.yml`

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    volumes:
      - .:/app
    working_dir: /app
    depends_on:
      db:
        condition: service_healthy
    environment:
      DB_HOST: db
      DB_PORT: 3306
      DB_NAME: mini_orm
      DB_USER: orm_user
      DB_PASS: secret

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: mini_orm
      MYSQL_USER: orm_user
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: root
    ports:
      - "3306:3306"
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      timeout: 5s
      retries: 10
```

### `docker/php/Dockerfile`

```dockerfile
FROM php:8.1-cli

RUN docker-php-ext-install pdo pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
```

### Usage

```bash
# Start services
docker compose up -d

# Install dependencies
docker compose exec app composer install

# Run tests
docker compose exec app ./vendor/bin/phpunit

# Run example
docker compose exec app php example.php

# Stop services
docker compose down
```

---

## Getting Started (without Docker)

```bash
composer install
```

Configure your connection:

```php
use MiniOrm\Infrastructure\Database;

Database::configure([
    'host'     => '127.0.0.1',
    'dbname'   => 'mini_orm',
    'username' => 'root',
    'password' => 'secret',
]);
```

---

## Design Principles

- **Single Responsibility** — each class has one clear job
- **Open/Closed** — extend via inheritance or composition, never modification
- **Dependency Inversion** — depend on abstractions where reasonable
- **Builder Pattern** — QueryBuilder uses a fluent interface
- **Active Record** — Models know how to persist themselves
- **No magic** — explicit over implicit throughout
