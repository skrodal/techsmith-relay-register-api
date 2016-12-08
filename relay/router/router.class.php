<?php
	/**
	 * Required scope:
	 *    - admin
	 * @author Simon Skrødal
	 * @since  July 2016
	 */

	namespace Relay\Router;

	use Relay\Api\Relay;
	use Relay\Auth\Dataporten;
	use Relay\Conf\Config;
	use Relay\Utils\Response;
	use Relay\Vendor\AltoRouter;


	class Router {

		private $altoRouter, $relay, $dataporten;

		function __construct() {
			### ALTO ROUTER
			$this->altoRouter = new AltoRouter();
			$this->altoRouter->setBasePath(Config::get('altoRouter')['api_base_path']);
			### DATAPORTEN
			$this->dataporten = new Dataporten();
			// Make all GET routes available
			$this->declareGetRoutes();
			// Make all POST routes available
			$this->declarePostRoutes();
			if($this->dataporten->isSuperAdmin()) {
				$this->declareDevRoutes();
			}
			// Activate routes
			$this->matchRoutes();

		}

		/**
		 * INFO ROUTES
		 *
		 * @return string
		 */
		private function declareGetRoutes() {
			$this->altoRouter->addRoutes([
				// List all routes
				array('GET', '/', function () {
					Response::result($this->altoRouter->getRoutes());
				}, 'All available routes.'),
				//
				array('GET', '/relay/version/', function () {
					$this->relay = new Relay($this->dataporten);
					Response::result($this->relay->getRelayVersion());
				}, 'TechSmith Relay version.'),
				//
				array('GET', '/relay/me/', function () {
					$this->relay = new Relay($this->dataporten);
					Response::result($this->relay->getRelayUser());
				}, 'User account details..'),
				// FROM KIND
				/*
				array('GET', '/relay/org/subscription/', function () {
					$this->relay = new Relay($this->dataporten);
					Response::result($this->relay->getSubscriberDetails());
				}, 'Get subscription details for logged on users org.'),
				*/
				// WHICH DB ARE WE READING FROM/WRITING TO?
				array('GET', '/service/testmode/', function () {
					Response::result(Config::get('settings')['dev_mode']);
				}, 'Testmode on/off.'),

			]);
		}

		/**
		 * CREATES THE NEW USER ACCOUNT
		 *
		 * @return string
		 */
		private function declarePostRoutes() {
			$this->altoRouter->addRoutes([
				array('POST', '/relay/me/create/', function () {
					$this->relay = new Relay($this->dataporten);
					Response::result($this->relay->createRelayUser());
				}, 'Create user account.'),
			]);
		}

		/**
		 * DEV PATHS - ONLY AVAILABLE IF LOGGED ON USER IS FROM UNINETT
		 *
		 * @return string
		 */
		private function declareDevRoutes() {
			$this->altoRouter->addRoutes([
				array('GET', '/server/', function () {
					$this->relay = new Relay($this->dataporten);
					$response['SERVER']                           = $_SERVER;
					$response['APACHE']                           = apache_request_headers();
					$response['USER']['INFO']                     = $this->dataporten->getUserInfo();
					$response['USER']['GROUPS']                   = $this->dataporten->getUserGroups();
					$response['RELAY']['USER']['ALL']             = $this->relay->getRelayUser();
					$response['RELAY']['USER']['ID']              = $this->relay->getRelayUserId();
					$response['RELAY']['USER']['HAS_ACCOUNT']     = $this->relay->getRelayAccountExists();
					$response['RELAY']['USER']['PROFILE_ID']      = $this->relay->getRelayProfileIdFromAffiliation();
					$response['RELAY']['DB']['tblUser']           = $this->relay->getSchema('tblUser');
					$response['RELAY']['DB']['tblUserProfile']    = $this->relay->getSchema('tblUserProfile');
					$response['RELAY']['DB']['tblRoleMembership'] = $this->relay->getSchema('tblRoleMembership');
					$response['RELAY']['VERSION']                 = $this->relay->getRelayVersion();
					$response['RELAY']['ORG']['SUBSCRIPTION']     = $this->relay->getSubscriberDetails();
					Response::result($response);
				}, 'Dev route for testing.'),

				array('GET', '/server/mail/send/', function () {
					$this->relay = new Relay($this->dataporten);
					$response = $this->relay->sendTestMail();
					Response::result($response);
				}, 'Dev route to test sending email.'),
			]);
		}


		private function matchRoutes() {
			$match = $this->altoRouter->match();

			if($match && is_callable($match['target'])) {
				call_user_func_array($match['target'], $match['params']);
			} else {
				Response::error(404, "URLen det spørres etter finnes ikke.");
			}
		}


	}
