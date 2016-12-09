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
		private $relaySQLConnection, $subscribers, $dataporten, $config;

		function __construct(Dataporten $dataPorten) {
			// Will exit on fail
			$this->config             = Config::getConfigFromFile(Config::get('auth')['relay_sql']);
			$this->relaySQLConnection = new RelaySQLConnection($this->config);
			$this->subscribers        = new Subscribers();
			$this->dataporten         = $dataPorten;
			// Check that org has access (will exit otherwise)
			$this->verifyOrgSubscription();
		}


		/**
		 * Check org/user access in MySQL subscribers table.
		 *
		 * @return bool
		 */
		private function verifyOrgSubscription() {
			// 1. Check if logged on user has status as student | employee - will exit if not
			$this->getRelayProfileIdFromAffiliation();
			// 2. Check MySQL table for user's home org affiliation access ('member' or 'employee')
			$orgAffiliationAccess = $this->subscribers->getOrgAffiliationAccess($this->dataporten->userOrgId());
			// User's org does NOT subscribe to the service (not found in table)
			if(!isset($orgAffiliationAccess[0]['affiliation_access'])) {
				Response::error(403, "Beklager, ditt lærested ser ikke ut til å abonnere på tjenesten.");
			}
			// 3. Check if home org allows user's affiliation access (Dataporten: primaryAffiliation, from Feide's eduPersonPrimaryAffiliation)
			switch(trim(strtolower($orgAffiliationAccess[0]['affiliation_access']))) {
				// If org only allows employees:
				case 'employee':
					// Check that user's primaryAffiliation is employee
					if(strcasecmp($this->dataporten->userAffiliation(), 'employee') !== 0) {
						Response::error(403, "Beklager, ditt lærested tilbyr ikke tjenesten for personer med tilhørighet som '" . $this->dataporten->userAffiliation() . "'.");
					}
					// OK, user is 'employee'
					return true;
				// Member == employees AND students have access.
				case 'member':
					// OK, user is either 'student' or 'employee' (we already checked for 'employee'/'student' status at the start of the function)
					return true;
				default:
					// For some reason, the subscribers table has something other than 'employee'/'member' recorded for this org
					// ->> This is WRONG (i.e. someone has entered incorrect data into the access table)
					Response::error(403, "Registrert tilgang for " . $this->dataporten->userOrgId() . " er satt til '" . $orgAffiliationAccess[0]['affiliation_access'] . "', mens din tilhørighet er ('" . $this->dataporten->userAffiliation() . "'). Her er det nok registrert feil i våre systemer. Fint om du varsler UNINETT om dette.");
					break;
			}
		}

		/**
		 * @return int
		 */
		public function getRelayProfileIdFromAffiliation() {
			// Will exit if user affiliation is not found in /groups/me/groups/
			switch($this->dataporten->userAffiliation()) {
				case 'student':
					return $this->studentProfileId();
				case 'employee':
					return $this->employeeProfileId();
				default:
					// Exit with error if no student/employee affiliation
					Response::error(403, "Du må ha tilhørighet som 'student' eller 'ansatt' ('employee'), men jeg fant ingen av delene: " . $this->dataporten->userAffiliation());
			}
		}

		/**
		 * Relay's ID for the student profile
		 * @return int
		 */
		public function studentProfileId() {
			return (int)$this->config['studentProfileId'];
		}

		/**
		 * Relay's ID for the employee profile
		 * @return int
		 */
		public function employeeProfileId() {
			return (int)$this->config['employeeProfileId'];
		}

		/**
		 * /relay/version/
		 * @return mixed
		 */
		public function getRelayVersion() {
			$sqlResponse = $this->relaySQLConnection->query("SELECT versValue FROM tblVersion")[0];

			return $sqlResponse['versValue'];
		}

		/**
		 * /me/create/
		 * @return array
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
				// a). Ask DB for the newly created user's ID
				$userId = $this->getRelayUserId();
				// If no ID returned, something is wrong...
				if(empty($userId)) {
					Response::error(500, "Kunne ikke opprette konto. Feilmelding: 'userId is null'.");
				}
				// 2. Associate new user with affiliated profile
				$this->sqlAddUserProfile($userId, $profileID);
				// 3. Associate new user with a role in tblRoleMembership (since v.4.0).
				$this->sqlAddUserRole($userId);
				// 4. Now call the database and request info for the account we just made
				$newUserAccount = $this->getRelayUser();
				if(!empty($newUserAccount)) {
					// ...and supplement the account details we're about to send back to the client
					$newAccount['userId']          = $newUserAccount['userId'];
					$newAccount['userName']        = $newUserAccount['userName'];
					$newAccount['userDisplayName'] = $newUserAccount['userDisplayName'];
					$newAccount['userFirstName']   = strtok($newUserAccount['userDisplayName'], " ");
					$newAccount['userEmail']       = $newUserAccount['userEmail'];
					$newAccount['userAffiliation'] = $newUserAccount['userAffiliation'];

					// Send user a confirmation email with the account details
					Utils::sendMail($newAccount);

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
		 *
		 * @return bool
		 */
		public function getRelayAccountExists() {
			$sqlResponse = $this->relaySQLConnection->query("
				SELECT userId 
				FROM tblUser 
				WHERE userName LIKE '" . $this->dataporten->userName() . "'"
			);

			return !empty($sqlResponse);
		}

		/**
		 * @param $length
		 *
		 * @return string
		 */
		private function generatePassword($length) {
			$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

			return substr(str_shuffle($chars), 0, $length);
		}

		/**
		 * @return string
		 */
		private function generateSalt() {
			return md5(uniqid(mt_rand(), true));
		}

		/**
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

		/**
		 * Create a new user, where 'FEIDE_USERNAME' is a dummy account used as a blueprint for
		 * the new account.
		 *
		 * @param $accountInsert
		 *
		 * @return int
		 */
		private function sqlCreateUser($accountInsert) {
			// Actual Insert
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
			// Note: 'FEIDE_USERNAME' is a dummy user that already exists in tblUser
			// Run & return
			return $this->relaySQLConnection->execute($SQL);
		}

		/**
		 * Get a user ID from userName.
		 * @return int|null
		 */
		public function getRelayUserId() {
			$sqlResponse = $this->relaySQLConnection->query("
				SELECT userId 
				FROM tblUser 
				WHERE userName LIKE '" . $this->dataporten->userName() . "'"
			);

			return !empty($sqlResponse) ? $sqlResponse[0]['userId'] : NULL;
		}


		/**
		 * Assign a profile to the newly created user. Either student or employee.
		 *
		 * @param $userId
		 * @param $profileID
		 *
		 * @return array
		 */
		private function sqlAddUserProfile($userId, $profileID) {
			$SQL = "INSERT INTO tblUserProfile (
					usprUser_userId, usprProfile_profId, usprAddedViaGroup, createdOn, createdByUser, 
					modifiedOn, modifiedByUser, modifiedByModule, modificationCount) 
					VALUES (
					$userId, $profileID, 0, GETDATE(),'Original Record', GETDATE(), 'Original Record', 'Original Record', 0);";

			// Run & return
			return $this->relaySQLConnection->query($SQL);
		}

		/**
		 * Assign newly created user with a role.
		 * Presenter role == 3 which is the role all users get by default.
		 *
		 * @param $userId
		 *
		 * @return array
		 */
		private function sqlAddUserRole($userId) {
			$SQL = "INSERT INTO tblRoleMembership (
					rmUser_userId, rmRole_RoleId, createdOn, createdByUser, modifiedOn, modifiedByUser, modifiedByModule, modificationCount) 
					VALUES ($userId, 3, GETDATE(), 'registration_script', GETDATE(), 'registration_script', 'Original Record', 0);";

			// Run & return
			return $this->relaySQLConnection->query($SQL);
		}


		/**
		 * /me/
		 *
		 * Get account info from userName
		 *
		 * @return bool
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
				return false;
			}
		}

		/**
		 * USELESS FUNCTION - USED SOLELY FOR TESTING ONLY
		 *
		 * @return string
		 */
		public function sendTestMail() {
			$newAccount                    = [];
			$newAccount['userFirstName']   = 'Bør';
			$newAccount['userName']        = 'bør@børson.no';
			$newAccount['userDisplayName'] = 'Bør Børson';
			$newAccount['userEmail']       = 'bør.børson@uninett.no';
			$newAccount['userPassword']    = 'autogeneratedpassword';

			return Utils::sendMail($newAccount);
		}

		/**
		 * USELESS FUNCTION - USED SOLELY FOR TESTING ONLY
		 *
		 * @return string
		 */
		public function getSchema($table) {
			$response    = $this->relaySQLConnection->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$table'");
			$newResponse = [];
			foreach($response as $index => $obj) {
				$newResponse[] = $obj['COLUMN_NAME'];
			}

			return implode(", ", $newResponse);
		}
	}