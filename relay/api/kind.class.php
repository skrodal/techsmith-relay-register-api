<?php

	namespace Relay\Api;

	use Relay\Auth\DataportenClient;

	class Kind {
		private $dataportenClient;

		function __construct(DataportenClient $dataportenClient) {
			$this->dataportenClient = $dataportenClient;
		}

		// Access routes in the Kind API
		public function callAPI($route) {
			return $this->dataportenClient->get($this->dataportenClient->getConfig('ecampus-kind') . $route);
		}
	}