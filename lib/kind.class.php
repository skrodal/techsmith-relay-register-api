<?php
	class Kind {
		// DATAPORTEN OAuth implementation
		protected $dataporten;
		// Kickoff
		function __construct($config) {
			$this->config = $config;
			// Use the DATAPORTEN OAuth implementation found in Dataporten.class.php
			$this->dataporten = new DataportenOAuth($this->config);
		}
		// Access routes in the Kind API
		public function callAPI($route) {
			return $this->dataporten->get($this->config['ecampus-kind'] . $route);
		}
	}