# Floop

![Floop Logo](https://www.intelligentgraphicandcode.com/storage/img/floop-banner-2_Zg0mq3.jpg)

An autonomous feedback loop for Laravel apps. Testers describe what they want. AI agents make it happen.

**Feedback in. Fixes out. That's the floop.**

## The Loop

1. **You notice something** while browsing the app and hit the floop button
2. **Floop captures the intent + full page context** (URL, route, controller, blade views, viewport etc) and writes a structured work order as a markdown file
3. **Ask your AI agent** to "work through feedback" and it reads each file, locates the exact code using the captured context, makes the change, and closes the loop
4. **You verify the fix** the next time you browse the screen

It's a feedback loop compressed into a single tool. In cybernetics, a system becomes self-correcting when it can sense errors and act on them. Floop gives your Laravel app that ability: the widget is the sensor, the markdown files are the signal, and your AI agent is the actuator. 

Use Floop while your agent is busy working. Browse the app, queue up work orders, then process them in batches. ..or run it continuously in a dedicated terminal and watch as your app fixes itself while you browse.

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
- Users type what they want and hit Enter (or click)
- Each submission generates a structured work order as a `.md` file in `storage/app/feedback/pending/`
- The work order captures everything an agent needs: the controller method, Blade view hierarchy, route info, viewport, and the message
- Click any element on the page to attach its CSS selector, tag, and text to the work order
- Capture a screenshot of the current page state with one click
- Console errors and failed network requests are automatically monitored and can be attached to any submission
- A "floop" sound confirms the submission

When the agent processes a work order, it moves from `pending/` to `actioned/`. The loop is closed.

```
storage/app/feedback/
├── pending/      ← open work orders
└── actioned/     ← closed loops
```

## What It Captures

Every work order includes the tester's message plus everything an agent needs to find and fix the code:

- **Page context** — URL, route name, controller method, HTTP method, viewport size, authenticated user
- **Blade views** — every view and partial rendered on the page, not just the main one
- **Targeted element** — click any element to capture its CSS selector, tag name, and text content
- **Screenshot** — one-click page capture, saved as a companion PNG alongside the work order
- **Console errors** — automatically monitored; attach up to 5 deduplicated errors to a submission
- **Network failures** — failed HTTP requests (status 400+) captured automatically with method, URL, and status code

Here's what a work order looks like:

````markdown
# 💬 Feedback: The submit button is too small on mobile

**Status:** 🟡 Pending
**Created:** 2026-02-16 14:30:00
**Type:** Feedback
**Priority:** 🔴 High

---

## Message

The submit button is too small on mobile. I can barely tap it.

---

## Page Context

| Property | Value |
|----------|-------|
| **URL** | `https://myapp.test/orders` |
| **Route** | `orders.index` |
| **Controller** | `App\Http\Controllers\OrderController@index` |
| **Method** | `GET` |
| **View** | `orders.index` |
| **User** | Joe (joe@example.com) |
| **Viewport** | 375x812 |

### Blade Views

- `layouts.app`
- `orders.index`
- `partials.header`
- `components.order-table`

---

## Targeted Element

| Property | Value |
|----------|-------|
| **Selector** | `#order-form > div.actions > button.btn-submit` |
| **Tag** | `BUTTON` |
| **Text** | Submit Order |
| **Position** | 340, 520 (240×36) |

---

## Screenshot

![Screenshot](2026-02-16_143000_the-submit-button-is-too-small.png)
````

## CLI Commands

```bash
# List work orders (defaults to pending)
php artisan floop:list
php artisan floop:list --status=actioned
php artisan floop:list --type=bug

# Close the loop / reopen
php artisan floop:action filename.md
php artisan floop:action filename.md --note="What you changed"  # appends an "Agent Notes" section to the work order
php artisan floop:action filename.md --reopen

# Install agent skill
php artisan floop:install-skill             # auto-detects .claude, .codex, .agents, .opencode
php artisan floop:install-skill --choose    # manually pick targets
php artisan floop:install-skill --force     # overwrite existing

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
- Dispatches `FeedbackStored` and `FeedbackActioned` events so you can hook in notifications, webhooks, or custom integrations

## Roadmap

- **React & Vue support** : first-class components for React and Vue so the widget integrates natively into SPA and Inertia apps instead of relying on Blade injection
- **Storage interface & drivers** : a pluggable storage layer so work orders can live in a database, S3, or any custom driver instead of only the local filesystem
- **Team testing tools** : better support for multi-tester workflows: assignments, labels, filtering, and visibility controls so teams can triage and track feedback together

## License

MIT - IGC Enterprises Ltd
