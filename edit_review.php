<?php  

/**
 * to edit a playback
 *
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions 
 */
	global $DB;
    require_once("../../config.php");
    require_once("lib.php");
	

    $id    = required_param('id',PARAM_INT);           // via
	$edit  = optional_param('edit', false, PARAM_INT);           // edit via reviews
	
	$playbackid = $_REQUEST['playbackid'];

    if (!$via = $DB->get_record('via', array('id'=>$id))) {
        print_error("Via ID is incorrect");
    }

    if (!$course = $DB->get_record('course', array('id'=>$via->course))) {
        print_error("Could not find this course!");
    }

    if (! $cm = get_coursemodule_from_instance("via", $via->id, $course->id)) {
        $cm->id = 0;
    }

    require_login($course->id, false, $cm);
	$context =  context_module::instance($cm->id);

    if (!has_capability('mod/via:manage', $context)) {
        print_error('You do not have the permission to edit via playbacks');
    }
	
	$playbacks = via_get_all_playbacks($via);
	
	foreach($playbacks as $key=>$playbacksearch){
		if(strtoupper($key) == strtoupper($playbackid)){
			$playback = $playbacksearch;
			break;
		}
	}
	
	$strrecording = get_string("recording", "via");
	
   if ($frm = data_submitted()) {
	   // editing playback
	   
	   if(isset($frm->cancel)){
		   redirect("view.php?id=$cm->id");
	   }
	   
		if(isset($frm->edit)){
			if(isset($frm->title)){
				$playback->title = $frm->title;
			}
		
			if(isset($frm->ispublic)){
				$playback->ispublic = 1;
			}else{
				$playback->ispublic = 0;
			}
			
			$api = new mod_via_api();
	
			try {
					$result = $api->editPlayback($via, $playbackid, $playback);
			}
			catch (Exception $e){
				$result = false;
				echo $OUTPUT->notification("error:".$e->getMessage());
			}
			
			if($result){
				redirect("view.php?id=$cm->id");
			}			
		}
	   
	}
	
	$PAGE->set_url('/mod/via/edit_review.php', array('id' => $cm->id));	
	$PAGE->set_title($course->shortname.': '.$via->name);
	$PAGE->set_heading($course->fullname);
	echo $OUTPUT->header();
	
    echo $OUTPUT->box_start('center');
	
	echo "<h2>".get_string("editrecord", "via")."</h2>";

	include('edit_review.form.php');

    echo $OUTPUT->box_end();
	
    echo $OUTPUT->footer($course);

?>
