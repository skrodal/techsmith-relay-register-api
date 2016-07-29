<?php
	/**
	 * Provides responses for all routes.
	 */

	namespace Relay\Api;

	use Relay\Auth\Dataporten;
	use Relay\Conf\Config;
	use Relay\Database\RelaySQLConnection;
	use Relay\Utils\Response;

	class Relay {
		private $relaySQLConnection, $dataporten, $config;

		function __construct(Dataporten $dataPorten) {
			// Will exit on fail
			$this->config             = Config::getConfigFromFile(Config::get('auth')['relay_sql']);
			$this->relaySQLConnection = new RelaySQLConnection($this->config);
			$this->dataporten         = $dataPorten;
		}

		/**
		 * /relay/version/
		 */
		public function getRelayVersion() {
			$sqlResponse = $this->relaySQLConnection->query("SELECT versValue FROM tblVersion")[0];

			return $sqlResponse['versValue'];
		}

		/**
		 * /me/
		 */
		public function getRelayUser() {
			$sqlResponse = $this->relaySQLConnection->query("
				SELECT userId, userName, userDisplayName, userEmail, usprProfile_profId AS userAffiliation
				FROM tblUser, tblUserProfile
				WHERE tblUser.userId = tblUserProfile.usprUser_userId
				AND userName = '" . $this->dataporten->userName() . "'");

			// Convert affiliation code to text
			// Some test users have more than one profile, thus the SQL query may return more than one entry for a single user.
			// Since we're after a specific profile - either employeeProfileId or studentProfileId - run this check and return entry
			// as soon as we have a match.
			if(!empty($sqlResponse)) {
				foreach($sqlResponse as $key => $info) {
					switch($sqlResponse[$key]['userAffiliation']) {
						case $this->employeeProfileId():
							$sqlResponse[$key]['userAffiliation'] = 'employee';
							return $sqlResponse[$key];
						case $this->studentProfileId():
							$sqlResponse[$key]['userAffiliation'] = 'student';
							return $sqlResponse[$key];
					}
				}
			} else {
				return false;
			}
		}


		public function employeeProfileId() {
			return (int)$this->config['employeeProfileId'];
		}

		public function studentProfileId() {
			return (int)$this->config['studentProfileId'];
		}

		/**
		 * /me/create/
		 */
		public function createRelayUser() {
			// Only create if user does not already exist
			if($this->getRelayAccountExists()) {
				// Will exit if student/employee is missing
				$profileID = $this->getRelayProfileIdFromAffiliation();

				// Details that will be returned to the client (user); some from Relay DB, some from Dataporten.
				$newAccount                = [];
				$newAccount['password']    = $this->generatePassword(10);
				$newAccount['affiliation'] = $this->dataporten->userAffiliation();
				//
				// Details that will be added to the account
				$accountInsert                    = [];
				$accountInsert['passwordSalt']    = $this->generateSalt();
				$accountInsert['userPassword']    = $this->hashPassword($newAccount['password'], $accountInsert['passwordSalt']);
				$accountInsert['userName']        = $this->dataporten->userName();
				$accountInsert['userDisplayName'] = $this->dataporten->userDisplayName();
				$accountInsert['userEmail']       = $this->dataporten->userEmail();
				// Used to add user to a Relay profile
				$accountInsert['profileId'] = $profileID;


				// TODO = CHECK BEFORE TESTING!
				$SQL = "
                    INSERT INTO tblUser (
                    userName, userDisplayName, userPassword, userEmail, userAccountType, 
                    userTechSmithId, userAnonymous, userMaster, userLocked, userLockedAtTime, 
                    userGetsAdminEmail, userLdapName, userPasswordHashAlgo, userPasswordSalt, 
                    createdOn, createdByUser, modifiedOn, modifiedByUser, modifiedByModule, modificationCount)

                    SELECT 
                    '" . $accountInsert['userName'] . "', 
                    '" . $accountInsert['userDisplayName'] . "', 
                    '" . $accountInsert['userPassword'] . "', 
                    '" . $accountInsert['userEmail'] . "',  
                    userAccountType, userTechSmithId, userAnonymous, userMaster, userLocked, 
                    userLockedAtTime, userGetsAdminEmail, userLdapName, userPasswordHashAlgo, 
                    '" . $accountInsert['passwordSalt'] . "', 
                    createdOn, createdByUser, modifiedOn, modifiedByUser, modifiedByModule, modificationCount
                    FROM tblUser
                    WHERE userName = 'FEIDE_USERNAME'
                ";

				$SQL = "SELECT 
                    '" . $accountInsert['userName'] . "', 
                    '" . $accountInsert['userDisplayName'] . "', 
                    '" . $accountInsert['passwordHashed'] . "', 
                    '" . $accountInsert['userEmail'] . "',  
                    userAccountType, userTechSmithId, userAnonymous, userMaster, userLocked, 
                    userLockedAtTime, userGetsAdminEmail, userLdapName, userPasswordHashAlgo, 
                    '" . $accountInsert['passwordSalt'] . "', 
                    createdOn, createdByUser, modifiedOn, modifiedByUser, modifiedByModule, modificationCount
                    FROM tblUser
                    WHERE userName = 'FEIDE_USERNAME'
                ";

				// CHECK ABOVE FIRST!
				$result = $this->relaySQLConnection->query($SQL);


				return $result;
			} else {
				// User exists already
				Response::error(403, "Account already exists!");
			}
		}

		/**
		 * Query for ID to check if user exists.
		 */
		private function getRelayAccountExists() {
			$sqlResponse = $this->relaySQLConnection->query("
				SELECT userId 
				FROM tblUser 
				WHERE userName LIKE '" . $this->dataporten->userName() . "'"
			);

			return !empty($sqlResponse);
		}

		private function getRelayProfileIdFromAffiliation() {
			// Will exit if user affiliation is not found in /groups/me/groups/
			switch($this->dataporten->userAffiliation()) {
				case 'student':
					return $this->studentProfileId();
				case 'employee':
					return $this->employeeProfileId();
				default:
					// Exit with error if no student/employee affiliation
					Response::error(403, "Affiliation is invalid/missing: " . $this->dataporten->userAffiliation());
			}
		}

		private function generatePassword($length) {
			$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

			return substr(str_shuffle($chars), 0, $length);
		}

		// SHA384

		private function generateSalt() {
			return md5(uniqid(mt_rand(), true));
		}

		private function hashPassword($password, $salt) {
			$hashedPassword         = hash('sha384', $password . $salt);
			$TIMES_TO_HASH_PASSWORD = 2;
			for($i = 0; $i < $TIMES_TO_HASH_PASSWORD; $i++) {
				$hashedPassword = hash('sha384', $hashedPassword . $salt);
			}

			return $hashedPassword;
		}

		public function kindId() {
			return $this->config['kindId'];
		}
	}