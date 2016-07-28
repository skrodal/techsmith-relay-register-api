<?php

	class Kind {
		// DATAPORTEN OAuth implementation
		private $dataportenClient;
		// Kickoff
		function __construct() {
			global $dataportenClient;
			$this->dataportenClient = $dataportenClient;
		}
		// Access routes in the Kind API
		public function callAPI($route) {
			return $this->dataportenClient->get($this->dataportenClient->getConfig('ecampus-kind') . $route);
		}
	}