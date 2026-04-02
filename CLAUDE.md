# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Herd** is a CLI utility for downloading, installing, switching, and managing multiple PHP versions (Windows-only) and backend services (MySQL, MariaDB, PostgreSQL, Redis, MongoDB, etc.; any OS). The main entry point is `herd.php`.

## Commands

```bash
# Install dependencies and set up directories
composer build:init   # or: composer b

# Run tests
composer tests:run    # or: composer t
# Run a single test file
php vendor/nette/tester/src/tester -C --colors 1 tests/src/Version.phpt

# Static analysis
composer phpstan:run  # or: composer ps

# Code style check
composer phpcs:src
composer phpcs:tests
composer phpcs:fix    # auto-fix

# All checks (tests + phpstan + lint + cs + spell)
composer check:fast   # or: composer cf
composer check:all    # across PHP 8.0–8.2
```

## Architecture

### Entry Point & Routing

`herd.php` is the executable CLI script. It instantiates all installer classes and routes commands based on CLI flags. 

Contents of `src/App/` are unused for now. it is experimental implementation of routing using PHP 8 attribute-based declarations (`#[Action]`, `#[Argument]`, `#[Option]`, `#[Resource]`, `#[RouteList]`).

`src/Application.php` is a skeleton class showing the intended route structure with these attributes. `src/Input.php` maps parsed CLI input to typed properties.

### Installers

All service installers live in `src/Installer/`. There are two base patterns:

- **`PhpInstaller.php`** — Windows-native PHP installation, downloads from php.net into `C:/tools/php`, manages NTS/TS variants, 32/64-bit, PECL extensions.
- **`DockerInstaller.php`** — Abstract base for all Docker-managed services (MySQL, MariaDB, PostgreSQL, Redis, Mongo, etc.). All database/cache installers extend this.

### Version Handling

`src/Version.php` is central — it parses version strings from URLs, release names, directory names, and user expressions. Version expressions support wildcards: `*` (any), `^` (last stable), `_` (any except last), ranges, and comma-separated lists.

### Other Key Files

- `src/HttpHelper.php` — HTTPS download utility using Dogma HTTP, handles Windows SSL certificates.
- `src/Caller.php` — Executes PHP functions in a different PHP binary (cross-version calling).
- `src/ExtensionInfoLoader.php` — Loads PHP extension metadata.
- `src/Info/PhpInfo.php` — Static data: PHP version family trees, core extensions per version.

## Test Format

Tests use **Nette Tester** (`.phpt` extension). Assertions use `Dogma\Tester\Assert`. Tests live in `tests/src/`.

## Notes

- Requires PHP 8.2+, Windows only (service management and paths are Windows-specific).
- PHPStan config: `build/PHPStan/phpstan.neon` (level 0, strict rules).
- Autoload uses `classmap` on `src/` — run `composer da` after adding new classes.
- The `composer.json` phpcs/lint scripts reference `sources` (legacy path); the actual source directory is `src/`.