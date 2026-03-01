# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Symfony 7.2 web app that serves as an extension catalog manager for Akeneo PIM. Users browse extensions from a GitHub-hosted `catalog.json`, then deploy/update/undeploy them to their PIM instance via the Akeneo REST API. Authentication uses OAuth 2.0 with PKCE, initiated from the PIM side.

## Development Commands

```bash
# Install dependencies
composer install

# Create and migrate database (SQLite)
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Run dev server
php -S localhost:8000 -t public/

# Reset database
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

No test suite, linter, or build step is currently configured.

## Architecture

### Request Flow

1. **OAuth activation** (`/activate`) → PIM redirects user here → app redirects to PIM's authorize endpoint with PKCE
2. **OAuth callback** (`/callback`) → exchanges code for access token, stores token + PIM URL in session
3. **Catalog** (`/catalog`) → fetches extensions from GitHub, shows deployment status per PIM instance

### Key Layers

- **Controllers** (`src/Controller/`) — handle HTTP routing and orchestrate services
  - `CatalogController` is the main controller: lists extensions, deploys, updates, undeploys
  - `ActivateController` / `CallbackController` handle the OAuth flow
- **Services** (`src/Service/`)
  - `GitHubCatalogProvider` — fetches and caches `catalog.json` from GitHub (5-min TTL)
  - `ExtensionDeployer` — builds multipart requests to the Akeneo UI Extensions API, handles deploy/update/undeploy
- **Entity** (`src/Entity/Deployment.php`) — tracks deployments per `(pim_url, extension_slug)` pair
- **DTO** (`src/Dto/ExtensionDto.php`) — maps each extension entry from `catalog.json`
- **Templates** (`templates/`) — Twig templates with inline CSS (no external CSS/JS build)

### Akeneo API Integration Quirks

- **Custom multipart builder**: `ExtensionDeployer` manually constructs `multipart/form-data` bodies because the Akeneo API rejects `Content-Type` headers on individual text fields (Symfony's default behavior).
- **Label truncation**: All labels are truncated to 30 characters via `mb_substr()` before sending to the API.
- **Update uses POST with `_method=PATCH`**: The update endpoint requires `POST /api/rest/v1/ui-extensions/{uuid}` with a `_method=PATCH` field rather than a real PATCH request.

### Session-Based Auth

No persistent user accounts. The OAuth access token and PIM URL live in the PHP session. The same app instance supports multiple PIM instances (different sessions point to different PIM URLs).

## Configuration

Environment variables in `.env`:
- `CLIENT_ID` / `CLIENT_SECRET` — Akeneo custom app OAuth credentials
- `DATABASE_URL` — SQLite by default (`var/data.db`)
- `APP_SECRET` — Symfony secret key

Service wiring is in `config/services.yaml`. Doctrine config in `config/packages/doctrine.yaml` with attribute-based entity mapping.
