<?php
	namespace Relay\Utils;

	use Relay\Conf\Config;

	/**
	 * @author Simon Skrødal
	 * @date   16/09/15
	 * @time   17:11
	 */
	class Utils {
		public static function log($text) {
			if(Config::get('utils')['debug']) {
				$trace  = debug_backtrace();
				$caller = $trace[1];
				error_log($caller['class'] . $caller['type'] . $caller['function'] . '::' . $caller['line'] . ': ' . $text);
			}
		}

		public static function getPresentationRequestBody(){
			$requestBody = json_decode(file_get_contents('php://input'), true);
			// No presentation content in the request body
			if(!$requestBody['presentation'] || empty($requestBody['presentation'])) {
				Response::error(400, "400 No Content.");
			}
			return $requestBody;
		}

		/**
		 * http://stackoverflow.com/questions/4861053/php-sanitize-values-of-a-array/4861211#4861211
		 */
		public static function sanitizeInput() {
			$_GET  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
			$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
		}

	}