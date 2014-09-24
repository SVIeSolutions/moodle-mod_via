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

if($via->noparticipants == 1 && $participanttype == 1){
	
	// there are no participants only animators and a presentor
	$participanttype = 3;
	
}

require_login($course, false, $cm); // check in other version of Moodle if this will work!

$context = context_module::instance($cm->id);
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

/// Check to see if groups are being used in this activity
$groupingid = $cm->groupingid;
if($groupingid != 0){
	$grouping_name = groups_get_grouping_name($groupingid) .'<br/>';	
}else{
	$grouping_name = '';
}

// $via->enroltype = 0  = inscription automatique
// $via->enroltype = 1  = inscription manuelle

// we only add participants automatically, all other type of users are added manually
if($via->enroltype == 0 && $participanttype == 1){ 
	$users = via_participants($course, $via, $participanttype, $context);
	if(empty($users)){
		if($participanttype == 1)	{	
			echo $OUTPUT->heading(get_string("noparticipants", "via"));	
		}else{
			echo $OUTPUT->heading(get_string("noanimators", "via"));
		}
		echo $OUTPUT->footer();
		exit;	
	} else {

		if($participanttype == 1)	{	
			$title = get_string("enroledparticipants","via", $via);
		}else{
			$title = get_string("enroledanimators","via", $via);
		}

		echo "<div style='text-align:center'><h2>".$grouping_name.$title."</h2></div>";

		echo '<table align="center" cellpadding="5" cellspacing="5">';
		foreach ($users as $user) {
			echo '<tr><td>';
			echo $OUTPUT->user_picture($user, array('courseid' => SITEID)); 
			echo '</td><td>';
			echo $user->firstname .' ' . $user->lastname;
			echo '</td><td>';
			echo $user->email;
			echo '</td></tr>';
		}
		echo "</table>";
		
		if(get_config('via','via_participantmustconfirm') && $via->needconfirmation){
			via_print_confirmation_table($via, $sql, $participants_confirms, $context, $table, false);
		}			

		echo $OUTPUT->footer();
		exit;
	}
		
}else{
	
	$strparticipants = get_string("participants", "via");
	$strsearch        = get_string("search");
	$strsearchresults  = get_string("searchresults");
	$strshowall = get_string("showall", "moodle", strtolower(get_string("participants", "via")));
	if($participanttype == 2){
		echo "<div style='text-align:center; margin:0;'><p>".  get_string('choosepresentor','via') ."</p></div>";
	}
	if($groupingid != 0 && $participanttype != 2){
		echo  "<div style='text-align:center; margin:0;'><p>".  get_string('groupusers','via', $grouping_name) ."</p></div>";;
	}

	$searchtext = optional_param('searchtext', '', PARAM_RAW);
	if ($frm = data_submitted()) {
		/// A form was submitted so process the input
		if (!empty($frm->add) and !empty($frm->addselect)) {
			foreach ($frm->addselect as $addsubscriber) {
				if($participanttype == 2){ //presentator
					// remove other presentors and add the new one selected
					$presentators = $DB->get_records('via_participants', array('activityid'=>$via->id, 'participanttype'=>2));
					foreach($presentators as $p){
						via_remove_participant($p->userid, $via->id);
						
						if($via->enroltype == 0 ){ // automatic enrollment							
							if(!isset($via->noparticipants)){
								$noparticipants = 0;
							}else{
								$noparticipants = $via->noparticipants;
							}
							if( $noparticipants == 0){
								$context = context_course::instance($via->course); 
								if(has_capability('moodle/course:viewhiddenactivities', $context, $p->userid)){
									via_add_participant($p->userid, $via->id, 3);	
								}else{			
									via_add_participant($p->userid, $via->id, 1);
								}
							}else{
								// add all participants as animators
								via_add_participant($p->userid, $via->id, 3);
							}
						}
					}
				}
				$added = via_add_participant($addsubscriber, $id, $participanttype);
				if (!$added) {
					print_error("Could not add user with id $addsubscriber to this activity!");
				}elseif($added === 'presenter'){
					echo "<div style='text-align:center; margin-top:0;' class='error'><h3>". get_string('userispresentor','via') ."</h3></div>";
				}
			}
		} else if (!empty($frm->remove) and !empty($frm->removeselect)) {
			foreach ($frm->removeselect as $removesubscriber) {
				if($via->enroltype == 0){
					// if enrollment is automatique we add the user as participant - we do not unenrol him/her
					via_add_participant($removesubscriber, $via->id, 1);
				}elseif (! via_remove_participant($removesubscriber, $id)) {
					
					print_error("Could not remove user with id $removesubscriber from this activity!");
				}
			}
		} else if (!empty($frm->showall)) {
			$searchtext = '';
		}
	}

	/// Get all existing subscribers for this activity.
	if (!$subscribers = via_participants($course, $via, $participanttype, $context)) {	   
		$subscribers = array();
	}

	/// Get all the potential subscribers excluding users already subscribed
	$users = via_get_potential_participants($context, 'u.id,u.email,u.firstname,u.lastname,u.idnumber', 'u.firstname ASC, u.lastname ASC');

	if (!$users) {
		$users = array();
	}
	
	foreach ($subscribers as $subscriber) {
		/* remove users already enrolled from the potential user list */
		unset($users[$subscriber->id]);	
		
		if($groupingid != 0){
			$usergroupingid = $DB->get_records_sql('SELECT distinct gg.groupingid 
													FROM mdl_groups_members gm 
													LEFT JOIN mdl_groups g ON gm.groupid = g.id
													LEFT JOIN mdl_groupings_groups gg ON gg.groupid = g.id
													WHERE gm.userid = '.$subscriber->id.' AND g.courseid = '.$via->course.'');
			/* some users might be in more than one group */ 
			if($usergroupingid){
				foreach ($usergroupingid as $gid){
					if($gid->groupingid == $groupingid){
						$subscriber->groupingid = $gid->groupingid;
						break;
					}else{
						$subscriber->groupingid = $gid->groupingid;
					}
				}
			}else{
				$subscriber->groupingid = 0;
			}
			
		}

	}
	
	

	/// This is yucky, but do the search in PHP, becuase the list we are using comes from get_users_by_capability,
	/// which does not allow searching in the database. Fortunately the list is only this list of users in this
	/// course, which is normally OK, except on the site course of a big site. But before you can enter a search
	/// term, you have already seen a page that lists everyone, since this code never does paging, so you have probably
	/// already crashed your server if you are going to. This will be fixed properly for Moodle 2.0: MDL-17550.
	if ($searchtext) {
		$searchusers = array();
		$lcsearchtext = textlib::strtolower($searchtext);
		foreach ($users as $userid => $user) {
			if (strpos(textlib::strtolower($user->email), $lcsearchtext) !== false ||
				strpos(textlib::strtolower($user->firstname . ' ' . $user->lastname), $lcsearchtext) !== false ||
				strpos(textlib::strtolower($user->idnumber), $lcsearchtext) !== false) {
					$searchusers[$userid] = $user;
				}
				unset($users[$userid]);
			}
		}	

		echo $OUTPUT->box_start('center');

		include('manage.form.php');

		echo $OUTPUT->box_end();

		echo $OUTPUT->footer();
	}
?>
