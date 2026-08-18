---
name: mediqueue-quality-gate
description: Run the complete MediQueue Laravel backend audit, repair, and delivery verification workflow. Use when asked to review the project, find all errors, fix undefined types, verify routes, run all tests, or prepare the backend for delivery.
---

# MediQueue Quality Gate

Run this workflow from the repository root. Follow `AGENTS.md` throughout, preserve the user's scope, and do not hide or reclassify failures.

## Safety boundary

- Use PHP 8.3+, Laravel 13, MariaDB 11.8+, `DB_CONNECTION=mariadb`, Sanctum, and Spatie Laravel Permission.
- Never use SQLite as a fallback.
- Never run `migrate:fresh`, truncation, destructive schema operations, or destructive tests against `mediqueue`.
- Before running database-resetting tests, inspect the test configuration and confirm it explicitly selects the MariaDB database `mediqueue_test`. Stop and report the blocker if either value differs or cannot be verified.
- Never print `.env` values, passwords, tokens, secrets, or private medical-document paths. Inspect named configuration keys without emitting their secret values.
- A review request authorizes inspection and reporting, not edits. Fix discovered errors only when the user's request authorizes changes; otherwise report the proposed fixes.

## Workflow

1. Run `git status --short` and `git diff` before editing. Preserve all pre-existing changes. If this is not a Git worktree, record that and inspect relevant files directly.
2. Read `composer.json` and verify its PSR-4 mappings against PHP file paths, namespaces, and class names. Check newly affected classes especially carefully. Use `composer dump-autoload --optimize --strict-psr` to surface PSR violations, then run the required plain `composer dump-autoload` during the final pass.
3. Inspect every application route action. Confirm each referenced controller class autoloads and every referenced method exists; closures and framework/vendor routes may be skipped. Treat missing classes, methods, invalid namespaces, and invalid route middleware aliases as failures.
4. Run `composer validate` and report warnings as well as errors.
5. Run PHP syntax checks over repository PHP source while excluding dependencies and generated/cache directories. Use null-delimited paths so spaces cannot corrupt the check.
6. Run `php artisan optimize:clear`.
7. Run `php artisan about` and confirm the reported PHP, Laravel, database, cache, queue, and environment configuration is plausible without revealing secrets.
8. Run `php artisan route:list`, then reconcile it with the route-controller inspection from step 3.
9. Review `storage/logs/laravel.log` and any rotated Laravel logs. Focus on current or reproducible `ERROR`, `CRITICAL`, `ALERT`, `EMERGENCY`, uncaught exception, and stack-trace entries; distinguish stale failures from current failures.
10. Verify the runtime database driver is MariaDB and run `php artisan migrate:status`. Do not apply, roll back, reset, or freshen migrations unless the current user request authorizes that state change. Report pending or failed migrations.
11. Inspect `phpunit.xml` and relevant testing configuration. Require `DB_CONNECTION=mariadb` and `DB_DATABASE=mediqueue_test` before running tests that migrate, refresh, truncate, or otherwise destroy data.
12. Run `php artisan test` only after the test database safety check passes. Capture the real test, assertion, failure, error, warning, and skipped counts.
13. Run `vendor/bin/pint --test`. If formatting fixes are authorized and needed, run `vendor/bin/pint`, inspect its changes, and repeat `vendor/bin/pint --test`.
14. Fix discovered application errors within the authorized task scope. Use Form Requests, Services, API Resources, Policies, thin controllers, `/api/v1`, explicit mass-assignment allow-lists, and ownership-scoped queries. Do not weaken tests or security checks to make the gate pass.
15. After any fix, inspect the diff and repeat every affected check. Before delivery, always run the repository's required completion sequence exactly:

```bash
composer dump-autoload
php artisan optimize:clear
php artisan route:list
php artisan test
vendor/bin/pint --test
```

16. Report each command as passed, failed, blocked, or not run. Include concrete counts and actionable failure summaries. Never state that the project is ready or tests pass unless the relevant commands completed successfully in this run.
