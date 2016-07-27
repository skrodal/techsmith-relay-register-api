<?php
	/**
	 * Dataporten OAuth Client Credentials Grant 
	 *
	 * This class facilitates getting a `TOKEN` pertaining to a Dataporten API GK which will allow 
	 * a client to communicate via the API without the need for user authentication (only the 
	 * client authenticates with the GK)
	 *
	 * @author Simon Skrødal
	 */
	
	session_start();

	class DataportenOAuth {

		protected $ep_token = 'https://auth.dataporten.no/oauth/token/';
		protected $config 	= null;
		protected $token 	= null;
		
		//
		function __construct($config) {
			//
			$this->config = $config;
			// Gets/ (or sets) a token
			$this->checkTokenValidity();
		}

		/**
		 * Ensures that a token exists for this sessionb and that it is valid (not expired) - create new if any issues.
		 */
		private function checkTokenValidity(){
			// Missing token info
			if (empty($_SESSION['token']) || empty($_SESSION['token_expires_in']) || empty($_SESSION['token_created'])) { 
				// error_log('Missing token info - getting new.');
				$this->getToken();
				return;
			} 
			// Expired token
			if ( ($_SESSION['token_created'] + $_SESSION['token_expires_in']) < time() ) {
				// error_log('Token expired - getting new.');
				$this->getToken();
				return;
			}

			// Token is still ok
			$this->token = $_SESSION['token'];
		}

		// 
		protected function setToken($token, $token_expiry) {
			$this->token = $token;
			$_SESSION['token'] = $token;
			$_SESSION['token_created'] = time();
			$_SESSION['token_expires_in'] = $token_expiry;
		}

		// Make a call to the token endpoint with client credentials
		protected function getToken() {
			// Sanity check
			if (empty($this->config['client_id'])) throw new Exception('Configuration [client_id] is REQUIRED but not set');
			if (empty($this->config['client_secret'])) throw new Exception('Configuration [client_id] is REQUIRED but not set');

			// error_log('Getting new token');

			// Build query
			$opts = array(
				'http' => array(
					'method'  => 'POST',
					'header'  =>
						"Content-type: application/x-www-form-urlencoded\r\n" .
						"Authorization: Basic " . base64_encode($this->config['client_id'] . ':' . $this->config['client_secret']),
					'content' => http_build_query([ "grant_type" => "client_credentials" ])
				)
			);
			$context  	= stream_context_create($opts);
			$result 	= file_get_contents($this->ep_token, false, $context);
			$data 		= json_decode($result, true);

			if ($data === null) {
				Response::error(500, "Could not parse JSON  output from Token endpoint. Debug response from the OAuth provider: " . json_encode($result));
			}
			// echo '<pre>' . json_encode($data) . '</pre>';
			// We have a token!
			$this->setToken($data['access_token'], $data['expires_in']);
		}

		// 
		public function reset() {
			$this->setToken(null, null);
		}

		public function isAuthenticated() {
			return $this->token !== null;
		}

		// Make an API call
		public function get($url) {
			return $this->protectedRequest($url);
		}

		// Process the API call request
		protected function protectedRequest($url) {
			if ($this->token === null) throw new Exception('Cannot get data without a token');

			$opts = array(
				'http' => array(
					'method'  => 'GET',
					'header'  => "Authorization: Bearer " . $this->token,
				),
			);
			$context  = stream_context_create($opts);
			$result = file_get_contents($url, false, $context);

			
			$data = json_decode($result, true);
			if ($data === null) {
				$data = array('status' => 'false', 'message' => 'Sorry, the API provided no response (' . $http_response_header[0] . ')');
			}
			return $data;
		}

	}