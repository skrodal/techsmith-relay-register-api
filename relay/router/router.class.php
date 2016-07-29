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
	use Relay\Utils\Utils;
	use Relay\Vendor\AltoRouter;


	class Router {

		private $altoRouter, $relay, $kind, $dataporten, $dataportenClient;

		function __construct() {
			### ALTO ROUTER
			$this->altoRouter = new AltoRouter();
			$this->altoRouter->setBasePath(Config::get('altoRouter')['api_base_path']);
			### DATAPORTEN
			$this->dataporten       = new Dataporten();
			$this->dataportenClient = new DataportenClient();
			### RELAY
			$this->relay = new Relay($this->dataporten);
			### KIND
			$this->kind = new Kind($this->dataportenClient);
			// Make all GET routes available
			$this->declareGetRoutes();
			// Make all POST routes available
			$this->declarePostRoutes();
			// Activate routes
			$this->matchRoutes();

		}

		private function declareGetRoutes() {
			$this->altoRouter->addRoutes([
				// List all routes
				array('GET', '/', function () {
					Response::result(true, $this->altoRouter->getRoutes());
				}, 'All available routes.'),

				array('GET', '/service/testmode/', function () {
					Response::result(true, Config::get('settings')['dev_mode']);
				}, 'Testmode on/off.'),

				array('GET', '/kind/subscribers/', function () {
					Response::result(true, $this->kind->callAPI('service/' . $this->relay->kindId() . '/subscribers/'));
				}, 'Test kind.'),

				array('GET', '/relay/version/', function () {
					Response::result(true, $this->relay->getServiceVersion());
				}, 'TechSmith Relay version.'),

				array('GET', '/relay/me/', function () {
					Response::result(true, $this->relay->getUser());
				}, 'User account details..'),
			]);
		}

		private function declarePostRoutes() {
			$this->altoRouter->addRoutes([
				array('GET', '/relay/me/create/', function () {
					Response::result(true, $this->relay->getUser());
				}, 'Create user account.'),
			]);
		}


		private function matchRoutes() {
			$match = $this->altoRouter->match();

			if($match && is_callable($match['target'])) {
				Utils::sanitizeInput();
				call_user_func_array($match['target'], $match['params']);
			} else {
				Response::error(404, $_SERVER["SERVER_PROTOCOL"] . " The requested resource route could not be found.");
			}
		}


	}
