<?php
	/**
	 * Provides responses for all routes.
	 */

	namespace Relay\Api;

	use Relay\Auth\Dataporten;
	use Relay\Database\RelaySQLConnection;


	class Relay {
		private $relaySQLConnection, $dataporten;

		function __construct(Dataporten $dataPorten) {
			//
			$this->relaySQLConnection = new RelaySQLConnection();
			$this->dataporten         = $dataPorten;
		}

		#
		# SERVICE ENDPOINTS
		#
		# /service/*/
		#
		// /service/ endpoint - not sure if needed...
		public function getServiceVersion() {
			return $this->relaySQLConnection->query("SELECT * FROM tblVersion")[0];
		}


		// /me/
		public function getUser() {
			$query = $this->relaySQLConnection->query("
				SELECT userId, userName, userDisplayName, userEmail, usprProfile_profId AS userAffiliation
				FROM tblUser, tblUserProfile
				WHERE tblUser.userId = tblUserProfile.usprUser_userId
				AND userName = '" . $this->dataporten->userName() . "'");

			// Convert affiliation code to text
			// Some test users have more than one profile, thus the SQL query may return more than one entry for a single user.
			// Since we're after a specific profile - either employeeProfileId or studentProfileId - run this check and return entry
			// as soon as we have a match.
			if(!empty($query)) {
				foreach($query as $key => $info) {
					switch($query[$key]['userAffiliation']) {
						case $this->relaySQLConnection->employeeProfileId():
							$query[$key]['userAffiliation'] = 'employee';

							return $query[$key];
						case $this->relaySQLConnection->studentProfileId():
							$query[$key]['userAffiliation'] = 'student';

							return $query[$key];
					}
				}
			} else {
				return false;
			}
		}

	}