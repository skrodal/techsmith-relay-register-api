<?php

	namespace Relay\Api;

	use Relay\Auth\DataportenClient;

	class Kind {
		private $dataportenClient;

		function __construct() {
			$this->dataportenClient = new DataportenClient();
		}

		// Access routes in the Kind API
		public function callAPI($route) {
			return $this->dataportenClient->get($this->dataportenClient->getConfig()['api_endpoints']['kind'] . $route);
		}

		public function orgSubscriberDetails($serviceKindId, $orgId){
			return $this->callAPI('service/' . $serviceKindId . '/org/' . $orgId . '/');
		}
	}