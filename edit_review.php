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
 * Edit playback name
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

global $DB;
require_once("../../config.php");
require_once("lib.php");
require_once(get_vialib());

$id = optional_param('id', null, PARAM_INT);
$viaid = optional_param('viaid', null, PARAM_INT);
$edit  = optional_param('edit', false, PARAM_TEXT);// Edit via recording.
$playbackid = optional_param('playbackid', null, PARAM_TEXT);

if ($id) {
    if (!$via = $DB->get_record('via', array('id' => $id))) {
        print_error("Via ID is incorrect");
    }
    if (! $cm = get_coursemodule_from_instance("via", $via->id, null)) {
        $cm->id = 0;
    }

    $viaurlparam = 'id';
    $viaurlparamvalue = $cm->id;
} else if ($viaid) {
    $viaassign = $DB->get_record('viaassign_submission', array('viaid' => $viaid));
    if (!($cm = get_coursemodule_from_instance('viaassign', $viaassign->viaassignid, null, false, MUST_EXIST))) {
        error("Course module ID is incorrect");
    }
    if (!($via = $DB->get_record('via', array('id' => $viaid)))) {
        error("Via ID is incorrect");
    }

    $viaurlparam = 'viaid';
    $viaurlparamvalue = $viaid;

}

if (!$course = $DB->get_record('course', array('id' => $via->course))) {
    print_error("Could not find this course!");
}

require_login($course->id, false, $cm);
$context = via_get_module_instance($cm->id);

$cancreatevia = has_capability('mod/via:manage', $context);

if ($viaid) {
    require_once($CFG->dirroot.'/mod/viaassign/locallib.php');
    $viaassign = new viaassign($context,  $cm, $course);
    if ($viaassign->can_create_via($USER->id, $viaassign->get_instance()->userrole)) {
        $cancreatevia = true;
    }
}

if (!$cancreatevia) {
    print_error('You do not have the permission to edit via playbacks');
}

if ($edit == get_string('cancel', 'via')) {
    // If cancelled was clicked on the modification page, we simply redirect to the details page.
    redirect("view.php?".$viaurlparam."=".$viaurlparamvalue);
}

if ($edit != 'del' && $edit != get_string('delete', 'via')) {
    // For all pages except delete we need to load the playback's info.
    $playbacks = $DB->get_records_sql('select * from {via_playbacks} WHERE playbackid = \''. $playbackid . '\'');

    foreach ($playbacks as $playbacksearch) {
        if (strtoupper($playbacksearch->playbackid) == strtoupper($playbackid)) {
            $playback = $playbacksearch;
            break;
        }
    }
}

if ($edit == get_string('delete', 'via')) {
    try {
        $api = new mod_via_api();
        $result = $api->delete_playback($via->viaactivityid, $playbackid);
        if ($result) {
            $DB->delete_records('via_playbacks', array('playbackid' => $playbackid));
            redirect("view.php?".$viaurlparam."=".$viaurlparamvalue);
        }

    } catch (Exception $e) {
        $error = $OUTPUT->notification("error:".$e->getMessage());
    }

} else if ($edit == get_string('save', 'via')) {
    if ($frm = data_submitted()) {
        // Coming from form page with new values.
        $playback->title = $frm->title;

        if (isset($frm->isdownloadable)) {
            $isdownloadable = $frm->isdownloadable;
        } else {
            $isdownloadable = 0;
        }

        if (isset($frm->accesstype)) {
            $accesstype = $frm->accesstype;
        } else {
            $accesstype = 0;
        }

        $playback->isdownloadable = $isdownloadable;

        $playback->accesstype = $accesstype;
    }

    try {
        $ispublic = $playback->accesstype == 2;

        $api = new mod_via_api();
        if ($ispublic) {
            $playback->accesstype = 1;
        }
        $result = $api->edit_playback($via, $playbackid, $playback);
        if ($result) {
            if ($ispublic) {
                $playback->accesstype = 2;
            }

            $sql = 'UPDATE {via_playbacks} SET title = \''.$playback->title.'\',
                    isdownloadable = '. $playback->isdownloadable.',
                    accesstype = '.$playback->accesstype;
            $sql .= ' WHERE playbackid = \''.$playback->playbackid.'\'';

            $DB->execute($sql);
            redirect("view.php?".$viaurlparam."=".$viaurlparamvalue);
        }

    } catch (Exception $e) {
        $error = $OUTPUT->notification("error:".$e->getMessage());
    }

}

$PAGE->set_url('/mod/via/edit_review.php', array('id' => $cm->id));
$PAGE->set_title($course->shortname.': '.$via->name);
$PAGE->set_heading($course->fullname);

if ($viaid) {
    $PAGE->navbar->add(format_string($via->name), '/mod/via/view.php?viaid='.$viaid);
}

echo $OUTPUT->header();

echo $OUTPUT->box_start('center');

echo "<h2>".get_string("editrecord", "via")."</h2>";

if (isset($error)) {
    echo $error;
}

if ($edit == 'del') {
    // Ask if the user really wants to delete!
    $form = '<form method="post" action="edit_review.php?'.$viaurlparam.'='.$via->id.'" class="mform">';
    $form .= get_string('confirmdelete', 'via'). '<br/><br/>';
    $form .= '<input type="hidden" name="playbackid" value="'.$playbackid.'" />';
    $form .= '<input type="submit" name="edit" id="edit" value="'.get_string('delete', 'via').'" />';
    $form .= '<input type="submit" name="edit" id="edit" value="'.get_string('cancel', 'via').'"
              onclick="skipClientValidation = true; return true;"/>';
    $form .= '</form>';

} else {
    // The user can change the title and make the recordings downloadable.
    $form = '<form id="editreviewform" method="post" action="edit_review.php?'.
             $viaurlparam.'='.$viaurlparamvalue.'" class="mform">';
    if ($id) {
        $form .= '<input type="hidden" name="id" value="'.$id.'" />';
    } else if ($viaid) {
        $form .= '<input type="hidden" name="viaid" value="'.$viaid.'" />';
    }
    $form .= '<input type="hidden" name="playbackid" value="'.$playbackid.'" />';
    $form .= '<div align="center"><table cellpadding="3">';
    $form .= '<tr><td align="right"><label for="title">'. get_string("recordingtitle", "via").'</label>';
    $form .= '</td>';
    $form .= '<td align="left"><input type="text" name="title" maxlength="100" value="' . $playback->title .'" id="title" />';
    $form .= '</td></tr>';

    // If the option is activated in the settings!
    if (get_config('via', 'via_downloadplaybacks')) {
        $form .= '<tr><td align="right"><label for="isdownloadable">'.get_string("recordingisdownloadable", "via").'</label>';
        $form .= '</td>';
        if ($playback->isdownloadable == 1) {
            $form .= '<td align="left"><input type="checkbox" name="isdownloadable" id="isdownloadable" value="0" checked >';
        } else {
            $form .= '<td align="left"><input type="checkbox" name="isdownloadable" id="isdownloadable" value="1"  >';
        }
        $form .= get_string("recordingisdownloadableinfo", "via");
        $form .= '</td></tr>';
    }

    $inputstart = '<input type="radio" id="accesstype" for="accesstype" name="accesstype" ';

    $form .= '<tr><td align="right" style="vertical-align:top;"><label>'.get_string("playbackaccesstypelbl", "via").'</label><td>';
    $form .= $inputstart. ' value="0"'.($playback->accesstype == 0 ? 'checked' : '').'/>'.get_string("playbackaccesstype0", "via");
    $form .= '<br/>'.$inputstart.' value="1"'.($playback->accesstype == 1 ? 'checked' : '').'/>'.
             get_string("playbackaccesstype1", "via");
    if ($viaid) {
        $form .= '<br/>'.$inputstart.' value="2"'.($playback->accesstype == 2 ? 'checked' : '').'/>'.
             get_string("playbackaccesstype2", "via");
    }
    $form .= '</select></td></tr>';

    $form .= '<tr><td></td><td><p>';
    $form .= '<input name="edit" value="'.get_string('save', 'via').'" type="submit" id="edit" />';
    $form .= '<input name="edit" value="'.get_string('cancel', 'via').'" type="submit" id="cancel"
             onclick="skipClientValidation = true; return true;"/>';
    $form .= '</p></td></tr></table>';
    $form .= '</div></form>';

}

echo $form;

echo $OUTPUT->box_end();

echo $OUTPUT->footer($course);
