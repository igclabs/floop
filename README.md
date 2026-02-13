# Floop

![Floop Logo](https://www.intelligentgraphicandcode.com/storage/img/floop-banner_X8zxXW.jpg)

A lightweight feedback widget for Laravel apps. Zero dependencies, no database, feedback is stored as markdown files that AI coding agents can read and action.

## The Feedback Loop

1. **Install Floop** into your Laravel app
2. **Add the Claude Code skill** so your agent knows how to process feedback
3. **Testers submit feedback** via the widget while browsing the app
4. **Ask your agent** to "work through feedback" and watch as it reads each file, understands the request, locates the code using the captured context, makes the change, and marks it as actioned

Feedback in. Fixes out. That's the floop.

## Installation

Add the path repository to your `composer.json`:

```bash
composer require igclabs/floop:@dev
```

That's it. The service provider is auto-discovered, the middleware registers itself, and the widget is automatically injected into HTML responses. Browse any page and you'll see the feedback button.

## Setup

**Install the Claude Code skill:**

```bash
php artisan floop:install-skill
```

Now when you ask Claude Code to "work through feedback" or "process feedback", it knows exactly what to do.

**Publish config** (optional):

```bash
php artisan vendor:publish --tag=floop-config
```

## Customisation

By default, Floop auto-injects the widget before `</body>` on every HTML response. If you want manual control over placement:

**1.** Disable auto-injection in `config/floop.php`:

```php
'auto_inject' => false,
```

**2.** Add `@floop` to your Blade layout (before `</body>`):

```blade
@floop
```

The middleware is registered automatically on the global stack. No manual middleware registration is needed.

## How It Works

- A floating button appears in the corner of every page
- Users type feedback and hit Enter (or click "Floop It")
- Each submission is saved as a `.md` file in `storage/app/feedback/pending/`
- A "floop" sound plays on successful submission
- The widget captures page context automatically: URL, route, controller, Blade views, viewport size

Each feedback file contains everything an AI agent needs to locate and fix the issue: the exact controller method, Blade view hierarchy, route parameters, and the user's message describing what they noticed.

### Tip

Use Floop while your agent is busy working, take the time to look around the app and queue up feedback and small fix jobs while it's busy and then complete them in batches or run it continously in a dedicated terminal and watch as your app fixes itself as you browse and send feedback! 

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
| `auto_inject` | `true` | Auto-inject widget into HTML responses (set `false` to use `@floop` manually) |
| `storage_path` | `storage_path('app/feedback')` | Where `.md` files are stored |
| `route_prefix` | `_feedback` | URL prefix for the widget API |
| `environments` | `['local']` | Environments where the widget renders (`['*']` for all) |
| `position` | `bottom-right` | Widget position: `bottom-right`, `bottom-left`, `top-right`, `top-left` |
| `shortcut` | `ctrl+shift+f` | Keyboard shortcut to toggle the feedback panel |
| `hide_shortcut` | `ctrl+shift+h` | Keyboard shortcut to hide/show the entire widget |
| `default_type` | `feedback` | Default feedback type |

## Keyboard Shortcuts

- **Ctrl+Shift+F** = toggle the feedback panel open/closed
- **Ctrl+Shift+H** = hide/show the entire widget
- **Enter** = submit feedback (when typing in the textarea)
- **Shift+Enter** = new line in the textarea
- **Escape** = close the panel

## Design

- Self-contained: all CSS and JS are inline, no CDNs or external assets
- Dark mode: respects `prefers-color-scheme: dark` and `data-bs-theme="dark"`
- System fonts only
- All styles scoped under `#floop-widget` to avoid conflicts
- Submit sound synthesized via Web Audio API (no audio files)

## License

MIT - IGC Enterprises Ltd
