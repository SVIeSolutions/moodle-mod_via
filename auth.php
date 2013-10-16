<?php

/**
 *  Visualization of a all via instances.
 *
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions
 */

global $CFG; 

require_once('../../config.php');
require_once($CFG->dirroot. '/lib/moodlelib.php');
require_once($CFG->dirroot.'/mod/via/lib.php');

header('content-type: text/xml');

$str = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Authentication>
<Login></Login>
<Password></Password>
</Authentication>
XML;


$xml = new SimpleXMLElement($str);

$login = (string)$xml->Login;
$password = (string)$xml->Password;

$muser = authenticate_user_login($login, $password);

if($muser){
	$api = new mod_via_api();
	$response = $api->UserGetSSOtoken(null, null, null, $muser->id);
	
	$response = explode('=', $response);
	$url = $response[0];
	$token = $response[1];
	
	echo '<?xml version="1.0" encoding="UTF-8"?>
			<Authentication>
			<Result>
				<Status>SUCCESS</Status>
				<Message/>
			</Result>
			<SSOToken>'.$token.'</SSOToken >
			<URLVia>http://via.sviesolutions.com/ </ URLVia>
			</Authentication>';

}else{
	echo 'is not a moodle user';
}





