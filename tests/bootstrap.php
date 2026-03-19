<?php

/**
 * PHPUnit bootstrap for detain/myadmin-domains-module.
 *
 * Defines framework constants and stubs required to load the Plugin class
 * without the full MyAdmin runtime.
 */

// The Plugin class references the PRORATE_BILLING constant in its $settings
// array initializer. It must be defined before the class is loaded.
if (!defined('PRORATE_BILLING')) {
    define('PRORATE_BILLING', 1);
}

// Autoload the package classes via Composer's PSR-4 mapping.
// When running from the package root the vendor dir may or may not exist;
// fall back to the parent project's autoloader.
$autoloaders = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../../autoload.php',
];

$loaded = false;
foreach ($autoloaders as $file) {
    if (file_exists($file)) {
        require_once $file;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    // Minimal PSR-4 registration so the test suite can still run
    spl_autoload_register(function ($class) {
        $prefix = 'Detain\\MyAdminDomains\\';
        if (strncmp($prefix, $class, strlen($prefix)) === 0) {
            $relative = substr($class, strlen($prefix));
            $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    });
}
