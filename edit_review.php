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
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

global $DB;
require_once("../../config.php");
require_once("lib.php");

$id    = required_param('id', PARAM_INT);// Via!
$edit  = optional_param('edit', false, PARAM_INT);// Edit via recording.

$error = '';
$title = false;

if (isset($_REQUEST['playbackid'])) {
    $playbackid = $_REQUEST['playbackid'];
}

if (isset($_REQUEST['delete'])) {
    $delete = $_REQUEST['delete'];
} else {
    $delete = false;
}

if (!$via = $DB->get_record('via', array('id' => $id))) {
    print_error("Via ID is incorrect");
}

if (!$course = $DB->get_record('course', array('id' => $via->course))) {
    print_error("Could not find this course!");
}

if (! $cm = get_coursemodule_from_instance("via", $via->id, $course->id)) {
    $cm->id = 0;
}

require_login($course->id, false, $cm);
$context = context_module::instance($cm->id);

if (!has_capability('mod/via:manage', $context)) {
    print_error('You do not have the permission to edit via playbacks');
}

if ($delete) {
    try {

        $api = new mod_via_api();
        $result = $api->delete_playback($via->viaactivityid, $playbackid);
        if ($result) {
            redirect("view.php?id=$cm->id");
        }

    } catch (Exception $e) {
        $result = false;
        echo $OUTPUT->notification("error:".$e->getMessage());
    }

} else {

    if (isset($_REQUEST['ispublic'])) {
        if ($_REQUEST['ispublic'] == get_string('show', 'via')) {
            $ispublic = 1;
        } else {
            $ispublic = 0;
        }
    }

    $playbacks = via_get_all_playbacks($via);

    foreach ($playbacks as $key => $playbacksearch) {
        if (strtoupper($key) == strtoupper($playbackid)) {
            $playback = $playbacksearch;
            break;
        }
    }


    if ($frm = data_submitted()) {
        // Editing playback!

        if (isset($frm->cancel)) {
            redirect("view.php?id=$cm->id");
        }

        if (isset($frm->edit)) {
            if (isset($frm->title)) {
                foreach ($playbacks as $pb) {
                    if ($pb->title == $frm->title) {
                        $title = true;
                        continue;
                    }
                }
                if (!$title) {
                    $playback->title = $frm->title;
                }
            }


            try {
                if (!$title) {
                    if (isset($ispublic) ) {
                        if ($ispublic == 1) {
                            $playback->ispublic = 1;
                        } else {
                            $playback->ispublic = 0;
                        }
                    }

                    $api = new mod_via_api();
                    $result = $api->edit_playback($via, $playbackid, $playback);
                    if ($result) {
                        redirect("view.php?id=$cm->id");
                    }

                } else {
                    $error = '<p class="error">'.get_string('title_exists', 'via').'</p>';
                    $result = false;
                }

            } catch (Exception $e) {
                $result = false;
                echo $OUTPUT->notification("error:".$e->getMessage());
            }
        }
    }

    $PAGE->set_url('/mod/via/edit_review.php', array('id' => $cm->id));
    $PAGE->set_title($course->shortname.': '.$via->name);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    echo $OUTPUT->box_start('center');

    echo "<h2>".get_string("editrecord", "via")."</h2>";

    echo $error;

    include('edit_review.form.php');

    echo $OUTPUT->box_end();

    echo $OUTPUT->footer($course);

}
