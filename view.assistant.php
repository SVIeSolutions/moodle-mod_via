<?php  

/**
 *  Redirection to assistance on Via
 *
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions 
 */

    require_once("../../config.php");
	require_once($CFG->dirroot.'/mod/via/api.class.php'); 
 
   $redirect = optional_param('redirect', NULL, PARAM_INT);
   
   $viaid = optional_param('viaid', 0, PARAM_INT);
   $courseid = optional_param('courseid', 0, PARAM_INT);
   
   if(isset($viaid)){
		$cm = get_coursemodule_from_id('via', $viaid);
		require_login($courseid, false, $cm);
  }

	$api = new mod_via_api();

	try {
		if($response = $api->UserGetSSOtoken(NULL, $redirect)){
			redirect($response);		
		}else{
			print_error("You can't access this activity for now.");	
		}
	}
	catch (Exception $e){
		$result = false;
		print_error(get_string("error:".$e->getMessage(), "via"));
	}

?>
