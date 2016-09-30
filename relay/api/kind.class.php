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
			$response = $this->dataportenClient->get($this->dataportenClient->getConfig()['api_endpoints']['kind'] . $route);
			if(is_null($response)){
				return ['status' => false, 'code' =>  explode(' ',  $http_response_header[0])[1]];
			}
			return $response;
		}

		public function orgSubscriberDetails($serviceKindId, $orgId){
			return $this->callAPI('service/' . $serviceKindId . '/org/' . $orgId . '/');
		}
	}