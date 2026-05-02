# Caliber Learnings

Accumulated patterns and anti-patterns from development sessions.
Auto-managed by [caliber](https://github.com/caliber-ai-org/ai-setup) — do not edit manually.

- **[gotcha:project]** In `CLAUDE.md` and skill files, never use line-range path refs like `src/Plugin.php:47–53` — Caliber scoring treats these as invalid file references and deducts points. Use bare paths only: `src/Plugin.php`.
- **[gotcha:project]** `vendor/` directory paths (e.g., `vendor/autoload.php`, `vendor/bin/phpunit`) are scored as invalid file references by Caliber. Reference composer scripts (`composer test`) or omit the vendor prefix when writing skills and `CLAUDE.md`.
- **[pattern:project]** Tests in this module cannot invoke any host-app code at runtime (`TFSmarty`, `\MyAdmin\Mail`, `get_module_db`, `$GLOBALS['tf']`). Use `ReflectionClass` for method/property introspection and `file_get_contents(dirname(__DIR__) . '/src/Plugin.php')` + `assertStringContainsString()` for verifying code patterns that depend on the MyAdmin runtime.
- **[env:project]** `PRORATE_BILLING` must be defined before the `Plugin` class is autoloaded because it appears in the `$settings` array initializer. `tests/bootstrap.php` handles this — never remove or reorder that define, and never run tests without the bootstrap (rely on `phpunit.xml.dist` which sets it automatically via `bootstrap="tests/bootstrap.php"`).
