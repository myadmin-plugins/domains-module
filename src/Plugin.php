<?php

namespace Detain\MyAdminDomains;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminDomains
 */
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

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
	}

	/**
	 * @return array
	 */
	public static function getHooks() {
		return [
			self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
			self::$module.'.settings' => [__CLASS__, 'getSettings']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function loadProcessing(GenericEvent $event) {
		$service = $event->getSubject();
		$service->setModule(self::$module)
			->setActivationStatuses(['pending', 'pendapproval', 'active'])
			->setEnable(function($service) {
				$serviceTypes = run_event('get_service_types', FALSE, self::$module);
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
				$GLOBALS['tf']->history->add(self::$module, 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
				$smarty = new \TFSmarty;
				$smarty->assign('domain_hostname', $serviceInfo[$settings['PREFIX'].'_hostname']);
				$smarty->assign('domain_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
				$email = $smarty->fetch('email/admin/domain_created.tpl');
				$subject = 'New Domain Created '.$db->Record[$settings['TITLE_FIELD']];
				$headers = '';
				$headers .= 'MIME-Version: 1.0'.EMAIL_NEWLINE;
				$headers .= 'Content-type: text/html; charset=UTF-8'.EMAIL_NEWLINE;
				$headers .= 'From: '.TITLE.' <'.EMAIL_FROM.'>'.EMAIL_NEWLINE;
				admin_mail($subject, $email, $headers, FALSE, 'admin/domain_created.tpl');
			})->setReactivate(function($service) {
				$serviceTypes = run_event('get_service_types', FALSE, self::$module);
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
				$GLOBALS['tf']->history->add(self::$module, 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
				$smarty = new \TFSmarty;
				$smarty->assign('domain_hostname', $serviceInfo[$settings['PREFIX'].'_hostname']);
				$smarty->assign('domain_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
				$email = $smarty->fetch('email/admin/domain_reactivated.tpl');
				$subject = $db->Record[$settings['TITLE_FIELD']].' '.$service_name.' '.$settings['TBLNAME'].' Re-Activated';
				$headers = '';
				$headers .= 'MIME-Version: 1.0'.EMAIL_NEWLINE;
				$headers .= 'Content-type: text/html; charset=UTF-8'.EMAIL_NEWLINE;
				$headers .= 'From: '.TITLE.' <'.EMAIL_FROM.'>'.EMAIL_NEWLINE;
				admin_mail($subject, $email, $headers, FALSE, 'admin/domain_reactivated.tpl');
			})->setDisable(function() {
			})->register();
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_dropdown_setting(self::$module, 'General', 'outofstock_domains', 'Out Of Stock Domains', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_DOMAINS'), ['0', '1'], ['No', 'Yes']);
	}
}
