<?php

	use Relay\Conf\Config;

	// Writes to development DB if true!!
	$devMode = false;
	// Error logging
	$debug = false;
	// Remember to update .htacces as well:
	$apiBasePath = '/api/techsmith-relay-register';
	// Path to config files specific to this API only
	$configRoot = '/var/www/etc/techsmith-relay-register/';
	// These configs are shared by other APIs as well. Switch between dev/prod
	$relayConfigPath = $devMode ?
		'/var/www/etc/techsmith-relay/relay_config_TEST.js' :
		'/var/www/etc/techsmith-relay/relay_config.js';


	// Shouldn't need to change anything below
	Config::add(
		[
			'settings'  => [
				'dev_mode'  => $devMode
			],
			'altoRouter' => [
				'api_base_path' => $apiBasePath
			],
			'auth'       => [
				'dataporten'        => $configRoot . 'dataporten_config.json',
				//'dataporten_client' => $configRoot . 'dataporten_client_config.json',
				'relay_sql'         => $relayConfigPath,
				'subscribers_mysql' => $configRoot . 'subscribers_mysql_config.json',
				'mail'              => $configRoot . 'mail_config.json'
			],
			'utils'      => [
				'debug' => $debug
			]
		]);

