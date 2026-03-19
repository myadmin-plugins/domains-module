# Domain Registrations Module for MyAdmin

[![Tests](https://github.com/detain/myadmin-domains-module/actions/workflows/tests.yml/badge.svg)](https://github.com/detain/myadmin-domains-module/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/detain/myadmin-domains-module/version)](https://packagist.org/packages/detain/myadmin-domains-module)
[![Total Downloads](https://poser.pugx.org/detain/myadmin-domains-module/downloads)](https://packagist.org/packages/detain/myadmin-domains-module)
[![License](https://poser.pugx.org/detain/myadmin-domains-module/license)](https://packagist.org/packages/detain/myadmin-domains-module)

A MyAdmin plugin module that provides domain registration management capabilities. It integrates with the MyAdmin service lifecycle to handle domain provisioning, activation, reactivation, and suspension through the Symfony EventDispatcher system.

## Features

- Domain registration service lifecycle management (enable, reactivate, disable)
- Configurable billing with prorate support and customizable day offsets
- Automated email notifications for domain creation and reactivation events
- Admin settings panel with out-of-stock toggle for controlling domain sales
- Event-driven architecture using Symfony EventDispatcher hooks

## Installation

Install with Composer:

```sh
composer require detain/myadmin-domains-module
```

## Configuration

The module provides configurable settings through the `Plugin::$settings` array including service ID offsets, billing parameters, suspension thresholds, and database table mappings.

## Testing

Run the test suite with PHPUnit:

```sh
composer install
vendor/bin/phpunit
```

## License

The Domain Registrations Module for MyAdmin is licensed under the LGPL-v2.1 license.
