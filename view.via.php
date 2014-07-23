<?php  

/**
 *  Redirection to an activity on via server
 *
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions 
 */


    require_once("../../config.php");
	require_once($CFG->dirroot.'/mod/via/lib.php');
	
	global $DB, $USER;
 
	$id = required_param('id', PARAM_INT);
	$review = optional_param('review', NULL, PARAM_INT);
	
	if(isset($_REQUEST['playbackid'])){
		$playbackid = $_REQUEST['playbackid'];
	}
	if(isset($_REQUEST['p'])){
		$private = $_REQUEST['p'];
	}else{
		$private = NULL;
	}

	if (! $cm = get_coursemodule_from_id('via', $id)) {
		error('Course Module ID was incorrect');
	}

	if (! $via = $DB->get_record('via', array('id'=>$cm->instance))) {
		error('Activity ID was incorrect');
	}

    if (! $course = $DB->get_record('course', array('id'=>$cm->course))) {
        error('Incorrect course id');
    }

	require_login($course, false, $cm);
	
	$context = context_module::instance($cm->id);
	$PAGE->set_url('/mod/via/view.php', array('id'=>$id));
	$PAGE->set_context($context);
	
	// show some info for guests
	if (isguestuser()) {
		$PAGE->set_title(format_string($chat->name));
		echo $OUTPUT->header();
		echo $OUTPUT->confirm('<p>'.get_string('noguests', 'chat').'</p>'.get_string('liketologin'),
				get_login_url(), $CFG->wwwroot.'/course/view.via.php?id='.$course->id);

		echo $OUTPUT->footer();
		exit;
	}
		
	$api = new mod_via_api();	
			
	$connexion = false;
	$response = false;
	// in case the user still has not been added
	// if user_participants field timesynched is not null we add him, otherwise we check if he is in the activity if he isn't we add him...	
	$via_participant = $DB->get_record('via_participants', array('userid'=>$USER->id, 'activityid'=>$via->id));
	if(!has_capability('moodle/site:approvecourse', context_system::instance())){ // only users with a lower role are added
		if(isset($via_participant->timesynched)){
			$connexion = true;
		}else{
			$via_user = $DB->get_record('via_users', array('userid'=>$USER->id));
			$participants_list = get_via_participants_list($via);
			$participants = array();
			if($participants_list){
				foreach($participants_list as $participant){
					if($participant != 0){
						$participants[] = $participant['UserID'];
					}
				}	
			}
		if(in_array($via_user->viauserid, $participants)){
			$synched = $DB->set_field("via_participants", "timesynched", time(), array("id"=>$via_user->userid));		
			$connexion = true;
		}elseif($via->enroltype == 0){
				$type = get_user_type($via_user->userid, $course->id);
				// we only add participants automatically!
				if($type == 1){
				$added = via_add_participant($via_user->userid, $via->id, $type, null, 1); 
				if($added && $added != 'presenter'){
					$DB->insert_record('via_log', array('userid'=>$via_user->userid, 'viauserid'=>$via_user->viauserid, 'activityid'=>$via->id, 'action'=>'user connexion', 'result'=>'user added', 'time'=>time()));
					$connexion = true;
				}elseif($added === 'presenter'){
						echo "<div style='text-align:center; margin-top:0;' class='error'><h3>". get_string('userispresentor','via') ."</h3></div>";	
					}
				}
			}
		}
	}else{
		if($via_participant){
			$connexion = true;
		}
	}
		
	try {
		if($connexion == 'true'){
			if(!$review){
				$response = $api->UserGetSSOtoken($via);
			}else{
				$response = $api->UserGetSSOtoken($via, 3, $playbackid, $private);
			}	
		}	
				
		if($response){		
			/*
			if(!$review){
					add_to_log($course->id, "via", "view session", "view.php?id=$cm->id", $via->id, $cm->id);
			}else{
					add_to_log($course->id, "via", "view recording", "view.php?id=$cm->id", $via->id, $cm->id);
			}
			*/
					
			redirect($response);
				
		}else{
			/* user is not enrolled and is not allowed to access the recordings; example an admin user*/ 
			$PAGE->set_title($course->shortname . ': ' . format_string($via->name));
			$PAGE->set_heading($course->fullname);
			echo $OUTPUT->header();
			echo $OUTPUT->box_start();
			$msg = get_string('notenrolled', 'via');	
			notify($msg, $style='recordwarning', $return=false);
			echo $OUTPUT->box_end();		
			echo $OUTPUT->footer($course);	
		}		

	}catch (Exception $e){
				
		if($e->getMessage() == 'STATUS_INVALID'){
			echo $OUTPUT->header();	
			echo $OUTPUT->heading(get_string($e->getMessage(), 'via'));
			echo $OUTPUT->box_start('notice');
			echo get_string('error:'.$e->getMessage(), 'via');
			$DB->insert_record('via_log', array('userid'=>$USER->id, 'viauserid'=>$via_user->viauserid, 'activityid'=>$via->id, 'action'=>'user connexion', 'result'=>$e->getMessage(), 'time'=>time()));
			echo $OUTPUT->box_end();	
			echo $OUTPUT->footer($course);	
		}else{		
			print_error('error:'.$e->getMessage(), 'via');
			$DB->insert_record('via_log', array('userid'=>$USER->id, 'viauserid'=>$via_user->viauserid, 'activityid'=>$via->id, 'action'=>'user connexion', 'result'=>$e->getMessage(), 'time'=>time()));
			$result = false;
		}
	}
			
?>
