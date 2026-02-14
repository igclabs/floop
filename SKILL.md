---
name: floop
description: Processes work orders from the Floop feedback loop. Use when asked to "work through feedback", "process feedback", "check feedback", or when pointed at .md files in storage/app/feedback/pending/. Reads each work order, understands what's being asked, locates the code using captured page context, makes the change, then closes the loop.
---

# Processing Work Orders

Work orders live in `storage/app/feedback/pending/` as `.md` files. Each one is a structured request from a tester or reviewer (a bug report, a task, a feature idea, or general feedback) submitted from a specific page in the app with full context about the route, controller, view, and data involved.

The widget captures the intent. The markdown file is the signal. You are the actuator. Close the loop.

## Workflow

For each pending work order:

1. **Read the file** and understand what's being asked
2. **Use the Page Context section** to locate the relevant code. The View tells you which Blade template, the Controller tells you which method, the Route tells you the URL structure
3. **Assess the request**: is it something you can action (a code change, a UI tweak, a bug fix) or is it ambiguous/out of scope?
4. **Make the change** if it's clear and actionable
5. **Close the loop** by running `php artisan floop:action <filename>`
6. **Move to the next work order**

## Reading a Work Order

Every file follows this structure:

```
# [Type]: [Summary]

**Status:** Pending
**Created:** [timestamp]
**Type:** [Feedback|Task|Idea|Bug]

---

## Message

[The actual request from the tester]

---

## Page Context

[Table with URL, Route, Controller, Method, View, User, Viewport, Browser]

### Blade Views

[List of all Blade views/partials rendered on that page]
```

The **Message** is what the person wants. The **Page Context** gives you everything you need to locate and fix the code without asking. The **Blade Views** list shows every template and partial that was rendered. This is your map of what files to look at.

## Locating Code

Use the context fields as direct pointers:

- **View** → `resources/views/{value}.blade.php` (dots become directory separators)
- **Controller** → the full class path and method, e.g. `App\Http\Controllers\Admin\AdminPostController@index` means look at the `index` method in `app/Http/Controllers/Admin/AdminPostController.php`
- **Route** → check `routes/web.php` or run `php artisan route:list --name={route_name}` to see the full route definition
- **Blade Views list** → all the partials and layouts involved, so you know the full template hierarchy

## Decision Making

**Action it** if the request is:
- A clear UI change ("add the date to this screen", "make this button blue", "move the sidebar")
- A bug with enough context to reproduce ("this link goes to the wrong page", "the count is wrong")
- A straightforward feature addition ("add a search box", "add pagination")
- A content/copy change ("change this heading", "fix this typo")

**Ask for clarification** if the request is:
- Ambiguous ("make this page better" ... better how?)
- Potentially breaking ("remove the auth check" ... are you sure?)
- A large architectural change that needs discussion
- Contradicting other work orders

**Skip and explain** if the request is:
- Out of scope for the codebase you have access to
- A duplicate of another work order
- Already resolved

## Closing the Loop

When you've completed the work for a work order:

```bash
php artisan floop:action <filename>
```

This moves the file from `pending/` to `actioned/` and updates its status. The loop is closed.

## Batch Processing

When asked to "process all feedback" or "work through the feedback":

1. Run `php artisan floop:list` to see everything pending
2. Read each file in `storage/app/feedback/pending/` in chronological order (oldest first)
3. Group related items if multiple work orders touch the same view or controller. Do them together to avoid conflicts
4. Process each one using the workflow above
5. Give a summary at the end: what you actioned, what you skipped, what needs discussion

## Important

- **Don't delete work orders.** Always use `php artisan floop:action` to close the loop. The actioned files serve as a history of what was changed and why.
- **Commit your code changes separately from processing.** The work order files are in `.gitignore` by default, so your code changes are the only things that need committing.
- **Read the full Blade Views list.** The issue might not be in the main view but in a partial or layout component listed there.
- **Check the viewport.** If the tester was on a specific screen size, the issue might be responsive/CSS related.
- **Note the user.** If the feedback references data they can see, it might be role/permission specific.
