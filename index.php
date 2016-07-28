<?php
	/**
	 * Required scope:
	 *    - admin
	 * @author Simon Skrødal
	 * @since  July 2016
	 */
	namespace RelayRegister;

	###	   LOAD DEPENDENCIES	###
	require_once('relay/autoload.php');

	use RelayRegister\Router\Router;
	use RelayRegister\Utils\Utils;

	// GET/POST
	Utils::sanitizeInput();
	// Init
	$router = new Router();