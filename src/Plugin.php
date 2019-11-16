<?php

namespace Detain\MyAdminDomains;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminDomains
 */
class Plugin
{
	public static $name = 'Domain Registrations';
	public static $description = 'Allows selling of Domain Registrations Module';
	public static $help = '';
	public static $module = 'domains';
	public static $type = 'module';
	public static $settings = [
		'SERVICE_ID_OFFSET' => 10000,
		'USE_REPEAT_INVOICE' => true,
		'USE_PACKAGES' => true,
		'BILLING_DAYS_OFFSET' => 45,
		'IMGNAME' => 'domain.png',
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
	public function __construct()
	{
	}

	/**
	 * @return array
	 */
	public static function getHooks()
	{
		return [
			self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
			self::$module.'.settings' => [__CLASS__, 'getSettings']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function loadProcessing(GenericEvent $event)
	{
		/**
		 * @var \ServiceHandler $service
		 */
		$service = $event->getSubject();
		$service->setModule(self::$module)
			->setActivationStatuses(['pending', 'pendapproval', 'active'])
			->setEnable(function ($service) {
				$serviceTypes = run_event('get_service_types', false, self::$module);
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
				$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
				$smarty = new \TFSmarty;
				$smarty->assign('domain_hostname', $serviceInfo[$settings['PREFIX'].'_hostname']);
				$smarty->assign('domain_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
				$email = $smarty->fetch('email/admin/domain_created.tpl');
				$subject = 'New Domain Created '.$serviceInfo[$settings['TITLE_FIELD']];
				(new MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/domain_created.tpl');
			})->setReactivate(function ($service) {
				$serviceTypes = run_event('get_service_types', false, self::$module);
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
				$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
				$smarty = new \TFSmarty;
				$smarty->assign('domain_hostname', $serviceInfo[$settings['PREFIX'].'_hostname']);
				$smarty->assign('domain_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
				$email = $smarty->fetch('email/admin/domain_reactivated.tpl');
				$subject = $serviceInfo[$settings['TITLE_FIELD']].' '.$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$settings['TBLNAME'].' Reactivated';
				(new MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/domain_reactivated.tpl');
			})->setDisable(function () {
			})->register();
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event)
	{
		/**
		 * @var \MyAdmin\Settings $settings
		 **/
		$settings = $event->getSubject();
		$settings->add_dropdown_setting(self::$module, _('General'), 'outofstock_domains', _('Out Of Stock Domains'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOMAINS'), ['0', '1'], ['No', 'Yes']);
	}
}
