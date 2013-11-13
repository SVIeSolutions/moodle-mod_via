<?php

/**
 * Basic functions and constants for the Via module.
 *
 * @package   mod-via
 * @copyright 2011-2013 SVIeSolutions
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/via/api.class.php');

function via_supports($feature) {
	switch($feature) {
		
		case FEATURE_MOD_INTRO:               return false;
		case FEATURE_BACKUP_MOODLE2:          return true;
		case FEATURE_GROUPS:                  return true;
		case FEATURE_GROUPINGS:               return true;
		case FEATURE_GROUPMEMBERSONLY:        return true;
		case FEATURE_SHOW_DESCRIPTION:        return true;	
		case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
		case FEATURE_GRADE_HAS_GRADE:         return true;			

		default: return null;
	}
}


/**
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will create a new instance and return its ID number.
 *
 * @param object $via An object from the form in mod_form.php
 * @return int The ID of the newly inserted via record
 */
function via_add_instance($via) {
	global $CFG, $DB, $USER;
	
	
	via_data_postprocessing($via);
	
	$via->lang = current_language();
	$via->timecreated  = time();
	$via->timemodified = time();

	
	if($CFG->via_moodleemailnotification){
		$via->moodleismailer = 1;	
	}else{
		$via->moodleismailer = 0;
	}
	
	if($CFG->via_categories == 0){
		$via->category = 0;
	}

	$api = new mod_via_api();
	
	try {
		$response = $api->activityCreate($via);
	}
	catch (Exception $e){
		print_error(get_string("error:".$e->getMessage(), "via"));
		return false;
	}
	
	// we add the activity creator as presentor
	if($new_activity = $DB->insert_record('via', $via)){
		
		// We add the presentor
		$presenteradded = via_add_participant($USER->id, $new_activity, 2);
		if($presenteradded){			
			// we remove the moodle_admin from the activity
			$moodleid = false;
			$api->removeUserActivity($via->viaactivityid, $CFG->via_adminid, $moodleid);
		}
		
		$context = get_context_instance( CONTEXT_COURSE, $via->course );

		if($via->enroltype == 0){ // if automatic enrol
			//We add users
			$query = 'select a.id as rowid, a.*, u.* from mdl_role_assignments as a, mdl_user as u where contextid=' . $context->id . ' and a.userid=u.id';
			$users = $DB->get_records_sql( $query );
			foreach($users as $user) {
				if(has_capability('moodle/course:viewhiddenactivities', $context, $user->id)){
					via_add_participant($user->id, $new_activity, 3);	
				}else{			
					via_add_participant($user->id, $new_activity, 1);
				}
			}
		}
	}
	
	$via->id = $new_activity;
	
	via_grade_item_update($via);
	
	if ($via->activitytype != 2) { //activitytype 2 = permanent activity, we do not add these to calendar
		// adding activity in calendar
		$event = new stdClass();
		$event->name        = $via->name;
		$event->description = $via->description;
		$event->courseid    = $via->course;
		$event->groupid     = 0;
		$event->userid      = 0;
		$event->modulename  = 'via';
		$event->instance    = $new_activity;
		$event->eventtype   = 'due';
		$event->timestart   = $via->datebegin;
		$event->timeduration = $via->duration*60;

		add_event($event);	
	}
	
	return $new_activity;
}

function get_enrolid($course, $userid){
	global $DB;
	
	$enrollments = $DB->get_records('enrol', array('courseid'=>$course, 'status'=>0));
	$enrollmentids = array();
	foreach ($enrollments as $enrol){
		$enrollmentids[] = $enrol->id;
	}
	foreach($enrollmentids as $enrolid){
		$enrollment = $DB->get_record('user_enrolments', array('userid'=>$userid, 'enrolid'=>$enrolid));
		if($enrollment){
			return $enrollment->enrolid;
			continue;
		}
	}
	// may be null in the case of a manager or admin user which can be added as animator or presentor without being enrolled in the course.
	return null;
}
/**
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $via An object from the form in mod_form.php
 * @return bool Success/Fail.
 */
function via_update_instance($via) {
	global $CFG, $DB;
	
	via_data_postprocessing($via);
	$via->id = $via->instance;
	$via->lang = current_language();
	$via->timemodified = time();
	
	if($CFG->via_moodleemailnotification){
		$via->moodleismailer = 1;	
	}else{
		$via->moodleismailer = 0;
	}
	if($CFG->via_categories == 0){
		$via->category = 0;
	}
	
	$viaactivity = $DB->get_record('via', array('id'=>$via->id));
	if($via->pastevent == 1){
		$via->datebegin = $viaactivity->datebegin;
		$via->activitytype = $viaactivity->activitytype;
	}
	
	$via->viaactivityid = $viaactivity->viaactivityid;
	
	$api = new mod_via_api();
	
	try {
		$response = $api->activityEdit($via);		
	}
	catch (Exception $e){
		print_error(get_string("error:".$e->getMessage(), "via"));
		return false;
	}
	
	via_grade_item_update($via); 
	
	// updates activity in calendar
	$event = new stdClass();
	
	if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'via', 'instance'=>$via->id))) {	
		$event->name        = $via->name;
		$event->description = $via->description;
		$event->timestart   = $via->datebegin;
		$event->timeduration = $via->duration*60;
		
		update_event($event);
	} else {
		$event = new stdClass();
		$event->name        = $via->name;
		$event->description = $via->description;
		$event->courseid    = $via->course;
		$event->groupid     = 0;
		$event->userid      = 0;
		$event->modulename  = 'via';
		$event->instance    = $via->id;
		$event->eventtype   = 'due';
		$event->timestart   = $via->datebegin;
		$event->timeduration = $via->duration*60;
		
		add_event($event);
	}
	
	return $DB->update_record('via', $via);
}

/**
 * Given an ID of an instance of a job via, this function will
 * permanently delete the instance and any data that depends on it.
 *
 * @param int $id ID of the via instance.
 * @return bool Success/Failure.
 */
function via_delete_instance($id) {
	global $DB;
	$result = true;

	$via = $DB->get_record('via', array('id'=>$id));
	
	if(!$via->backupvia){ 
		// if activity was never backuped on Moodle, delete only moodle instance AND via instance
		$api = new mod_via_api();
		
		try {
			$response = $api->activityEdit($via, 2);
		}
		catch (Exception $e){
			$result = false;
			print_error(get_string("error:".$e->getMessage(), "via"));
			return false;		
		}
	}else{
		// if activity was backuped on Moodle, do not delete actvity on VIA
		// We only unenrol participants
		$result = via_remove_participants(array($via));
	}
	
	if($result){	
		if (!$DB->delete_records('via', array('id'=>$id))) {
			$result = false;
		}
		if (!$DB->delete_records('via_participants', array('activityid'=>$id))) {
			$result = false;
		}
		if (!$DB->delete_records('event', array('modulename'=>'via', 'instance'=>$id))) {
			$result = false;
		}
		via_grade_item_delete($via);
	}

	return $result;
}

/**
 * Converts certain form values before entering them in the database.
 *
 * @param object $via An object from the form in mod_form.php
 */
function via_data_postprocessing(&$via) {
	$via->profilid = $via->profilid;
	
	if(isset($via->activitytype)){
		switch($via->activitytype){
			case 0:
				$via->activitytype = 1;
				break;
			case 1:
				$via->activitytype = 2;
				break;
			default:
				$via->activitytype = 1;
				break;
		}
	}else{
		$via->activitytype = 1;
	}
}


/**
 * Gets the categories created in Via by the administrators 
 *
 * @return an array of the different categories to create drop down list in the mod_form.
 */
function get_via_categories(){
	global $CFG;
	
	$result = true;
	
	$api = new mod_via_api();

	try {
		$response = $api->getCategories();
	}		
	catch (Exception $e){
		notify(get_string("error:".$e->getMessage(), "via"));
	}
	
	return $response;
}

/**
 * Updates Moodle Via info with data coming from VIA server.
 *
 * @param object $values An object from view.php containg via activity infos
 * @return object containing new infos for activity.
 */
function update_info_database($values){
	global $CFG, $DB;
	
	$result = true;
	$via = new stdClass();
	$svi = new stdClass();
	
	foreach($values as $key=>$value){
		$via->$key = $value;
	}

	$api = new mod_via_api();
	
	try {
		$info_svi = $api->activityGet($via);

		foreach($info_svi as $key=>$info){
			$svi->$key = $info;
		}
	}
	catch (Exception $e){
		if($e->getMessage() == "ACTIVITY_DOES_NOT_EXIST"){
			$error = get_string("activitywaserased", "via");
		}else{
			$error = get_string("error:".$e->getMessage(), "via");
		}
		$result = false;
	}
	
	if($result){
		
		$via->name = $svi->Title;
		$via->description = $via->description;
		$via->invitemsg = $via->invitemsg;
		$via->profilid = $svi->ProfilID;
		
		$via->isreplayallowed = $svi->IsReplayAllowed;
		$via->roomtype = $svi->RoomType;
		$via->audiotype = $svi->AudioType;
		$via->activitytype = $svi->ActivityType;
		$via->recordingmode = $svi->RecordingMode;
		$via->recordmodebehavior = $svi->RecordModeBehavior;
		
		if(!$CFG->via_moodleemailnotification){
			$via->remindertime = via_get_remindertime_from_svi($svi->ReminderTime); //the reminder email is sent with Moodle, not VIA.
			$via->moodleismailer = 0;
		}else{
			$via->moodleismailer = 1;			
		}
		
		$via->datebegin = strtotime($svi->DateBegin);
		
		$via->duration = $svi->Duration;
		$via->waitingroomaccessmode = $svi->WaitingRoomAccessMode;
		$via->timemodified = time();
		$via->nbConnectedUsers = $svi->NbConnectedUsers;
		
		if($DB->update_record('via', $via)){
			
			$event = new stdClass();
			require_once($CFG->dirroot.'/calendar/lib.php');
			
			if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'via', 'instance'=>$via->id))) {	
				$event->name        = $via->name;
				$event->description = $via->description;
				$event->timestart   = $via->datebegin;
				$event->timeduration = $via->duration*60;
				
				$calendarevent = calendar_event::load($event->id);
				$calendarevent->update($event, $checkcapability = false);
				
			} else {
				$event = new stdClass();
				$event->name        = $via->name;
				$event->description = $via->description;
				$event->courseid    = $via->course;
				$event->groupid     = 0;
				$event->userid      = 0;
				$event->modulename  = 'via';
				$event->instance    = $via->id;
				$event->eventtype   = 'due';
				$event->timestart   = $via->datebegin;
				$event->timeduration = $via->duration*60;
				
				calendar_event::create($event);
			}
		}
		
		return $via;		
	}else{
		print_error($error, "$CFG->wwwroot/course/view.php?id=$via->course");
	}
}

/**
 * Get the list of potential participants to via. 
 *
 * @param object $viacontext the via context.
 * @param integer $groupid the id of a group, or 0 for all groups.
 * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
 * @param string $sort sort order. As for get_users_by_capability.
 * @return array list of users.
 */
function via_get_potential_participants($viacontext, $groupid, $fields, $sort) {
	return get_users_by_capability($viacontext, 'mod/via:view', $fields, $sort, '', '', $groupid, '', false, true);
}

/**
 * Returns list of user objects that are participants to this via activity
 *
 * @param object $course the course
 * @param forum $via the via activity
 * @param integer $groupid group id, or 0 for all.
 * @param object $context the via context, to save re-fetching it where possible.
 * @return array list of users.
 */

function via_participants($course, $via, $groupid=0, $participanttype, $context = NULL) {
	global $CFG, $DB;

	if ($groupid) {
		$grouptables = ", mdl_groups_members gm ";
		$groupselect = "AND gm.groupid = $groupid AND u.id = gm.userid";
	} else  {
		$grouptables = '';
		$groupselect = '';
	}
	
	$results = $DB->get_records_sql('SELECT distinct u.id, u.username, u.firstname, u.lastname, u.maildisplay, u.mailformat, u.maildigest, u.emailstop, u.imagealt, u.idnumber,
                                   u.email, u.city, u.country, u.lastaccess, u.lastlogin, u.picture, u.timezone, u.theme, u.lang, u.trackforums, u.mnethostid
								   FROM mdl_user u,
                                   mdl_via_participants s '. $grouptables.'
									WHERE s.activityid = '.$via->id.'
									AND s.userid = u.id 
									AND s.participanttype = '. $participanttype.' 
									AND u.deleted = 0  '. $groupselect.' 
									ORDER BY u.email ASC ');

	static $guestid = null;

	if (is_null($guestid)) {
		if ($guest = guest_user()) {
			$guestid = $guest->id;
		} else {
			$guestid = 0;
		}
	}

	// Guest user should never be subscribed to a via activity.
	unset($results[$guestid]);

	return $results;
}

/**
 * Updates confirmation status for each participant of a given via activity on VIA
 *
 * @param object $via the via activity
 * @param integer $userid the user id we are updating his status
 * @return integer the user confirmation status or FALSE if user is no longer enrol in this activity.
 */
function via_update_moodle_confirmationstatus($via, $userid){
	global $CFG, $DB;
	
	$result = true;
	$via->userid = $userid;
	
	$api = new mod_via_api();

	try {
		$response = $api->getUserActivity($via);
	}		
	catch (Exception $e){
		// user is no longer a participant in this activity on Via, so we remove him from de moodle activity
		if($e->getMessage() == "USER_NOT_ASSOCIATE"){
			$DB->delete_records('via_participants', array('userid'=>$userid, 'activityid'=>$via->id));
			return false;
		}else{
			$result = false;
			notify(get_string("error:".$e->getMessage(), "via"));
		}
	}
	if(($user_roles = $DB->get_records_sql("SELECT * FROM mdl_via_participants WHERE userid=$userid AND activityid=$via->id")) && $result){
		if(count($user_roles)>0){
			foreach($user_roles as $user_role){
				if($user_role->confirmationstatus != $response['ConfirmationStatus']){
					// confitmation status has change on via. Update Moodle
					$user_role->confirmationstatus = $response['ConfirmationStatus'];
					$DB->update_record("via_participants", $user_role);
				}
				return $user_role->confirmationstatus;
			}
		}
	}
}


/**
* Adds user to the participant list
*
* @param integer $userid the user id we are updating his status
* @param integer $viaid the via activity ID
* @param integer $type the user type (presentator, animator or partcipant)
* @param integer $confirmationstatus if enrol directly on Via, get his confirmation status 
* @return bool Success/Fail.
*/
function via_add_participant($userid, $viaid, $type, $confirmationstatus=NULL) {
	global $CFG, $DB;
	
	$update = true;
	$sub = new stdClass();
	$sub->id = null;
	
	if ($participant = $DB->get_record('via_participants', array('userid'=>$userid, 'activityid'=> $viaid))) {	
		if($type == $participant->participanttype){
			return true; 
		}
		if($participant->participanttype == 2){ // presentator
			$update = false;
			$added = false;
		}
		if($participant->participanttype != $type && $update && $type != 2){ // animator
			$update = $update;
			$sub->id = $participant->id;
		}
	}
	
	$sub->userid  = $userid;	
	$sub->activityid = $viaid;
	
	$viaactivity = $DB->get_record('via', array('id'=>$viaid));
	$enrolid = get_enrolid($viaactivity->course, $userid); 
	if($enrolid){
		$sub->enrolid = $enrolid;
	}else{
		$sub->enrolid = 0; // we need this 0 later to keep the user not enrolled in the coruse not to be deleted when synching users.
	}
	$sub->viaactivityid = $viaactivity->viaactivityid;
	$sub->participanttype = $type;
	$sub->timemodified = time();
	$sub->timesynched = NULL;
	
	if(!$confirmationstatus){
		$sub->confirmationstatus = 1; // not confirmed
	}else{
		$sub->confirmationstatus = $confirmationstatus;
	}
	
	if($update){ // update only if given a higher role then the other (if there is)
		$api = new mod_via_api();
		
		try {
			$response = $api->addUserActivity($sub);
			$sub->timesynched = time();
			if($sub->id){
				$added = $DB->update_record("via_participants", $sub);
			}else{
				$added = $DB->insert_record("via_participants", $sub);
			}
			
		}catch (Exception $e){
			$via_user = $DB->get_record('via_users', array('userid'=>$userid));
			$DB->insert_record('via_log', array('userid'=>$userid, 'viauserid'=>$via_user->viauserid, 'activityid'=>$viaid, 'action'=>'call to API with via_add_participant', 'result'=>$e->getMessage(), 'time'=>time()));
			$added = false;
		}
	}
	return $added;
}

/**
* Adds users to the participant list
 *
* @param array $users array of users
* @param integer $viaid the via activity ID
* @param integer $type the user type (presentator, animator or partcipant)
* @return bool Success/Fail.
*/
function via_add_participants($users, $viaid, $type) {
	global $DB;
	
	foreach($users as $user){
		$result = via_add_participant($user->id, $viaid, $type);
	}

	return $result;
}

/**
* update participants list on moodle with via infos
 *
* @param object $via the via
* @param integer $userid the user id if updating only one participant
* @return bool Success/Fail.
*/
function via_update_participants_list($via, $userid=NULL){

	$result = true;
	
	$api = new mod_via_api();

	try {
		$response = $api->getUsersListActivity($via->viaactivityid);
	}
	catch (Exception $e){
		notify(get_string("error:".$e->getMessage(), "via"));
		$result = false;
	}
	
	if(count($response)>1 && $result){		
		if(!$userid){
			add_new_via_participants($response, $via->id);
			remove_old_via_participants($response, $via->id);
		}else{
			return add_new_via_participants($response, $via->id, $userid);
		}
	}else{
		$result = false;
	}
	
	return $result;

}

/**
* get participants list on via 
 *
* @param object $via the via
* @return list.
* 
*/
function get_via_participants_list($via){

	$result = true;
	
	$api = new mod_via_api();

	try {
		$result = $api->getUsersListActivity($via->viaactivityid);
	}
	catch (Exception $e){
		notify(get_string("error:".$e->getMessage(), "via"));
		$result = false;
	}
	
	return $result;
}

/**
* adding new participants that were added directly on via
 *
* @param object $response list of users from Via
* @param integer $activityid the via id
* @param integer $userid user id if adding a specific user
* @return bool Success/Fail.
*/
function add_new_via_participants($response, $activityid, $userid=NULL){
	
	foreach($response as $user){
		if(count($user)>0){
			if(isset($user['UserID'])){
				if($userid && $user['UserID'] == $userid){
					add_new_via_participant($user, $activityid);		
					return true;
				}elseif(!$userid){
					add_new_via_participant($user, $activityid);						
				}
			}
		}
	}
	if($userid){
		return false;
	}else{
		return true;	
	}
}

/**
* adding a new participant that was added directly on via
 *
* @param object $user user data from via
* @param integer $activityid the via id
*/
function add_new_via_participant($user, $activityid){
	global $CFG, $DB;

	if($u = $DB->get_record('via_users', array('viauserid'=>$user['UserID']))){
		// verifies if user is already enroled in this activity on moodle
		if($participants = $DB->get_records_sql("SELECT * FROM mdl_via_participants WHERE userid={$u->userid} AND activityid=$activityid")){
			// already enrol			
			foreach($participants as $participant){
				
				$modif_type = false;	
				$modif_status = false;
				
				if($participant->participanttype != $user['ParticipantType'] && $modif_type != "ok"){
					// enroled but with a different role
					$participant->participanttype = $user['ParticipantType'];		
					$modif_type = true;
				}elseif($participant->participanttype == $user['ParticipantType'] && $modif_type){
					// role is ok, user is enroled with more than one role
					$modif_type = "ok";
					$participant->participanttype = $user['ParticipantType'];										
				}
				if($participant->confirmationstatus != $user['ConfirmationStatus'] && $user['ParticipantType'] == 1){
					// need to update confirmation status of student.
					$participant->confirmationstatus = $user['ConfirmationStatus'];
					$modif_status = true;
				}
				
				if($modif_status || $modif_type == true){
					$DB->update_record("via_participants", $participant);
				}
			}								
		}else{
			// only if the user exists in via_users + is enrolled in the course!
			$is_enrolled = $DB->get_records_sql('SELECT e.id FROM mdl_via_participants vp
												left join mdl_via v ON vp.activityid = v.id
												left join mdl_enrol e ON v.course = e.courseid
												left join mdl_user_enrolments ue ON ue.enrolid = e.id AND ue.userid = vp.userid
												where vp.userid = '.$u->userid.' and vp.activityid = '.$activityid.' AND ue.id is not null');
			if($DB->get_record('via_users', array('viauserid'=>$u->viauserid)) && $is_enrolled != null){
				// not enroled in moodle via activity, so adding him
				$newparticipant = new stdClass();
				$newparticipant->activityid = $activityid;
				$newparticipant->userid = $u->userid;
				$newparticipant->participanttype = $user['ParticipantType'];
				$newparticipant->confirmationstatus = $user['ConfirmationStatus'];
				$newparticipant->enrolid = $is_enrolled;
				$newparticipant->timemodified = time();
				$added = $DB->insert_record("via_participants", $newparticipant);
				echo $added;
			}
		}
	}else{
		if($new_user = $DB->get_record('user', array('email'=>$user['Email']))){ // should we vaildate that the user exists in via_users?????
			// user is not in via_users table on Moodle DB. So adding him
			$new_via_user->userid = $new_user->id;
			$new_via_user->viauserid = $user['UserID'];
			$new_via_user->username = $user['Email'];
			if($user['ParticipantType'] >1){
				$new_via_user->usertype = 3;
			}else{
				$new_via_user->usertype = 2;
			}
			$new_via_user->timecreated = time();
			$DB->insert_record("via_users", $new_via_user);
			
			$newparticipant->activityid = $activityid;
			$newparticipant->enrolid = $enrolid;
			$newparticipant->userid =  $new_user->id;
			$newparticipant->participanttype = $user['ParticipantType'];
			$newparticipant->confirmationstatus = $user['ConfirmationStatus'];
			$newparticipant->timemodified = time();
			$DB->insert_record("via_participants", $newparticipant);
		}
		// user is not a moodle user at all
	}
}

/**
* removing participants that were deleted directly on via
 *
* @param object $via_participants list of participants
* @param integer $activityid the via id
*/
function remove_old_via_participants($via_participants, $activityid){
	global $CFG, $DB;
	
	if($moodle_participants = $DB->get_records_sql("SELECT * FROM mdl_via_participants WHERE activityid=$activityid")){
		
		foreach($moodle_participants as $moodle_participant){
			$removeuser = true;
			// get userid on via
			if($viauserid = $DB->get_record('via_users', array('userid'=>$moodle_participant->userid))){
				foreach($via_participants as $via_participant){
					if(count($via_participant)>0){
						if(isset($via_participant['UserID']) && $via_participant['UserID'] == $viauserid->viauserid){	
							$removeuser = false;
							continue;
						}
					}
				}
			}
			if($removeuser){
				// user wa unenroled on via, so remove from participants on moodle
				$DB->delete_records_select("via_participants", "userid={$moodle_participant->userid} AND activityid=$activityid");
			}
		}
		
	}
}

/**
* Removes user from the participant list
 *
* @param integer $userid the user id 
* @param integer $viaid the via id
* @param integer $type role we have to remove of user on via (if has more than 1 role for activity)
* @return bool Success/Fail
*/
function via_remove_participant($userid, $viaid, $type=NULL) {
	global $CFG, $DB;
	
	$via = $DB->get_record('via', array('id'=>$viaid));
	
	if($type && $user_roles = $DB->get_records_sql("SELECT * FROM mdl_via_participants WHERE userid=$userid AND activityid=$viaid")) {		
		if(count($user_roles)>1){
			$update = true;
			foreach($user_roles as $user_role){
				if($user_role->participanttype == $type){
					continue;
				}
				if($user_role->participanttype == 2){ // presentator
					$update = false;
					$new_role = 2;
					continue;
				}
				if($user_role->participanttype > $type && $update){ // animator
					$update = false;
					$new_role = $user_role->participanttype;
					continue;
				}
				$new_role = $user_role->participanttype; // participant
			}
		}
	}

	// now we update it on via
	$api = new mod_via_api();

	try {
		if(!isset($new_role)){
			$response = $api->removeUserActivity($via->viaactivityid, $userid);
		}else{
			$sub = new stdClass();
			$sub->userid  = $userid;	
			$viaactivity = $via;
			$sub->activityid = $viaid;
			$sub->viaactivityid = $viaactivity->viaactivityid;
			$sub->participanttype = $new_role;
			//$sub->confirmationstatus = 2;
			$sub->confirmationstatus = 1;
			
			$response = $api->addUserActivity($sub);
		}
	}
	catch (Exception $e){
		notify(get_string("error:".$e->getMessage(), "via"));
		$result = false;
	}
	
	if($type){
		// if user has more than one role for activity, we do not want do remove any entry.
		return $DB->delete_records('via_participants', array('userid'=>$userid, 'activityid'=> $viaid, 'participanttype'=>$type));		
	}else{
		return $DB->delete_records('via_participants', array('userid'=>$userid, 'activityid'=>$viaid));		
	}
}

/**
* add all course's users in this activity 
* (called when activity is created and, if automatic enrol is activated, when activity is updated)
 *
* @param object $via the via 
* @param integer $idactivity the via id
*/

function add_participants_to_activity($via, $idactivity){
	global $DB, $COURSE;	
	
	$cm = get_coursemodule_from_instance("via", $idactivity, $via->course, $fields = '', $sort = '', $groupid = '');	
	$context = get_context_instance(CONTEXT_MODULE, $cm->id); 
	
	// get all teachers and editing teachers
	$allteachers = get_users_by_capability($context, 'mod/via:manage', $fields, $sort, '', '', $groupid, '', false, true);		
	$creator_info = $DB->get_record('user', array('id'=>$via->creator));
	$creator[$creator_info->id] = $creator_info;
	
	if($via->groupingid){
		// if grouping, only adding the grouping members
		$group_users = groups_get_grouping_members($via->groupingid, $fields='u.id');		
		// all users except teachers
		$users = array_diff_key($group_users, $allteachers);	
		// all participants, except creator
		$participants = array_diff_key($users, $creator);
		via_add_participants($participants, $idactivity, 1);
	}else{		
		$allusers = get_users_by_capability($context, 'mod/via:view', $fields, $sort, '', '', $groupid, '', false, true);
		$users = array_diff_key($allusers, $allteachers);	
		$participants = array_diff_key($users, $creator);	
		via_add_participants($participants, $idactivity, 1);
	}
	
	// remove the creator from the teachers list. The creator has an other role (presentator)
	$teachers = array_diff_key($allteachers, $creator);
	via_add_participants($teachers, $idactivity, 3);		
}

/**
*  Creates a random password
 *
* @param string : a random password
*/
function via_create_user_password(){
	$password = via_get_random_letter().via_get_random_letter().via_get_random_letter().rand(2,9).rand(2,9).via_get_random_letter().via_get_random_letter();
	return $password;
}

/**
*  Select a random letter for password
*  We do not want i, l or 0 because it can be confused with 1 or 0.
 *
* @param string : a random letter
*/
function via_get_random_letter(){
	$lettres = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'j', 'k', 'm', 'n', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');
	return $lettres[rand(0,count($lettres)-1)];
}

/**
*  Find out if user is a presentaor for the activity
 *
* @param integer $userid the user id
* @param integer $activityid the via id
* @return bool  presentator/not presentator
*/
function via_get_is_user_presentator($userid, $activityid){
	global $DB;
	
	$presentator = $DB->count_records('via_participants', array('userid'=>$userid, 'activityid'=>$activityid, 'participanttype'=>2));
	if($presentator >= 1){
		return true;	
	}
	
	return false;
}

/**
*  Verifies what a user can view for an activity, depending of his role and
*  if activity is done or not. If there's reviews ect.
 *
* @param object $via the via 
* @return integer what user can view
*/
function via_access_activity($via){
	global $USER, $CFG, $DB;
	
	$participant = $DB->get_record('via_participants', array('userid'=>$USER->id, 'activityid'=>$via->id));

	if(!$participant){
		// if user cant access this page, he should be able to access the activity.
		
		if($via->enroltype == 0){
			// if automatic enrol
			$userid = $DB->get_record('via_users', array('userid'=>$USER->id));
			// verifying if user was enrolled directly on via, if so, we enrol him
			if(!$userid || !via_update_participants_list($via, $userid->viauserid) && !has_capability('moodle/site:approvecourse', get_context_instance(CONTEXT_SYSTEM))){ 
				if(!$userid){
					$viauserid = null;
				}else{
					$viauserid = $userid->viauserid;
				}
				$type = get_user_type($USER->id, $via->course);
				$added = via_add_participant($USER->id, $via->id, $type);
				if($added){
					$DB->insert_record('via_log', array('userid'=>$USER->id, 'viauserid'=>$viauserid, 'activityid'=>$via->id, 'action'=>'user accesses activity details', 'result'=>'user added', 'time'=>time()));
				}
			}else{
				// we tell him is doesn't have access to this activity and to contact his teacher if there's a problem
				return 6;	
			}
		}else{
			// verifying if user was enrol directly on via
			// if not enrol, we tell him is doesn't have access to this activity and to contact his teacher if there's a problem
			if(!$userid || !via_update_participants_list($via, $userid->viauserid)){
				return 6;
			}
		}
	}else{
		if($participant->participanttype != 2){ // no need to validate if presentor
			$type = get_user_type($USER->id, $via->course);
			if($type != $participant->participanttype){
				$update = via_add_participant($participant->userid, $via->id, $type);
				$participant = $DB->get_record('via_participants', array('userid'=>$USER->id, 'activityid'=>$via->id));
			}
		}
	}	
	
	if((time() >= ($via->datebegin -  (30 * 60)) && time() <= ($via->datebegin + ($via->duration * 60)+60)) || $via->activitytype == 2){
		// if activity is hapening right now, show link to the activity		
		return 1;
	}elseif(time()<$via->datebegin){
		// activity hasn't started yet.
		
		if($participant->participanttype > 1){
			// if participant, user can't access activity
			return 2;			
		}else{
			// if participant is animator or presentator, show link to prepare activity
			return 3;			
		}
	}else{
		// activity is done. Must verify if there are any recordings of if
		return 5;	
	}
}

/**
*  Constructs rows for a table with all participants and confirmation status
 *
* @param integer $id via id
* @param object $context course context
* @param object $via the via
* @param object $table object table to construct
* @param object $participants_confirms list of particpants from moodle
* @param bool $quickupdate if true, we do not update confirmation status
* @return object the table with data rows
*/
function via_get_confirmation_table($id, $context, $via, $table, $participants_confirms, $quickupdate=FALSE){
	global $CFG;
	
	foreach($participants_confirms as $participant_confirm){		
		
		if(has_capability('moodle/course:viewparticipants', $context)){
			//commented by svi when making sure participants weren't deleted from list, uncommented durning search for error		
			if(!$quickupdate){
				//we need to get updates from via server
				$participant_confirm_update = via_update_moodle_confirmationstatus($via, $participant_confirm->id);
			}else{
				// we do not need to update status, already done
				$participant_confirm_update = $participant_confirm->confirmationstatus;
			}
			/////
			
			if($participant_confirm_update !== false){	
				
				if($participant_confirm_update == 1){
					$confirmimg = "waiting_confirm.gif";
					$confirmtitle = get_string("waitingconfirm", "via");
				}elseif($participant_confirm_update== 2){
					$confirmimg = "confirm.gif";
					$confirmtitle = get_string("confirmed", "via");
				}else{
					$confirmimg = "refuse.gif";
					$confirmtitle = get_string("refused", "via");			
				}
				// changed type of table
				$table->data[] = array($participant_confirm->firstname, $participant_confirm->lastname, "<a href='mailto:".$participant_confirm->email."'>".$participant_confirm->email."</a>", "<img src='" . $CFG->wwwroot . "/mod/via/pix/".$confirmimg."' width='16' height='16' alt='".$confirmtitle . "' title='".$confirmtitle . "' align='absmiddle'/>");		
			}
		}	
	}
	return $table;
}

/**
*  prints a table with all participants and confirmation status
 *
* @param integer $via via 
* @param object $sql the sql requests to get all students
* @param object $participants_confirms first result of sql request
* @param object $context course context
* @param object $table object table to construct
* @param bool $printbox if true, wrap table un a simple box
*/
function via_print_confirmation_table($via, $sql, $participants_confirms, $context, $table){
	global $CFG, $DB, $OUTPUT;
	if($CFG->via_participantmustconfirm && $via->needconfirmation){

		echo $OUTPUT->box_start('center');

		echo "<div style='text-align:center'>";
		
		echo "<h2>".get_string("confirmation", "via")."</h2>";	
		
		$participants_confirms_updated = $DB->get_records_sql($sql);	
		
		if($participants_confirms != $participants_confirms_updated){
			// a participant was added or remove from activity, we have to update de confirmations' table
			$table->data = array();
			$table = via_get_confirmation_table($via->id, $context, $via, $table, $participants_confirms_updated, true);
			
		}
		
		echo html_writer::table($table, 'center');
		
		echo "<div style='padding-top:0.2em;'><img src='" . $CFG->wwwroot . "/mod/via/pix/confirm_small.gif' width='16' height='16' alt='".get_string("confirmed", "via") . "' title='".get_string("confirmed", "via") . "' align='absmiddle' hspace='3'/><span style='font-size:0.9em; padding-right:1em;'>".get_string("confirmed", "via")."</span><img src='" . $CFG->wwwroot . "/mod/via/pix/refuse_small.gif' width='16' height='16' alt='".get_string("refused", "via") . "' title='".get_string("refused", "via") . "' align='absmiddle' hspace='3'/><span style='font-size:0.9em; padding-right:1em;'>".get_string("refused", "via")."</span><img src='" . $CFG->wwwroot . "/mod/via/pix/waiting_confirm_small.gif' width='16' height='16' alt='".get_string("waitingconfirm", "via") . "' title='".get_string("waitingconfirm", "via") . "' align='absmiddle' hspace='3'/><span style='font-size:0.9em; padding-right:1em;'>".get_string("waitingconfirm", "via")."</span></div>";
		
		echo "</div>";

		echo $OUTPUT->box_end();
	}

}


/**
*  Verify if we can access activity reviews
*
* @param $via object the via object
* @return bool can access reviews/can't access reviews
*/
function via_access_review($via){
	
	if($via->isreplayallowed){
		if($via->activitytype == 2){
			// we can always see reviews with a permanent activty
			return true;
		}
		if(time() >= ($via->datebegin -  (30 * 60))){
			// activity is started, we can view review, if there is one
			return true;					
		}
		if(time() <= ($via->datebegin + ($via->duration * 60)+60)){
			// activity is done, we can view review 1 minute after the end of the activity
			return true;	
		}		
	}
	return false;
}

/**
*  Get the available profiles for the company on via
*
* @return obejct list of profiles
*/
function via_get_listProfils(){
	$result = true;
	
	$api = new mod_via_api();

	try {
		$response = $api->listProfils();
	}
	catch (Exception $e){
		$result = false;
		notify(get_string("error:".$e->getMessage(), "via"));
	}
	$profil = array();
	foreach($response['Profil'] as $profil_info){
		if(isset($profil_info['ProfilID'])){
			$profil[$profil_info['ProfilID']] = $profil_info['ProfilName'];
		}
		
	}
	return $profil;
}

/**
*  Get all the playbacks for an acitivity
*
* @param object $via the via object
* @return obejct list of playbacks
*/
function via_get_all_playbacks($via){
	
	$result = true;
	$playbacksMoodle = false;
	$api = new mod_via_api();
	
	try {
		$playbacks = $api->listPlayback($via);
	}
	catch (Exception $e){
		$result = false;
		notify(get_string("error:".$e->getMessage(), "via"));
	}
	
	if(isset($playbacks['Playback']) && count($playbacks) == 1){
		$aplaybacks = $playbacks['Playback'];
	}else{
		$aplaybacks = $playbacks;
	}
	if(gettype($aplaybacks == "array") && count($aplaybacks)>1){
		
		foreach($aplaybacks as $playback){
			if(gettype($playback) == "array"){
				
				if(isset($playback['BreackOutPlaybackList'])){
					foreach($playback['BreackOutPlaybackList'] as $breakout){
						if(gettype($breakout) == "array"){
							if(isset($breakout['PlaybackID'])){
								$playbacksMoodle[$playback['PlaybackID']] = new stdClass();
								$playbacksMoodle[$breakout['PlaybackID']]->title = $breakout['Title'];
								$playbacksMoodle[$breakout['PlaybackID']]->duration = $breakout['Duration'];
								$playbacksMoodle[$breakout['PlaybackID']]->creationdate = $breakout['CreationDate'];
								$playbacksMoodle[$breakout['PlaybackID']]->ispublic = $breakout['IsPublic'];
								$playbacksMoodle[$breakout['PlaybackID']]->playbackrefid = $breakout['PlaybackRefID'];
							}else{
								foreach($breakout as $bkout){
									if(gettype($bkout) == "array"){
										$playbacksMoodle[$playback['PlaybackID']] = new stdClass();
										$playbacksMoodle[$bkout['PlaybackID']]->title = $bkout['Title'];
										$playbacksMoodle[$bkout['PlaybackID']]->duration = $bkout['Duration'];
										$playbacksMoodle[$bkout['PlaybackID']]->creationdate = $bkout['CreationDate'];
										$playbacksMoodle[$bkout['PlaybackID']]->ispublic = $bkout['IsPublic'];
										$playbacksMoodle[$bkout['PlaybackID']]->playbackrefid = $bkout['PlaybackRefID'];
									}
								}
							}
						}
					}
				}else{
					$playbacksMoodle[$playback['PlaybackID']] = new stdClass();
					$playbacksMoodle[$playback['PlaybackID']]->title = $playback['Title'];
					$playbacksMoodle[$playback['PlaybackID']]->duration = $playback['Duration'];
					$playbacksMoodle[$playback['PlaybackID']]->creationdate = $playback['CreationDate'];
					$playbacksMoodle[$playback['PlaybackID']]->ispublic = $playback['IsPublic'];
				}
			}
		}	
	}
	
	return $playbacksMoodle;
	
}

/**
*  Get the usertype as an integer
*
* @param string $usertype
* @return integer the integer for the usertype
*/
function via_get_userType($usertype){
	switch($usertype){
		case "Participant":
			return 2;
		case "Collaborator":
			return 3;
		case "Coordonator":
			return 4;
		case "Administrator":
			return 5;
		default:
			return 2;
	}
}

/**
*  Get the remindertime for an activity
*
* @param object $via the via object
* @return object containing the remindertime
*/
function via_get_remindertime($via){
	$remindertime = $via->datebegin - $via->remindertime;
	return $remindertime;
}

/**
*  Get the remindertime integer for svi
*
* @param integer $remindertime time in seconds for the remindertime
* @return integer the remindertime integer for via
*/
function via_get_remindertime_svi($remindertime){
	switch($remindertime){
		case 0:
			$reminder = 0;
			break;
		case 3600:
			$reminder = 1;
			break;
		case 7200:
			$reminder = 2;
			break;
		case 86400:
			$reminder = 3;
			break;
		case 172800:
			$reminder = 4;
			break;
		case 604800:
			$reminder = 5;
			break;
		default:
			$reminder = 0;
			break;
	}
	
	return $reminder;
}

/**
*  Get the remindertime integer from svi for moodle
*
* @param integer $remindertime remindertime integer from via
* @return integer the time in seconds for the remindertime for moodle
*/
function via_get_remindertime_from_svi($remindertime){
	switch($remindertime){
		case 0:
			$reminder = 0;
			break;
		case 1:
			$reminder = 3600;
			break;
		case 2:
			$reminder = 7200;
			break;
		case 3:
			$reminder = 86400;
			break;
		case 4:
			$reminder = 172800;
			break;
		case 5:
			$reminder = 604800;
			break;
		default:
			$reminder = 0;
			break;
	}
	
	return $reminder;
}

/**
*
*/
function via_print_overview($courses, &$htmlarray) {
	global $USER, $CFG;
	/// These next 6 Lines are constant in all modules (just change module name)
	if (empty($courses) || !is_array($courses) || count($courses) == 0) {
		return array();
	}

	if (!$vias = get_all_instances_in_courses('via', $courses)) {
		return;
	}

	/// Fetch some language strings outside the main loop.
	$strvia = get_string('modulename', 'via');

	$now = time();
	foreach ($vias as $via) {
		if (($via->datebegin + ($via->duration*60) >= $now && ($via->datebegin - (30*60)) < $now) || $via->activitytype == 2) {
			/// Give a link to via
			$str = '<div class="via overview"><div class="name"><img src="'.$CFG->wwwroot.'/mod/via/pix/icon.gif" 
             "class="icon" alt="'.$strvia.'">';
			
			$str .= '<a ' . ($via->visible ? '' : ' class="dimmed"') .
				' href="' . $CFG->wwwroot . '/mod/via/view.php?id=' . $via->coursemodule . '">' .
				$via->name . '</a></div>';
			if($via->activitytype != 2){
				$time->start = userdate($via->datebegin);
				$time->end = userdate($via->datebegin + ($via->duration*60));
				$str .= '<div class="info_dev">' . get_string('overview', 'via', $time) . '</div>';
			}


			/// Add the output for via to the rest.
			$str .= '</div>';
			if (empty($htmlarray[$via->course]['via'])) {
				$htmlarray[$via->course]['via'] = $str;
			} else {
				$htmlarray[$via->course]['via'] .= $str;
			}
		}else{
			continue;	
		}
	}
}


/**
 * Create grade item for given activity
 *
 * @param object $via object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function via_grade_item_update($via, $grades=null) {
	global $CFG, $DB;
	
	if (!function_exists('grade_update')) { //workaround for buggy PHP versions
		require_once($CFG->libdir.'/gradelib.php');
	}
	
	if (!isset($via->courseid)) {
		$via->courseid = $via->course;
	}
	if (!isset($via->id)) {
		$via->id = $via->instance;
	}
	
	if (array_key_exists('cmidnumber', $via)) { //it may not be always present
		$params = array('itemname'=>$via->name, 'idnumber'=>$via->cmidnumber);
	} else {
		$params = array('itemname'=>$via->name);
	}
	
	if ($via->grade > 0) {
		$params['gradetype'] = GRADE_TYPE_VALUE;
		$params['grademax']  = $via->grade;
		$params['grademin']  = 0;

	} else if ($via->grade < 0) {
		$params['gradetype'] = GRADE_TYPE_SCALE;
		$params['scaleid']   = -$via->grade;

	} else {
		$params['gradetype'] = GRADE_TYPE_TEXT; // allow text comments only
	}

	if ($grades  === 'reset') {
		$params['reset'] = true;
		$grades = NULL;
	}
	
	return grade_update('mod/via', $via->courseid, 'mod', 'via', $via->id, 0, $grades, $params);
}

/**
 * Delete grade item for given activity
 *
 * @param object $via object
 * @return object va
 */
function via_grade_item_delete($via) {
	global $CFG;
	require_once($CFG->libdir.'/gradelib.php');

	if (!isset($via->courseid)) {
		$via->courseid = $via->course;
	}

	return grade_update('mod/via', $via->courseid, 'mod', 'via', $via->id, 0, NULL, array('deleted'=>1));
}


/**
 * Function to be run periodically according to the moodle cron
 * updates remindertime on VIA server if parameter via_moodleemailnotification is change
 * Updates participant list on Moodle, if changes directly on via server
 * Finds all invitation and reminders that have to be sent and send them
 * @return bool $result sucess/fail
 */
function via_cron() {
	global $CFG;
	$result = true;
	echo 'via';
	echo "\n";
	$result = via_change_reminder_sender() && $result;
	
	if($CFG->via_moodleemailnotification){
		echo "\n";
		$result = via_send_reminders() && $result;
	}		
	
	if($CFG->via_sendinvitation){
		echo "\n";
		$result = via_send_invitations() && $result;
	}
	echo "\n";
	$result = add_enrolids() && $result;
	
	echo "synching users \n";
	$result = synch_participants() && $result;
	
	echo "check categories \n";
	$result = check_categories() && $result;
	
	return $result;
}

/**
 * Called by the cron job to add enrolids to the via_participants table, 
 * this will only happen once when the plugin is updated and the core adds in removed
 * afterwards the enrolid will be added when the activity is created or the user added to the course
 *
 * @return bool $result sucess/fail
 */
function add_enrolids(){
	global $DB;
	$result = true;
	$participants = $DB->get_records('via_participants', array('enrolid'=>NULL, 'timesynched'=>NULL));
	if($participants){
		foreach($participants as $participant){
			$enrolid = $DB->get_record_sql('SELECT e.id FROM mdl_via_participants vp
												left join mdl_via v ON vp.activityid = v.id
												left join mdl_enrol e ON v.course = e.courseid
												left join mdl_user_enrolments ue ON ue.enrolid = e.id AND ue.userid = vp.userid
												where vp.userid = '.$participant->userid.' and vp.activityid = '.$participant->activityid.' AND ue.id is not null');	
			if($enrolid){
				$DB->set_field('via_participants', 'enrolid', $enrolid->id, array('id'=>$participant->id));	
				$DB->set_field('via_participants', 'timemodified', time(), array('id'=>$participant->id));	
			}			
		}
	}
	return $result;
}

/**
 * Called by the cron job to check if categories added to activities still exits, 
 * if they don't we remove them from the via_catgoires table so that no new activity can be added to it
 * activites already created with the old category keep it though
 *
 * @return bool $result sucess/fail
 */
function check_categories(){
	global $DB;
	
	$result = true;
	$via_array= array();
	$existing_array= array();
	
	$via_catgeories = get_via_categories();	
	foreach($via_catgeories['Category'] as $via_cat){
		$via_array[$via_cat["CategoryID"]] = $via_cat["Name"];
	}
	
	$existingcats = $DB->get_records('via_categories');
	foreach($existingcats as $cats){
		$existing_array[$cats->id_via] = $cats->name;
	}
	
	$differences = array_diff($existing_array, $via_array);
	if($differences){
		foreach($differences as $key => $value)
			$delete = $DB->delete_records('via_categories', array('id_via'=>$key));	
	}
	
	return $result;
}

/**
 * Called by the cron job to send email reminders
 *
 * @return bool $result sucess/fail
 */
function via_send_reminders() {
	global $CFG, $DB;

	$reminders = via_get_reminders();
	if (!$reminders) {
		echo "    No email reminders need to be sent.\n";
		return true;
	}

	echo '    ', count($reminders), ' email reminder', (1 < count($reminders) ? 's have' : 'has'), " to be sent.\n";

	// If anything fails, we'll keep going but we'll return false at the end.
	$result = true;

	foreach ($reminders as $r) {
		
		$muser = $DB->get_record('user', array('id'=>$r->userid));
		$from = get_presenter($r->id);
		
		if (!$muser) {
			echo "    User with ID {$r->userid} doesn't exist?!\n";
			$result = false;
			continue;
		}
		
		if($CFG->via_moodleemailnotification){
			// send reminder with Moodle
			$result = send_moodle_reminders($r, $muser, $from);
		}else{
			// send reminder with VIA
			$api = new mod_via_api();
			
			try {
				$response = $api->sendinvitation($user->id, $r->viaactivityid);
			}
			catch (Exception $e){
				echo "   SVI could not send email to <".$muser->email.">  (<".$e.">)\n";
				echo get_string("error:".$e->getMessage(), "via")."\n";
				$result = false;
				continue;
			}
			
			echo "    Sent an email reminder to ".$muser->firstname . $muser->lastname . $muser->email. ">.\n";
			
			$record->id = $r->id;
			$record->mailed = 1;
			if (!$DB->update_record('via', $record)) {
				// If this fails, stop everything to avoid sending a bunch of dupe emails.
				echo "    Could not update via table!\n";
				$result = false;
				continue;	
			}
		}		
	}	

	return $result;
}

/**
 * gets all activity that need reminders to ben sent
 *
 * @return object $via object
 */
function via_get_reminders(){
	global $CFG, $DB;
	$now = time();
	
	$sql = "SELECT p.id, p.userid, p.activityid, v.name, v.datebegin, v.duration, v.viaactivityid, v.course, v.activitytype ".
		"FROM mdl_via_participants p ".
		"INNER JOIN mdl_via v ON p.activityid = v.id ".
		"WHERE v.remindertime > 0 AND ($now  >= (v.datebegin - v.remindertime)) AND v.mailed = 0";		
	
	$reminders = $DB->get_records_sql($sql);		 
	
	return $reminders;
}

/**
 * Sends reminders with moodle
 *
 * @param object $r via object
 * @param object $user user to send reminder
 * @return bool $result sucess/fail
 */
function send_moodle_reminders($r, $muser, $from){
	global $CFG, $DB;

	$result = true;
	// recipient is self
	$a = new stdClass(); 
	$a->username = fullname($muser);
	$a->title = $r->name;
	$a->datebegin = userdate($r->datebegin, '%A %d %B %Y');
	$a->hourbegin = userdate($r->datebegin, '%H:%M');
	$a->hourend = userdate($r->datebegin+($r->duration*60), '%H:%M');
	$a->datesend = userdate(time());
	
	$coursename = $DB->get_record('course', array('id'=>$r->course));	
	if (! $cm = get_coursemodule_from_instance("via", $r->activityid, $r->course)) {
		$cm->id = 0;
	}	

	$a->config = $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=7&viaid='.$r->activityid.'&courseid='.$r->course;
	$a->assist = $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=6&viaid='.$r->activityid.'&courseid='.$r->course;
	$a->activitylink = $CFG->wwwroot.'/mod/via/view.php?id='.$cm->id;
	$a->coursename = $coursename->shortname;
	$a->modulename = get_string('modulename', 'via');
	
	// fetch the subject and body from strings
	$subject = get_string('reminderemailsubject', 'via', $a);
	
	$body = get_string('reminderemail', 'via', $a);

	$bodyhtml = utf8_encode(get_string('reminderemailhtml', 'via', $a));
	
	$bodyhtml = via_make_invitation_reminder_mail_html($r->course, $r, $muser, TRUE);
	
	if (!isset($user->emailstop) || !$user->emailstop) {
		if (true !== email_to_user($muser, $from, $subject, $body, $bodyhtml)) {
			echo "    Could not send email to <{$muser->email}> (unknown error!)\n";
			$result = false;
		}else{
			echo "Sent an email reminder to {$muser->firstname} {$muser->lastname} <{$muser->email}>.\n";
		}
	}

	$record = new stdClass();
	$record->id = $r->activityid;
	$record->mailed = 1;
	if (!$DB->update_record('via', $record)) {
		// If this fails, stop everything to avoid sending a bunch of dupe emails.
		echo "    Could not update via table!\n";
		$result = false;
		continue;	
	}
	
	return $result;
}

/**
 * Called by the cron job to send email invitation
 *
 * @return bool $result sucess/fail
 */
function via_send_invitations() {
	global $CFG, $DB;

	$invitations = via_get_invitations();
	if (!$invitations) {
		echo "    No email invitations need to be sent.\n";
		return true;
	}

	echo '    ', count($invitations), ' email invitation', (1 < count($invitations) ? 's have' : 'has'), " to be sent.\n";

	// If anything fails, we'll keep going but we'll return false at the end.
	$result = true;

	foreach ($invitations as $i) {
		
		$muser = $DB->get_record('user', array('id'=>$i->userid));
		$from = get_presenter($i->activityid);
		
		if (!$muser) {
			echo "User with ID {$i->userid} doesn't exist?!\n";
			$result = false;
			continue;
		}
		
		if($CFG->via_moodleemailnotification){
			// send reminder with Moodle
			$result = send_moodle_invitations($i, $muser, $from);
		}else{
			// send reminder with SVI
			$api = new mod_via_api();
			try {
				$response = $api->sendinvitation($muser->id, $i->viaactivityid, $i->invitemsg);
			}
			catch (Exception $e){
				echo "   SVI could not send email to <{$muser->email}>  (<{$e}>)\n";
				echo get_string("error:".$e->getMessage(), "via")."\n";
				$result = false;
				continue;
			}
			
			echo "Sent an email invitations to" .$muser->firstname . " " . $muser->lastname . " " . $muser->email ."\n";
			
			$record->id = $i->activityid;
			$record->sendinvite = 0;
			if (!$DB->update_record('via', $record)) {
				// If this fails, stop everything to avoid sending a bunch of dupe emails.
				echo "    Could not update via table!\n";
				$result = false;
				continue;	
			}

		}		
		
	}	

	return $result;
}

function  get_presenter($activityid){
	global $DB;
	
	$presenter = $DB->get_record('via_participants', array('activityid'=>$activityid, 'participanttype'=>2));
	if($presenter){
		$from = $DB->get_record('user', array('id'=>$presenter->userid));
	}else{
		$from = get_admin();	
	}
	
	return $from;
}

/**
 * Sends invitations with moodle
 *
 * @param object $i via object
 * @param object $user user to send reminder
 * @return bool $result sucess/fail
 */
function send_moodle_invitations($i, $user, $from){
	global $CFG, $DB;

	$muser = $user;
	// recipient is self
	$result = true;
	$a = new stdClass();
	
	$a->username = fullname($muser);
	$a->title = $i->name;
	$a->datebegin = userdate($i->datebegin, '%A %d %B %Y');
	$a->hourbegin = userdate($i->datebegin, '%H:%M');
	$a->hourend = userdate($i->datebegin+($i->duration*60), '%H:%M');
	$a->datesend = userdate(time());
	
	$coursename = $DB->get_record('course', array('id'=>$i->course));	
	if (! $cm = get_coursemodule_from_instance("via", $i->activityid, $i->course)) {
		$cm->id = 0;
	}	

	$a->config = $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=7&viaid='.$i->activityid.'&courseid='.$i->course;
	$a->assist = $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=6&viaid='.$i->activityid.'&courseid='.$i->course;
	$a->activitylink = $CFG->wwwroot.'/mod/via/view.php?id='.$cm->id;
	$a->coursename = $coursename->shortname;
	$a->modulename = get_string('modulename', 'via');
	
	if(!empty($i->invitemsg)){		
		if($muser->mailformat != 1){
			$a->invitemsg = $i->invitemsg;
		}else{
			$a->invitemsg = nl2br($i->invitemsg);
		}
	}else{
		$a->invitemsg = "";	
	}
	
	// fetch the subject and body from strings
	$subject = get_string('inviteemailsubject', 'via', $a);
	if($i->activitytype == 2){
		$body = get_string('inviteemailpermanent', 'via', $a);
	}else{
		$body = get_string('inviteemail', 'via', $a);
	}
	
	$bodyhtml = via_make_invitation_reminder_mail_html($i->course, $i, $user);
	
	if (!isset($muser->emailstop) || !$muser->emailstop) {
		if (true !== email_to_user($user, $from, $subject, $body, $bodyhtml)) {
			echo "    Could not send email to <{$user->email}> (unknown error!)\n";
			$result = false;
			continue;
		}
	}

	echo "    Sent an email invitations to " . $muser->firstname ." " . $muser->lastname . " " .$muser->email. "\n";
	
	$record = new stdClass();
	$record->id = $i->activityid;
	$record->sendinvite = 0;
	if (!$DB->update_record('via', $record)) {
		// If this fails, stop everything to avoid sending a bunch of dupe emails.
		echo "    Could not update via table!\n";
		$result = false;
		continue;	
	}
	
	return $result;
}

function via_make_invitation_reminder_mail_html($courseid, $via, $muser, $reminder=FALSE) {
	global $CFG, $DB;

	if ($muser->mailformat != 1) {  // Needs to be HTML
		return '';
	}

	$strvia = get_string('modulename', 'via');
	
	$posthtml = '<head></head>';
	$posthtml .= "\n<body id=\"email\">\n\n";

	$coursename = $DB->get_record('course', array('id'=>$courseid));
	
	if (! $cm = get_coursemodule_from_instance("via", $via->activityid, $courseid)) {
		$cm->id = 0;
	}
	$a = new stdClass();
	$a->username = fullname($muser);
	$a->title = $via->name;
	$a->datebegin = userdate($via->datebegin, '%A %d %B %Y');
	$a->hourbegin = userdate($via->datebegin, '%H:%M');
	$a->hourend = userdate($via->datebegin+($via->duration*60), '%H:%M');
	
	if(!empty($via->invitemsg) && !$reminder){
		$a->invitemsg = nl2br($via->invitemsg);
	}else{
		$a->invitemsg = "";	
	}

	$posthtml .= '<div class="navbar">'.
		'<a target="_blank" href="'.$CFG->wwwroot.'/course/view.php?id='.$courseid.'">'.$coursename->shortname.'</a> &raquo; '.
		'<a target="_blank" href="'.$CFG->wwwroot.'/mod/via/index.php?id='.$courseid.'">'.$strvia.'</a> &raquo; '.
		'<a target="_blank" href="'.$CFG->wwwroot.'/mod/via/view.php?id='.$cm->id.'">'.$via->name.'</a></div>';
	
	
	$posthtml .= '<table border="0" cellpadding="3" cellspacing="0" class="forumpost">';

	$posthtml .= '<tr class="header"><td width="35" valign="top" class="picture left">';
	
	$posthtml .= '</td>';

	$posthtml .= '<td class="topic starter">';
	
	$b = new stdClass();
	$b->title = $a->title;
	
	if(!$reminder){
		$posthtml .= '<div class="subject">'.get_string("inviteemailsubject", "via", $b).'</div>';
	}else{	
		$posthtml .= '<div class="subject">'.get_string("reminderemailsubject", "via", $b).'</div>';
	}

	$posthtml .= '<div class="author">'.userdate(time()).'</div>';

	$posthtml .= '</td></tr>';
	
	$posthtml .= '<tr><td class="left side" valign="top">';
	$posthtml .= '&nbsp;';
	
	$posthtml .= '</td><td class="content">';
	
	if($via->activitytype == 2){
		$posthtml .= get_string("inviteemailhtmlpermanent", "via", $a);	
	}else{
		$posthtml .= get_string("inviteemailhtml", "via", $a);	
	}
	
	$posthtml .= "<div style='margin:20px;'>";
	
	$posthtml .= "<div style='border:1px solid #999; margin-top:10px; padding:10px;'>";
	
	$posthtml .= "<span style='font-size:1.2em; font-weight:bold;'>".get_string("invitepreparationhtml", "via")."</span>";	
	
	$posthtml .= "<div style='text-align:center'>";
	
	$posthtml .= "<a href='" . $CFG->wwwroot ."/mod/via/view.assistant.php?redirect=7&viaid=". $via->activityid ."&courseid=". $via->course ."' style='background:#5c707c; padding:8px 10px; color:#fff; text-decoration:none; margin-right:20px' > <img src='" . $CFG->wwwroot ."/mod/via/pix/config.png' height='27px' align='absmiddle' hspace='5' width='27px'  >&nbsp;".get_string("configassist", "via")."</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
	
	$posthtml .= "<a href='" . $CFG->wwwroot ."/mod/via/view.assistant.php?redirect=6&viaid=". $via->activityid ."&courseid=". $via->course ."' style='background:#5c707c; padding:8px 10px; color:#fff; text-decoration:none;'><img src='" . $CFG->wwwroot ."/mod/via/pix/assistance.png' align='absmiddle' hspace='5' height='27px' width='27px'>".get_string("technicalassist", "via")."</a>";
	
	$posthtml .= "</div>";
	
	$posthtml .= "</div>";
	
	$posthtml .= "<div style='border:1px solid #999; margin-top:10px; padding:10px;'>";
	
	$posthtml .= "<span style='font-size:1.2em; font-weight:bold;'>".get_string("invitewebaccesshtml", "via")."</span><br/><br/>".get_string("inviteclicktoaccesshtml", "via")."";
	
	$posthtml .= "<div style='text-align:center'>";
	
	/*$link = str_replace("www.", "",$CFG->wwwroot);*/
	
	$posthtml .= "<a href='".$CFG->wwwroot."/mod/via/view.php?id=".$cm->id."' style='background:#6ab605; padding:8px 10px; color:#fff; text-decoration:none;'><img src='" . $CFG->wwwroot ."/mod/via/pix/access.png' align='absmiddle' hspace='5' height='27px' width='27px'>".get_string("gotoactivity", "via")."</a>";
	
	$posthtml .= "<p><br/>". $CFG->wwwroot."/mod/via/view.php?id=".$cm->id /*.str_replace("https", "http",$link)*/."</p>";
	
	$posthtml .= "</div>";
	
	$posthtml .= "</div>";
	
	$posthtml .= "<div style='border:1px solid #999; margin-top:10px; font-size:0.9em; padding:10px;'>";
	
	$posthtml .= get_string("invitewarninghtml", "via");
	
	$posthtml .= "</div>";
	
	$posthtml .= "</div>";

	$posthtml .= '</td></tr></table>'."\n\n";

	$posthtml .= '</body>';

	return $posthtml;
}

/**
 * gets all activity that need invitations to ben sent
 *
 * @return object $via object
 */
function via_get_invitations(){
	global $CFG, $DB;
	$now = time();
	
	$sql = "SELECT p.id, p.userid, p.activityid, v.name, v.course, v.datebegin, v.duration, v.viaactivityid, v.invitemsg, v.activitytype ".
			"FROM mdl_via_participants p ".
			"INNER JOIN mdl_via v ON p.activityid = v.id ".
			"WHERE v.sendinvite = 1";		
	
	$invitations = $DB->get_records_sql($sql);		 
	
	return $invitations;
}

/**
 * Change remindertime on VIA server if parameter via_moodleemailnotification
 * is change. If Moodle sends email, every activity on VIA server needs to have
 * 0 as the remindertime, so VIA won't send emails. Else, if VIA is the sender
 * change the remindertime back.
 *
 * @return object $via object
 */
function via_change_reminder_sender(){
	global $CFG, $DB;
	
	$result = true;
	echo "change reminder sender \n";
	if($vias = $DB->get_records_sql("SELECT * FROM mdl_via WHERE remindertime != 0 AND mailed=0 AND moodleismailer != ". $CFG->via_moodleemailnotification ." ")){
		
		foreach($vias as $via){
			
			$api = new mod_via_api();
			try {
				$response = $api->activityGet($via);
				
				if($response['ReminderTime'] == 0 && !$CFG->via_moodleemailnotification){
					echo "Changer le reminder sur VIA pour que VIA envoie les emails\n";
					
					$update = $api->activityEdit($via);
					$via->invitemsg = $via->invitemsg;
					$via->moodleismailer = $CFG->via_moodleemailnotification;
					$DB->update_record("via", $via);
					
				}elseif($response['ReminderTime'] != 0 && $CFG->via_moodleemailnotification){
					echo "Enlever le reminder sur via, moodle envoie les emails\n";
					
					$update = $api->activityEdit($via);
					$via->invitemsg = $via->invitemsg;
					$via->moodleismailer = $CFG->via_moodleemailnotification;
					$DB->update_record("via", $via);
					
				}
				// else everything is OK, do not change anything
			}
			catch (Exception $e){
				notify(get_string("error:".$e->getMessage(), "via"));
				$result = false;
				continue;
			}
			
		}
	}
	// else, no change, the sender is the same
	return $result;
	
}

function synch_participants(){ 
	global $DB, $CFG;
	
	$via = $DB->get_record('modules', array('name'=>'via'));
	$lastcron = $via->lastcron;
	
	// add participants (with student roles only) that are in the ue table but not in via	
	$sql = 'from mdl_user_enrolments ue 
				left join mdl_enrol e on ue.enrolid = e.id
				left join mdl_via v on e.courseid = v.course
				left join mdl_via_participants vp on vp.activityid = v.id AND ue.userid = vp.userid
				left join mdl_context c on c.instanceid = e.courseid 
				left join mdl_role_assignments ra on ra.contextid = c.id AND ue.userid = ra.userid';
	$where = 'where (vp.activityid is null OR ra.timemodified > '.$lastcron.') and c.contextlevel = 50 and v.enroltype = 0 and e.status = 0 and v.enroltype = 0';
	
	if($CFG->dbtype == 'mysqli' || $CFG->dbtype == 'mysql'){
		$newenrollments = $DB->get_records_sql('select distinct @curRow := @curRow + 1 AS id, ue.userid, e.courseid, v.id as viaactity '.$sql.'
													JOIN    (SELECT @curRow := 0) r	'.$where.'');						
	}else{
		$newenrollments = $DB->get_records_sql('SELECT ROW_NUMBER() OVER (ORDER BY t.viaactity) AS id, 
												* FROM  (select distinct ue.userid, e.courseid, v.id as viaactity  '.$sql.' '.$where.') AS t');
	}

	foreach($newenrollments as $add){
		try{
			$type = get_user_type($add->userid, $add->courseid);
		}catch(Exception $e){
			notify("error:".$e->getMessage());
		}
		try{
			if($type != 2){ // only add participants and animators
				via_add_participant($add->userid, $add->viaactity, $type);	
			}
		}catch(Exception $e){
			notify("error:".$e->getMessage());
		}
	}
	
	// now we remove via participants that have been unerolled from a cours
	$oldenrollments = $DB->get_records_sql('select vp.id, vp.activityid, vp.userid, ue.id as enrolid from mdl_via_participants vp
												left join  mdl_user_enrolments ue on ue.enrolid = vp.enrolid and ue.userid = vp.userid
												where ue.enrolid is null and vp.userid != 2 and vp.enrolid != 0'); // 2== admin user which is never enrolled
	
	// if we are using cohortes, removed groups are not removed from enrollements so we check if they have a role as well 								
	$oldenrollments2 = $DB->get_records_sql('SELECT vp.id, vp.activityid, vp.userid FROM mdl_via_participants vp 
											INNER JOIN mdl_via v ON v.id = vp.activityid
											LEFT JOIN mdl_context c ON v.course = c.instanceid
											LEFT JOIN mdl_role_assignments ra ON c.id = ra.contextid AND vp.userid = ra.userid
											WHERE c.contextlevel = 50 AND ra.roleid IS NULL and vp.userid != 2 and (vp.participanttype = 1 OR vp.participanttype = 3)');
	
	$total = array_merge($oldenrollments, $oldenrollments2);
	foreach($total as $remove){	
		try{
			via_remove_participant($remove->userid, $remove->activityid);
		}catch(Exception $e){
			notify("error:".$e->getMessage());
		}
	}
	return true;
}

function get_user_type($userid, $courseid){
	global $DB, $CFG;
	
	$context = get_context_instance(CONTEXT_COURSE, $courseid);
	if(has_capability('moodle/course:viewhiddenactivities', $context, $userid)){
		$type ='3';	// animator
	}else{
		$type = '1'; // participant
	}

	return $type;
}

/**
 * Adds the necessary elements to the course reset form (called by course/reset.php)
 * @param object $mform The form object (passed by reference).
 */
function via_reset_course_form_definition(&$mform) {
	global $COURSE;

	$mform->addElement('header', 'viaheader', get_string('modulenameplural', 'via'));

	$mform->addElement('checkbox', 'delete_via_modules', get_string('resetdeletemodules', 'via'));
	$mform->addElement('checkbox', 'reset_via_participants', get_string('resetparticipants', 'via'));
	$mform->addElement('checkbox', 'disable_via_reviews', get_string('resetdisablereviews', 'via'));
	$mform->disabledIf('reset_via_participants', 'delete_via_modules', 'checked');
	$mform->disabledIf('disable_via_reviews', 'delete_via_modules', 'checked');
}


/**
* Performs course reset actions, depending on the checked options.
* @param object $data Reset form data.
* @param Array with status data.
* @return Array component, item and error message if any
*/
function via_reset_userdata($data) {
	global $COURSE, $CFG;
	
	$context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
	$vias = get_all_instances_in_course('via', $COURSE);

	$status = array();
	
	// deletes all via activities
	if (!empty($data->delete_via_modules)) {
		$result = via_delete_all_modules($vias);				
		$status[] = array(
			'component' => get_string('modulenameplural', 'via'),
			'item' => get_string('resetdeletemodules', 'via'),
			'error' => $result ? false : get_string('error:deletefailed', 'via'));
	}
	
	// unenrol all participants on via activities
	if (!empty($data->reset_via_participants)) {
		$result = via_remove_participants($vias);
		$status[] = array(
			'component' => get_string('modulenameplural', 'via'),
			'item' => get_string('resetparticipants', 'via'),
			'error' => $result ? false : get_string('error:resetparticipants', 'via'));
	}
	
	// disables all playback so participants cannot review activities
	if (!empty($data->disable_via_reviews)) {
		$result = via_disable_review_mode($vias);
		$status[] = array(
			'component' => get_string('modulenameplural', 'via'),
			'item' => get_string('resetdisablereviews', 'via'),
			'error' => $result ? false : get_string('error:disablereviews', 'via'));
	}

	return $status;
}

/**
* Delete all via activities 
* @param object $vias all via activiites for a given course
* @return bool sucess/fail
*/
function via_delete_all_modules($vias){
	global $CFG, $COURSE, $DB;
	
	$result = true;
	
	foreach($vias as $via){		
		
		//delete svi
		if(!via_delete_instance($via->id)){
			$result = false;	
		}
		
		require_once($CFG->dirroot.'/course/lib.php');
		
		if(!$cm = $DB->get_record('course_modules', array('id'=>$via->coursemodule))){
			$result = false;
		}
		
		if (!delete_course_module($via->coursemodule)) {
			$result = false;
		}
		
		if (!delete_mod_from_section($via->coursemodule, $cm->section)) {
			$result = false;	
		}
		
		if(!$DB->delete_records('via_participants', array('activityid'=>$via->id))){
			$result = false;		
		}
	}			
	rebuild_course_cache($COURSE->id);
	return $result;
}

/**
* Unenrol all participants for all activities
* @param object $vias all via activiites for a given course
* @return bool sucess/fail
*/
function via_remove_participants($vias){
	global $CFG, $DB;
	
	$result = true;

	foreach($vias as $via){
		// unenrol all participants on VIA server only if we do not delete activity on VIA, since this activity was backuped
		$participants = $DB->get_records_sql("SELECT * FROM mdl_via_participants WHERE activityid=$via->id AND participanttype != 2");
		foreach($participants as $participant){			
			if(!via_remove_participant($participant->userid, $via->id)){
				$result = false;
			}
		}	
	}
	return $result;
}

/**
* Disables the review mode for all activities
* @param object $vias all via activiites for a given course
* @return bool sucess/fail
*/
function via_disable_review_mode($vias){
	global $DB; 
	
	$result = true;
	
	foreach($vias as $via){
		$result = true;
		
		$via->timemodified = time();
		$via->isreplayallowed = 0;
		$api = new mod_via_api();
		
		// disables review mode on VIA server
		try {
			$response = $api->activityEdit($via);
		}
		catch (Exception $e){
			print_error(get_string("error:".$e->getMessage(), "via"));
			$result = false;		
		}
		
		// disables review mode on Moodle
		$via->name = $via->name;
		$via->invitemsg = $via->invitemsg;
		$via->description = $via->description;
		if(!$DB->update_record("via", $via)){
			$result = false;		
		}
	}
	return $result;
}

/**
* Changes confirmation status of a participant
* @param integer $viaid Via id on Moodle DB
*/
function via_set_participant_confirmationstatus($viaid, $present){
	global $CFG, $USER, $DB;
	
	if($participant_types = $DB->get_records_sql("SELECT * FROM mdl_via_participants WHERE userid=$USER->id AND activityid=$viaid")){
		foreach($participant_types as $participant_type){
			$participant_type->confirmationstatus = $present;
			$DB->update_record("via_participants", $participant_type);
		}
	}
	
	if($via = $DB->get_record('via', array('id'=>$viaid))){
		$via->userid = $USER->id;
		$via->confirmationstatus = $present;
		
		$api = new mod_via_api();

		try {
			$response = $api->editUserActivity($via);
		}
		catch (Exception $e){
			print_error(get_string("error:".$e->getMessage(), "via"));
			$result = false;		
		}
	}
	
}
?>