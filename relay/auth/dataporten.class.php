<?php

	namespace Relay\Auth;

	use Relay\Conf\Config;
	use Relay\Utils\Response;

	class Dataporten {

		private $config;

		function __construct() {
			// Exits on OPTION call
			$this->_checkCORS();
			// Dataporten username and pass (will exit on fail)
			$this->config = Config::getConfigFromFile(Config::get('auth')['dataporten']);
			// Exits on incorrect credentials
			$this->_checkGateKeeperCredentials();
			// Will exit if client does not have required scope
			if(!$this->_hasDataportenScope('admin')) {
				Response::error(403, $_SERVER["SERVER_PROTOCOL"] . ' Client does not have required scope to access this API.');
			};
		}

		private function _checkCORS() {
			// Access-Control headers are received during OPTIONS requests
			if(strcasecmp($_SERVER['REQUEST_METHOD'], "OPTIONS") === 0) {
				Response::result('CORS OK :-)');
			}
		}

		private function _checkGateKeeperCredentials() {
			if(empty($_SERVER["PHP_AUTH_USER"]) || empty($_SERVER["PHP_AUTH_PW"])) {
				Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' Unauthorized (Missing API Gatekeeper Credentials)');
			}

			// Gatekeeper. user/pwd is passed along by the Dataporten Gatekeeper and must matched that of the registered API:
			if((strcmp($_SERVER["PHP_AUTH_USER"], $this->config['user']) !== 0) ||
				(strcmp($_SERVER["PHP_AUTH_PW"], $this->config['passwd']) !== 0)
			) {
				// The status code will be set in the header
				Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' Unauthorized (Incorrect API Gatekeeper Credentials)');
			}
		}

		private function _hasDataportenScope($scope) {
			// Get the scope(s)
			$scopes = $_SERVER["HTTP_X_DATAPORTEN_SCOPES"];
			// Make array
			$scopes = explode(',', $scopes);

			// True/false
			return in_array($scope, $scopes);
		}

		public function userDisplayName() {
			// TODO!
			return NULL;
		}

		public function userEmail() {
			// TODO!
			return NULL;
		}

		public function isSuperAdmin() {
			return strcasecmp($this->userOrgId(), "uninett.no") === 0;
		}

		public function userOrgId() {
			$userOrg = explode('@', $this->userName());

			return $userOrg[1];
		}

		public function userName() {
			return $this->_getFeideUsername();
		}

		private function _getFeideUsername() {
			if(!isset($_SERVER["HTTP_X_DATAPORTEN_USERID_SEC"])) {
				Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' Unauthorized (user not found)');
			}

			$userIdSec = NULL;
			// Get the username(s)
			$userid = $_SERVER["HTTP_X_DATAPORTEN_USERID_SEC"];
			// Future proofing...
			if(!is_array($userid)) {
				// If not already an array, make it so. If it is not a comma separated list, we'll get a single array item.
				$userid = explode(',', $userid);
			}
			// Fish for a Feide username
			foreach($userid as $key => $value) {
				if(strpos($value, 'feide:') !== false) {
					$value     = explode(':', $value);
					$userIdSec = $value[1];
				}
			}
			// No Feide...
			if(!isset($userIdSec)) {
				Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' Unauthorized (user not found)');
			}

			// 'username@org.no'
			return $userIdSec;
		}

		public function userOrgName() {
			$userOrg = explode('@', $this->userName());
			$userOrg = explode('.', $userOrg[1]);

			return $userOrg[0];
		}

	}