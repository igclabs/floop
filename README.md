# Floop

A lightweight feedback widget for Laravel apps. Zero dependencies, no database, feedback is stored as markdown files that AI coding agents can read and action.

## The Feedback Loop

1. **Install Floop** into your Laravel app
2. **Add the Claude Code skill** so your agent knows how to process feedback
3. **Testers submit feedback** via the widget while browsing the app
4. **Ask your agent** to "work through feedback" it reads each file, understands the request, locates the code using the captured context, makes the change, and marks it as actioned

Feedback in. Fixes out. That's the floop.

## Installation

Add the path repository to your `composer.json`:

```json
"repositories": [
    { "type": "path", "url": "packages/igclabs/floop" }
]
```

```bash
composer require igclabs/floop:@dev
```

The service provider is auto-discovered.

## Setup

**1. Register the middleware** in `bootstrap/app.php`:

```php
$middleware->appendToGroup('web', \IgcLabs\Floop\Http\Middleware\InjectFloopContext::class);
```

**2. Add the widget** to your Blade layout (before `</body>`):

```blade
@floop
```

**3. Install the Claude Code skill** — copy `SKILL.md` from this package into your project:

```bash
mkdir -p .claude/skills/floop
cp packages/igclabs/floop/SKILL.md .claude/skills/floop/SKILL.md
```

Now when you ask Claude Code to "work through feedback" or "process feedback", it knows exactly what to do.

**4. Publish config** (optional):

```bash
php artisan vendor:publish --tag=floop-config
```

## How It Works

- A floating button appears in the corner of every page
- Users type feedback and hit Enter (or click "Floop It")
- Each submission is saved as a `.md` file in `storage/app/feedback/pending/`
- A "floop" sound plays on successful submission
- The widget captures page context automatically: URL, route, controller, Blade views, viewport size

Each feedback file contains everything an AI agent needs to locate and fix the issue — the exact controller method, Blade view hierarchy, route parameters, and the user's message describing what they noticed.

### File Storage

```
storage/app/feedback/
├── pending/      ← new submissions land here
└── actioned/     ← resolved items move here
```

Filenames follow the pattern: `YYYY-MM-DD_HHmmss_slug-of-message.md`

## CLI Commands

```bash
# List feedback items (defaults to pending)
php artisan floop:list
php artisan floop:list --status=actioned
php artisan floop:list --type=bug

# Mark as actioned / reopen
php artisan floop:action filename.md
php artisan floop:action filename.md --reopen

# Clear feedback
php artisan floop:clear              # clear pending
php artisan floop:clear --actioned   # clear actioned
php artisan floop:clear --all        # clear everything

# Enable / disable the widget
php artisan floop:enable
php artisan floop:disable
```

## Configuration

Key options in `config/floop.php`:

| Key | Default | Description |
|-----|---------|-------------|
| `storage_path` | `storage_path('app/feedback')` | Where `.md` files are stored |
| `route_prefix` | `_feedback` | URL prefix for the widget API |
| `environments` | `['local']` | Environments where the widget renders (`['*']` for all) |
| `position` | `bottom-right` | Widget position: `bottom-right`, `bottom-left`, `top-right`, `top-left` |
| `shortcut` | `ctrl+shift+f` | Keyboard shortcut to toggle the feedback panel |
| `hide_shortcut` | `ctrl+shift+h` | Keyboard shortcut to hide/show the entire widget |
| `default_type` | `feedback` | Default feedback type |

## Keyboard Shortcuts

- **Ctrl+Shift+F** — toggle the feedback panel open/closed
- **Ctrl+Shift+H** — hide/show the entire widget
- **Enter** — submit feedback (when typing in the textarea)
- **Shift+Enter** — new line in the textarea
- **Escape** — close the panel

## Design

- Self-contained: all CSS and JS are inline, no CDNs or external assets
- Dark mode: respects `prefers-color-scheme: dark` and `data-bs-theme="dark"`
- System fonts only
- All styles scoped under `#floop-widget` to avoid conflicts
- Submit sound synthesized via Web Audio API (no audio files)

## License

MIT - IGC Enterprises Ltd
