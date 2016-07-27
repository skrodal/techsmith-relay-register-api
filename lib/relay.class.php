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
		private $relaySQL;

		function __construct() {
			global $relayConfig;
			$this->config   = $relayConfig;
			$this->relaySQL = new RelaySQLConnection($relayConfig);
		}

		########################################################
		# FUNCTIONS ACCESSIBLE BY ROUTES
		########################################################

		/**
		 * GET /version/
		 *
		 * @return string
		 */
		public function getRelayVersion() {
			$versionResponse = $this->relaySQL->query("SELECT * FROM tblVersion")[0];
			return $versionResponse['versValue'];
		}

		/**
		 * GET /me/
		 *
		 * @param $username
		 * @return null|object
		 */
		public function getUserInfo($username){
			$sqlUserInfoResponse = $this->relaySQL->query("
				SELECT userName, userEmail, userDisplayName
				FROM tblUser
				WHERE userName = '$username'");

			return empty($sqlUserInfoResponse) ? false : $sqlUserInfoResponse[0];
		}

		public function createUser($userDetails){
			// TODO
		}
	}



