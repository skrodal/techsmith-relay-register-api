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
		private $dataporten;

		function __construct() {
			global $relayConfig, $dataporten;
			$this->config   = $relayConfig;
			$this->relaySQL = new RelaySQLConnection($relayConfig);
			$this->dataporten = $dataporten;
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
		 * POST /me/create/
		 *
		 * @param $affiliation
		 *
		 * @return array
		 */
		public function createUser($affiliation) {
			// Only create if user does not already exist
			if(!$this->getUserInfo()) {
				$profileID = NULL;
				// Match affiliation with a Profile ID in Relay
				switch(trim(strtolower($affiliation))) {
					case 'student':
						$profileID = $this->relaySQL->studentProfileId();
						break;
					case 'employee':
						$profileID = $this->relaySQL->employeeProfileId();
						break;
					default:
						Response::error(403, "Invalid affiliation!");
				}

				$userAccount = [];
				$userAccount['disp'] =
				$userAccount['affiliation'] = $affiliation;
				$userAccount['profile_id'] = $profileID;
				// Generate a random password
				$userAccount['password'] = generatePassword(10);
				// ...otherwise, all good: Generate a random salt
				$salt = generateSalt();
				// Hash the password and salt
				$hash = hashPassword($userAccount['password'], $salt);
				return $userAccount;
			}
		}

		/**
		 * GET /me/
		 *
		 * @return false|object
		 */
		public function getUserInfo() {

			$username = $this->dataporten->getUserName();

			$sqlUserInfoResponse = $this->relaySQL->query("
				SELECT userName, userEmail, userDisplayName
				FROM tblUser
				WHERE userName = '$username'");

			return empty($sqlUserInfoResponse) ? false : $sqlUserInfoResponse[0];
		}

		/**
		 * GET /me/id/
		 *
		 * @return string
		 * @internal param $userName
		 */
		public function getUserId()
		{
			$username = $this->dataporten->getUserName();
			$sqlUserIdResponse = $this->relaySQL->query("SELECT userId FROM tblUser WHERE userName LIKE '$username'");
			return empty($sqlUserIdResponse) ? false : $sqlUserIdResponse[0];
		}


		// Good -> let's make the call...


		######
		# Helpers
		######


		/**
		 * Generate a random (enough) salt.
		 * @return string
		 */
		private function generateSalt() {
			return md5(uniqid(mt_rand(), true));
		}

		/**
		 * Generate a hashed password
		 *
		 * 1. Hash password + salt using SHA384
		 * 2. Hash the product of 1. two more times.
		 *
		 * @param $password
		 * @param $salt
		 *
		 * @return string
		 */
		private function hashPassword($password, $salt) {
			$hashedPassword = hash('sha384', $password . $salt);
			$TIMES_TO_HASH_PASSWORD = 2;
			for($i = 0; $i < $TIMES_TO_HASH_PASSWORD; $i++) {
				$hashedPassword = hash('sha384', $hashedPassword . $salt);
			}
			return $hashedPassword;
		}


		/**
		 * Create a random password
		 *
		 * @param $length
		 *
		 * @return string
		 */
		private function generatePassword($length) {
			$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

			return substr(str_shuffle($chars), 0, $length);
		}
	}



