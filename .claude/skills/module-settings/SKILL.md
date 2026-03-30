---
name: module-settings
description: Adds admin settings fields via getSettings(GenericEvent $event) using $settings->add_dropdown_setting() or similar methods. Follows the pattern in Plugin::getSettings(). Use when user says 'add setting', 'add config option', 'toggle feature', 'out-of-stock flag', or 'admin configuration'. Do NOT use for service lifecycle changes, hook registration for non-settings events, or $settings array keys in Plugin::$settings.
---
# Module Settings

## Critical

- **Never** access `$_GET`/`$_POST` directly inside `getSettings()` — settings values come only from `$settings->get_setting('KEY')`.
- Setting key in `get_setting()` **must be UPPERCASE** (e.g. `'OUTOFSTOCK_DOMAINS'`); the storage key passed to `add_dropdown_setting()` **must be lowercase_underscored** (e.g. `'outofstock_domains'`).
- All display strings **must** be wrapped in `_()` for i18n.
- Use tabs for indentation (enforced by `.scrutinizer.yml`).
- The `domains.settings` hook must already be registered in `getHooks()` — verify before adding a new setting.

## Instructions

1. **Verify the hook is registered** in `Plugin::getHooks()` (`src/Plugin.php`):
   ```php
   public static function getHooks()
   {
       return [
           self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
           self::$module.'.settings'        => [__CLASS__, 'getSettings'],
       ];
   }
   ```
   If `domains.settings` is missing, add it before proceeding.

2. **Open `src/Plugin.php`** and locate `getSettings()`. If it does not exist, add it as a `public static` method accepting `GenericEvent $event`:
   ```php
   public static function getSettings(GenericEvent $event)
   {
       /** @var \MyAdmin\Settings $settings **/
       $settings = $event->getSubject();
   }
   ```
   Confirm `use Symfony\Component\EventDispatcher\GenericEvent;` is at the top of the file.

3. **Add a dropdown setting** inside `getSettings()` using `add_dropdown_setting()`:
   ```php
   $settings->add_dropdown_setting(
       self::$module,                          // module: 'domains'
       _('General'),                           // group (translatable)
       'outofstock_domains',                   // storage key (lowercase_underscored)
       _('Out Of Stock Domains'),              // label (translatable)
       _('Enable/Disable Sales Of This Type'), // description (translatable)
       $settings->get_setting('OUTOFSTOCK_DOMAINS'), // current value (UPPERCASE key)
       ['0', '1'],                             // option values
       ['No', 'Yes']                           // option labels
   );
   ```
   - For a text input use `add_text_setting(module, group, key, label, description, current_value)`.
   - Verify the lowercase storage key matches the UPPERCASE key in `get_setting()` (e.g. `outofstock_domains` ↔ `OUTOFSTOCK_DOMAINS`).

4. **Add a test** in `tests/PluginTest.php` confirming the new setting call exists in source:
   ```php
   public function testSourceContainsDropdownSetting(): void
   {
       $source = file_get_contents($this->sourceFile);
       $this->assertStringContainsString('add_dropdown_setting(', $source);
   }
   ```
   Run `composer test` and confirm green.

5. **Run the test suite** to verify nothing is broken:
   ```bash
   composer test
   ```

## Examples

**User says:** "Add a toggle to disable domain sales when out of stock."

**Actions taken:**
1. Confirmed `domains.settings => [__CLASS__, 'getSettings']` exists in `getHooks()`.
2. In `getSettings()`, retrieved `$settings = $event->getSubject()`.
3. Called:
   ```php
   $settings->add_dropdown_setting(
       self::$module,
       _('General'),
       'outofstock_domains',
       _('Out Of Stock Domains'),
       _('Enable/Disable Sales Of This Type'),
       $settings->get_setting('OUTOFSTOCK_DOMAINS'),
       ['0', '1'],
       ['No', 'Yes']
   );
   ```
4. Added `testSourceContainsDropdownSetting` in `tests/PluginTest.php`.
5. All tests pass.

**Result:** Admin UI shows a Yes/No dropdown under General settings for domain out-of-stock control.

## Common Issues

- **`Call to undefined method getSubject()`** — `$event` is not a `GenericEvent`. Confirm `use Symfony\Component\EventDispatcher\GenericEvent;` is imported and the hook dispatches a `GenericEvent`.
- **Setting always returns empty/null** — Key mismatch: `get_setting()` expects UPPERCASE (`'OUTOFSTOCK_DOMAINS'`), but you passed lowercase. Fix the `get_setting()` argument.
- **Tests fail with `Class 'MyAdmin\Settings' not found`** — `tests/bootstrap.php` only defines `PRORATE_BILLING` and resolves the autoloader; the `\MyAdmin\Settings` class is from the host app and not available in isolation. Mock it or test via source-string assertion as shown in Step 4.
- **Indentation errors flagged by Scrutinizer** — Use tabs, not spaces. Run `make php-cs-fixer` if available, or manually verify with `cat -A src/Plugin.php | grep '^ '` (spaces will show as `^I` vs spaces).
