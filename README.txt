Version 2014080162


/* for more information on the version please see mod/via/version.php */
/* This plugin required Via 6.2 */


Procedure for a NEW installation of the Via plugin for Moodle 2.3 to 2.7 :
*********************************************************************


1 - Copy the Via folder into the mod folder of your moodle.

2 - You will need to contact SVIeSolutions (http://sviesolutions.com/) for the following access codes.
	- API's URL
	- Via ID (CieID)
	- Via API ID (ApiID)
	- You can test the connexion + validate that you are connected to the correct version of Via

	- Moodle admin ID
	- You can test the key


3 - For invoicing and statistics purposes you may create categories in Via and make them available in Moodle. Note these need to be created in Via to be available.  For these to be available, the moodle administrator must check the ckeckbox in the settings page, it is them possible to chose which categories will be avaiable and set one as default.

    

Procedure for updating the Via plugin for Moodle 2.3 to 2.7 :
****************************************************************

1 - Copy the via folder into the mod folder of your moodle, replacing the old version.

2 - Remove the code that used to be needed for synchronising users in lib/enrollib.php 
    in function 'public function enrol_user()' 
    before 'if ($userid == $USER->id)'
    in moodle 2.0 this is at line +/- 1098
    in moodle 2.4 this is at line +/- 1317
	
	/********************************************/			
	/*Added in order to update Via participants */		
	require_once($CFG->dirroot.'/mod/via/lib.php');		
	add_participant_via($userid,  $instance->courseid);		
	/********************************************/

    AND 

    in function 'public function unenrol_user()'
    before '$DB->delete_records('user_lastaccess', array('userid'=>$userid, 'courseid'=>$courseid));'
    in moodle 2.0 this is at line +/- 1220
    in moodle 2.4 this is at line +/- 1448 

	/**********************************************/		
	/*Added in order to update Via participants */		
	require_once("$CFG->dirroot/mod/via/lib.php");		
	remove_participant_via($userid, $courseid);		
	/**********************************************/

    From this version on the users added by automatic enrollment will be synchronised with the cron, the modifications are therefore not instant and may take up to 10 minutes, depending at how often the cron runs.

4 - Add the moodle key and test it. This is new, if you have not received it yet please contact SVIesolutions.

5 - Empty all caches.

Your existing activities will not be affected  by these changes.




