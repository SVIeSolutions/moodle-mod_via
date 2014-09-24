<?php

/**
 *  Visualization of a all via instances.
 *
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions last update 01/05/2013
 *
 */

global $CFG, $DB; 

require_once('../../../config.php');
require_once($CFG->dirroot. '/lib/moodlelib.php');
require_once($CFG->dirroot.'/mod/via/lib.php');

header('content-type: text/xml');

$str = file_get_contents("php://input");

$urlwhole = get_config('via', 'via_apiurl'); 
$url = explode('/', $urlwhole);

/* default values */
$status = 'ERROR';
$token = '';

if($str){ 

$xmlstr = <<<XML
$str
XML;

	$xml = new SimpleXMLElement($xmlstr);

	$login = (string)$xml->Login;
	$password = (string)$xml->Password;
	$password25 = (string)$xml->Password25; /* the password slating changed with moodle version 2.5 */
	$validated = false;
	
	$muser = $DB->get_record('user', array('username'=>$login));
	if($muser){
		$moodlepassword = base64_encode(hash('sha256', utf8_encode($muser->password), true));
		if($moodlepassword == $password){
			$validated = true;
		}else{
			$password25 = base64_decode($password25);
			$validated = validate_internal_user_password($muser, $password25);					
		}	
		
		if($validated){
			$api = new mod_via_api();
			$response = $api->UserGetSSOtoken(null, null, null, null, $muser->id);
			
			$response = explode('?', $response);
			$explose = explode('&', $response[1]);
			$token = explode('=', $explose[0]);
			$token = $token[1];
			
			$status = 'SUCCESS';
			$message = '<Message/>';	

		}else{	// if the user does not exist		
			$message = '<Message>AUTH_FAILED_BAD_PASSWORD</Message>';	
		}
	}else{  // if the user does not exist		
		$message = '<Message>UTH_FAILED_BAD_USERNAME</Message>';	
	}	
	
}else{  // if no xml is posted
	$message = '<Message>INVALID_XML_FORMAT</Message>';	
}

echo '<?xml version="1.0" encoding="UTF-8"?>
		<Authentication>
		<Result>
			<Status>'.$status.'</Status>
			'.$message.'
		</Result>
		<SSOToken>'.$token.'</SSOToken>
		<URLVia>'.$url[0].'//'.$url[2].'</URLVia>
		</Authentication>';
