<?php

/**
 * Visualization of a via instance.
 *
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions 
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/via/lib.php');

global $DB, $CFG, $USER;

$action = optional_param('action', null, PARAM_CLEAN);
$id = required_param('id', PARAM_INT);

if (!($cm = get_coursemodule_from_id('via', $id))) {
	error("Course module ID is incorrect");
}

if (!($course = $DB->get_record('course', array('id'=>$cm->course)))) {
	error("Course ID is incorrect");
}
if (!($via = $DB->get_record('via', array('id'=> $cm->instance)))) {
    error("Via ID is incorrect");
}
if (!($context = context_module::instance($cm->id))) {
    error("Module context is incorrect");
}

require_login($course->id, false, $cm);

require_capability('mod/via:view', $context);

if(has_capability('mod/via:delete', $context)) {
	
} 

// Initialize $PAGE
$PAGE->set_url('/mod/via/view.php', array('id' => $cm->id));
$PAGE->requires->js('/mod/via/javascript/viabutton.js');

$PAGE->set_title($course->shortname . ': ' . format_string($via->name));
$PAGE->set_heading($course->fullname);

//add_to_log($course->id, "via", "view session details", "view.php?id=$cm->id", $via->id, $cm->id);x

// show some info for guests
if (isguestuser()) {
    $PAGE->set_title(format_string($via->name));
    echo $OUTPUT->header();
    echo $OUTPUT->confirm('<p>'.get_string('noguests', 'chat').'</p>'.get_string('liketologin'),
            get_login_url(), $CFG->wwwroot.'/course/view.php?id='.$course->id);

    echo $OUTPUT->footer();
    exit;
}

if ($frm = data_submitted()) {	
// participant is modifying his confrmation status
	if (!empty($frm->confirm)) {
			via_set_participant_confirmationstatus($cm->instance, 2);
		}elseif (!empty($frm->notconfirm)){
			via_set_participant_confirmationstatus($cm->instance, 3);
		}elseif (!empty($frm->modify)){
			via_set_participant_confirmationstatus($cm->instance, 1);
		}
}
	
// We validate if the activity was deleted in Via + if the user has editing rights we update Via with the information in moodle
try{
		
	$api = new mod_via_api();
	$svi_infos = $api->activityGet($via); 
	if($svi_infos == "ACTIVITY_DOES_NOT_EXIST"){
		$deleted = true;
	}elseif ($via->timemodified > time()+2*60 && has_capability('mod/via:manage', $context)) {
		$update = $api->activityEdit($via);
	} 
	
}catch(Exception $e) {
	$result = false;
	notify(get_string("error:".$e->getMessage(), "via"));	
}

$button = $OUTPUT->update_module_button($cm->id,'via');
$PAGE->set_button($button);

/// Print the page header
echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($via->name));

echo $OUTPUT->box_start('center', '', '', 0, 'generalbox', 'intro');

if(isset($deleted)){
		
	echo '<p>'.get_string('activity_deleted', 'via').'</p>';
	echo $OUTPUT->box_end();
	
}else{
	$table = new html_table();
	$table->align = array('center', 'center');
	$table->attributes['class'] = 'generaltable boxaligncenter';
	$table->id = 'via_activity';
	$table->width = "90%";
	$table->data = array();

	if($via->activitytype != 2){
		$row = array();
		$row[] = "<p style='text-align:left; margin: 0.5em 0;'><b>".get_string("startdate", "via").":</b> " . userdate($via->datebegin) .'</p>';
		$row[] = "<p style='text-align:left; margin: 0.5em 0;'><b>".get_string("enddate", "via").":</b> " .  userdate($via->datebegin + ($via->duration*60)) .'</p>';
		$table->data[] = $row;
		
		$cell = new html_table_cell("<p style='text-align:left; margin: 0.5em 0;'><b>".get_string("duration", "via")." :</b> " . ($via->duration)."</p>", '');
		$cell->colspan = 2;
		$table->data[] = new html_table_row(array($cell));
	}
	
	$cell = new html_table_cell("<p style='text-align:left; margin: 0.5em 0;'><b>".get_string("description", "via").":</b> " . strip_tags($via->description)."</p>");
	$cell->colspan = 2;
	$table->data[] = new html_table_row(array($cell));
	
	if(has_capability('mod/via:view', $context)){
		// get the type of access user can view
		$access = via_access_activity($via);
		
		if(get_config('via', 'via_technicalassist_url') == null){
			$assistant = '<a class="button" target="configvia" href="'.$CFG->wwwroot.'/mod/via/view.assistant.php?redirect=6" onclick="this.target=\'configvia\'; 
					      return openpopup(null, {url:\'/mod/via/view.assistant.php?redirect=6\', name:\'configvia\', options:\'menubar=0,location=0,scrollbars,resizable,width=750,height=700\'});">';
		}else{
			$assistant = '<a class="button" target="configvia" href="' . get_config('via', 'via_technicalassist_url'). '" onclick="this.target=\'configvia\'; 
					      return openpopup(null, {url:\'' . get_config('via', 'via_technicalassist_url').'\', name:\'configvia\', options:\'menubar=0,location=0,scrollbars,resizable,width=750,height=700\'});">';
		}

		$row2 = array();
		$row2[] =	'<p class="title">'.get_string('preparation', 'via') . '</p>
					<a class="button" target="configvia" href="' . $CFG->wwwroot .'/mod/via/view.assistant.php?redirect=7" onclick="this.target=\'configvia\'; 
					 return openpopup(null, {url:\'/mod/via/view.assistant.php?redirect=7\', name:\'configvia\', options:\'menubar=0,location=0,scrollbars,resizable,width=750,height=700\'});">
					<img src="' . $CFG->wwwroot . '/mod/via/pix/config.png" width="27" height="27" hspace="5" alt="' .get_string('recentrecordings', 'via') . '" />' .get_string("configassist", "via").'</a>';	
		
		$row2[] =	'<p class="title"><br/></p>'. $assistant .'<img src="' . $CFG->wwwroot . '/mod/via/pix/assistance.png" width="27" height="27" hspace="5" alt="' .get_string('recentrecordings', 'via') . '" />'.get_string("technicalassist", "via").'</a>';
			
		$table->data[] = $row2;
	}
	
	if (has_capability('mod/via:manage', $context)) {
		
		$row3 = array();
		if(get_config('via','via_sendinvitation')){ // if user can send invites
			$row3[] ="<a class='button' href='send_invite.php?id=$cm->instance'><img src='" . $CFG->wwwroot . "/mod/via/pix/mail.png' width='27' height='27' alt='".get_string("sendinvite", "via") . "' title='".get_string("sendinvite", "via") . "'  hspace='5'/>".get_string("sendinvite", "via")."</a>";
		}
		
		// for animator, presentator, show link to manage participants
		$row3[] ="<a class='button' href='manage.php?id=$cm->instance'><img src='" . $CFG->wwwroot . "/mod/via/pix/users.png' width='27' height='27' alt='".get_string("manageparticipants", "via") . "' title='".get_string("manageparticipants", "via") . "'  hspace='5'/>".get_string("manageparticipants", "via")."</a>";
		$table->data[] = $row3;
	}
	
	/* buttons so that students may confirm teir precence */
	if (!has_capability('mod/via:manage', $context) && $via->needconfirmation && get_config('via','via_participantmustconfirm')){
		
		// if participant must confim attendance
		$confirmation = true;
		
		if($participant_types = $DB->get_records('via_participants', array('userid'=>$USER->id, 'activityid'=>$via->id))){				
			
			via_update_moodle_confirmationstatus($via, $USER->id); // check if participant confirmation status changed on Via server
			
			foreach($participant_types as $participant_type){
				if($participant_type->confirmationstatus == 1){
					$confirmation = false;
				}else{
					$type = 	$participant_type->confirmationstatus;
				}
			}
			
			$cell = new html_table_cell();
			$cell->colspan = 2;
			$cell->style = 'text-align:center';
			
			if(!$confirmation){
				$cell->text = get_string("confirmneeded", "via")."<br>
							<form name='confirmation' action='?id=".$cm->id."' method='POST'>
								<input type='submit' value='".get_string("attending", "via")."' id='confirm' name='confirm'>
								<input type='submit' value='".get_string("notattending", "via")."' id='notconfirm' name='notconfirm'>
							</form>";
			}else{
				if($type == 2){
					$attending = get_string("hasconfirmed", "via");
				}elseif($type == 1){
					$attending = get_string("hasconfirmednot", "via");
				}elseif($type == 3){
					$attending = get_string("notattending", "via");
				}
				// participant already answered if he's attending or not, but he may want to change his anwser
				$cell->text = $attending."<br>
						<form name='confirmation' action='?id=".$cm->id."' method='POST'>
						<input type='submit' value='".get_string("edit")."' name='modify'>
						</form>";
			}
			$table->data[] = new html_table_row(array($cell));
		}			

	}
	
	if(has_capability('mod/via:view', $context)){	
		
		$cell = new html_table_cell();
		$cell->colspan = 2;
		$cell->style = 'text-align:center';
		$cell->text = '<p class="title">'.get_string('accessactivity', 'via') . '</p>';
		
		switch($access){	
			case 1:
				// activity is started, user can access it
				if($via->recordingmode != 0){
					$cell->text .= '<p>' .get_string('recordwarning', 'via') .'</p>';
					$cell->text .= '<p><input type="checkbox" id="checkbox" />'.get_string('recordaccept', 'via').'<p id="error" class="error hide">'.get_string('mustaccept','via').'</p>';
					$cell->text .= "<a id='active' class='accessbutton active hide' href='view.via.php?id=$cm->id' target='_blank'><img src='" . $CFG->wwwroot . "/mod/via/pix/access.png' width='27' height='27' alt='".get_string("gotoactivity", "via") . "' title='".get_string("gotoactivity", "via") . "' hspace='5' />".get_string("gotoactivity", "via")."</a>";	
					$cell->text .= "<a id='inactive' class='accessbutton inactive' href='#' ><img src='" . $CFG->wwwroot . "/mod/via/pix/access.png' width='27' height='27' alt='".get_string("gotoactivity", "via") . "' title='".get_string("gotoactivity", "via") . "' hspace='5' />".get_string("gotoactivity", "via")."</a>";	
				}else{
					$cell->text .= "<a class='accessbutton' href='view.via.php?id=$cm->id' target='_blank'><img src='" . $CFG->wwwroot . "/mod/via/pix/access.png' width='27' height='27' alt='".get_string("gotoactivity", "via") . "' title='".get_string("gotoactivity", "via") . "' hspace='5' />".get_string("gotoactivity", "via")."</a>";	
				}
				$table->data[] = new html_table_row(array($cell));
				break;
			case 2:
				// acitivity isn't started yet, but animators and presentators can access it to do some preparation
				$cell->text .= '<p>' .get_string("notstarted", "via").'</p><br/>'; 
				
				if($via->recordingmode != 0){
					$cell->text .= '<p>' .get_string('recordwarning', 'via') .'</p>';
					$cell->text .= '<p><input type="checkbox" id="checkbox" />'.get_string('recordaccept', 'via').'<p id="error" class="error hide">'.get_string('mustaccept','via').'</p>';
					$cell->text .= "<a id='active' class='accessbutton active hide' href='view.via.php?id=$cm->id' target='_blank'><img src='" . $CFG->wwwroot . "/mod/via/pix/access.png' width='27' height='27' alt='".get_string("prepareactivity", "via") . "' title='".get_string("prepareactivity", "via") . "' hspace='5' />".get_string("prepareactivity", "via")."</a>";	
					$cell->text .= "<a id='inactive' class='accessbutton inactive' href='#' ><img src='" . $CFG->wwwroot . "/mod/via/pix/access.png' width='27' height='27' alt='".get_string("prepareactivity", "via") . "' title='".get_string("prepareactivity", "via") . "' hspace='5' />".get_string("prepareactivity", "via")."</a>";	
				}else{
					$cell->text .= "<a class='accessbutton' href='view.via.php?id=$cm->id' target='_blank'><img src='" . $CFG->wwwroot . "/mod/via/pix/access.png' width='27' height='27' alt='".get_string("prepareactivity", "via") . "' title='".get_string("prepareactivity", "via") . "' hspace='5' />".get_string("prepareactivity", "via")."</a>";	
				}
				$table->data[] = new html_table_row(array($cell));
				break;
			case 3:
				// for participants : activity isn't started yet
				$cell->text .= '<p>' .get_string("notstarted", "via").'</p><br/>'; 
				$table->data[] = new html_table_row(array($cell));
				break;
			case 5:
				// acitivity is done
				$cell->text .= get_string("activitydone", "via");
				$table->data[] = new html_table_row(array($cell));
				break;
			case 6:
				// participant can't access activity, he is not enroled in it.
				$cell->text .= get_string("notenrolled", "via");
				$table->data[] = new html_table_row(array($cell));
				break;
			default :
				break;		
		}

		echo html_writer::table($table); // print activity info

		echo $OUTPUT->box_end();
		
		function is_mobile_phone(){
			
			$ua = strtolower($_SERVER['HTTP_USER_AGENT']);
			$mobiles = array("iphone", "ipod", "blackberry", "nokia", "phone",
				"mobile safari", "iemobile", "ipad", "android");
			foreach($mobiles as $mobile)
			{
				if(strpos($ua,$mobile)) return true;
			}
			return false;
		}
		
		/* print recordings list  */	
		if(has_capability('mod/via:view', $context) && is_mobile_phone() == false){	
			if(isset($_REQUEST['error'])){
				echo  'this title aready exists';
			}
			
			$playbacks = via_get_all_playbacks($via);
						
			if($playbacks){
										
				echo "<table cellpadding='2' cellspacing='0' class='generaltable boxaligncenter' id='via_recordings'>";				
											
				foreach($playbacks as $key=>$playback){ 
					// lists all playbacks for acitivity		
					if(isset($playback->playbackrefid)){
						$style = "atelier";
						$li = "<li style='list-style-image:url(".$CFG->wwwroot."/mod/via/pix/arrow.gif);'>";
						$endli = "</li>";
					}else{
						$style = "";	
						$li = "";
						$endli = "";
					}
					
							
					$private = $playback->ispublic? "" : "dimmed_text";
					
								
					if($playback->ispublic || has_capability('mod/via:manage', $context) /*|| has_capability('moodle/site:config', context_system::instance())*/){	
						// if playback is public and/or if user is animator or presentator
						if(!isset($header)){ // we only display it once
							$header = "<h2 class='main'>".get_string("recordings", "via")."</h2>";
							echo $header;
						}
						echo "<tr class='$style'>";
									
						echo "<td class='title $style  $private'>$li";
									
						echo $playback->title;
									
						echo "$endli</td>";
									
						echo "<td class='duration  $private' style='text-align:left'>".userdate(strtotime($playback->creationdate))."<br/> ".get_string("timeduration", "via")." ".gmdate("H:i:s",  $playback->duration)."</td>";
						
						if(has_capability('mod/via:manage', $context)){
							
							if($playback->ispublic == 1){
								$checked = get_string("mask", "via");
								$css = 'maskvia';
							}else{
								$checked = get_string("show", "via");
								$css = 'show';
							}
							
							echo "<td class='modify'>";
							echo '<form class="editplayback '.$css.'" action="edit_review.php?id='.$via->id.'" method="post">
							<input type="hidden" name="playbackid" id="playbackid" value="'.$key.'">
							<input type="hidden" name="edit" id="edit" value="Enregistrer"  />
							<input type="submit" name="ispublic" id="ispublic" value="'.$checked.'" /></form>';
							echo "</td>"; 	
														 							
							echo "<td class='modify'>";
							echo '<form id="'.$key.'" class="modify" action="edit_review.php?id='.$via->id.'" method="post">
							<input type="hidden" name="playbackid" id="playbackid" value="'.$key.'">
							<input id="'.$key.'" type="submit" value="'.get_string("edit", "via").'" />
							</form>'; 
							echo "</td>"; 	
							
							echo "<td class='modify'>";
							echo '<form class="deleteplayback" action="edit_review.php?id='.$via->id.'" method="post">
							<input type="hidden" name="playbackid" id="playbackid" value="'.$key.'">
							<input type="submit" name="delete" id="delete" value="'.get_string("delete").'" /></form>';
							echo "</td>";
							
						}
						
						echo "<td class='review  $private'>";
						if($private == "dimmed_text"){
							$param = '&p=1';
						}else{
							$param = '';	
						}

						echo '<form id="'.$key.'" class="playback" action="view.via.php?id='.$cm->id.'&review=1'.$param.'" target="_blank" method="post">
						<input type="hidden" name="playbackid" value="'.$key.'">
						<input id="'.$key.'" type="submit" value="'.get_string("view", "via").'" />
						</form>';  	
													
						echo "</td>";

						echo "</tr>";
					}
				}
							
				echo "</table>";
			}
					
		}
		
		/* print user list */	
		echo get_participants_list_table($via, $context);

		echo '<hr>';
		echo '<a class="index" href="'.$CFG->wwwroot .'/mod/via/index.php?id='.$course->id.'">'.get_string('list_activities','via').'</a>';
		echo '<div class="vialogo" ><img src="' . $CFG->wwwroot . '/mod/via/pix/logo_via.png" width="60" height="33" alt="VIA" /> '.get_string('by','via').'&nbsp;&nbsp;<img src="' . $CFG->wwwroot . '/mod/via/pix/logo_svi.png" width="52" height="33" alt="VIA" /></div>';
	}
}
	
echo $OUTPUT->footer();

