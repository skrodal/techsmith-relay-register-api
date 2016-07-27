<?php

	class Kind {
		// DATAPORTEN OAuth implementation
		private $dataporten, $config;
		// Kickoff
		function __construct() {
			global $dataPortenClientConfig, $dataportenClient;
			$this->config = $dataPortenClientConfig;
			$this->dataporten = $dataportenClient;
		}
		// Access routes in the Kind API
		public function callAPI($route) {
			return $this->dataporten->get($this->config['ecampus-kind'] . $route);
		}
	}