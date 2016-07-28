<?php

	namespace Relay\Auth;

	session_start();

	use Relay\Conf\Config;
	use Relay\Utils\Response;

	/**
	 * Dataporten OAuth Client Credentials Grant
	 *
	 * This class facilitates getting a `TOKEN` pertaining to a Dataporten API GK which will allow
	 * a client to communicate via the API without the need for user authentication (only the
	 * client authenticates with the GK)
	 *
	 * @author Simon Skrødal
	 */
	class DataportenClient {

		private $ep_token = 'https://auth.dataporten.no/oauth/token/';
		private $token = NULL;
		private $config;

		//
		function __construct() {
			$this->config = Config::getConfigFromFile(Config::get('auth')['dataporten_client']);
		}

		public function getConfig() {
			return $this->config;
		}

		public function reset() {
			$this->setToken(NULL, NULL);
		}

		//

		public function get($url) {
			// Gets/ (or sets) a token
			$this->checkTokenValidity();

			return $this->protectedRequest($url);
		}

		// Make a call to the token endpoint with client credentials

		/**
		 * Ensures that a token exists for this sessionb and that it is valid (not expired) - create new if any issues.
		 */
		protected function checkTokenValidity() {
			// Missing token info
			if(empty($_SESSION['token']) || empty($_SESSION['token_expires_in']) || empty($_SESSION['token_created'])) {
				// error_log('Missing token info - getting new.');
				$this->getToken();

				return;
			}
			// Expired token
			if(($_SESSION['token_created'] + $_SESSION['token_expires_in']) < time()) {
				// error_log('Token expired - getting new.');
				$this->getToken();

				return;
			}

			// Token is still ok
			$this->token = $_SESSION['token'];
		}

		//

		protected function getToken() {
			// Sanity check
			if(empty($this->config['dp_auth']['client_id'])) {
				throw new Exception('Configuration [client_id] is REQUIRED but not set');
			}
			if(empty($this->config['dp_auth']['client_secret'])) {
				throw new Exception('Configuration [client_id] is REQUIRED but not set');
			}

			// error_log('Getting new token');

			// Build query
			$opts    = array(
				'http' => array(
					'method'  => 'POST',
					'header'  =>
						"Content-type: application/x-www-form-urlencoded\r\n" .
						"Authorization: Basic " . base64_encode($this->config['dp_auth']['client_id'] . ':' . $this->config['dp_auth']['client_secret']),
					'content' => http_build_query(["grant_type" => "client_credentials"])
				)
			);
			$context = stream_context_create($opts);
			$result  = file_get_contents($this->ep_token, false, $context);
			$data    = json_decode($result, true);

			if($data === NULL) {
				Response::error(500, "Could not parse JSON  output from Token endpoint. Debug response from the OAuth provider: " . json_encode($result));
			}
			// echo '<pre>' . json_encode($data) . '</pre>';
			// We have a token!
			$this->setToken($data['access_token'], $data['expires_in']);
		}

		protected function setToken($token, $token_expiry) {
			$this->token                  = $token;
			$_SESSION['token']            = $token;
			$_SESSION['token_created']    = time();
			$_SESSION['token_expires_in'] = $token_expiry;
		}

		// Make an API call

		protected function protectedRequest($url) {
			if($this->token === NULL) {
				throw new Exception('Cannot get data without a token');
			}

			$opts    = array(
				'http' => array(
					'method' => 'GET',
					'header' => "Authorization: Bearer " . $this->token,
				),
			);
			$context = stream_context_create($opts);
			$result  = file_get_contents($url, false, $context);


			$data = json_decode($result, true);
			if($data === NULL) {
				$data = array('status' => 'false', 'message' => 'Sorry, the API provided no response (' . $http_response_header[0] . ')');
			}

			return $data;
		}

		// Process the API call request

		protected function isAuthenticated() {
			return $this->token !== NULL;
		}

	}