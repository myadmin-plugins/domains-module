<?php
/* TODO:
 - service type, category, and services  adding
 - dealing with the SERVICE_TYPES_domains define
 - add way to call/hook into install/uninstall
*/
return [
	'name' => 'Domain Registrations Module',
	'description' => 'Allows selling of Domain Registrations Module',
	'help' => '',
	'module' => 'domains',
	'author' => 'detain@interserver.net',
	'home' => 'https://github.com/detain/myadmin-domains-module',
	'repo' => 'https://github.com/detain/myadmin-domains-module',
	'version' => '1.0.0',
	'type' => 'module',
	'hooks' => [
		'domains.load_processing' => ['Detain\MyAdminDomains\Plugin', 'Load'],
		'domains.settings' => ['Detain\MyAdminDomains\Plugin', 'Settings'],
		/* 'function.requirements' => ['Detain\MyAdminDomains\Plugin', 'Requirements'],
		'domains.activate' => ['Detain\MyAdminDomains\Plugin', 'Activate'],
		'domains.change_ip' => ['Detain\MyAdminDomains\Plugin', 'ChangeIp'],
		'ui.menu' => ['Detain\MyAdminDomains\Plugin', 'Menu'] */
	],
];
