<?php

	namespace Relay\Auth;

	use Relay\Conf\Config;
	use Relay\Utils\Response;

	class Dataporten {

		private $config, $userInfo, $userGroups;

		function __construct() {
			// Exits on OPTION call
			$this->checkCORS();
			// Dataporten username and pass (will exit on fail)
			$this->config = Config::getConfigFromFile(Config::get('auth')['dataporten']);
			// Exits on incorrect credentials
			$this->checkGateKeeperCredentials();
			// Will exit if client does not have required scope
			if(!$this->hasDataportenScope('admin')) {
				Response::error(403, 'Tjenesten mangler nødvendige rettigheter (scope) for å kunne fortsette.');
			};
			// Endpoint /userinfo/
			$this->userInfo = $this->getUserInfo();
			// Endpoint /groups/me/groups/
			$this->userGroups = $this->getUserGroups();
		}

		private function checkCORS() {
			// Access-Control headers are received during OPTIONS requests
			if(strcasecmp($_SERVER['REQUEST_METHOD'], "OPTIONS") === 0) {
				Response::result('CORS OK :-)');
			}
		}

		private function checkGateKeeperCredentials() {
			if(empty($_SERVER["PHP_AUTH_USER"]) || empty($_SERVER["PHP_AUTH_PW"])) {
				Response::error(401, 'Unauthorized (Missing API Gatekeeper Credentials)');
			}

			// Gatekeeper. user/pwd is passed along by the Dataporten Gatekeeper and must matched that of the registered API:
			if((strcmp($_SERVER["PHP_AUTH_USER"], $this->config['user']) !== 0) ||
				(strcmp($_SERVER["PHP_AUTH_PW"], $this->config['passwd']) !== 0)
			) {
				// The status code will be set in the header
				Response::error(401, 'Unauthorized (Incorrect API Gatekeeper Credentials)');
			}
		}

		private function hasDataportenScope($scope) {
			// Get the scope(s)
			$scopes = $_SERVER["HTTP_X_DATAPORTEN_SCOPES"];
			// Make array
			$scopes = explode(',', $scopes);

			// True/false
			return in_array($scope, $scopes);
		}

		// Call /userinfo/ for name/email of user
		public function getUserInfo() {
			return $this->protectedRequest('https://auth.dataporten.no/userinfo')['user'];
		}
		public function getUserGroups() {
			return $this->protectedRequest('https://groups-api.dataporten.no/groups/me/groups?query=fc:org');
		}

		private function protectedRequest($url) {
			$token = $_SERVER['HTTP_X_DATAPORTEN_TOKEN'];
			if(empty($token)) {
				Response::error(403, "Tjenesten fikk ikke tilgang til å hente brukerinfo.");
			}

			$opts    = array(
				'http' => array(
					'method' => 'GET',
					'header' => "Authorization: Bearer " . $token,
				),
			);
			$context = stream_context_create($opts);
			$result  = file_get_contents($url, false, $context);


			$data = json_decode($result, true);
			if(empty($data)) {
				Response::error(204, "Tjenesten fant ikke noe informasjon om din (Feide) bruker.");
			}

			return $data;
		}

		/**
		 * What we want here is 'student' or 'employee'.
		 * @return string
		 */
		public function userAffiliation() {
			$affiliation = null;
			foreach($this->userGroups as $group){
				if($group['type'] === 'fc:org') {
					if(!empty($group['membership']['primaryAffiliation'])){
						return trim(strtolower($group['membership']['primaryAffiliation']));
					}
				}
			}
			//
			Response::error(401, "Tjenesten fikk ikke tilgang til din tilhørighet fra Feide ('primaryAffiliation').");
		}

		public function userDisplayName() {
			return $this->userInfo['name'];
		}

		public function userFirstName() {
			return strtok($this->userDisplayName(), " ");
		}

		public function userEmail() {
			return $this->userInfo['email'];
		}

		public function isSuperAdmin() {
			return strcasecmp($this->userOrgId(), "uninett.no") === 0;
		}

		public function userOrgId() {
			$userOrg = explode('@', $this->userName());

			return $userOrg[1];
		}

		public function userName() {
			return $this->getFeideUsername();
		}

		private function getFeideUsername() {
			if(!isset($_SERVER["HTTP_X_DATAPORTEN_USERID_SEC"])) {
				Response::error(401, 'Tjenesten ble nektet tilgang (fikk ikke tak i ditt brukernavn)');
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
				Response::error(401, 'Tjenesten ble nektet tilgang (fikk ikke tak i ditt brukernavn)');
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