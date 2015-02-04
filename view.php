<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * View activity details
 * 
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/via/lib.php');

global $DB, $CFG, $USER;

$action = optional_param('action', null, PARAM_CLEAN);
$id = required_param('id', PARAM_INT);
$error = optional_param('error', null, PARAM_TEXT);

if (!($cm = get_coursemodule_from_id('via', $id))) {
    error("Course module ID is incorrect");
}

if (!($course = $DB->get_record('course', array('id' => $cm->course)))) {
    error("Course ID is incorrect");
}
if (!($via = $DB->get_record('via', array('id' => $cm->instance)))) {
    error("Via ID is incorrect");
}
if (!($context = context_module::instance($cm->id))) {
    error("Module context is incorrect");
}

require_login($course->id, false, $cm);

require_capability('mod/via:view', $context);


// Initialize $PAGE.
$PAGE->set_url('/mod/via/view.php', array('id' => $cm->id));
$PAGE->requires->js('/mod/via/javascript/viabutton.js');

$PAGE->set_title($course->shortname . ': ' . format_string($via->name));
$PAGE->set_heading($course->fullname);

// Show some info for guests.
if (isguestuser()) {
    $PAGE->set_title(format_string($via->name));
    echo $OUTPUT->header();
    echo $OUTPUT->confirm('<p>'.get_string('noguests', 'chat').'</p>'.get_string('liketologin'),
        get_login_url(), $CFG->wwwroot.'/course/view.php?id='.$course->id);

    echo $OUTPUT->footer();
    exit;
}

if ($frm = data_submitted()) {
    // Participant is modifying his confrmation status.
    if (!empty($frm->confirm)) {
        via_set_participant_confirmationstatus($cm->instance, 2);
    } else if (!empty($frm->notconfirm)) {
        via_set_participant_confirmationstatus($cm->instance, 3);
    } else if (!empty($frm->modify)) {
        via_set_participant_confirmationstatus($cm->instance, 1);
    }
}

// We validate if the activity was deleted in Via + if the user has editing rights we update Via with the information in moodle.
try {
    $previous = $_SERVER['HTTP_REFERER'];
    $connectedusers = 0;
    if (strpos($previous, 'modedit') == false && strpos($previous, 'via/view') == false ) {
        // We only check or update if we are not coming directly from the editing page.
        $api = new mod_via_api();
        $sviinfos = $api->activity_get($via);

        if ($sviinfos == "ACTIVITY_DOES_NOT_EXIST") {
            $deleted = true;

        } else if (has_capability('mod/via:manage', $context)) {
            $update = $api->activity_edit($via);
            $connectedusers = $sviinfos["NbConnectedUsers"];
        }
    }
} catch (Exception $e) {
    notify(get_string("error:".$e->getMessage(), "via"));
}

$button = $OUTPUT->update_module_button($cm->id, 'via');
$PAGE->set_button($button);

// Print the page header.
echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($via->name));

echo $OUTPUT->box_start('center', '', '', 0, 'generalbox', 'intro');

if (isset($deleted)) {
    echo '<p>'.get_string('activity_deleted', 'via').'</p>';
    echo $OUTPUT->box_end();

} else {
    $table = new html_table();
    $table->align = array('center', 'center');
    $table->attributes['class'] = 'generaltable boxaligncenter';
    $table->id = 'via_activity';
    $table->width = "90%";
    $table->data = array();

    if ($via->activitytype != 2) {
        $row = array();
        $row[] = "<p style='text-align:left; margin: 0.5em 0;'><b>".get_string("startdate", "via").
            ":</b> " . userdate($via->datebegin) .'</p>';
        $row[] = "<p style='text-align:left; margin: 0.5em 0;'><b>".get_string("enddate", "via").
            ":</b> " .  userdate($via->datebegin + ($via->duration * 60)) .'</p>';
        $table->data[] = $row;

        $cell = new html_table_cell("<p style='text-align:left; margin: 0.5em 0;'>
        <b>".get_string("duration", "via")." :</b> " . ($via->duration)."</p>", '');
        $cell->colspan = 2;
        $table->data[] = new html_table_row(array($cell));
    }

    $cell = new html_table_cell("<p style='text-align:left; margin: 0.5em 0;'>
    <b>".get_string("description", "via").":</b></p>" . format_module_intro('via', $via, $cm->id));

    $cell->colspan = 2;
    $table->data[] = new html_table_row(array($cell));

    if (has_capability('mod/via:view', $context)) {

        if (get_config('via', 'via_technicalassist_url') == null) {
            $assistant = '<a class="viabutton"" target="configvia" href="'.$CFG->wwwroot.'/mod/via/view.assistant.php?redirect=6"
            onclick="this.target=\'configvia\';
            return openpopup(null, {url:\'/mod/via/view.assistant.php?redirect=6\',
            name:\'configvia\', options:\'menubar=0,location=0,scrollbars,resizable,width=750,height=700\'});">';
        } else {
            $assistant = '<a class="viabutton"" target="configvia" href="' . get_config('via', 'via_technicalassist_url'). '"
            onclick="this.target=\'configvia\';
            return openpopup(null, {url:\'' . get_config('via', 'via_technicalassist_url').'\',
            name:\'configvia\', options:\'menubar=0,location=0,scrollbars,resizable,width=750,height=700\'});">';
        }

        $row2 = array();
        $row2[] = '<p class="title">'.get_string('preparation', 'via') . '</p>
        <a class="viabutton"" target="configvia" href="' . $CFG->wwwroot .'/mod/via/view.assistant.php?redirect=7"
        onclick="this.target=\'configvia\';
        return openpopup(null, {url:\'/mod/via/view.assistant.php?redirect=7\',
        name:\'configvia\', options:\'menubar=0,location=0,scrollbars,resizable,width=750,height=700\'});">
        <img src="' . $CFG->wwwroot . '/mod/via/pix/config.png" width="27" height="27" hspace="5" alt="' .
            get_string('recentrecordings', 'via') . '" />' .get_string("configassist", "via").'</a>';

        $row2[] = '<p class="title"><br/></p>'. $assistant .'<img src="' .
            $CFG->wwwroot . '/mod/via/pix/assistance.png" width="27" height="27" hspace="5" alt="' .
            get_string('recentrecordings', 'via') . '" />'.get_string("technicalassist", "via").'</a>';

        $table->data[] = $row2;
    }

    if (has_capability('mod/via:manage', $context)) {

        $row3 = array();

        $row3[] = "<a class='viabutton' href='send_invite.php?id=$cm->instance'>
        <img src='" . $CFG->wwwroot . "/mod/via/pix/mail.png' width='27' height='27' alt='".
        get_string("sendinvite", "via") . "' title='".get_string("sendinvite", "via") . "'
        hspace='5'/>".get_string("sendinvite", "via")."</a>";

        // For animator, presentator, show link to manage participants.
        $row3[] = "<a class='viabutton' href='manage.php?id=$cm->instance'>
        <img src='" . $CFG->wwwroot . "/mod/via/pix/users.png' width='27' height='27' alt='".
        get_string("manageparticipants", "via") . "' title='".get_string("manageparticipants", "via") . "'
        hspace='5'/>".get_string("manageparticipants", "via")."</a>";

        $table->data[] = $row3;
    }

    // Buttons so that students may confirm teir precence.
    if (!has_capability('mod/via:manage', $context) && $via->needconfirmation && get_config('via', 'via_participantmustconfirm')) {

        // If participant must confim attendance.
        $confirmation = true;

        if ($ptypes = $DB->get_records('via_participants', array('userid' => $USER->id, 'activityid' => $via->id))) {

            foreach ($ptypes as $participanttype) {
                if ($participanttype->confirmationstatus == 1) {
                    $confirmation = false;
                } else {
                    $type = $participanttype->confirmationstatus;
                }
            }

            $cell = new html_table_cell();
            $cell->colspan = 2;
            $cell->style = 'text-align:center';

            if (!$confirmation) {
                $cell->text = get_string("confirmneeded", "via")."<br>
                <form name='confirmation' action='?id=".$cm->id."' method='POST'>
                    <input type='submit' value='".get_string("attending", "via")."' id='confirm' name='confirm'>
                    <input type='submit' value='".get_string("notattending", "via")."' id='notconfirm' name='notconfirm'>
                </form>";
            } else {
                if ($type == 2) {
                    $attending = get_string("hasconfirmed", "via");
                } else if ($type == 1) {
                    $attending = get_string("hasconfirmednot", "via");
                } else if ($type == 3) {
                    $attending = get_string("notattending", "via");
                }
                // Participant already answered if he's attending or not, but he may want to change his anwser.
                $cell->text = $attending."<br>
                <form name='confirmation' action='?id=".$cm->id."' method='POST'>
                <input type='submit' value='".get_string("edit")."' name='modify'>
                </form>";
            }
            $table->data[] = new html_table_row(array($cell));
        }

    }


    // Get the type of access user can view.
    $access = via_access_activity($via);
    $viewinfo = true;

    if (has_capability('mod/via:view', $context)) {

        $cell = new html_table_cell();
        $cell->colspan = 2;
        $cell->style = 'text-align:center';
        $cell->text = '<p class="title">'.get_string('accessactivity', 'via') . '</p>';

        switch($access) {
            case 1:
                // Activity is started, user can access it.
                if ($via->recordingmode != 0) {
                    $cell->text .= '<p>' .get_string('recordwarning', 'via') .'</p>';
                    $cell->text .= '<p><input type="checkbox" id="checkbox" />'.get_string('recordaccept', 'via').
                        '<p id="error" class="error hide">'.get_string('mustaccept', 'via').'</p>';
                    $cell->text .= via_add_button(true, true, $cm->id);
                    $cell->text .= via_add_button(true, false);
                } else {
                    $cell->text .= via_add_button(false, false, $cm->id);
                }
                $table->data[] = new html_table_row(array($cell));
                break;
            case 2:
                // Acitivity isn't started yet, but animators and presentators can access it to do some preparation.
                $cell->text .= '<p>' .get_string("notstarted", "via").'</p><br/>';
                if ($via->recordingmode != 0) {
                    $cell->text .= '<p>' .get_string('recordwarning', 'via') .'</p>';
                    $cell->text .= '<p><input type="checkbox" id="checkbox" />'.get_string('recordaccept', 'via').'
                    <p id="error" class="error hide">'.get_string('mustaccept', 'via').'</p>';
                    $cell->text .= via_add_button(true, true, $cm->id, true);
                    $cell->text .= via_add_button(true, false, null, true);
                } else {
                    $cell->text .= via_add_button(false, false, $cm->id, true);
                }
                $table->data[] = new html_table_row(array($cell));
                break;
            case 3:
                // For participants : activity isn't started yet.
                $cell->text .= '<p>' .get_string("notstarted", "via").'</p><br/>';
                $table->data[] = new html_table_row(array($cell));
                break;
            case 5:
                // Acitivity is done.
                $cell->text .= get_string("activitydone", "via");
                $table->data[] = new html_table_row(array($cell));
                break;
            case 6:
                // Participant can't access activity, he is not enroled in it.
                $cell->text .= get_string("notenrolled", "via");
                $table->data[] = new html_table_row(array($cell));
                $viewinfo = false;
                break;
            case 7;
                // Admin user which is not enrolled but can access the activity anyways.
                if ($via->activitytype == 1 && $via->datebegin + ($via->duration * 60) < time()) {
                    $cell->text .= get_string("activitydone", "via");
                } else {
                    $cell->text .= '<p>' .get_string("adminnotrenrolled", "via").'</p><br/>';
                    $cell->text .= via_add_button(false, true, $cm->id, false, true);
                }
                $table->data[] = new html_table_row(array($cell));
                break;
            default :
                break;
        }

        echo html_writer::table($table); // Print activity info.

        echo $OUTPUT->box_end();

        // Print recordings list.
        if ($viewinfo && has_capability('mod/via:view', $context) && is_mobile_phone() == false) {
            if (isset($error)) {
                echo  'this title aready exists';
            }

            $playbacks = via_get_all_playbacks($via);

            if ($playbacks) {
                echo via_get_playbacks_table($playbacks, $via, $context);
            }

        }

        // If activity is finished and the user has the right to see reports, we display the report.
        echo $OUTPUT->box_start('center');

        if (get_config('via', 'via_presencestatus') && $via->activitytype == 1 &&
            ($via->datebegin + ($via->duration * 60)) < time()) {
            if ($connectedusers == 0 && has_capability('mod/via:viewpresence', $context)) {

                echo via_report_btn($via->id);

                echo via_get_participants_table($via, $context, true);

                echo via_report_btn($via->id);

                echo "<p style='margin: auto; width: 90%;'>".get_string("presencewarning", "via")."</p>";
            }
        } else {
            // If the activity has not yet started we print the user list for everyone to see!
            echo via_get_participants_table($via, $context);
        }

        echo $OUTPUT->box_end();

        echo '<hr>';
        echo '<a class="index" href="'.$CFG->wwwroot .'/mod/via/index.php?id='.$course->id.'">'.
            get_string('list_activities', 'via').'</a>';

        echo '<div class="vialogo" ><img src = "' . $CFG->wwwroot . '/mod/via/pix/logo_via.png" width="60"
        height="33" alt="VIA" /> '.get_string('by', 'via').'&nbsp;&nbsp;<img src = "' .
            $CFG->wwwroot . '/mod/via/pix/logo_svi.png" width="52" height="33" alt="VIA" /></div>';
    }
}

echo $OUTPUT->footer();
