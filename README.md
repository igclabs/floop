# Floop

![Floop Logo](https://www.intelligentgraphicandcode.com/storage/img/floop-banner_X8zxXW.jpg)

An autonomous feedback loop for Laravel apps. Testers describe what they want. AI agents make it happen.

**Feedback in. Fixes out. That's the floop.**

## The Loop

1. **Tester notices something** while browsing the app and hits the floop button
2. **Floop captures the intent + full page context** — URL, route, controller, Blade views, viewport — and writes a structured work order as a markdown file
3. **Ask your AI agent** to "work through feedback" — it reads each work order, locates the exact code using the captured context, makes the change, and closes the loop
4. **Tester verifies the fix** next time they browse past

The widget is the sensor. The markdown file is the signal. The AI agent is the actuator. The tester closes the loop.

## Installation

```bash
composer require igclabs/floop:@dev
```

That's it. The service provider is auto-discovered, the middleware registers itself, and the widget is automatically injected into every HTML response. Browse any page and you'll see the floop button.

**Install the agent skill:**

```bash
php artisan floop:install-skill
```

Now when you ask Claude Code to "work through feedback" or "process feedback", it knows exactly how to read the work orders, locate the code, make the changes, and close each loop.

## How It Works

- A floating button appears in the corner of every page
- Users type what they want and hit Enter (or click "Floop It")
- Each submission generates a structured work order as a `.md` file in `storage/app/feedback/pending/`
- The work order captures everything an agent needs: the exact controller method, Blade view hierarchy, route parameters, viewport size, and the user's message
- A "floop" sound confirms the submission

When the agent processes a work order, it moves from `pending/` to `actioned/` — the loop is closed.

```
storage/app/feedback/
├── pending/      ← open work orders
└── actioned/     ← closed loops
```

### Tip

Use Floop while your agent is busy working. Browse the app, queue up work orders, then process them in batches — or run it continuously in a dedicated terminal and watch as your app fixes itself while you browse.

## Why "Floop"?

A floop is a feedback loop compressed into a single tool. In cybernetics, a system becomes self-correcting when it can sense errors and act on them. Floop gives your Laravel app that ability: the widget is the sensor, the markdown file is the signal, and the AI agent is the actuator. The tester closes the loop by verifying the fix.

## CLI Commands

```bash
# List work orders (defaults to pending)
php artisan floop:list
php artisan floop:list --status=actioned
php artisan floop:list --type=bug

# Close the loop / reopen
php artisan floop:action filename.md
php artisan floop:action filename.md --reopen

# Clear work orders
php artisan floop:clear              # clear pending
php artisan floop:clear --actioned   # clear actioned
php artisan floop:clear --all        # clear everything

# Enable / disable the widget
php artisan floop:enable
php artisan floop:disable
```

## Configuration

**Publish config** (optional):

```bash
php artisan vendor:publish --tag=floop-config
```

Key options in `config/floop.php`:

| Key | Default | Description |
|-----|---------|-------------|
| `auto_inject` | `true` | Auto-inject widget into HTML responses (set `false` to use `@floop` manually) |
| `storage_path` | `storage_path('app/feedback')` | Where work order files are stored |
| `route_prefix` | `_feedback` | URL prefix for the submission endpoint |
| `environments` | `['local']` | Environments where the widget renders (`['*']` for all) |
| `position` | `bottom-right` | Widget position: `bottom-right`, `bottom-left`, `top-right`, `top-left` |
| `shortcut` | `ctrl+shift+f` | Keyboard shortcut to toggle the feedback panel |
| `hide_shortcut` | `ctrl+shift+h` | Keyboard shortcut to hide/show the entire widget |
| `default_type` | `feedback` | Default feedback type |

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

## Keyboard Shortcuts

- **Ctrl+Shift+F** = toggle the feedback panel open/closed
- **Ctrl+Shift+H** = hide/show the entire widget
- **Enter** = submit (when typing in the textarea)
- **Shift+Enter** = new line in the textarea
- **Escape** = close the panel

## Design

- Self-contained: all CSS and JS are inline, no CDNs or external assets
- Filesystem-native: lives in your repo, not your infrastructure
- Dark mode: respects `prefers-color-scheme: dark` and `data-bs-theme="dark"`
- All styles scoped under `#floop-widget` to avoid conflicts
- Submit sound synthesized via Web Audio API (no audio files)

## License

MIT - IGC Enterprises Ltd
