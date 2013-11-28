<?php  

/**
 *  Form to send invites to participants
 *
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions 
 */

    require_once("../../config.php");
    require_once("lib.php");
	global $DB;

    $id    = required_param('id',PARAM_INT);           // via

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

	$context = context_module::instance($cm->id);

    if (!has_capability('mod/via:manage', $context) || !$CFG->via_sendinvitation) {
        print_error('You do not have the permission to send invites');
    }

	// Initialize $PAGE
	$PAGE->set_url('/mod/via/send_invite.php', array('id' => $cm->id));
	$PAGE->set_title($course->shortname . ': ' . format_string($via->name));
	$PAGE->set_heading($course->fullname);
	$button = $OUTPUT->update_module_button($cm->id,'via');
	$PAGE->set_button($button);

	if ($frm = data_submitted()) {

		/// A form was submitted so process the input
        if (!empty($frm->msg)) {
			$via->invitemsg = $frm->msg;
		}
		$via->sendinvite = 1;
		$DB->update_record("via", $via);
		redirect($CFG->wwwroot."/mod/via/view.php?id=".$cm->id, get_string("invitessend", "via"), 0);	
    }
	
	/// Print the page header
	echo $OUTPUT->header();
	
    echo $OUTPUT->box_start('center');
	
    include('send_invite.form.html');

    echo $OUTPUT->box_end();
	
    echo $OUTPUT->footer($course);

?>
