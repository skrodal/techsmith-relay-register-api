<?php
	namespace Relay\Database;

	use Relay\Utils\Response;

	ini_set('mssql.charset', 'UTF-8');

	class RelaySQLConnection {

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
				Response::error(500, 'Samtale med database feilet (SQL): ' . $e->getMessage());
			}
		}

		/**
		 * @return PDO
		 */
		private function getConnection() {
			if(!is_null($this->conn)) {
				return $this->conn;
			}
			// Read only access
			$host = $this->config['host'];
			$db   = $this->config['db'];
			$user = $this->config['user'];
			$pass = $this->config['pass'];
			try {
				$connection = new \PDO("dblib:host=$host;dbname=$db;charset=UTF8", $user, $pass);
				$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

				return $connection;
			} catch(\PDOException $e) {
				Response::error(503, 'Utilgjengelig. Databasekobling Relay feilet: ' . $e->getMessage());
			}
		}
	}



	/**
	 *    Open MSSQL conn
	 *
	 * private function getConnection() {
	 * if(!is_null($this->conn)) {
	 * return $this->conn;
	 * }
	 * //
	 * $connection = mssql_connect($this->config['host'], $this->config['user_write'], $this->config['pass']);
	 * if(!$connection) {
	 * Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' Kan ikke koble til database.');
	 * }
	 * if(!mssql_select_db($this->config['db'])) {
	 * Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' Kan ikke koble til databasens tabell.');
	 * }
	 * Utils::log("DB CONNECTED");
	 *
	 * return $connection;
	 * }
	 * */


	/*
	public function query($sql) {
		//
		$this->conn = $this->getConnection();
		// Run query
		$query = mssql_query($sql, $this->conn);
		// On error
		if($query === false) {
			Response::error(500, 'Samtale med database feilet (SQL): ' . mssql_get_last_message());
		}
		// Response
		$response = array();
		//
		Utils::log("Rows returned: " . mssql_num_rows($query));
		// Loop rows and add to response array
		if(mssql_num_rows($query) > 0) {
			while($row = mssql_fetch_assoc($query)) {
				$response[] = $row;
			}
		}
		// Free the query result
		mssql_free_result($query);
		// Close link
		$this->closeConnection();

		//
		return $response;
	}
	*/