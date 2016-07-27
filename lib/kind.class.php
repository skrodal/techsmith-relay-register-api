<?php

	class Kind {
		// DATAPORTEN OAuth implementation
		private $dataportenClient, $config;
		// Kickoff
		function __construct() {
			global $dataPortenClientConfig, $dataportenClient;
			$this->config = $dataPortenClientConfig;
			$this->dataportenClient = $dataportenClient;
		}
		// Access routes in the Kind API
		public function callAPI($route) {
			return $this->dataportenClient->get($this->config['ecampus-kind'] . $route);
		}
	}