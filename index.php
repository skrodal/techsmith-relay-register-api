<?php

	// Indicates use of old DB
	$API_TEST_MODE = true;

	$DATAPORTEN_CONFIG_PATH = '/var/www/etc/techsmith-relay-register/dataporten_config.js';

	if($API_TEST_MODE) {
		// TEST CONFIG (USES OLD DB)
		$RELAY_CONFIG_PATH = '/var/www/etc/techsmith-relay/relay_config_TEST.js';
	} else {
		// PROD CONFIG (USES CURRENT DB)
		$RELAY_CONFIG_PATH = '/var/www/etc/techsmith-relay/relay_config.js';
	}

	$API_BASE_PATH = '/api/techsmith-relay-register'; // Remember to update .htacces as well. Same with a '/' at the end...

	//
	$BASE = dirname(__FILE__);

	// Result or error responses
	require_once($BASE . '/lib/response.class.php');
	// Checks CORS and pulls Dataporten info from headers
	require_once($BASE . '/lib/dataporten.class.php');
	$dataporten_config = json_decode(file_get_contents($DATAPORTEN_CONFIG_PATH), true);
	$dataporten        = new Dataporten($dataporten_config);
	//  http://altorouter.com
	require_once($BASE . '/lib/router.class.php');
	$router = new Router();
	// $router->addMatchTypes(array('userlist' => '[0-9A-Za-z\[\]@.,%]++'));
	$router->setBasePath($API_BASE_PATH);
	// Relay API
	require_once($BASE . '/lib/relay.class.php');
	$relay_config  = json_decode(file_get_contents($RELAY_CONFIG_PATH), true);
	$relay         = new Relay($relay_config);
	$relay_version = $relay->getRelayVersion();

// ---------------------- DEFINE ROUTES ----------------------


	/**
	 * GET all REST routes
	 */
	$router->map('GET', '/', function () {
		global $router;
		Response::result($router->getRoutes());
	}, 'Routes listing');


	/**
	 * GET TechSmith Relay version
	 */
	$router->map('GET', '/version/', function () {
		global $relay, $relay_version;
		Response::result($relay_version);
	}, 'TechSmith Relay version');

	/**
	 * GET User account info (name, email, username)
	 */
	$router->map('GET', '/me/', function () {
		global $relay, $dataporten;
		Response::result($relay->getUserInfo($dataporten->getUserName()));
	}, 'User account info (false if no account)');

	/**
	 * GET Template
	 *
	 * $router->map('GET', '/PATH/[i:iD]/status/', function ($iD) {
	 * global $dataporten;
	 * Response::result(array('status' => true, 'data' => $dataporten->SOME_FUNCTION($iD)));
	 * }, 'DESCRIPTION OF ROUTE');
	 */

	/**
	 * Run account checkups with TechSmith Relay
	 */
	$router->map('POST', '/me/create/', function () {
		global $relay;
		Response::result($relay->createUser($_POST));
	}, 'Create a user.');


	// -------------------- UTILS -------------------- //

	// Restrict access to specified org
	function verifyOrgAccess() {
		global $dataporten;

		if(!$dataporten->isUserSuperAdmin()) {
			Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (USER is missing required access rights). ');
		}

		if(!$dataporten->hasAdminScope()) {
			Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (CLIENT is missing required scope). ');
		}
	}

	/**
	 * http://stackoverflow.com/questions/4861053/php-sanitize-values-of-a-array/4861211#4861211
	 */
	function sanitizeInput() {
		$_GET  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
		$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
	}

	// -------------------- ./UTILS -------------------- //


	// ---------------------- MATCH AND EXECUTE REQUESTED ROUTE ----------------------
	$match = $router->match();

	if($match && is_callable($match['target'])) {
		verifyOrgAccess();
		sanitizeInput();
		call_user_func_array($match['target'], $match['params']);
	} else {
		Response::error(404, $_SERVER["SERVER_PROTOCOL"] . " The requested resource [" . get_path_info() . "] could not be found.");
	}
	// ---------------------- /.MATCH AND EXECUTE REQUESTED ROUTE ----------------------


	function get_path_info() {
		global $API_BASE_PATH;
		$requestUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

		return substr($requestUrl, strlen($API_BASE_PATH));
	}