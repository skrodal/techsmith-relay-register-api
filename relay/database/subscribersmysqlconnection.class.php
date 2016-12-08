<?php
	/**
	 * Read subscription info a table in UNINETTs MySQL Cluster. Implemented to move away from dependencies on Kind,
	 * given that v.2 will be delayed by ~1 year. This solution ensures that the registration service will continue
	 * to work when Kind v1 is taken down.
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

		/**
		 * @param $org
		 *
		 * @return bool
		 */
		public function deleteOrg($org) {
			$this->conn = $this->getConnection();
			$table = $this->config['table'];

			try {
				$stmt = $this->conn->prepare("DELETE FROM $table WHERE org = :org");
				$stmt->bindParam(':org', $org, \PDO::PARAM_STR);
				return $stmt->execute() > 0 ? true : false;
			} catch(\PDOException $e) {
				Response::error(500, 'Samtale med database feilet (MySQL): ' . $e->getMessage());
			}
		}

		/**
		 * @param $org
		 *
		 * @return bool
		 */
		public function insertOrg($org) {
			$this->conn = $this->getConnection();
			$table = $this->config['table'];

			try {
				$stmt = $this->conn->prepare("INSERT INTO $table (org, affiliation_access) VALUES (:org, :affiliation)");
				$stmt->bindParam(':org', $org, \PDO::PARAM_STR);
				$stmt->bindParam(':affiliation', $affiliation, \PDO::PARAM_STR);
				return $stmt->execute() > 0 ? true : false;
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