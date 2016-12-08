<?php
	/**
	 * Created by PhpStorm.
	 * User: Wintermild
	 * Date: 08/12/2016
	 * Time: 14:16
	 */

	namespace Relay\Api;


	use Relay\Conf\Config;
	use Relay\Database\SubscribersMySQLConnection;

	class Subscribers {
		private $subscribersMySQLConnection, $config;

		public function __construct() {
			$this->config                     = Config::getConfigFromFile(Config::get('auth')['subscribers_mysql']);
			$this->subscribersMySQLConnection = new SubscribersMySQLConnection($this->config);
		}

		public function getSubscribers() {
			$table = $this->config['table'];

			return $this->subscribersMySQLConnection->query("SELECT * FROM $table");
		}

		/**
		 * Remove a record in the table.
		 *
		 * Returns true if a line was deleted.
		 *
		 * @param $org
		 *
		 * @return bool
		 */
		public function deleteSubscriber($org){
			return $this->subscribersMySQLConnection->deleteOrg($org);
		}

		public function addSubscriber($org){
			// TODO CHECK POST
			return $this->subscribersMySQLConnection->createOrg($org);
		}

		/**
		 * Query table for affiliation access for a given org
		 *
		 * @param $org
		 *
		 * @return array
		 */
		public function getOrgAffiliationAccess($org) {
			$table = $this->config['table'];

			return $this->subscribersMySQLConnection->query("SELECT affiliation_access FROM $table WHERE org = '$org' AND active = 1");
		}
	}