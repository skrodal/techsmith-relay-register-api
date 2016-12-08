<?php
	/**
	 * Read subscription info a table in UNINETTs MySQL Cluster. Implemented to move away from dependencies on Kind,
	 * given that v.2 will be delayed by ~1 year. This solution ensures that the registration service will continue
	 * to work when Kind v1 is taken down.
	 *
	 * NOTE 1: THIS TABLE IS NOT AUTHORITATIVE!
	 * NOTE 2: THE TABLE DOES NOT INCLUDE SUBSCRIPTION STATUS. ACCESS TO THE CLIENT MUST BE REGULATED IN DATAPORTEN!
	 *
	 *
	 * @author Simon Skrødal
	 * @since  08/12/2016
	 *
	 */

	namespace Relay\Database;

	use Relay\Utils\Response;

	class SubscribersMySQLConnection {
		private $config;
		private $conn = NULL;

		function __construct($config) {
			$this->config = $config;
		}

		/**
		 * Query table for affiliation access for a given org
		 * @param $org
		 *
		 * @return array
		 */
		public function getOrgAffiliationAccess($org) {
			$table = $this->config['table'];

			return $this->query("SELECT affiliation_access FROM $table WHERE org = '$org'");
		}

		public function query($sql) {
			$this->conn = $this->getConnection();

			try {
				$response = array();
				$query    = $this->conn->query($sql, \PDO::FETCH_ASSOC);
				foreach($query as $row) {
					$response[] = $row;
				}
				$query->closeCursor();

				return $response;
			} catch(\PDOException $e) {
				Response::error(500, 'Samtale med database feilet (MySQL): ' . $e->getMessage());
			}
		}

		public function getConnection() {
			if(!is_null($this->conn)) {
				return $this->conn;
			}
			$host = $this->config['host'];
			$db   = $this->config['db'];
			$user = $this->config['user_rw'];
			$pass = $this->config['pass'];
			try {
				$connection = new \PDO("mysql:host=$host;dbname=$db;charset=UTF8", $user, $pass);
				$connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
				$connection->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

				return $connection;

			} catch(\PDOException $e) {
				Response::error(503, 'Utilgjengelig. Databasekobling kundeliste feilet: ' . $e->getMessage());
			}
		}
	}