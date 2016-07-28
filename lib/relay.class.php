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
		private $config, $relaySQL, $dataporten;

		function __construct() {
			global $relayConfig, $dataporten;
			$this->config     = $relayConfig;
			$this->relaySQL   = new RelaySQLConnection($relayConfig);
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
			if(!$this->getUserId()) {
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
				// So far, so good. Let's create the account

				$userAccount                    = [];
				$userAccount['userName']        = $this->dataporten->getUserName();
				$userAccount['userDisplayName'] = $this->dataporten->getUserDisplayName();
				$userAccount['userEmail']       = $this->dataporten->getUserEmail();
				$userAccount['userPassword']    = $this->generatePassword(10);
				// FOR INITIAL TESTING ONLY
				$userAccount['user_id']     = $this->getUserId();
				$userAccount['affiliation'] = $affiliation;
				$userAccount['profile_id']  = $profileID;
				//
				$userAccount['passwordSalt']   = $this->generateSalt();
				$userAccount['passwordHashed'] = hashPassword($userAccount['userPassword'], $userAccount['passwordSalt']);;


				// TODO = CHECK BEFORE TESTING!
				$SQL = "
                    INSERT INTO tblUser (
                    userName, userDisplayName, userPassword, userEmail, userAccountType, 
                    userTechSmithId, userAnonymous, userMaster, userLocked, userLockedAtTime, 
                    userGetsAdminEmail, userLdapName, userPasswordHashAlgo, userPasswordSalt, 
                    createdOn, createdByUser, modifiedOn, modifiedByUser, modifiedByModule, modificationCount)

                    SELECT 
                    '" . $userAccount['userName'] . "', 
                    '" . $userAccount['userDisplayName'] . "', 
                    '" . $userAccount['passwordHashed'] ."', 
                    '" . $userAccount['userEmail'] ."',  
                    userAccountType, userTechSmithId, userAnonymous, userMaster, userLocked, 
                    userLockedAtTime, userGetsAdminEmail, userLdapName, userPasswordHashAlgo, 
                    '" . $userAccount['passwordSalt'] . "', 
                    createdOn, createdByUser, modifiedOn, modifiedByUser, modifiedByModule, modificationCount
                    FROM tblUser
                    WHERE userName = 'FEIDE_USERNAME'
                ";

				$SQL = "SELECT 
                    '" . $userAccount['userName'] . "', 
                    '" . $userAccount['userDisplayName'] . "', 
                    '" . $userAccount['passwordHashed'] . "', 
                    '" . $userAccount['userEmail'] . "',  
                    userAccountType, userTechSmithId, userAnonymous, userMaster, userLocked, 
                    userLockedAtTime, userGetsAdminEmail, userLdapName, userPasswordHashAlgo, 
                    '" . $userAccount['passwordSalt'] . "', 
                    createdOn, createdByUser, modifiedOn, modifiedByUser, modifiedByModule, modificationCount
                    FROM tblUser
                    WHERE userName = 'FEIDE_USERNAME'
                ";

				// CHECK ABOVE FIRST!
				$result = $this->relaySQL->query($SQL);

				$userAccount['headers'] = $_SERVER;
				$userAccount['sql'] = $result;

				return $userAccount;
			} else {
				// User exists already
				Response::error(403, "Account already exists!");
			}
		}

		private function getUserId() {
			$sqlUserIdResponse = $this->relaySQL->query("SELECT userId FROM tblUser WHERE userName LIKE '" . $this->dataporten->getUserName() . "'");

			return empty($sqlUserIdResponse) ? false : $sqlUserIdResponse[0];
		}




		######
		# Helpers
		######

		// Used to check for existence of account

		private function generatePassword($length) {
			$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

			return substr(str_shuffle($chars), 0, $length);
		}

		//

		private function generateSalt() {
			return md5(uniqid(mt_rand(), true));
		}

		/**
		 * GET /me/
		 *
		 * @return false|object
		 */
		public function getUserInfo() {
			$sqlUserInfoResponse = $this->relaySQL->query("
				SELECT userName, userEmail, userDisplayName
				FROM tblUser
				WHERE userName = '$this->username'");

			return empty($sqlUserInfoResponse) ? false : $sqlUserInfoResponse[0];
		}

		//

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
			$hashedPassword         = hash('sha384', $password . $salt);
			$TIMES_TO_HASH_PASSWORD = 2;
			for($i = 0; $i < $TIMES_TO_HASH_PASSWORD; $i++) {
				$hashedPassword = hash('sha384', $hashedPassword . $salt);
			}

			return $hashedPassword;
		}
	}



