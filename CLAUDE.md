# myadmin-domains-module

Composer plugin package for the MyAdmin billing system. Provides domain registration lifecycle management via Symfony EventDispatcher hooks.

## Commands

```bash
composer install                          # install deps
composer test                             # run all tests
composer test -- --verbose               # verbose test output
composer coverage                         # coverage text report
```

## Architecture

- **Namespace**: `Detain\MyAdminDomains\` → `src/` · tests `Detain\MyAdminDomains\Tests\` → `tests/`
- **Entry**: `src/Plugin.php` — single class, all static methods
- **Hooks**: `Plugin::getHooks()` returns `['domains.load_processing' => [...], 'domains.settings' => [...]]`
- **Events**: all handlers receive `Symfony\Component\EventDispatcher\GenericEvent`
- **Deps**: `symfony/event-dispatcher ^5.0` · `detain/myadmin-plugin-installer dev-master` · `phpunit/phpunit ^9.6` (dev)
- **Bootstrap**: `tests/bootstrap.php` — defines `PRORATE_BILLING`, resolves autoloader
- **CI**: `.scrutinizer.yml` runs phpunit with clover coverage · `.github/` contains CI/CD workflows including `workflows/tests.yml` for automated test runs
- **IDE Config**: `.idea/` stores IDE configuration including `inspectionProfiles/Project_Default.xml`, `deployment.xml`, and `encodings.xml`

## Running Tests

```bash
# via composer scripts (recommended)
composer test

# scrutinizer-equivalent direct invocation
phpunit --bootstrap tests/bootstrap.php tests/ --coverage-clover coverage.xml --whitelist src/
```

## Plugin::$settings Keys

Required keys in every module's `$settings`:
`SERVICE_ID_OFFSET` · `USE_REPEAT_INVOICE` · `USE_PACKAGES` · `BILLING_DAYS_OFFSET` · `REPEAT_BILLING_METHOD` · `DELETE_PENDING_DAYS` · `SUSPEND_DAYS` · `SUSPEND_WARNING_DAYS` · `TITLE` · `MENUNAME` · `EMAIL_FROM` · `TBLNAME` · `TABLE` · `TITLE_FIELD` · `PREFIX`

## Global Helpers (from host app)

```php
$settings = get_module_settings(self::$module);  // returns $settings array above
$db = get_module_db(self::$module);              // module DB connection
run_event('get_service_types', false, self::$module); // dispatch event
$GLOBALS['tf']->history->add($table, $action, $status, $id, $custid);
```

## Coding Conventions

- **Indentation**: tabs (enforced in `.scrutinizer.yml` `use_tabs: true`)
- **PHP**: `>= 5.0.0` declared in `composer.json`; CI tests PHP 5.4–7.x via `.travis.yml`
- **Braces**: end-of-line for classes, functions, if/for/while
- **Constants**: uppercase (`PRORATE_BILLING`)
- **camelCase**: parameters and properties
- All DB queries pass `__LINE__, __FILE__` as 2nd/3rd args
- Smarty emails: `email/admin/domain_created.tpl`, `email/admin/domain_reactivated.tpl`
- i18n: wrap strings in `_()`
- Commit messages: lowercase, descriptive

## Adding a New Hook

1. Add entry to `Plugin::getHooks()` return array
2. Implement static handler method accepting `GenericEvent $event`
3. Access subject via `$event->getSubject()`
4. Add test in `tests/PluginTest.php`

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->
