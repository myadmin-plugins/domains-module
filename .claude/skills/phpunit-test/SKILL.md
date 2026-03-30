---
name: phpunit-test
description: Writes PHPUnit 9.6 tests under `tests/` using the `Detain\MyAdminDomains\Tests\` namespace and bootstrap from `tests/bootstrap.php`. Use when user says 'add test', 'write unit test', 'test the plugin', or 'test hook handler'. Covers Plugin::getHooks(), Plugin::$settings, static properties, and event handler signatures via ReflectionClass — without requiring the MyAdmin runtime. Do NOT use for integration tests that need a live DB or full MyAdmin bootstrap.
---
# PHPUnit Test

## Critical

- **Never instantiate framework-dependent code** (`TFSmarty`, `\MyAdmin\Mail`, `$GLOBALS['tf']`, `get_module_db`). Use `ReflectionClass` or `file_get_contents` source checks instead.
- `PRORATE_BILLING` must be defined before `Plugin` loads. `tests/bootstrap.php` does this — never remove it.
- Use **tabs** for indentation (enforced by `.scrutinizer.yml`).
- All test methods must be `public function testXxx(): void` — PHPUnit 9.6 with `beStrictAboutTestsThatDoNotTestAnything=true` will fail void tests with no assertions.
- Run tests via `composer test` (uses `phpunit.xml.dist` bootstrap automatically).

## Instructions

1. **Confirm bootstrap defines the required constant.** Open `tests/bootstrap.php` and verify it has:
   ```php
   if (!defined('PRORATE_BILLING')) {
       define('PRORATE_BILLING', 1);
   }
   ```
   Verify before writing any test that references `Plugin::$settings['REPEAT_BILLING_METHOD']`.

2. **Create or open `tests/PluginTest.php`.** The file must start with:
   ```php
   <?php
   namespace Detain\MyAdminDomains\Tests;

   use PHPUnit\Framework\TestCase;
   use ReflectionClass;
   use ReflectionMethod;
   ```
   Verify the namespace matches `autoload-dev` in `composer.json`: `Detain\MyAdminDomains\Tests\` → `tests/`.

3. **Add a `setUp()` method** initializing the two shared fixtures used across tests:
   ```php
   private $reflection;
   private $sourceFile;

   protected function setUp(): void
   {
       $this->reflection = new ReflectionClass(\Detain\MyAdminDomains\Plugin::class);
       $this->sourceFile = dirname(__DIR__) . '/src/Plugin.php';
   }
   ```
   This step's output (`$this->reflection`, `$this->sourceFile`) is used by all subsequent test methods.

4. **Write static property tests** using direct class access — no reflection needed:
   ```php
   public function testModuleProperty(): void
   {
       $this->assertSame('domains', \Detain\MyAdminDomains\Plugin::$module);
   }
   ```
   Pattern: `Plugin::$propertyName` for `$name`, `$description`, `$help`, `$module`, `$type`.

5. **Write `$settings` tests** in three layers:
   - **Keys present**: `assertArrayHasKey($key, Plugin::$settings, "Missing: {$key}")` in a loop over the 16 required keys.
   - **Types**: loop `$intKeys`, `$boolKeys`, `$strKeys` with `assertIsInt`/`assertIsBool`/`assertIsString`.
   - **Values**: `assertSame($expected, Plugin::$settings[$key])` for every scalar.
   Verify `assertCount(16, Plugin::$settings)` as a guard against silent key additions.

6. **Write `getHooks()` tests** covering: return type, key names, count, callable format, and method mapping:
   ```php
   public function testGetHooksMethodMapping(): void
   {
       $hooks = \Detain\MyAdminDomains\Plugin::getHooks();
       $this->assertSame('loadProcessing', $hooks['domains.load_processing'][1]);
       $this->assertSame('getSettings', $hooks['domains.settings'][1]);
   }
   ```
   Also assert all keys start with `Plugin::$module . '.'`.

7. **Write method signature tests** using `$this->reflection`:
   ```php
   public function testLoadProcessingSignature(): void
   {
       $method = $this->reflection->getMethod('loadProcessing');
       $this->assertTrue($method->isPublic());
       $this->assertTrue($method->isStatic());
       $params = $method->getParameters();
       $this->assertCount(1, $params);
       $this->assertSame('event', $params[0]->getName());
       $this->assertSame(
           'Symfony\\Component\\EventDispatcher\\GenericEvent',
           $params[0]->getType()->getName()
       );
   }
   ```
   Apply the same pattern to `getSettings()`.

8. **Write source-level checks** for patterns that need framework globals at runtime:
   ```php
   public function testSourceReferencesFrameworkFunctions(): void
   {
       $source = file_get_contents($this->sourceFile);
       $this->assertStringContainsString('get_module_settings(', $source);
       $this->assertStringContainsString('get_module_db(', $source);
       $this->assertStringContainsString('run_event(', $source);
   }
   ```
   Use this pattern for: email templates, `history->add(`, `TFSmarty`, `->setEnable(`, `->setReactivate(`, `->register()`.

9. **Run tests and confirm green:**
   ```bash
   composer test
   ```
   Expected: `OK (N tests, N assertions)`. Fix any failures before committing.

## Examples

**User says:** "Add a test that verifies the hook for `domains.settings` calls `getSettings`"

**Actions taken:**
1. Open `tests/PluginTest.php`, confirm `setUp()` exists with `$this->reflection`.
2. Add inside the `getHooks` group:
   ```php
   public function testDomainsSettingsHookCallsGetSettings(): void
   {
       $hooks = \Detain\MyAdminDomains\Plugin::getHooks();
       $this->assertArrayHasKey('domains.settings', $hooks);
       $callable = $hooks['domains.settings'];
       $this->assertSame([\Detain\MyAdminDomains\Plugin::class, 'getSettings'], $callable);
       $this->assertTrue($this->reflection->hasMethod('getSettings'));
   }
   ```
3. Run `composer test` → `OK`.

**Result:** One new test confirming the hook wiring without touching the MyAdmin runtime.

## Common Issues

- **`Error: Undefined constant "PRORATE_BILLING"`** — `tests/bootstrap.php` is not being used. Run via `composer test`. If using a custom command, add `--bootstrap tests/bootstrap.php`.

- **`This test did not perform any assertions`** — PHPUnit 9.6 with `beStrictAboutTestsThatDoNotTestAnything=true` treats this as a failure. Add at least one `$this->assertXxx()` call, or add `/** @doesNotPerformAssertions */` docblock only if intentional.

- **`Class "Detain\MyAdminDomains\Plugin" not found`** — Composer autoloader not loaded. Check `tests/bootstrap.php` finds the autoloader. Run `composer install` if `vendor/` is missing.

- **`ReflectionException: Method X does not exist`** — You referenced a method name that doesn't match the actual source. Check `src/Plugin.php` for the exact spelling (`loadProcessing`, not `load_processing`).

- **`assertCount(16, ...)` fails with 17** — A new key was added to `Plugin::$settings`. Update the count assertion and add the new key to the `testSettingsContainsRequiredKeys` expected array.
