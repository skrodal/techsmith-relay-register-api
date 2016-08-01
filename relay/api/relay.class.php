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
		 * /me/create/
		 */
		public function createRelayUser() {
			// Only create if user does not already exist
			if(!$this->getRelayAccountExists()) {
				// Will exit if student/employee affiliation is missing
				$profileID = $this->getRelayProfileIdFromAffiliation();
				// Details that will be returned to the client (user):
				$newAccount                 = [];
				$newAccount['userPassword'] = $this->generatePassword(10);
				// Details that will be added to the new account
				$accountInsert                    = [];
				$accountInsert['userName']        = $this->dataporten->userName();
				$accountInsert['userDisplayName'] = $this->dataporten->userDisplayName();
				$accountInsert['userEmail']       = $this->dataporten->userEmail();
				$accountInsert['passwordSalt']    = $this->generateSalt();
				$accountInsert['userPassword']    = $this->hashPassword($newAccount['userPassword'], $accountInsert['passwordSalt']);

				// 1. Create user
				$this->sqlCreateUser($accountInsert);
				// a). Ask for the newly created user's ID
				$userId = $this->getRelayUserId();
				// 2. Associate new user with affiliated profile
				$this->sqlAddUserProfile($userId, $profileID);
				// 3. Associate new user with a role in tblRoleMembership (since v.4.0).
				$this->sqlAddUserRole($userId);
				// 4. Now call the database and request info for the account we just made
				$userAccount = $this->getRelayUser();
				if(!empty($userAccount)){
					// ...and supplement the account details we're about to send back to the client
					$newAccount['userId']          = $userAccount['userId'];
					$newAccount['userName']        = $userAccount['userName'];
					$newAccount['userDisplayName'] = $userAccount['userDisplayName'];
					$newAccount['userEmail']       = $userAccount['userEmail'];
					$newAccount['userAffiliation'] = $userAccount['userAffiliation'];
					// Done
					return $newAccount;
				} else {
					// Hmm. If we get here, something went wrong when registering the user. And, for some reason,
					// it was not caught earlier in one of the registration queries.. Should never happen, but here goes:
					Response::error(500, "En ukjent feil oppstod i registrering av din konto (createRelayUser).");
				}

			} else {
				// User exists already
				Response::error(403, "Konto med brukernavn '" . $this->dataporten->userName() . "' eksisterer allerede.");
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
					Response::error(403, "Du må ha tilhørighet som student eller ansatt, men jeg fant ingen av delene: " . $this->dataporten->userAffiliation());
			}
		}

		public function studentProfileId() {
			return (int)$this->config['studentProfileId'];
		}

		public function employeeProfileId() {
			return (int)$this->config['employeeProfileId'];
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

		private function sqlCreateUser($accountInsert) {

			// Actual Insert (consider utf8_decode...)
			// NOTE! `FEIDE_USERNAME` is a dummy (model) user that already exists in tblUser
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
	                    GETDATE(), createdByUser, GETDATE(), modifiedByUser, modifiedByModule, modificationCount
                    FROM tblUser
                    WHERE userName = 'FEIDE_USERNAME'
                ";

			// Run & return
			return $this->relaySQLConnection->query($SQL);
		}

		private function getRelayUserId() {
			$sqlResponse = $this->relaySQLConnection->query("
				SELECT userId 
				FROM tblUser 
				WHERE userName LIKE '" . $this->dataporten->userName() . "'"
			);

			return !empty($sqlResponse) ? $sqlResponse[0]['userId'] : NULL;
		}


		private function sqlAddUserProfile($userId, $profileID) {
			$SQL = "INSERT INTO tblUserProfile (
					usprUser_userId, usprProfile_profId, usprAddedViaGroup, createdOn, createdByUser, 
					modifiedOn, modifiedByUser, modifiedByModule, modificationCount) 
					VALUES (
					$userId, $profileID, 0, GETDATE(),'Original Record', GETDATE(), 'Original Record', 'Original Record', 0);";

			// Run & return
			return $this->relaySQLConnection->query($SQL);
		}

		private function sqlAddUserRole($userId) {
			// Presenter role == 3 which is the role all users get by default.
			$SQL = "INSERT INTO tblRoleMembership (
					rmUser_userId, rmRole_RoleId, createdOn, createdByUser, modifiedOn, modifiedByUser, modifiedByModule, modificationCount) 
					VALUES ($userId, 3, GETDATE(), 'registration_script', GETDATE(), 'registration_script', 'Original Record', 0);";

			// Run & return
			return $this->relaySQLConnection->query($SQL);
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
			if(!empty($sqlResponse)) {
				switch($sqlResponse[0]['userAffiliation']) {
					case $this->employeeProfileId():
						$sqlResponse[0]['userAffiliation'] = 'employee';

						return $sqlResponse[0];
					case $this->studentProfileId():
						$sqlResponse[0]['userAffiliation'] = 'student';

						return $sqlResponse[0];
				}
			} else {
				//
				return null;
			}
		}

		public function kindId() {
			return $this->config['kindId'];
		}
	}