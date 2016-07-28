<?php
	/**
	 * Required scope:
	 *    - admin
	 * @author Simon Skrødal
	 * @since  July 2016
	 */
	namespace Relay;

	###	   LOAD DEPENDENCIES	###
	require_once('relay/autoload.php');

	use Relay\Router\Router;
	use Relay\Utils\Utils;

	// GET/POST
	Utils::sanitizeInput();
	// Init
	$router = new Router();