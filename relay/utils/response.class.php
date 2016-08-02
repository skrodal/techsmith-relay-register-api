<?php
	namespace Relay\Utils;
	use Relay\Conf\Config;

	/**
	 *
	 * @author Simon Skrødal
	 * @since  July 2016
	 */

	class Response {

		public static function result($result) {
			// Ensure no caching occurs on server (correct for HTTP/1.1)
			header('Cache-Control: no-cache, no-store, must-revalidate');
			header("Expires: Fri, 10 Oct 1980 04:00:00 GMT"); // Date in the past
			// CORS
			header("Access-Control-Allow-Origin: *");
			header("Access-Control-Allow-Credentials: true");
			header("Access-Control-Allow-Methods: HEAD, GET, OPTIONS");
			header("Access-Control-Allow-Headers: Authorization, X-Requested-With, Origin, Accept, Content-Type");
			header("Access-Control-Expose-Headers: Authorization, X-Requested-With, Origin, Accept, Content-Type");
			//
			header('content-type: application/json; charset=utf-8');
			//
			http_response_code(200);
			// Return response
			exit(json_encode(
				array(
					'status' => true,
					'data'   => $result
				)
				, JSON_UNESCAPED_UNICODE));
		}

		public static function error($code, $error) {
			// Ensure no caching occurs on server (correct for HTTP/1.1)
			header('Cache-Control: no-cache, no-store, must-revalidate');
			header("Expires: Fri, 10 Oct 1980 04:00:00 GMT"); // Date in the past
			// CORS
			header("Access-Control-Allow-Origin: *");
			header("Access-Control-Allow-Credentials: true");
			header("Access-Control-Allow-Methods: HEAD, GET, OPTIONS");
			header("Access-Control-Allow-Headers: Authorization, X-Requested-With, Origin, Accept, Content-Type");
			header("Access-Control-Expose-Headers: Authorization, X-Requested-With, Origin, Accept, Content-Type");
			//
			header('content-type: application/json; charset=utf-8');
			http_response_code($code);

			exit(json_encode(
				array(
					'status'  => false,
					'message' => $error
				)
			));
		}

		public static function sendMail($userInfo) {

			$config = Config::getConfigFromFile(Config::get('auth')['mail']);
			require_once $config['mail_path'];

			$message = "
                        <html>
                        <head>
                            <title>TechSmith Relay kontoinformasjon</title>
                        </head>
                        <body>
                            <p>Hei " . $userInfo['userFirstName'] . ",</p>
                            <p>Din TechSmith Relay konto er registrert og klar til bruk:</p>
                            
                            <table style='width: 100%;'>
                                <tr>
                                    <td style='width: 150px;'>Navn: </td>
                                    <td> " . $userInfo['userDisplayName'] . "</td>
                                </tr>           
                                <tr>
                                    <td>Epost: </td>
                                    <td> " . $userInfo['userEmail'] . "</td>
                                </tr>           
                                <tr>
                                    <td>Brukernavn: </td>
                                    <td> " . $userInfo['userName'] . "</td>
                                </tr>
                                <tr>
                                    <td>Passord: </td>
                                    <td> " . $userInfo['userPassword'] . "</td>
                                </tr>
                            </table>
                            <br/>                    
                            <p><strong>Slik kommer du i gang:</strong></p>
                            
                            1. Logg inn p&aring; <a href='https://relay.uninett.no/'>relay.uninett.no</a> med ditt brukernavn og passord<br/>\r\n
                            2. Last ned programvare for Windows eller Mac under menyvalg 'Download Recorders' og installer denne p&aring; din datamaskin<br/>\r\n
                            3. Start opp TechSmith Relay programvare og logg inn med ditt brukernavn og passord<br/>\r\n
                            4. Du er n&aring; klar til &aring; gj&oslash;re opptak!<br/>\r\n
                            
                            <br/>
                            <p><strong>Support:</strong></p>

                            <p>
                                Du finner brukerveiledning for TechSmith Relay p&aring; <a href='https://support.ecampus.no/techsmithrelay/'>support.ecampus.no</a>. 
                                Trenger du mer hjelp tar du kontakt med brukerst&oslash;tte ved ditt l&aelig;rested.
                            </p>

                            <p>
                                Med vennlig hilsen, <br /><br />
                                UNINETT
                            </p>                
                        </body>
                        </html>";


			$headers = array('From'                      => $config['from'],
			                 'To'                        => $userInfo['userEmail'],
			                 'Subject'                   => $config['subject'],
			                 'MIME-Version'              => '1.0',
			                 'Content-Type'              => 'text/html; charset=utf-8',
			                 'Content-Transfer-Encoding' => '8bit',
			);

			$smtp = Mail::factory('smtp',
				array('host' => $config['host'],
				      'port' => $config['port']));

			$mail = $smtp->send($userInfo['userEmail'], $headers, $message);

			if(PEAR::isError($mail)) {
				Response::error(500, "Epost med kontodetailjer feilet (".$mail->getMessage()."). Noter passordet for din konto (".$userInfo['userPassword'].") før du forlater denne siden.");
			} else {
				return "Kontodetaljer ble sendt til " . $userInfo['userEmail'];
			}
		}
	}