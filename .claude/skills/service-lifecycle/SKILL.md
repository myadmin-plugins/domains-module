---
name: service-lifecycle
description: Implements the setEnable/setReactivate/setDisable callback chain inside loadProcessing() for a MyAdmin plugin module. Use when user says 'add activation logic', 'handle enable', 'send email on reactivate', 'update service status', or 'implement lifecycle callbacks'. Do NOT use for settings panel changes (getSettings) or billing/invoice logic.
---
# service-lifecycle

## Critical

- All three callbacks (`setEnable`, `setReactivate`, `setDisable`) **must** be chained and terminated with `->register()` — omitting `->register()` silently skips the entire lifecycle.
- Never interpolate raw `$_GET`/`$_POST` into queries. Use `$settings['TABLE']`, `$settings['PREFIX']`, and values from `$serviceInfo` only (already sanitised by the framework).
- Always pass `__LINE__, __FILE__` as the 2nd and 3rd args to every `$db->query()` call.
- `$serviceInfo` keys are always `{PREFIX}_{column}` — e.g. `domain_id`, `domain_custid`, `domain_status`.

## Instructions

1. **Wire the hook** — confirm `loadProcessing` is registered in `getHooks()`:
   ```php
   public static function getHooks()
   {
       return [
           self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
           self::$module.'.settings'        => [__CLASS__, 'getSettings'],
       ];
   }
   ```
   Verify `self::$module` matches your module string (e.g. `'domains'`) before proceeding.

2. **Implement `loadProcessing()`** — receive the `$service` subject, set module/statuses, then chain all three lifecycle callbacks:
   ```php
   public static function loadProcessing(GenericEvent $event)
   {
       /** @var \ServiceHandler $service */
       $service = $event->getSubject();
       $service->setModule(self::$module)
           ->setActivationStatuses(['pending', 'pendapproval', 'active'])
           ->setEnable(function ($service) {
               // Step 3
           })->setReactivate(function ($service) {
               // Step 4
           })->setDisable(function () {
               // Step 5
           })->register();
   }
   ```

3. **`setEnable` body** — fires when a new service is activated:
   ```php
   $serviceTypes = run_event('get_service_types', false, self::$module);
   $serviceInfo  = $service->getServiceInfo();
   $settings     = get_module_settings(self::$module);
   $db           = get_module_db(self::$module);
   $db->query(
       "update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' "
       ."where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'",
       __LINE__, __FILE__
   );
   $GLOBALS['tf']->history->add(
       $settings['TABLE'], 'change_status', 'active',
       $serviceInfo[$settings['PREFIX'].'_id'],
       $serviceInfo[$settings['PREFIX'].'_custid']
   );
   $smarty = new \TFSmarty();
   $smarty->assign('{prefix}_hostname', $serviceInfo[$settings['PREFIX'].'_hostname']);
   $smarty->assign('{prefix}_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
   $email   = $smarty->fetch('email/admin/{module}_created.tpl');
   $subject = 'New '.ucfirst(self::$module).' Created '.$serviceInfo[$settings['TITLE_FIELD']];
   (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/{module}_created.tpl');
   ```
   Replace `{prefix}` with your module's `PREFIX` value and `{module}` with the module name.

4. **`setReactivate` body** — same DB update + history, different email template and subject:
   ```php
   // ... (same get_module_settings / get_module_db / DB update / history->add as Step 3)
   $email   = $smarty->fetch('email/admin/{module}_reactivated.tpl');
   $subject = $serviceInfo[$settings['TITLE_FIELD']].' '
            .$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']
            .' '.$settings['TBLNAME'].' Reactivated';
   (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/{module}_reactivated.tpl');
   ```

5. **`setDisable` body** — implement suspension logic or leave empty if not needed:
   ```php
   ->setDisable(function () {
       // add suspension DB update + history->add here if required
   })
   ```

6. **Add PHPUnit tests** in `tests/PluginTest.php` asserting source contains:
   - `->setEnable(`, `->setReactivate(`, `->setDisable(`, `->register()`
   - `history->add(` and `change_status`
   - `get_module_settings(`, `get_module_db(`, `run_event(`
   - Both email template paths

   Run: `composer test`

## Examples

**User says:** "Add activation logic for the SSL module"

**Actions taken:**
1. Confirm `ssl.load_processing` key exists in `getHooks()`.
2. Implement `loadProcessing()` with `setModule('ssl')` + `setActivationStatuses(['pending','active'])`.
3. `setEnable`: query `update ssl set ssl_status='active' where ssl_id='...'`, history add, TFSmarty fetch `email/admin/ssl_created.tpl`, `adminMail()`.
4. `setReactivate`: same query + history, fetch `email/admin/ssl_reactivated.tpl`.
5. `setDisable`: empty closure.
6. Chain ends with `->register()`.

**Result:** New SSL services transition to `active`, admin email sent, history row written.

## Common Issues

- **`->register()` missing → lifecycle never runs:** The chain must end with `->register()`. Check `src/Plugin.php` for the closing `->register();`.
- **`Undefined index: {prefix}_hostname`:** `$serviceInfo` key uses `{PREFIX}_` prefix from `$settings['PREFIX']`. Check `Plugin::$settings['PREFIX']` matches the actual DB column prefix.
- **`Class 'TFSmarty' not found`:** `TFSmarty` is a global class from the host app. Use the FQCN `new \TFSmarty()` (backslash prefix) to escape the module namespace.
- **Email not sent but no error:** Verify the `.tpl` file exists at `include/templates/email/admin/{module}_created.tpl` in the host MyAdmin installation.
- **`run_event` returns empty array:** The `get_service_types` event must be dispatched after the module is loaded. Confirm `Plugin::getHooks()` is registered before `loadProcessing` fires.
- **Tests fail with `Cannot instantiate interface ServiceHandler`:** Tests run without the host app — use source-level `file_get_contents` assertions (as in `tests/PluginTest.php`) rather than invoking `loadProcessing()` directly.
