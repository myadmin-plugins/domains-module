---
name: plugin-hook
description: Adds a new Symfony EventDispatcher hook to the Plugin class in src/Plugin.php. Registers in getHooks(), implements a static handler accepting GenericEvent $event, accesses subject via $event->getSubject(). Use when user says 'add hook', 'new event', 'listen for event', or 'register handler'. Do NOT use for modifying existing hooks or for creating new plugin packages.
---
# plugin-hook

## Critical

- Event keys MUST be prefixed with `self::$module . '.'` — never hardcode the module name string in the key.
- Handler method MUST be `public static` and type-hint `GenericEvent $event`.
- `getHooks()` returns `[__CLASS__, 'methodName']` callables — never closures or instance methods.
- Use tabs for indentation (enforced by `.scrutinizer.yml`).
- After adding a hook, the `testGetHooksCount` test will fail — update it and add a corresponding signature test in `tests/PluginTest.php`.

## Instructions

1. **Choose an event name and method name.**
   - Event key format: `{module}.{action}` (e.g. `domains.cancel`, `domains.suspend`).
   - Method name: camelCase (e.g. `cancelService`, `suspendService`).
   - Verify no existing key in `getHooks()` matches before proceeding.

2. **Register the hook in `getHooks()` (`src/Plugin.php`).**
   Add the new entry to the returned array:
   ```php
   public static function getHooks()
   {
       return [
           self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
           self::$module.'.settings'        => [__CLASS__, 'getSettings'],
           self::$module.'.your_event'      => [__CLASS__, 'yourMethod'],
       ];
   }
   ```
   Verify the array key starts with `self::$module . '.'`.

3. **Implement the static handler method in `src/Plugin.php`.**
   Add after the last handler. Access the dispatched subject via `$event->getSubject()`:
   ```php
   /**
    * @param \Symfony\Component\EventDispatcher\GenericEvent $event
    */
   public static function yourMethod(GenericEvent $event)
   {
       $subject = $event->getSubject();
       $settings = get_module_settings(self::$module);
       $db = get_module_db(self::$module);
       // your logic here
       // DB queries: $db->query("...", __LINE__, __FILE__);
       // history: $GLOBALS['tf']->history->add($settings['TABLE'], 'action', 'status', $id, $custid);
   }
   ```
   Verify the method is `public static` and has exactly one parameter named `$event` typed `GenericEvent`.

4. **Add tests in `tests/PluginTest.php`.**

   a. Update `testGetHooksCount` to reflect the new count:
   ```php
   $this->assertCount(3, $hooks); // was 2
   ```

   b. Update `testGetHooksKeys` to include the new key:
   ```php
   $expectedKeys = [
       'domains.load_processing',
       'domains.settings',
       'domains.your_event',
   ];
   ```

   c. Add a signature test:
   ```php
   public function testYourMethodSignature(): void
   {
       $method = $this->reflection->getMethod('yourMethod');
       $this->assertTrue($method->isPublic());
       $this->assertTrue($method->isStatic());
       $params = $method->getParameters();
       $this->assertCount(1, $params);
       $this->assertSame('event', $params[0]->getName());
   }
   ```

5. **Run tests to verify.**
   ```bash
   composer test
   ```
   All tests must pass before the task is complete.

## Examples

**User says:** "Add a hook for domains.suspend that logs a suspension event."

**Actions taken:**

`getHooks()` updated:
```php
self::$module.'.suspend' => [__CLASS__, 'suspendService'],
```

New handler added to `src/Plugin.php`:
```php
/**
 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
 */
public static function suspendService(GenericEvent $event)
{
	$service = $event->getSubject();
	$settings = get_module_settings(self::$module);
	$db = get_module_db(self::$module);
	$serviceInfo = $service->getServiceInfo();
	$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='suspended' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
	$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'suspended', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
}
```

**Result:** Hook registered, handler implemented with DB update and history tracking, test count updated to 3.

## Common Issues

- **`testGetHooksCount` fails with "Failed asserting that 3 matches expected 2"**: You added a hook but forgot to update the count assertion in `testGetHooksCount`. Change `assertCount(2, $hooks)` to `assertCount(3, $hooks)`.
- **`testGetHooksKeys` fails**: You must add the new event key to the `$expectedKeys` array in that test — order matters, `assertSame` checks array order.
- **`testHookKeysArePrefixedWithModuleName` fails**: Your event key does not use `self::$module . '.'` — never hardcode `'domains.'` directly.
- **`testEventHandlersTypeHintGenericEvent` fails**: The new handler parameter is missing the `GenericEvent` type hint. Add `GenericEvent $event` as the parameter.
- **`testClassMethodCount` fails with count mismatch**: This test asserts exactly 4 public methods. Update it to `assertCount(5, $methods)` (or the new total) when adding a handler.
