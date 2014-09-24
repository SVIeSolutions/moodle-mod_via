<?php

/**
 * A simple Web Services connection test script for the configured via server.
 * 
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions 
 */

    require_once('../../config.php');
	require_once('lib.php');
	global $CFG, $DB;

	require_login();

	if ($site = get_site()) {
        if (function_exists('require_capability')) {
            require_capability('moodle/site:config', context_system::instance());
        } else if (!isadmin()) {
            error("You need to be admin to use this page");
        }
    }
	
	$PAGE->set_context(context_system::instance());
	
	$site = get_site();
	
	
    $apiurl = required_param('apiurl', PARAM_NOTAGS);
    $cleid  = required_param('cleid', PARAM_NOTAGS);
    $apiid  = required_param('apiid', PARAM_NOTAGS);

	
	// Initialize $PAGE
	$PAGE->set_url('/mod/via/conntest.php');
	$PAGE->set_heading("$site->fullname");
	$PAGE->set_pagelayout('popup');
	
	// New with version 20140861 */
	// We validate the version */
	$required = '6.2';

	/// Print the page header
	echo $OUTPUT->header();

    echo $OUTPUT->box_start('center', '100%');
	
	$result = true;	
			$api = new mod_via_api();

			try {
				$response = $api->testconnection($apiurl, $cleid, $apiid);	
			}
			
			catch (Exception $e){
				$result = false;
				notify(get_string("error:".$e->getMessage(), "via"));	
			}
			
			if($result){
					
				echo '<div class="alert alert-block alert-info">'. get_string('connectsuccess', 'via'). '</div>';
				if(isset($response['BuildVersions'])){
					$build_version = $response['BuildVersions'];	
				}else{
					$build_version = 0;
				}
				if(validate_api_version($required,$build_version)){
					echo '<div class="alert alert-block alert-info">'.get_string('versionscompatible', 'via').'</div>';
				}else{
					echo '<div class="alert alert-block alert-error">'.get_string('versions_not_compatible', 'via').$required.'</div>';
				}

			}
			
    echo '<center><input type="button" onclick="self.close();" value="' . get_string('closewindow') . '" /></center>';

    echo $OUTPUT->box_end();
    echo $OUTPUT->footer($site);

?>
