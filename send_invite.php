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
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once("../../config.php");
require_once("lib.php");
require_once(get_vialib());

global $DB;

$id = optional_param('id', null, PARAM_INT);
$viaid = optional_param('viaid', null, PARAM_INT);

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
    print_error('You do not have the permission to send invites');
}

// Initialize $PAGE!
$PAGE->set_url('/mod/via/send_invite.php', array('id' => $cm->id));
$PAGE->set_title($course->shortname . ': ' . format_string($via->name));
$PAGE->set_heading($course->fullname);
if ($viaid) {
    $PAGE->navbar->add(format_string($via->name), '/mod/viaassign/view.php?viaid='.$viaid);

}

if ($frm = data_submitted()) {
    if (!empty($frm->cancel)) {
        redirect($CFG->wwwroot."/mod/via/view.php?".$viaurlparam."=".$viaurlparamvalue, '', 0);
    }

    // A form was submitted so process the input.
    if (!empty($frm->msg)) {
        $via->invitemsg = $frm->msg['text'];
    }
    $via->sendinvite = 1;
    $DB->update_record("via", $via);

    $sql = "SELECT count(p.id) cnt
        FROM {via_participants} p";
    $sql .= " WHERE p.activityid = " . $via->id;

    $count = $DB->get_record_sql($sql);

    if ($count->cnt <= 50) {
        // Send invites now.
        via_send_invitations($via->id);
        if ($viaid) {
            redirect($CFG->wwwroot."/mod/via/view.php?".$viaurlparam."=".$viaurlparamvalue, get_string("invitessent", "via"), 5);
        } else {
            redirect($CFG->wwwroot."/mod/via/view.php?".$viaurlparam."=".$viaurlparamvalue, get_string("invitessent", "via"), 5);
        }
    } else {
        // Send invites later by task.
        if ($viaid) {
            redirect($CFG->wwwroot."/mod/viaassign/view.php?".$viaurlparam."=".$viaurlparamvalue,
            get_string("invitessent", "via"), 5);
        } else {
            redirect($CFG->wwwroot."/mod/via/view.php?".$viaurlparam."=".$viaurlparamvalue, get_string("invitessent", "via"), 5);
        }
    }
}

if (isset($via->invitemsg)) {
    $msg = $via->invitemsg;
} else {
    $msg = "";
}

// Print the page header.
echo $OUTPUT->header();

echo $OUTPUT->box_start();

$mform = new via_send_invite_form('', array('message' => $msg, $viaurlparam => $via->id));

$mform->display();

echo $OUTPUT->box_end();

echo $OUTPUT->footer($course);
