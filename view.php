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
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once('../../config.php');
global $CFG;
require_once($CFG->dirroot.'/mod/via/lib.php');
require_once(get_vialib());

global $DB, $CFG, $USER;

$action = optional_param('action', null, PARAM_CLEAN);
$error = optional_param('error', null, PARAM_TEXT);
$synch = optional_param('synch', null, PARAM_CLEAN);
$pbsync = optional_param('pbsync', null, PARAM_CLEAN);

// Modified to come from viaassign, which has a via id but does not have a course module id!
$id = optional_param('id', null, PARAM_INT);
$viaid = optional_param('viaid', null, PARAM_INT);
$viaidpage = optional_param('viaidpage', null, PARAM_INT);
$subroomid = optional_param('subroomid', null, PARAM_TEXT);

$viaurlparam = 'id';
if ($viaidpage) {
    $viaid = $viaidpage;
}
if ($id && !$viaidpage) {
    if (!($cm = get_coursemodule_from_id('via', $id))) {
        print_error("Course module ID is incorrect");
    }
    if (!($via = $DB->get_record('via', array('id' => $cm->instance)))) {
        print_error("Via ID is incorrect");
    }

    $viaurlparamvalue = $cm->id;
} else if ($viaid || $viaidpage) {
    $viaassign = $DB->get_record('viaassign_submission', array('viaid' => $viaid));
    if (!($cm = get_coursemodule_from_instance('viaassign', $viaassign->viaassignid, null, false, MUST_EXIST))) {
        print_error("Course module ID is incorrect");
    }
    if (!($via = $DB->get_record('via', array('id' => $viaid)))) {
        print_error("Via ID is incorrect");
    }
    $viaurlparam = 'viaid';
    $viaurlparamvalue = $viaid;
}

if (!($course = $DB->get_record('course', array('id' => $cm->course)))) {
    print_error("Course ID is incorrect");
}

if (!($context = via_get_module_instance($cm->id))) {
    print_error("Module context is incorrect");
}

require_login($course->id, false, $cm);

require_capability('mod/via:view', $context);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$ishtml5 = $via->activityversion == 1;

// SYNC STUFF.

// We validate if the activity was deleted in Via + if the user has editing rights we update Via with the information in moodle.
try {
    if (isset($_SERVER['HTTP_REFERER'])) {
        $previous = $_SERVER['HTTP_REFERER'];
    } else {
        $previous = '';
    }

    $connectedusers = 0;
    $deleted = !isset($via->viaactivityid) && $via->activitytype != 3;

    if (!$deleted && $via->activitytype != 4 && $via->activitytype != 3 && strpos($previous, 'modedit') == false
        && strpos($previous, 'via/view') == false ) {
        // We only check or update if we are not coming directly from the editing page.
        $api = new mod_via_api();
        if ($ishtml5) {
            $sviinfos = $api->via_activity_get_html5($via->viaactivityid);
        } else {
            $sviinfos = $api->activity_get($via);
        }

        if ($sviinfos == "ACTIVITY_DOES_NOT_EXIST") {
            $deleted = true;
            $via->viaactivityid = null;
            $updatevia = $DB->update_record('via', $via);
            // Delete activity associated playbacks.
            $DB->execute('DELETE FROM {via_playbacks} WHERE activityid = ' . $via->id);
        } else if (has_capability('mod/via:manage', $context)) {

            if ($via->activitytype == 1 && time() > $via->datebegin && isset($sviinfos["Duration"]) &&
                $via->duration != $sviinfos["Duration"] && $via->duration != 0) {
                // When activity has been ended in Via.
                $via->duration = $sviinfos["Duration"];
                $updatevia = $DB->update_record('via', $via);
            }

            if ($ishtml5) {
                // No need of $connectedusers because no reports yet.
                // Don't get why we need an API update here? we don't come from edition page.
            } else {
                $connectedusers = $sviinfos["NbConnectedUsers"];

                $update = $api->activity_edit($via);
            }

        }
    }

    via_viewed_log($via, $context, $cm);
} catch (Exception $e) {
    print_error(get_error_message($e));
}

if (!$deleted) {
    $cancreatevia = false;

    if ($viaid) {
        require_once($CFG->dirroot.'/mod/viaassign/locallib.php');
        $button = "";
        $PAGE->navbar->add(format_string($via->name), '/mod/via/view.php?viaid='.$viaid);
        $viaassign = new viaassign($context,  $cm, $course);

        // Only the host can modify the activity! OR someone with editing rights!
        if ($host = $DB->get_record('via_participants',
                array('userid' => $USER->id, 'activityid' => $via->id, 'participanttype' => 2))
            || has_capability('mod/viaassign:deleteothers', $context)) {
            $cancreatevia = true;
        }
    } else {
        $cancreatevia = has_edition_capability($via->id, $context);
    }

    if ($cancreatevia) {
        if (($via->usersynchronization + 300) < time() && $via->enroltype == 0 &&
            $via->activitytype == 1 && time() > ($via->datebegin + $via->duration * 60)) {
            // Check to sync users to the activity : permanent and not ended activities are already checked in the task.
            via_synch_participants(null, $via->id);

            $via->usersynchronization = time();
            $updated = $DB->update_record('via', $via);

        }
    } else {
        if (!($userassociated = $DB->get_record('via_participants', array('activityid' => $via->id, 'userid' => $USER->id )))) {
            // User is not associated... we look to sync him.
            if ($via->activitytype == 1 && time() > ($via->datebegin + $via->duration * 60)) {
                via_synch_participants($USER->id, $via->id);
            }
        }
    }
    if (has_capability('mod/via:view', $context) && (is_mobile_phone() == false || $via->isnewvia == 1)
        && $via->activitytype != 3 && $via->activitytype != 4 && ($via->recordingmode != 0 || $via->activityversion == 1)
        && $via->viaactivityid <> null) {
        if ($via->recordingmode == 1 || (isset($pbsync) && $cancreatevia)) { // Si on est en mode unifié
            $via->playbacksync = 0;
        }
        via_sync_activity_playbacks($via);
    }

    // Initialize $PAGE.
    $PAGE->set_url('/mod/via/view.php', array('id' => $cm->id));
    $PAGE->requires->jquery();
    $PAGE->requires->js('/mod/via/javascript/viabutton.js');
    $PAGE->requires->js('/mod/via/javascript/resource.js');

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
        // Participant is modifying his confirmation status.
        if (!empty($frm->confirm)) {
            via_set_participant_confirmationstatus($cm->instance, 2);
        } else if (!empty($frm->notconfirm)) {
            via_set_participant_confirmationstatus($cm->instance, 3);
        } else if (!empty($frm->modify)) {
            via_set_participant_confirmationstatus($cm->instance, 1);
        }
    }
}

// Print the page header.
echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($via->name), 2, 'main');

echo $OUTPUT->box_start('generalbox intro');

// If activity is Deleted in Via but not in Moodle.
if ($deleted) {
    echo '<p>'.get_string('activity_deleted', 'via').'</p>';
    if (!isset($viaassign)) {
        if (has_capability('mod/via:manage', $context)) {
             echo '<div class="singlebutton via"><form method="post" action="'.
        $CFG->wwwroot.'/course/mod.php?sesskey='.sesskey().'&delete='.$cm->id.'"><div><input
            type="submit" value="'.get_string("delete_activity", "via").'" /></div></form></div>';
        }
    } else {

        echo '<div class="singlebutton via"><form method="post" action="'.
        $CFG->wwwroot.'/mod/viaassign/view.php?action=confirm_delete_via&id='.$cm->id.'&sesskey='.sesskey().'&viaid='.$via->id.'&userid='.$USER->id.'"><div><input
            type="submit" value="'.get_string("delete_activity", "via").'" /></div></form></div>';
    }
    echo $OUTPUT->box_end();
} else {
    $table = "<table>";

    // Desc.
    echo format_module_intro('via', $via, $cm->id);

    if ($via->activitytype != 2 && !($via->activitytype == 4 && $via->duration == 0)) {
        // Start date.
        $table .= '<tr>';
        $table .= "<td><b>".get_string("startdate", "via").":</b></td>";
        if ($via->activitytype == 1||$via->activitytype == 4) {
            $table .= "<td style='padding-left:5px;'>".userdate($via->datebegin)."</td>";
        } else {
            $table .= "<td style='padding-left:5px;'>".get_string("unplanned_text", "via")."</td>";
        }
        $table .= '</tr>';
        // Duration.
        $table .= '<tr>';
        $table .= "<td><b>".get_string("duration", "via").":</b></td>";
        $table .= "<td style='padding-left:5px;'>".$via->duration."</td>";
        $table .= '</tr>';

    }


    if ($cancreatevia) {
        if ($via->presence != 0 && $via->activitytype != 2) {
            // Presence.
            $table .= '<tr>';
            $table .= "<td><b>".get_string("presence", "via").":</b></td>";
            $table .= "<td style='padding-left:5px;'>".$via->presence."</td>";
            $table .= '</tr>';
        }

        // Qualité Multimédia.
        if ($via->activitytype != 4 && !$ishtml5) {
            $table .= '<tr>';
            $table .= "<td><b>".get_string("multimediaquality", "via").":</b></td>";
            $qualityoption = $DB->get_record('via_params', array('param_type' => 'multimediaprofil', 'value' => $via->profilid));
            if ($qualityoption) {
                $table .= "<td style='padding-left:5px;'>".via_get_profilname($qualityoption->param_name)."</td>";
            }
            $table .= '</tr>';
        }
    }

    // Recordingmode.
    if (!$ishtml5) {
        $table .= '<tr>';
        $table .= "<td><b>".get_string("recordingmode", "via").":</b></td>";
        switch($via->recordingmode) {
            case 0 : $table .= "<td style='padding-left:5px;'>".get_string('notactivated', 'via')."</td>";
                break;
            case 1 : $table .= "<td style='padding-left:5px;'>".get_string('unified', 'via')."</td>";
                break;
            case 2 : $table .= "<td style='padding-left:5px;'>".get_string('multiple', 'via')."</td>";
                break;
        }
        $table .= '</tr>';
    }
    $table .= '</table> <br />';

    echo $table; // Print activity info.

    echo '<div style="width:100%">';

    if ($cancreatevia || !$ishtml5) {
        echo '<b>'. get_string('preparation', 'via') . ':</b>';
    }

    if (has_capability('mod/via:view', $context) && !$ishtml5) {
        if (is_mobile_phone() == false) {
            // This is only displayed if the user is NOT on a mobile.
            echo '<a class="viabtnlink" style="margin-right:10%;" target="configvia" href="' .
            $CFG->wwwroot .'/mod/via/view.assistant.php?redirect=7"
            onclick="this.target=\'configvia\';
            return openpopup(null, {url:\'/mod/via/view.assistant.php?redirect=7\',
            name:\'configvia\', options:\'menubar=0,location=0,scrollbars,resizable,width=750,height=700\'});">
            <i class="fa fa-cog via"></i>' .
            get_string("configassist", "via").'</a>';
        }

        if (get_config('via', 'via_technicalassist_url') == null) {
            $assistant = '<a class="viabtnlink" style="padding-right:10%;" target="configvia" href="'.
            $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=6"
            onclick="this.target=\'configvia\';
            return openpopup(null, {url:\'/mod/via/view.assistant.php?redirect=6\',
            name:\'configvia\', options:\'menubar=0,location=0,scrollbars,resizable,width=750,height=700\'});">';
        } else {
            $assistant = '<a class="viabtnlink" style="padding-right:10%;" target="configvia" href="' .
            get_config('via', 'via_technicalassist_url'). '"
            onclick="this.target=\'configvia\';
            return openpopup(null, {url:\'' . get_config('via', 'via_technicalassist_url').'\',
            name:\'configvia\', options:\'menubar=0,location=0,scrollbars,resizable,width=750,height=700\'});">';
        }

        echo $assistant .'<i class="fa fa-question-circle via"></i>'. get_string("technicalassist", "via").'</a>';
    }

    if ($cancreatevia) {
        $class = $ishtml5 ? "viabtnlink html5" : "viabtnlink";
        echo "<a class='".$class."' href='send_invite.php?".$viaurlparam."=".$via->id."'><i class='fa fa-envelope via'></i>".
        get_string("sendinvitation", "via")."</a>";
    }
    echo '</div><br />';
    if ($cancreatevia) {
        if (isset($viaid)) {
            echo "<a class='viabtnlink' href='view.php?viaid=".$viaid."&pbsync=1'><i class='fa fa-link via'></i>".
            get_string("playbackSynchronize", "via")."</a><br /><br />";
        } else {
            echo "<a class='viabtnlink' href='view.php?id=".$id."&pbsync=1'><i class='fa fa-link via'></i>".
            get_string("playbackSynchronize", "via")."</a><br /><br />";
        }
    }

    $table = new html_table();
    $table->align = array('center', 'center');
    $table->attributes['class'] = 'via generaltable';
    $table->id = 'via_activity';
    $table->width = "90%";
    $table->data = array();

    // Buttons so that students may confirm their precence.
    $host = $DB->get_record('via_participants', array('activityid' => $via->id, 'participanttype' => '2'));
    // Only the host doesn't have to confirm.
    if ($host && $host->userid != $USER->id && $via->needconfirmation && get_config('via', 'via_participantmustconfirm') && !$ishtml5) {
        // If participant must confirm attendance.
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
                <form name='confirmation' action='?".$viaurlparam."=".$viaurlparamvalue."' method='POST'>
                    <input type='submit' value='".get_string("attending", "via")."' id='confirm' name='confirm'>
                    <input type='submit' value=\"".get_string("notattending", "via")."\" id='notconfirm' name='notconfirm'>
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
                <form name='confirmation' action='?".$viaurlparam."=".$viaurlparamvalue."' method='POST'>
                <input type='submit' value='".get_string("edit")."' name='modify'>
                </form>";
            }
            $table->data[] = new html_table_row(array($cell));
        }
    }

    // Get the type of access user can view.
    $access = via_access_activity($via, $cm->id);
    $viewinfo = true;

    if (has_capability('mod/via:view', $context)) {
        $cell = new html_table_cell();
        $cell->colspan = 2;
        $cell->style = 'text-align:center';
            $cell->text = '<p style="margin-bottom:0px;"><span style="vertical-align:top;" class="title">'.
            get_string('accessactivity', 'via')
            . " </span><span class='viatext' style=\"width: 330px;display: inline-block;text-align: left;word-wrap:break-word;\">";

        if ($via->activitytype == 4) {
            $cell->text = '<span style="color:red;">';
            $cell->text .= get_string('desactivatedMessage', 'via');
            $cell->text .= "</span>";
            $table->data[] = new html_table_row(array($cell));
        } else {
            switch($access) {
                case 1:
                    // Activity is started, user can access it.
                    if ($via->recordingmode != 0 && !$ishtml5) {// && !$viaid) {
                        $cell->text .= get_string('recordwarning', 'via');
                        $cell->text .= '<br /><input type="checkbox" id="checkbox" />
                        <label for="checkbox" style="margin-right: 10px;">'.get_string('recordaccept', 'via').'</label>'.
                        '<span id="error" class="error hide"><br/>'.get_string('mustaccept', 'via').'</span>';
                        $cell->text .= via_add_button(true, true, $viaurlparamvalue, null, null, $viaurlparam);
                        // Pas de bouton désactivé pour viaassign.
                        $cell->text .= via_add_button(true, false);
                    } else {
                        $cell->text .= via_add_button(false, false, $viaurlparamvalue, null, null, $viaurlparam, $ishtml5);
                    }
                    $cell->text .= "</span></p>";
                    $table->data[] = new html_table_row(array($cell));
                    break;
                case 2:
                    // Activity isn't started yet, but animators and hosts can access it to do some preparation.
                    $cell->text .= get_string("notstarted", "via").'</span></p><br/>';
                    if ($via->recordingmode != 0 && !$ishtml5) {// && !$viaid) {
                        $cell->text .= get_string('recordwarning', 'via') .'</span></p>';
                        $cell->text .= '<br /><input type="checkbox" id="checkbox" />'.get_string('recordaccept', 'via').'
                        <span id="error" class="error hide"><br />'.get_string('mustaccept', 'via').'</span><br />';
                        $cell->text .= via_add_button(true, true, $viaurlparamvalue, true, true, $viaurlparam);
                        $cell->text .= via_add_button(true, false, null, true);
                    } else {
                        $cell->text .= via_add_button(false, false, $viaurlparamvalue, true, true, $viaurlparam, $ishtml5);
                    }
                    $cell->text .= "</span></p>";
                    $table->data[] = new html_table_row(array($cell));
                    break;
                case 3:
                    // For participants : activity isn't started yet.
                    $cell->text .= get_string("notstarted", "via").'</span></p>';
                    $table->data[] = new html_table_row(array($cell));
                    break;
                case 5:
                    // Activity is done.
                    $cell->text .= get_string("activitydone", "via").'</span></p>';
                    $table->data[] = new html_table_row(array($cell));
                    break;
                case 6:
                    // Participant can't access activity, he is not enroled in it.
                    $cell->text .= get_string("notenrolled", "via").'</span></p>';
                    $table->data[] = new html_table_row(array($cell));
                    $viewinfo = false;
                    break;
                case 7;
                    // Admin user which is not enrolled and activity is done.
                    $cell->text .= get_string("activitydone", "via");
                    $cell->text .= '</span></p><br/>';
                    $table->data[] = new html_table_row(array($cell));
                    break;
                case 8;
                    // Activity not yet planned!
                    $cell->text .= get_string('unplanned_text', 'via') . '</span></p>';
                    $table->data[] = new html_table_row(array($cell));
                    break;
                case 9;
                    // Admin user which is not enrolled but can access the activity anyways.
                    if ($via->recordingmode != 0 && !$ishtml5) {
                        $cell->text .= get_string("adminnotrenrolled", "via").'<br />';
                        $cell->text .= get_string('recordwarning', 'via');
                        $cell->text .= '<br /><input type="checkbox" id="checkbox" />
                                <label for="checkbox" style="margin-right: 10px;">'.get_string('recordaccept', 'via').'</label>'.
                                '<span id="error" class="error hide"><br/>'.get_string('mustaccept', 'via').'</span>';
                        $cell->text .= via_add_button(true, true, $viaurlparamvalue, null, true, $viaurlparam);
                        // Pas de bouton désactivé pour viaassign.
                        $cell->text .= via_add_button(true, false);
                    } else {
                        $cell->text .= get_string("adminnotrenrolled", "via");
                        $cell->text .= '<br />' . via_add_button(false, true, $viaurlparamvalue, false, true, $viaurlparam, $ishtml5);
                        $cell->text .= '</span></p><br/>';
                    }
                    $table->data[] = new html_table_row(array($cell));
                    break;
                default :
                        break;
            }
        }

        echo html_writer::table($table); // Print activity info.

        echo $OUTPUT->box_end();

        // Print downloadable files list.
        if ( $via->activitytype != 4 && $viewinfo && $via->activitytype != 3  && has_capability('mod/via:view', $context)) {
            if (isset($error)) {
                echo  'this title aready exists';
            }

            $api = new mod_via_api();
            if (!$ishtml5) {
                $dlfiles = $api->list_downloadablefiles($via);

                echo via_get_downlodablefiles_table($dlfiles, $via, $context, $cancreatevia, $viaurlparam);
            } else {
                // We get all subrooms of activity.
                $subrooms = $api->viahtml_getsubroomlist($via->viaactivityid);

                if (!isset($subroomid) || $subroomid == "" || !$cancreatevia) {
                    // If no subroom is specified (or user has no rights), we use the main room (the first one).
                    $subroomid = $subrooms[0]["subRoomId"];
                }

                $dlfiles = $api->viahtml_getsubroomresourcelist($via->viaactivityid, $subroomid);
                echo via_get_downlodablefiles_table_viahtml($dlfiles, $subrooms, $via, $cancreatevia, $id, $subroomid);
            }
        }

        // No point validating everthing, the activity is not yet planned, there are no playbacks and no users!
        if ($via->activitytype != 3 && $via->activitytype != 4) {
            // Print recordings list.
            if ($viewinfo && has_capability('mod/via:view', $context) && (is_mobile_phone() == false || $via->isnewvia == 1)) {
                if (isset($error)) {
                    echo  'this title aready exists';
                }

                echo via_get_playbacks_table($via, $context, $cancreatevia, $viaurlparam);
            }

            // If activity is finished and the user has the right to see reports, we display the report.

            echo $OUTPUT->box_start('via generaltable');

            if (get_config('via', 'via_presencestatus') && $via->presence != 0 && $via->activitytype == 1 &&
                ($via->datebegin + ($via->duration * 60)) < time()) {
                if ($connectedusers == 0 && (has_capability('mod/via:viewpresence', $context) || $cancreatevia)) {
                    echo via_report_btn($via->id, $viaid);

                    if ($viaid) {
                        echo via_get_participants_table($via, $context, true, $viaid);
                    } else {
                        echo via_get_participants_table($via, $context, true);
                    }
                    echo via_report_btn($via->id, $viaid);

                    echo "<p style='margin: auto; width: 90%;'>".get_string("presencewarning", "via")."</p>";
                }
            } else {
                // If the activity has not yet started we print the user list for everyone to see!
                if ($cancreatevia || get_config('via', 'via_displayuserlist')) {
                    if ($synch == 1) {
                        echo '<p class="notifysuccess">'.get_string('notifysuccess_synch1', 'via').'</p>';
                    } else if ($synch == 2) {
                        echo '<p class="notifysuccess">'.get_string('notifysuccess_synch2', 'via').'</p>';
                    }
                    if ($viaid) {
                        echo via_get_participants_table($via, $context, false, $viaid);
                    } else {
                        echo via_get_participants_table($via, $context);
                    }
                }
            }
             echo $OUTPUT->box_end();
        }
        echo '<hr>';

        echo '<div class="vialogo" ><img src = "' . $CFG->wwwroot . '/mod/via/pix/logo_via.png" width="60"
        height="33" alt="VIA" /> '.get_string('by', 'via').'&nbsp;&nbsp;<img src = "' .
            $CFG->wwwroot . '/mod/via/pix/logo_svi.png" width="52" height="33" alt="VIA" /></div>';
    }


}

echo $OUTPUT->footer();