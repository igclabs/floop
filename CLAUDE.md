# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Floop is a Laravel package (library) that adds a floating feedback widget to Laravel apps. Users submit feedback through the widget, which is saved as markdown files (no database). AI agents then read and action the feedback via CLI commands.

**Namespace:** `IgcLabs\Floop`
**PHP:** 8.1+ | **Laravel:** 10, 11, 12

## Architecture

- **FloopServiceProvider** — Registers config, routes, views, Blade directive (`@floop`), and console commands. Auto-discovered by Laravel.
- **FloopManager** — Singleton handling all feedback file I/O: store, list, mark actioned/pending, delete. Uses `storage/app/feedback/{pending,actioned}/` directories.
- **FloopController** — Single `store()` endpoint that validates input and delegates to FloopManager.
- **InjectFloopContext** middleware — Captures request context (URL, route, controller, user) and listens for Blade view renders to build a view list, shared to the widget via `View::share()`.
- **widget.blade.php** — Self-contained UI (inline CSS/JS, no external assets). Renders the floating button, feedback panel, and handles submission via fetch API.

Data flow: Widget JS → POST to `/_feedback/store` → FloopController → FloopManager → markdown file in `pending/` → CLI to move to `actioned/`.

## Key Conventions

- **No database** — all state is filesystem-based markdown files
- **No build toolchain** — CSS and JS are inline in the Blade view, no webpack/Vite
- **No test suite** — no tests directory or PHPUnit config exists yet
- **PSR-4 autoloading** under `src/`
- Widget enable/disable is a `.disabled` flag file in the storage path

## CLI Commands

```bash
php artisan floop:list                    # List pending feedback
php artisan floop:list --status=actioned  # List actioned feedback
php artisan floop:action filename.md      # Mark feedback as actioned
php artisan floop:action filename.md --reopen  # Reopen actioned feedback
php artisan floop:clear                   # Clear pending feedback
php artisan floop:enable                  # Enable widget
php artisan floop:disable                 # Disable widget
```

## Version Management

```bash
./update_version.sh "optional commit message"
```

Increments version in `version.txt`, commits, tags, and pushes.

## SKILL.md

The `SKILL.md` file contains instructions for Claude Code agents on how to process feedback files. It is installed into host projects at `.claude/skills/floop/SKILL.md`. When modifying the feedback file format or workflow, keep SKILL.md in sync.
