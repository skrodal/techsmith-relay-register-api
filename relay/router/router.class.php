<?php
	/**
	 * Required scope:
	 *    - admin
	 * @author Simon Skrødal
	 * @since  July 2016
	 */

	namespace Relay\Router;

	use Relay\Api\Relay;
	use Relay\Api\Subscribers;
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
			//$this->altoRouter->addMatchTypes(array('user' => '[0-9A-Za-z.@]++', 'org' => '[0-9A-Za-z.]++'));
			$this->altoRouter->addMatchTypes(array('org' => '[0-9A-Za-z.]++'));
			### DATAPORTEN
			$this->dataporten = new Dataporten();
			// Make all GET routes available
			$this->declareGetRoutes();
			// Make all POST routes available
			$this->declarePostRoutes();
			// Routes pertaining to CRUD on Subscribers table and DEV routes
			if($this->dataporten->isSuperAdmin()) {
				$this->declareAdminRoutes();
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
		 * Routes pertaining to CRUD on Subscribers Table (MySQL)
		 */
		private function declareAdminRoutes(){

			// List all orgs in table
			$this->altoRouter->addRoutes([
				array('GET', '/subscribers/', function () {
					$subscribers = new Subscribers();
					Response::result($subscribers->getSubscribers());
				}, 'Get all orgs in subscribers table (active and inactive).'),
			]);

			// Delete an org
			$this->altoRouter->addRoutes([
				array('DELETE', '/subscribers/delete/[org:orgId]/', function ($orgId) {
					$subscribers = new Subscribers();
					Response::result($subscribers->deleteSubscriber($orgId));
				}, 'Delete an org from the table.'),
			]);

			// Add an org
			$this->altoRouter->addRoutes([
				/** STUPID ad-blockers block calls to routes with the word '/affiliation/', hence changed to affiliation_access **/
				// $affiliation will only match 'employee' or 'member'
				array('POST', '/subscribers/create/[org:orgId]/affiliation_access/[employee|member:affiliation]/', function ($orgId, $affiliation) {
					$subscribers = new Subscribers();
					Response::result($subscribers->addSubscriber($orgId, $affiliation));
				}, 'Add a new org to the table.'),
			]);

			// Update affiliation
			$this->altoRouter->addRoutes([
				// $affiliation will only match 'employee' or 'member'
				array('POST', '/subscribers/update/[org:orgId]/affiliation_access/[employee|member:affiliation]/', function ($orgId, $affiliation) {
					$subscribers = new Subscribers();
					Response::result($subscribers->updateSubscriberAffiliationAccess($orgId, $affiliation));
				}, 'Update an orgs affiliation access.'),
			]);

			// Activate/deactivate subscription
			$this->altoRouter->addRoutes([
				array('POST', '/subscribers/update/[org:orgId]/active/[0|1:active_status]/', function ($orgId, $active_status) {
					$subscribers = new Subscribers();
					Response::result($subscribers->updateSubscriberStatus($orgId, $active_status));
				}, 'Update an orgs active status.'),
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
