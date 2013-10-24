<?php  

/**
 * Manage participants, animators and presentator.
 *
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions 
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/via/lib.php');

global $CFG, $DB;

$id    = required_param('id',PARAM_INT);           // via
$group = optional_param('group',0,PARAM_INT);      // change of group
$participanttype  = optional_param('t', 1, PARAM_INT);     // participant type we are editing (participants, animators, presentator)

if (!$via = $DB->get_record('via', array('id'=>$id))) {
	error("Via ID is incorrect");
}
if (!$course = $DB->get_record('course', array('id'=>$via->course))) {
	error("Could not find this course!");
}
if (! $cm = get_coursemodule_from_instance("via", $via->id, $course->id)) {
	$cm->id = 0;
}

require_login($course->id, false, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
$PAGE->set_context($context);

// show some info for guests
if (isguestuser()) {
	$PAGE->set_title(format_string($via->name));
	echo $OUTPUT->header();
	echo $OUTPUT->confirm('<p>'.get_string('noguests', 'via').'</p>'.get_string('liketologin'),
		get_login_url(), $CFG->wwwroot.'/course/view.php?id='.$course->id);

	echo $OUTPUT->footer();
	exit;
}

if (!has_capability('mod/via:manage', $context)) {
	error('You do not have the permission to view via participants');
}

$strparticipants = get_string("participants", "via");

// Initialize $PAGE
$PAGE->set_url('/mod/via/manage.php', array('id' => $cm->id));
$PAGE->set_title($course->shortname . ': ' . format_string($via->name));
$PAGE->set_heading($course->fullname);

$button = $OUTPUT->update_module_button($cm->id,'via');
$PAGE->set_button($button);

/// Print the page header
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($via->name));	

// Print the main part of the page
// Print heading and tabs (if there is more than one).	   
if($participanttype === 1){
	$currenttab = 'participants';
	$strexistingparticipants   = get_string("existingparticipants", 'via');
	$strpotentialparticipants  = get_string("potentialparticipants", 'via');
}else if($participanttype == 3){
	$currenttab = 'animators';
	$strexistingparticipants   = get_string("existinganimators", 'via');
	$strpotentialparticipants  = get_string("potentialanimators", 'via');
}else{
	$currenttab = 'presentator';
	$strexistingparticipants   = get_string("existingpresentator", 'via');
	$strpotentialparticipants  = get_string("potentialpresentator", 'via');
}

include('tabs.php');

if($CFG->via_participantmustconfirm && $via->needconfirmation){

	$table = new html_table();
	$table->attributes = array('align'=>'center');
	$table->id = 'via_confirmation';
	$table->head = array(
		get_string('firstname'), 
		get_string('lastname'), 
		get_string('email'), 
		get_string('confirmationstatus', 'via'),
		);
	
	$sql="SELECT u.id, u.firstname, u.lastname, u.idnumber, u.email, p.confirmationstatus FROM mdl_user u INNER JOIN mdl_via_participants p ON u.id = p.userid WHERE p.activityid=$id AND p.participanttype=1";
	if($participants_confirms = $DB->get_records_sql($sql)){		
		$table = via_get_confirmation_table($id, $context, $via, $table, $participants_confirms);
	}
	
}


/// Check to see if groups are being used in this activity
groups_print_activity_menu($cm, $CFG->wwwroot."/mod/via/manage.php?id=$via->id&t=".$participanttype);
$currentgroup = groups_get_activity_group($cm);
$groupmode = groups_get_activity_groupmode($cm);	

// $via->enroltype = 0  = inscription automatique
// $via->enroltype = 1  = inscription manuelle

// we only add participants automatically, all other type of users are added manually
if($via->enroltype == 0 && $participanttype != 2){ 
	$users = via_participants($course, $via, $currentgroup, $participanttype, $context);
	if(empty($users)){
		if($participanttype == 1)	{	
			echo $OUTPUT->heading(get_string("noparticipants", "via"));	
		}else{
			echo $OUTPUT->heading(get_string("noanimators", "via"));
		}
		echo $OUTPUT->footer();
		exit;	
	} else {
		$groupingonly = '';
		if (!empty($CFG->enablegroupings) and $cm->groupmembersonly) {
			$groupingonly .= ' ('.groups_get_grouping_name($cm->groupingid).')';
		}
		if($participanttype == 1)	{	
			$title = get_string("enroledparticipants","via", $via);
		}else{
			$title = get_string("enroledanimators","via", $via);
		}

		echo "<div style='text-align:center'><h2>".$title.$groupingonly."</h2></div>";

		echo '<table align="center" cellpadding="5" cellspacing="5">';
		foreach ($users as $user) {
			echo '<tr><td>';
			echo $OUTPUT->user_picture($user, array('courseid' => SITEID)); 
			echo '</td><td>';
			echo fullname($user);
			echo '</td><td>';
			echo $user->email;
			echo '</td></tr>';
		}
		echo "</table>";
		
		if($CFG->via_participantmustconfirm && $via->needconfirmation){
			via_print_confirmation_table($via, $sql, $participants_confirms, $context, $table, false);
		}			

		echo $OUTPUT->footer();
		exit;
	}

} else{
	
	$strparticipants = get_string("participants", "via");
	$strsearch        = get_string("search");
	$strsearchresults  = get_string("searchresults");
	$strshowall = get_string("showall", "moodle", strtolower(get_string("participants", "via")));

	$searchtext = optional_param('searchtext', '', PARAM_RAW);
	if ($frm = data_submitted()) {
		/// A form was submitted so process the input
		if (!empty($frm->add) and !empty($frm->addselect)) {
			foreach ($frm->addselect as $addsubscriber) {
				$added = via_add_participant($addsubscriber, $id, $participanttype);
				if (!$added ) {
					$DB->insert_record('via_log', array('userid'=>$addsubscriber, 'viauserid'=>null, 'activityid'=>$id, 'action'=>'adding user manualy', 'result'=>'Could not add user', 'time'=>time()));
					print_error("Could not add user with id $addsubscriber to this activity!");
				}
			}
		} else if (!empty($frm->remove) and !empty($frm->removeselect)) {
			foreach ($frm->removeselect as $removesubscriber) {
				if (! via_remove_participant($removesubscriber, $id, $participanttype)) {
					$DB->insert_record('via_log', array('userid'=>$removesubscriber, 'viauserid'=>null, 'activityid'=>$id, 'action'=>'removing user manualy', 'result'=>'Could not remove user', 'time'=>time()));
					print_error("Could not remove user with id $removesubscriber from this activity!");
				}
			}
		} else if (!empty($frm->showall)) {
			$searchtext = '';
		}
	}

	/// Get all existing subscribers for this activity.
	if (!$subscribers = via_participants($course, $via, $currentgroup, $participanttype, $context)) {	   
		$subscribers = array();
	}

	/// Get all the potential subscribers excluding users already subscribed
	$users = via_get_potential_participants($context, $currentgroup, 'u.id,u.email,u.firstname,u.lastname,u.idnumber', 'u.firstname ASC, u.lastname ASC');

	// if groupmembersonly used, remove users who are not in any group
	if($participanttype === 1){
		if ($users and !empty($CFG->enablegroupings) and $cm->groupmembersonly) {
			if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id,u.email,u.firstname,u.lastname,u.idnumber', 'u.id')) {
				$users = array_intersect_key($users, $groupingusers);
				$groupingonly = ' ('.groups_get_grouping_name($cm->groupingid).')';
			}
		}
	}
	
	if (!$users) {
		$users = array();
	}
	
	foreach ($subscribers as $subscriber) {
		unset($users[$subscriber->id]);		
		//if(!has_capability('moodle/course:view', $context, $subscriber->id)){
		//unset($subscribers[$subscriber->id]);
		//}

	} 

	/// This is yucky, but do the search in PHP, becuase the list we are using comes from get_users_by_capability,
	/// which does not allow searching in the database. Fortunately the list is only this list of users in this
	/// course, which is normally OK, except on the site course of a big site. But before you can enter a search
	/// term, you have already seen a page that lists everyone, since this code never does paging, so you have probably
	/// already crashed your server if you are going to. This will be fixed properly for Moodle 2.0: MDL-17550.
	if ($searchtext) {
		$searchusers = array();
		$lcsearchtext = moodle_strtolower($searchtext);
		foreach ($users as $userid => $user) {
			if (strpos(moodle_strtolower($user->email), $lcsearchtext) !== false ||
				strpos(moodle_strtolower($user->firstname . ' ' . $user->lastname), $lcsearchtext) !== false ||
				strpos(moodle_strtolower($user->idnumber), $lcsearchtext) !== false) {
					$searchusers[$userid] = $user;
				}
				unset($users[$userid]);
			}
		}	

		echo $OUTPUT->box_start('center');

		include('manage.form.html');

		echo $OUTPUT->box_end();
		
		if($CFG->via_participantmustconfirm && $via->needconfirmation){
			via_print_confirmation_table($via, $sql, $participants_confirms, $context, $table);	
		}

		echo $OUTPUT->footer();
	}
?>
