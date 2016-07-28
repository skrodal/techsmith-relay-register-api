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

	// Init
	$router = new Router();