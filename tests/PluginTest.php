<?php

namespace Detain\MyAdminDomains\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests for the Detain\MyAdminDomains\Plugin class.
 *
 * Because the Plugin class is tightly coupled to a large MyAdmin framework
 * (global functions, MyAdmin\App::tf(), database access, Smarty templates, etc.),
 * we focus on testing what can be verified without that runtime:
 *   - Class structure via ReflectionClass
 *   - Static property values (constants / config)
 *   - Pure-logic method: getHooks()
 *   - Method signatures for event handlers
 *   - Source-level checks via file_get_contents for patterns
 */
class PluginTest extends TestCase
{
    /**
     * @var ReflectionClass
     */
    private $reflection;

    /**
     * @var string Absolute path to Plugin.php source file
     */
    private $sourceFile;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\Detain\MyAdminDomains\Plugin::class);
        $this->sourceFile = dirname(__DIR__) . '/src/Plugin.php';
    }

    // ------------------------------------------------------------------
    //  Class structure
    // ------------------------------------------------------------------

    /**
     * Tests that Plugin resides in the expected namespace.
     *
     * Ensures the PSR-4 autoloading declared in composer.json maps correctly.
     */
    public function testClassExistsInCorrectNamespace(): void
    {
        $this->assertTrue(class_exists(\Detain\MyAdminDomains\Plugin::class));
        $this->assertSame('Detain\\MyAdminDomains', $this->reflection->getNamespaceName());
    }

    /**
     * Tests that the Plugin class is not abstract and can be instantiated.
     *
     * The constructor is intentionally empty, but the class must remain concrete
     * so the MyAdmin plugin loader can instantiate it.
     */
    public function testClassIsInstantiable(): void
    {
        $this->assertTrue($this->reflection->isInstantiable());
    }

    /**
     * Tests that Plugin does not extend any parent class.
     *
     * This is a standalone plugin; it should not inherit from a base class.
     */
    public function testClassHasNoParent(): void
    {
        $this->assertFalse($this->reflection->getParentClass());
    }

    /**
     * Tests that Plugin implements no interfaces.
     *
     * MyAdmin plugins are duck-typed via event hooks, not via interfaces.
     */
    public function testClassImplementsNoInterfaces(): void
    {
        $this->assertEmpty($this->reflection->getInterfaceNames());
    }

    // ------------------------------------------------------------------
    //  Static properties
    // ------------------------------------------------------------------

    /**
     * Tests the $name static property.
     *
     * This value is used in log messages and UI labels throughout the plugin.
     */
    public function testNameProperty(): void
    {
        $this->assertSame('Domain Registrations', \Detain\MyAdminDomains\Plugin::$name);
    }

    /**
     * Tests the $description static property.
     *
     * Displayed in the admin module listing.
     */
    public function testDescriptionProperty(): void
    {
        $this->assertSame('Allows selling of Domain Registrations Module', \Detain\MyAdminDomains\Plugin::$description);
    }

    /**
     * Tests the $help static property is an empty string by default.
     *
     * No help text has been written for this module yet.
     */
    public function testHelpPropertyIsEmpty(): void
    {
        $this->assertSame('', \Detain\MyAdminDomains\Plugin::$help);
    }

    /**
     * Tests the $module static property equals 'domains'.
     *
     * This identifier is used as an event namespace prefix and DB module key.
     */
    public function testModuleProperty(): void
    {
        $this->assertSame('domains', \Detain\MyAdminDomains\Plugin::$module);
    }

    /**
     * Tests the $type static property equals 'module'.
     *
     * Distinguishes this plugin type from 'service' or 'addon' types.
     */
    public function testTypeProperty(): void
    {
        $this->assertSame('module', \Detain\MyAdminDomains\Plugin::$type);
    }

    // ------------------------------------------------------------------
    //  Settings array
    // ------------------------------------------------------------------

    /**
     * Tests that $settings contains all required configuration keys.
     *
     * These keys drive module behaviour: billing, DB table references, UI, etc.
     */
    public function testSettingsContainsRequiredKeys(): void
    {
        $expected = [
            'SERVICE_ID_OFFSET',
            'USE_REPEAT_INVOICE',
            'USE_PACKAGES',
            'BILLING_DAYS_OFFSET',
            'IMGNAME',
            'REPEAT_BILLING_METHOD',
            'DELETE_PENDING_DAYS',
            'SUSPEND_DAYS',
            'SUSPEND_WARNING_DAYS',
            'TITLE',
            'MENUNAME',
            'EMAIL_FROM',
            'TBLNAME',
            'TABLE',
            'TITLE_FIELD',
            'PREFIX',
        ];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, \Detain\MyAdminDomains\Plugin::$settings, "Missing settings key: {$key}");
        }
    }

    /**
     * Tests specific scalar values inside $settings.
     *
     * Verifies billing offsets, day thresholds, and naming conventions
     * that other modules depend on.
     */
    public function testSettingsValues(): void
    {
        $s = \Detain\MyAdminDomains\Plugin::$settings;

        $this->assertSame(10000, $s['SERVICE_ID_OFFSET']);
        $this->assertTrue($s['USE_REPEAT_INVOICE']);
        $this->assertTrue($s['USE_PACKAGES']);
        $this->assertSame(45, $s['BILLING_DAYS_OFFSET']);
        $this->assertSame('domain.png', $s['IMGNAME']);
        $this->assertSame(45, $s['DELETE_PENDING_DAYS']);
        $this->assertSame(14, $s['SUSPEND_DAYS']);
        $this->assertSame(7, $s['SUSPEND_WARNING_DAYS']);
        $this->assertSame('Domain Registrations', $s['TITLE']);
        $this->assertSame('Domains', $s['MENUNAME']);
        $this->assertSame('support@interserver.net', $s['EMAIL_FROM']);
        $this->assertSame('Domains', $s['TBLNAME']);
        $this->assertSame('domains', $s['TABLE']);
        $this->assertSame('domain_hostname', $s['TITLE_FIELD']);
        $this->assertSame('domain', $s['PREFIX']);
    }

    /**
     * Tests that the REPEAT_BILLING_METHOD setting references the PRORATE_BILLING constant.
     *
     * We verify it exists in the source code via static analysis.
     */
    public function testSettingsReferencesPRORATE_BILLING(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('PRORATE_BILLING', $source);
    }

    /**
     * Tests that the $settings array has exactly 16 entries.
     *
     * Guards against accidentally adding or removing a settings key.
     */
    public function testSettingsArrayCount(): void
    {
        $this->assertCount(16, \Detain\MyAdminDomains\Plugin::$settings);
    }

    // ------------------------------------------------------------------
    //  getHooks()
    // ------------------------------------------------------------------

    /**
     * Tests that getHooks() returns an array.
     *
     * The Symfony EventDispatcher subscriber loader expects an array of
     * event-name => callable pairs.
     */
    public function testGetHooksReturnsArray(): void
    {
        $hooks = \Detain\MyAdminDomains\Plugin::getHooks();
        $this->assertIsArray($hooks);
    }

    /**
     * Tests that getHooks() returns exactly the two expected event names.
     *
     * These are the lifecycle events the plugin subscribes to.
     */
    public function testGetHooksKeys(): void
    {
        $hooks = \Detain\MyAdminDomains\Plugin::getHooks();
        $expectedKeys = [
            'domains.load_processing',
            'domains.settings',
        ];
        $this->assertSame($expectedKeys, array_keys($hooks));
    }

    /**
     * Tests that each hook value is a valid static callable.
     *
     * Each value should be [ClassName, methodName] and the method must exist.
     */
    public function testGetHooksValuesAreCallable(): void
    {
        $hooks = \Detain\MyAdminDomains\Plugin::getHooks();

        foreach ($hooks as $event => $callable) {
            $this->assertIsArray($callable, "Hook for '{$event}' should be an array");
            $this->assertCount(2, $callable, "Hook for '{$event}' should have [class, method]");
            $this->assertSame(\Detain\MyAdminDomains\Plugin::class, $callable[0]);
            $this->assertTrue(
                $this->reflection->hasMethod($callable[1]),
                "Method {$callable[1]} referenced in hook '{$event}' does not exist"
            );
        }
    }

    /**
     * Tests that getHooks values map to the correct methods.
     *
     * Ensures the wiring between event names and handler methods is correct.
     */
    public function testGetHooksMethodMapping(): void
    {
        $hooks = \Detain\MyAdminDomains\Plugin::getHooks();

        $this->assertSame('loadProcessing', $hooks['domains.load_processing'][1]);
        $this->assertSame('getSettings', $hooks['domains.settings'][1]);
    }

    /**
     * Tests that getHooks() returns exactly 2 hooks.
     *
     * The domains plugin registers load_processing and settings hooks.
     */
    public function testGetHooksCount(): void
    {
        $hooks = \Detain\MyAdminDomains\Plugin::getHooks();
        $this->assertCount(2, $hooks);
    }

    // ------------------------------------------------------------------
    //  Method signatures and visibility
    // ------------------------------------------------------------------

    /**
     * Tests that the constructor is public.
     *
     * Required for the plugin loader to instantiate the class.
     */
    public function testConstructorIsPublic(): void
    {
        $ctor = $this->reflection->getConstructor();
        $this->assertNotNull($ctor);
        $this->assertTrue($ctor->isPublic());
    }

    /**
     * Tests that the constructor takes no parameters.
     *
     * Plugin instantiation must be zero-argument.
     */
    public function testConstructorHasNoParameters(): void
    {
        $ctor = $this->reflection->getConstructor();
        $this->assertCount(0, $ctor->getParameters());
    }

    /**
     * Tests that getHooks() is public and static.
     *
     * The plugin registry calls Plugin::getHooks() statically.
     */
    public function testGetHooksIsPublicStatic(): void
    {
        $method = $this->reflection->getMethod('getHooks');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    /**
     * Tests that getHooks() has no required parameters.
     *
     * It should be callable without arguments.
     */
    public function testGetHooksHasNoParameters(): void
    {
        $method = $this->reflection->getMethod('getHooks');
        $this->assertCount(0, $method->getParameters());
    }

    /**
     * Tests that loadProcessing() is public and static with one parameter.
     *
     * This is the main service-lifecycle handler.
     */
    public function testLoadProcessingSignature(): void
    {
        $method = $this->reflection->getMethod('loadProcessing');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('event', $params[0]->getName());
    }

    /**
     * Tests that getSettings() is public and static with one parameter.
     *
     * Adds admin settings UI fields when dispatched.
     */
    public function testGetSettingsSignature(): void
    {
        $method = $this->reflection->getMethod('getSettings');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('event', $params[0]->getName());
    }

    /**
     * Tests that event handler parameters type-hint GenericEvent.
     *
     * Ensures Symfony EventDispatcher compatibility.
     */
    public function testEventHandlersTypeHintGenericEvent(): void
    {
        $handlers = ['loadProcessing', 'getSettings'];
        foreach ($handlers as $name) {
            $method = $this->reflection->getMethod($name);
            $param = $method->getParameters()[0];
            $type = $param->getType();
            $this->assertNotNull($type, "Parameter of {$name}() should have a type hint");
            $this->assertSame(
                'Symfony\\Component\\EventDispatcher\\GenericEvent',
                $type->getName(),
                "{$name}() parameter should type-hint GenericEvent"
            );
        }
    }

    // ------------------------------------------------------------------
    //  Static property declarations via Reflection
    // ------------------------------------------------------------------

    /**
     * Tests that all six expected static properties are declared.
     *
     * Catches accidental removal of required properties.
     */
    public function testAllStaticPropertiesExist(): void
    {
        $expected = ['name', 'description', 'help', 'module', 'type', 'settings'];
        foreach ($expected as $prop) {
            $this->assertTrue(
                $this->reflection->hasProperty($prop),
                "Static property \${$prop} should exist"
            );
            $rp = $this->reflection->getProperty($prop);
            $this->assertTrue($rp->isStatic(), "\${$prop} should be static");
            $this->assertTrue($rp->isPublic(), "\${$prop} should be public");
        }
    }

    // ------------------------------------------------------------------
    //  Source-level / static analysis tests
    // ------------------------------------------------------------------

    /**
     * Tests that the source file is valid PHP by tokenizing it.
     *
     * Catches syntax errors that would not surface until runtime.
     */
    public function testSourceFileIsValidPhp(): void
    {
        $source = file_get_contents($this->sourceFile);
        $tokens = token_get_all($source);
        $this->assertNotEmpty($tokens);
        // First meaningful token should be T_OPEN_TAG
        $this->assertSame(T_OPEN_TAG, $tokens[0][0]);
    }

    /**
     * Tests that the source uses the correct namespace declaration.
     *
     * A mismatched namespace would break autoloading.
     */
    public function testSourceHasCorrectNamespace(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('namespace Detain\\MyAdminDomains;', $source);
    }

    /**
     * Tests that the source imports GenericEvent from the Symfony package.
     *
     * Without this use-statement the type hints would fail at runtime.
     */
    public function testSourceImportsGenericEvent(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('use Symfony\\Component\\EventDispatcher\\GenericEvent;', $source);
    }

    /**
     * Tests that the source contains all expected service lifecycle methods.
     *
     * setEnable, setReactivate, setDisable are the three lifecycle
     * callbacks the module registers via ServiceHandler.
     */
    public function testSourceContainsServiceLifecycleMethods(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('->setEnable(', $source);
        $this->assertStringContainsString('->setReactivate(', $source);
        $this->assertStringContainsString('->setDisable(', $source);
        $this->assertStringContainsString('->register()', $source);
    }

    /**
     * Tests that the source references email template paths used by the plugin.
     *
     * These templates must exist in the MyAdmin template directory at runtime.
     */
    public function testSourceReferencesEmailTemplates(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('email/admin/domain_created.tpl', $source);
        $this->assertStringContainsString('email/admin/domain_reactivated.tpl', $source);
    }

    /**
     * Tests that getSettings references the outofstock setting.
     *
     * Controls whether domain services are available for sale.
     */
    public function testSourceContainsOutOfStockSetting(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('outofstock_domains', $source);
        $this->assertStringContainsString('OUTOFSTOCK_DOMAINS', $source);
    }

    /**
     * Tests that the source references activation statuses.
     *
     * The loadProcessing method sets activation statuses for the service handler.
     */
    public function testSourceContainsActivationStatuses(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('pending', $source);
        $this->assertStringContainsString('pendapproval', $source);
        $this->assertStringContainsString('active', $source);
    }

    /**
     * Tests that the source references the setModule call with the module name.
     *
     * The service handler must be configured with the correct module identifier.
     */
    public function testSourceContainsSetModuleCall(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('->setModule(', $source);
        $this->assertStringContainsString('->setActivationStatuses(', $source);
    }

    /**
     * Tests that the source references domain_hostname in template assignments.
     *
     * The domain hostname is assigned to Smarty templates for email notifications.
     */
    public function testSourceReferencesDomainHostname(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('domain_hostname', $source);
        $this->assertStringContainsString('domain_name', $source);
    }

    /**
     * Tests that the source references MyAdmin Mail class.
     *
     * Email notifications use the MyAdmin Mail utility.
     */
    public function testSourceReferencesMailClass(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('\\MyAdmin\\Mail()', $source);
        $this->assertStringContainsString('adminMail(', $source);
    }

    /**
     * Tests that the class declares exactly the expected number of methods.
     *
     * Guards against accidental removal or unexpected additions.
     * __construct, getHooks, loadProcessing, getSettings = 4
     */
    public function testClassMethodCount(): void
    {
        $methods = $this->reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        $this->assertCount(4, $methods);
    }

    /**
     * Tests that constructing the Plugin does not throw.
     *
     * The constructor body is empty; this confirms no side effects.
     */
    public function testConstructorDoesNotThrow(): void
    {
        $plugin = new \Detain\MyAdminDomains\Plugin();
        $this->assertInstanceOf(\Detain\MyAdminDomains\Plugin::class, $plugin);
    }

    /**
     * Tests that hook keys use the module name as prefix.
     *
     * Convention: all hook event names must start with the module identifier.
     */
    public function testHookKeysArePrefixedWithModuleName(): void
    {
        $hooks = \Detain\MyAdminDomains\Plugin::getHooks();
        foreach (array_keys($hooks) as $key) {
            $this->assertStringStartsWith(
                \Detain\MyAdminDomains\Plugin::$module . '.',
                $key,
                "Hook key '{$key}' should be prefixed with module name"
            );
        }
    }

    /**
     * Tests that source file uses the expected class name declaration.
     *
     * A mismatch between filename and class name would break PSR-4 autoloading.
     */
    public function testSourceDeclaresClassPlugin(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertMatchesRegularExpression('/^class\s+Plugin\s*$/m', $source);
    }

    /**
     * Tests that the source file path matches the PSR-4 convention.
     *
     * The file should be at src/Plugin.php relative to the package root.
     */
    public function testSourceFileExistsAtExpectedPath(): void
    {
        $this->assertFileExists($this->sourceFile);
    }

    /**
     * Tests that the EMAIL_FROM setting is a syntactically valid email address.
     *
     * Prevents misconfigured sender addresses.
     */
    public function testEmailFromIsValidEmail(): void
    {
        $email = \Detain\MyAdminDomains\Plugin::$settings['EMAIL_FROM'];
        $this->assertNotFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
    }

    /**
     * Tests that numeric settings have correct types.
     *
     * All day-count and offset settings must be integers.
     */
    public function testNumericSettingsAreIntegers(): void
    {
        $intKeys = ['SERVICE_ID_OFFSET', 'BILLING_DAYS_OFFSET', 'DELETE_PENDING_DAYS', 'SUSPEND_DAYS', 'SUSPEND_WARNING_DAYS'];
        foreach ($intKeys as $key) {
            $this->assertIsInt(
                \Detain\MyAdminDomains\Plugin::$settings[$key],
                "Settings key '{$key}' should be an integer"
            );
        }
    }

    /**
     * Tests that boolean settings have correct types.
     *
     * USE_REPEAT_INVOICE and USE_PACKAGES must be boolean.
     */
    public function testBooleanSettingsAreBool(): void
    {
        $boolKeys = ['USE_REPEAT_INVOICE', 'USE_PACKAGES'];
        foreach ($boolKeys as $key) {
            $this->assertIsBool(
                \Detain\MyAdminDomains\Plugin::$settings[$key],
                "Settings key '{$key}' should be a boolean"
            );
        }
    }

    /**
     * Tests that string settings have correct types and are non-empty.
     *
     * These settings are used as table/column names and must not be blank.
     */
    public function testStringSettingsAreNonEmptyStrings(): void
    {
        $strKeys = ['IMGNAME', 'TITLE', 'MENUNAME', 'EMAIL_FROM', 'TBLNAME', 'TABLE', 'TITLE_FIELD', 'PREFIX'];
        foreach ($strKeys as $key) {
            $val = \Detain\MyAdminDomains\Plugin::$settings[$key];
            $this->assertIsString($val, "Settings key '{$key}' should be a string");
            $this->assertNotEmpty($val, "Settings key '{$key}' should not be empty");
        }
    }

    /**
     * Tests that SUSPEND_WARNING_DAYS is less than SUSPEND_DAYS.
     *
     * The warning must come before the actual suspension.
     */
    public function testSuspendWarningIsBeforeSuspendDays(): void
    {
        $s = \Detain\MyAdminDomains\Plugin::$settings;
        $this->assertLessThan($s['SUSPEND_DAYS'], $s['SUSPEND_WARNING_DAYS']);
    }

    /**
     * Tests that SUSPEND_DAYS is less than DELETE_PENDING_DAYS.
     *
     * Suspension must occur before deletion.
     */
    public function testSuspendDaysIsBeforeDeletePendingDays(): void
    {
        $s = \Detain\MyAdminDomains\Plugin::$settings;
        $this->assertLessThan($s['DELETE_PENDING_DAYS'], $s['SUSPEND_DAYS']);
    }

    /**
     * Tests that the source references TFSmarty for template rendering.
     *
     * Email notifications are rendered via TFSmarty templates.
     */
    public function testSourceReferencesTFSmarty(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('TFSmarty', $source);
    }

    /**
     * Tests that the source references the history tracking mechanism.
     *
     * Status changes are logged via the history add method.
     */
    public function testSourceReferencesHistoryTracking(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('history->add(', $source);
        $this->assertStringContainsString('change_status', $source);
    }

    /**
     * Tests that the source references database query operations.
     *
     * The enable and reactivate handlers update domain status via SQL.
     */
    public function testSourceReferencesDatabaseQueries(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('->query(', $source);
        $this->assertStringContainsString('update', $source);
    }

    /**
     * Tests that the source references get_module_settings and get_module_db.
     *
     * These framework functions provide module configuration and database access.
     */
    public function testSourceReferencesFrameworkFunctions(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('get_module_settings(', $source);
        $this->assertStringContainsString('get_module_db(', $source);
        $this->assertStringContainsString('run_event(', $source);
    }

    /**
     * Tests that the source contains the add_dropdown_setting call in getSettings.
     *
     * This is how the plugin registers its admin UI settings.
     */
    public function testSourceContainsDropdownSetting(): void
    {
        $source = file_get_contents($this->sourceFile);
        $this->assertStringContainsString('add_dropdown_setting(', $source);
    }

    /**
     * Tests that the SERVICE_ID_OFFSET is a positive integer.
     *
     * The offset must be positive to avoid ID collisions with other modules.
     */
    public function testServiceIdOffsetIsPositive(): void
    {
        $this->assertGreaterThan(0, \Detain\MyAdminDomains\Plugin::$settings['SERVICE_ID_OFFSET']);
    }

    /**
     * Tests that the PREFIX setting matches the TABLE convention.
     *
     * The PREFIX should be the singular form related to the TABLE name.
     */
    public function testPrefixIsRelatedToTable(): void
    {
        $s = \Detain\MyAdminDomains\Plugin::$settings;
        $this->assertStringStartsWith($s['PREFIX'], $s['TABLE']);
    }

    /**
     * Tests that the TITLE_FIELD starts with the PREFIX.
     *
     * Column naming convention requires the prefix as part of the field name.
     */
    public function testTitleFieldStartsWithPrefix(): void
    {
        $s = \Detain\MyAdminDomains\Plugin::$settings;
        $this->assertStringStartsWith($s['PREFIX'], $s['TITLE_FIELD']);
    }
}
