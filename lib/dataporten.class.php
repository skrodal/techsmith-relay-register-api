<?php

	/**
	 * @author Simon Skrødal
	 * @date   22/07/16
	 */
	class Dataporten {

		private $dataportenClient, $dataPortenClientConfig, $clientHasAdminScope, $userName, $userAffiliation, $userOrgName, $userOrgId, $config;

		function __construct() {
			global $dataportenConfig, $dataPortenClientConfig, $dataportenClient;
			// Exits on OPTION call
			$this->_checkCORS();
			//
			$this->config = $dataportenConfig;
			$this->dataportenClient = $dataportenClient;
			$this->dataPortenClientConfig = $dataPortenClientConfig;
			// Exits on incorrect credentials
			$this->_checkGateKeeperCredentials();
			//
			$this->clientHasAdminScope = $this->_hasDataportenScope('admin');
			$this->userName            = $this->_getFeideUsername(); // Exits if not found
			$this->userAffiliation     = $this->_getFeideUserAffiliation();
			$this->userOrgId           = $this->_getFeideUserOrgId($this->userName);
			$this->userOrgName         = $this->_getFeideUserOrgName($this->userName);
		}

		private function _checkCORS() {
			// Access-Control headers are received during OPTIONS requests
			if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
				Response::result('CORS OK :-)');
			}
		}

		private function _checkGateKeeperCredentials() {
			if(empty($_SERVER["PHP_AUTH_USER"]) || empty($_SERVER["PHP_AUTH_PW"])) {
				Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (Missing API Gatekeeper Credentials)');
			}

			// Gatekeeper. user/pwd is passed along by the Dataporten Gatekeeper and must matched that of the registered API:
			if($_SERVER["PHP_AUTH_USER"] !== $this->config['user'] || $_SERVER["PHP_AUTH_PW"] !== $this->config['passwd']) {
				// The status code will be set in the header
				Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (Incorrect API Gatekeeper Credentials)');
			}
		}

		private function _hasDataportenScope($scope) {
			if(!isset($_SERVER["HTTP_X_DATAPORTEN_SCOPES"])) {
				Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (missing client scope)');
			}
			// Get the scope(s)
			$scopes = $_SERVER["HTTP_X_DATAPORTEN_SCOPES"];
			// Make array
			$scopes = explode(',', $scopes);

			// True/false
			return in_array($scope, $scopes);
		}

		/**
		 * Gets the feide username (if present) from the Gatekeeper via HTTP_X_DATAPORTEN_USERID_SEC.
		 *
		 * It should only return a single string, 'feide:user@org.no', but future development might introduce
		 * a comma-separated or array representation of more than one username
		 * (e.g. "openid:user@org.no, feide:user@org.no")
		 *
		 * This function takes care of all of these cases.
		 */
		private function _getFeideUsername() {
			if(!isset($_SERVER["HTTP_X_DATAPORTEN_USERID_SEC"])) {
				Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (user not found)');
			}

			$userIdSec = NULL;
			// Get the username(s)
			$userid = $_SERVER["HTTP_X_DATAPORTEN_USERID_SEC"];
			// Future proofing...
			if(!is_array($userid)) {
				// If not already an array, make it so. If it is not a comma separated list, we'll get a single array item.
				$userid = explode(',', $userid);
			}

			foreach($userid as $key => $value) {
				if(strpos($value, 'feide:') !== false) {
					$value     = explode(':', $value);
					$userIdSec = $value[1];
				}
			}

			if(!isset($userIdSec)) {
				Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (*Feide* user not found)');
			}

			// Either null or 'username@org.no'
			return $userIdSec;
		}

		private function _getFeideUserAffiliation() {

		}

		private function _getFeideUserOrgId($userName) {
			$orgId = explode('@', $userName); // Split username@org.no
			return $orgId[1]; // org.no
		}

		private function _getFeideUserOrgName($userName) {
			$orgId   = explode('@', $userName); // Split username@org.no
			$orgName = explode('.', $orgId); // Split org.no
			return $orgName[0]; // org
		}

		public function getUserName() {
			return $this->userName;
		}

		public function hasAdminScope() {
			return $this->clientHasAdminScope;
		}

		public function getUserOrgId() {
			return $this->userOrgId;
		}

		public function getUserOrgName() {
			return $this->userOrgName;
		}

	}