<?php
	/**
	 *
	 *
	 * @author Simon Skrodal
	 * @since  July 2016
	 */

	require_once($BASE . '/lib/db/relaysqlconnection.class.php');

	// Some calls take a long while so increase timeout limit from def. 30
	set_time_limit(300);    // 5 mins
	// Have experienced fatal error - allowed memory size of 128M exhausted - thus increase
	ini_set('memory_limit', '350M');

	class Relay {
		protected $config;
		private $DEBUG = false;
		private $relaySQL;

		function __construct($config) {
			$this->config   = $config;
			$this->relaySQL = new RelaySQLConnection($config);
		}

		########################################################
		# FUNCTIONS ACCESSIBLE BY ROUTES
		########################################################

		public function getRelayVersion() {
			$versionResponse = $this->relaySQL->query("SELECT * FROM tblVersion")[0];
			return $versionResponse['versValue'];
		}

		/**
		 * Check if a user exists.
		 *
		 * @param $username
		 *
		 * @return bool
		 */
		private function _checkUserExists($username) {
			// Lookup account info for requested user
			$sqlUserInfoResponse = $this->relaySQL->query("
				SELECT userName, userEmail
				FROM tblUser
				WHERE userName = '$username'");
			//
			return !empty($sqlUserInfoResponse);
		}

		public function createUser($userDetails){
			// TODO
		}





		// ---------------------------- UTILS ----------------------------

		private function _responseToArray($response) {
			$newArr = Array();
			foreach($response as $child) {
				$newArr[] = $child;
			}

			return $newArr;
		}

		private function _logger($text, $line, $function) {
			if($this->DEBUG) {
				error_log($function . '(' . $line . '): ' . $text);
			}
		}

		// ---------------------------- ./UTILS ----------------------------


	}



