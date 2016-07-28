<?php

	use RelayRegister\Conf\Config;

	// Writes to development DB if true!!
	$devMode = TRUE;
	//
	$configRoot = '/var/www/etc/techsmith-relay-register/';

	// TEST CONFIG (USES OLD DB) : PROD CONFIG (USES CURRENT DB)
	$relayConfigPath = $devMode ?
		'/var/www/etc/techsmith-relay/relay_config_TEST.js' :
		'/var/www/etc/techsmith-relay/relay_config.js';


	Config::add(
		[
			'altoRouter' => [
				// Remember to update .htacces as well:
				'api_base_path' => '/api/techsmith-relay'
			],
			'auth'   => [
				'dataporten'        => $configRoot . 'dataporten_config.js',
				'dataporten_client' => $configRoot . 'dataporten_client_config.js',
				'relay_sql'         => $relayConfigPath
			],
			'utils'  => [
				'debug' => true
			]
		]);

