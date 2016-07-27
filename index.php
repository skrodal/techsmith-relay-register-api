<?php

	// Indicates use of old DB
	$API_TEST_MODE = TRUE;
	// Dataporten API GK
	$dpConfigPath = '/var/www/etc/techsmith-relay-register/dataporten_config.js';
	// Dataporten Client
	$dpOauthConfigPath = '/var/www/etc/techsmith-relay-register/dataporten_oauth_config.js';
	// TEST CONFIG (USES OLD DB) : PROD CONFIG (USES CURRENT DB)
	$relayConfigPath = $API_TEST_MODE ? '/var/www/etc/techsmith-relay/relay_config_TEST.js' : '/var/www/etc/techsmith-relay/relay_config.js';
	// Remember to update .htacces as well. Same with a '/' at the end...
	$apiBasePath = '/api/techsmith-relay-register';
	//
	$BASE = dirname(__FILE__);

	// Result or error responses
	require_once($BASE . '/lib/response.class.php');
	// Checks CORS and pulls Dataporten info from headers
	require_once($BASE . '/lib/dataporten.class.php');
	$dataportenConfig = json_decode(file_get_contents($dpConfigPath), true);
	$dataporten       = new Dataporten($dataportenConfig);
	//
	require_once($BASE . '/lib/kind.class.php');
	$dataPortenOauthConfig = json_decode(file_get_contents($dpOauthConfigPath), true);
	$kind = new Kind($dataPortenOauthConfig);

	//  http://altorouter.com
	require_once($BASE . '/lib/router.class.php');
	$router = new Router();
	// $router->addMatchTypes(array('userlist' => '[0-9A-Za-z\[\]@.,%]++'));
	$router->setBasePath($apiBasePath);
	// Relay API
	require_once($BASE . '/lib/relay.class.php');
	$relayConfig = json_decode(file_get_contents($relayConfigPath), true);
	$relay       = new Relay($relayConfig);

// ---------------------- DEFINE ROUTES ----------------------


	/**
	 * GET all REST routes
	 */
	$router->map('GET', '/', function () {
		global $router;
		Response::result($router->getRoutes());
	}, 'Routes listing');


	$router->map('GET', '/kind/test/', function () {
		global $router, $kind;
		$kindRelayID = '1780362';
		$orgSubscribers = json_decode(json_encode($kind->callAPI('service/' . $kindRelayID . '/subscribers/')));
		Response::result($router->getRoutes());
	}, 'Kind test');


	/**
	 * GET If we're using old database (for testing)
	 */
	$router->map('GET', '/testmode/', function () {
		global $API_TEST_MODE;
		Response::result($API_TEST_MODE === true);
	}, 'Check if API is running in test mode (read/write to old DB)');

	/**
	 * GET TechSmith Relay version
	 */
	$router->map('GET', '/version/', function () {
		global $relay;
		Response::result($relay->getRelayVersion());
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
		global $apiBasePath;
		$requestUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

		return substr($requestUrl, strlen($apiBasePath));
	}