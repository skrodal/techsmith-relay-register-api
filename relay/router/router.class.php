<?php
	/**
	 * Required scope:
	 *    - admin
	 * @author Simon Skrødal
	 * @since  July 2016
	 */

	namespace Relay\Router;

	use Relay\Api\Kind;
	use Relay\Api\Relay;
	use Relay\Auth\Dataporten;
	use Relay\Auth\DataportenClient;
	use Relay\Conf\Config;
	use Relay\Utils\Response;
	use Relay\Vendor\AltoRouter;


	class Router {

		private $altoRouter, $relay, $kind, $dataporten, $dataportenClient;

		function __construct() {
			// Gatekeeper and provider of useful info
			$this->dataporten = new Dataporten();
			//
			$this->dataportenClient = new DataportenClient();
			//
			$this->relay = new Relay($this->dataporten);
			//
			$this->kind = new Kind($this->dataportenClient);
			### 	  ALTO ROUTER 		###
			$this->altoRouter = new AltoRouter();
			$this->altoRouter->setBasePath(Config::get('altoRouter')['api_base_path']);
			//
			$this->declareGetRoutes();
			$this->declarePostRoutes();
			if($this->dataporten->isSuperAdmin()) {
				$this->declareDevRoutes();
			}
			//
			$this->matchRoutes();

		}

		private function declareGetRoutes() {
			$this->altoRouter->addRoutes([
				// List all routes
				array('GET', '/', function () {
					Response::result(true, $this->altoRouter->getRoutes());
				}, 'All available routes.'),

				array('GET', '/version/', function () {
					Response::result(true, $this->relay->getServiceVersion());
				}, 'TechSmith Relay version.'),

				array('GET', '/me/', function () {
					Response::result(true, $this->relay->getUser());
				}, 'User account details..'),
			]);
		}

		private function declarePostRoutes() {
			$this->altoRouter->addRoutes([
				array('GET', '/me/create/', function () {
					Response::result(true, $this->relay->getUser());
				}, 'Create user account.'),
			]);
		}

		private function declareDevRoutes() {
			if($this->dataporten->isSuperAdmin()) {
				$this->altoRouter->addRoutes([
					array('GET', '/dev/something/', function () {
						Response::result(true, "SOMETHING");
					}, 'Test route.')
				]);
			}
		}

		private function matchRoutes() {
			$match = $this->altoRouter->match();

			if($match && is_callable($match['target'])) {
				sanitizeInput();
				call_user_func_array($match['target'], $match['params']);
			} else {
				Response::error(404, $_SERVER["SERVER_PROTOCOL"] . " The requested resource route could not be found.");
			}
		}


	}
