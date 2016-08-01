<?php
	namespace Relay\Database;

	use Relay\Utils\Response;
	use Relay\Utils\Utils;

	ini_set('mssql.charset', 'UTF-8');

	class RelaySQLConnection {

		private $connection, $config;

		function __construct($config) {
			$this->config = $config;
		}

		/**
		 * @param $sql
		 *
		 * @return array
		 */
		public function query($sql) {
			//
			$this->connection = $this->getConnection();
			// Run query
			$query = mssql_query($sql, $this->connection);
			// On error
			if($query === false) {
				Response::error(500, 'Samtale med database feilet (SQL).');
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

		/**
		 *    Open MSSQL connection
		 */
		private function getConnection() {
			//
			$connection = mssql_connect($this->config['host'], $this->config['user'], $this->config['pass']);
			//
			if(!$connection) {
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' Kan ikke koble til database.');
			}
			//
			if(!mssql_select_db($this->config['db'])) {
				Response::error(500, $_SERVER["SERVER_PROTOCOL"] . ' Kan ikke koble til databasens tabell.');
			}

			Utils::log("DB CONNECTED");

			return $connection;
		}

		/**
		 *    Close MSSQL connection
		 */
		private function closeConnection() {
			if($this->connection !== false) {
				mssql_close($this->connection);
			}
			Utils::log("DB CLOSED");
		}

	}