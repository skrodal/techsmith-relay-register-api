<?php
	/**
	 * Provides responses for all routes.
	 */

	namespace Relay\Api;

	use Relay\Auth\Dataporten;
	use Relay\Conf\Config;
	use Relay\Database\RelaySQLConnection;
	use Relay\Utils\Response;
	use Relay\Utils\Utils;

	class Relay {
		private $relaySQLConnection, $dataporten, $config;

		function __construct(Dataporten $dataPorten) {
			// Will exit on fail
			$this->config             = Config::getConfigFromFile(Config::get('auth')['relay_sql']);
			$this->relaySQLConnection = new RelaySQLConnection($this->config);
			$this->dataporten         = $dataPorten;
		}

		#
		# SERVICE ENDPOINTS
		#
		# /service/*/
		#
		public function getRelayVersion() {
			$sqlResponse = $this->relaySQLConnection->query("SELECT versValue FROM tblVersion")[0];

			return $sqlResponse['versValue'];
		}

		// /me/
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

		public function createRelayUser() {
			// $affiliation = $_POST['userAffiliation'];
			$affiliation = Utils::getRequestBody('userAffiliation');

			// Only create if user does not already exist
			if($this->getUserId()) {
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
						Response::error(403, "Affiliation is invalid/missing: " . $affiliation);
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
				$userAccount['passwordHashed'] = $this->hashPassword($userAccount['userPassword'], $userAccount['passwordSalt']);;


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
                    '" . $userAccount['passwordHashed'] . "', 
                    '" . $userAccount['userEmail'] . "',  
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

				$userAccount['headers']     = $_SERVER;
				$userAccount['post']        = $_POST;
				$userAccount['sqlresponse'] = $result;

				return $userAccount;
			} else {
				// User exists already
				Response::error(403, "Account already exists!");
			}
		}

		private function getUserId() {
			$sqlResponse = $this->relaySQLConnection->query("
				SELECT userId 
				FROM tblUser 
				WHERE userName LIKE '" . $this->dataporten->userName() . "'"
			);

			return empty($sqlResponse) ? false : $sqlResponse[0];
		}

		private function generatePassword($length) {
			$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

			return substr(str_shuffle($chars), 0, $length);
		}

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

		// SHA384

		public function kindId() {
			return $this->config['kindId'];
		}
	}