<?php

	use Relay\Conf\Config;

	// Writes to development DB if true!!
	$devMode = true;
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
				'dataporten'        => $configRoot . 'dataporten_config.js',
				'dataporten_client' => $configRoot . 'dataporten_client_config.js',
				'relay_sql'         => $relayConfigPath
			],
			'utils'      => [
				'debug' => $debug
			]
		]);

