<?php
	namespace Relay\Utils;
	use Relay\Conf\Config;
	use Mail;

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

		public static function sendMail($userInfo) {
			require_once 'Mail.php';
			$config = Config::getConfigFromFile(Config::get('auth')['mail']);
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

			$smtp = \Mail::factory('smtp',
				array('host' => $config['host'],
				      'port' => $config['port']));

			$mail = $smtp->send($userInfo['userEmail'], $headers, $message);

			if(\PEAR::isError($mail)) {
				Response::error(500, "Epost med kontodetailjer feilet (".$mail->getMessage()."). Noter passordet for din konto (".$userInfo['userPassword'].") før du forlater denne siden.");
			} else {
				return "Kontodetaljer ble sendt til " . $userInfo['userEmail'];
			}
		}
	}