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
 * Send html invitation with link to the activity.
 * 
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

require_once("../../config.php");
require_once("lib.php");
global $DB;

$id    = required_param('id', PARAM_INT);// VIA!

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
    print_error('You do not have the permission to send invites');
}

// Initialize $PAGE!
$PAGE->set_url('/mod/via/send_invite.php', array('id' => $cm->id));
$PAGE->set_title($course->shortname . ': ' . format_string($via->name));
$PAGE->set_heading($course->fullname);
$button = $OUTPUT->update_module_button($cm->id, 'via');
$PAGE->set_button($button);

if ($frm = data_submitted()) {

    // A form was submitted so process the input.
    if (!empty($frm->msg)) {
        $via->invitemsg = $frm->msg['text'];
    }
    $via->sendinvite = 1;
    $DB->update_record("via", $via);
    redirect($CFG->wwwroot."/mod/via/view.php?id=".$cm->id, get_string("invitessend", "via"), 0);
}

if (isset($via->invitemsg)) {
    $msg = $via->invitemsg;
} else {
    $msg = "";
}

// Print the page header.
echo $OUTPUT->header();

echo $OUTPUT->box_start('center');

$mform = new via_send_invite_form('', array('message' => $msg, 'id' => $id));

$mform->display();

echo $OUTPUT->box_end();

echo $OUTPUT->footer($course);
