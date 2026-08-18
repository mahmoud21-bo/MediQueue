# MediQueue Repository Rules

These instructions apply to the entire repository.

## Platform

- Use Laravel 13 and PHP 8.3 or newer.
- Use MariaDB 11.8 or newer with `DB_CONNECTION=mariadb`.
- Never use SQLite in development, testing, CI, examples, or fallback configuration.
- Authentication uses Laravel Sanctum.
- Authorization uses Spatie Laravel Permission. Roles and permissions are the source of truth; do not add a manually editable role column.
- Prefix every API route with `/api/v1`.

## Architecture and security

- Use dedicated Form Requests for validation and input normalization.
- Put business workflows and transactions in Services.
- Shape public API output with API Resources.
- Use Policies and route middleware for authorization.
- Keep controllers thin: validate or authorize, delegate to a service, and return a response.
- Prevent IDOR by resolving ownership and access from the authenticated user and by scoping nested resources to their parent.
- Prevent mass-assignment vulnerabilities with explicit fillable fields and validated, allow-listed input.
- Never expose passwords, plain-text tokens, medical-document storage paths or public URLs, secrets, or `.env` values in responses, logs, fixtures, documentation, command output, or reports.

## Database safety

- Never run `migrate:fresh`, destructive schema commands, truncation, or destructive database tests against `mediqueue`.
- Destructive database tests are allowed only against the `mediqueue_test` MariaDB database.
- Before any destructive database operation, resolve and verify the active database name. Stop if it is not exactly `mediqueue_test`.
- Use forward-only compatibility migrations for schemas that may already be deployed. Do not rewrite executed production migration history to apply a schema change.

## Change discipline

- Before editing, inspect `git status` and `git diff`. Preserve existing user changes and avoid unrelated edits.
- If the directory is not a Git worktree, report that fact and inspect the relevant files before editing.
- Never claim a command or test passed unless it was actually executed successfully in the current task.
- Report failures honestly, including commands that could not run because of environment or permission constraints.

## Required completion checks

Before completing any implementation or fix task, run these commands from the repository root in this order:

```bash
composer dump-autoload
php artisan optimize:clear
php artisan route:list
php artisan test
vendor/bin/pint --test
```

Do not substitute SQLite when MariaDB is unavailable. If a required check cannot run, report it as not run or failed rather than claiming success.
