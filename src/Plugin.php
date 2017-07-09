<?php

namespace Detain\MyAdminDomains;

use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public static $name = 'Domain Registrations';
	public static $description = 'Allows selling of Domain Registrations Module';
	public static $help = '';
	public static $module = 'domains';
	public static $type = 'module';
	public static $settings = [
		'SERVICE_ID_OFFSET' => 10000,
		'USE_REPEAT_INVOICE' => TRUE,
		'USE_PACKAGES' => TRUE,
		'BILLING_DAYS_OFFSET' => 45,
		'IMGNAME' => 'server_add_48.png',
		'REPEAT_BILLING_METHOD' => PRORATE_BILLING,
		'DELETE_PENDING_DAYS' => 45,
		'SUSPEND_DAYS' => 14,
		'SUSPEND_WARNING_DAYS' => 7,
		'TITLE' => 'Domain Registrations',
		'MENUNAME' => 'Domains',
		'EMAIL_FROM' => 'support@interserver.net',
		'TBLNAME' => 'Domains',
		'TABLE' => 'domains',
		'TITLE_FIELD' => 'domain_hostname',
		'PREFIX' => 'domain'];


	public function __construct() {
	}

	public static function getHooks() {
		return [
			self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
		];
	}

	public static function loadProcessing(GenericEvent $event) {

	}

	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_dropdown_setting(self::$module, 'General', 'outofstock_domains', 'Out Of Stock Domains', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_DOMAINS'), array('0', '1'), array('No', 'Yes'));
	}
}
