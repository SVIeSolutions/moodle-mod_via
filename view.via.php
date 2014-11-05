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

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/via/lib.php');

global $DB, $USER;

$id = required_param('id', PARAM_INT);
$review = optional_param('review', null, PARAM_INT);

$connexion = false;
$response = false;
$forcedaccess = 0;
$forcededit = 0;
$playbackid = null;

if (isset($_REQUEST['playbackid'])) {
    $playbackid = $_REQUEST['playbackid'];
}
if (isset($_REQUEST['fa'])) {
    $forcedaccess = 1;
    $connexion = true;
}
if (isset($_REQUEST['p'])) {
    $forcedaccess = 1;
    $forcededit = 1;
    $connexion = true;
}

if (! $cm = get_coursemodule_from_id('via', $id)) {
    error('Course Module ID was incorrect');
}

if (! $via = $DB->get_record('via', array('id' => $cm->instance))) {
    error('Activity ID was incorrect');
}

if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
    error('Incorrect course id');
}

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
$PAGE->set_url('/mod/via/view.php', array('id' => $id));
$PAGE->set_context($context);

// Show some info for guests.
if (isguestuser()) {
    $PAGE->set_title(format_string($chat->name));
    echo $OUTPUT->header();
    echo $OUTPUT->confirm('<p>'.get_string('noguests', 'chat').'</p>'.get_string('liketologin'),
        get_login_url(), $CFG->wwwroot.'/course/view.via.php?id='.$course->id);

    echo $OUTPUT->footer();
    exit;
}

$api = new mod_via_api();

// In case the user still has not been added.
// If user_participants field timesynched is not null we add him.
// Otherwise we check if he is in the activity if he isn't we add him.

$viaparticipant = $DB->get_record('via_participants', array('userid' => $USER->id, 'activityid' => $via->id));
if (!has_capability('moodle/site:approvecourse', context_system::instance())) {
    // Only users with a lower role are added.
    if (isset($viaparticipant->timesynched)) {
        $connexion = true;
    } else {
        $viauser = $DB->get_record('via_users', array('userid' => $USER->id));
        $participantslist = get_via_participants_list($via);
        $participants = array();
        if ($participantslist) {
            foreach ($participantslist as $participant) {
                if ($participant != 0) {
                    $participants[] = $participant['UserID'];
                }
            }
        }
        if (in_array($viauser->viauserid, $participants)) {
            $synched = $DB->set_field("via_participants", "timesynched", time(), array("id" => $viauser->userid));
            $connexion = true;
        } else if ($via->enroltype == 0) {
            $type = get_user_type($viauser->userid, $course->id);
            // We only add participants automatically!
            if ($type == 1) {
                try {
                    // Via_add_participant($userid, $viaid, $type, $confirmationstatus, $checkuserstatus, $adddeleteduser).
                    $added = via_add_participant($viauser->userid, $via->id, $type, null, 1);
                    if ($added === 'presenter') {
                        echo "<div style='text-align:center; margin-top:0;' class='error'><h3>".
                        get_string('userispresentor', 'via') ."</h3></div>";
                    }
                } catch (Exception $e) {
                    echo '<div class="alert alert-block alert-info">'.
                    get_string('error_user', 'via', $muser->firstname.' '.$muser->lastname).'</div>';
                }
            }
        }
    }
} else {
    if ($viaparticipant) {
        $connexion = true;
    }
}

try {
    if ($connexion == 'true') {
        if (!$review) {
            $response = $api->userget_ssotoken($via, 3, null, $forcedaccess);
        } else {
            $response = $api->userget_ssotoken($via, 3, $playbackid, $forcedaccess, $forcededit);
        }
    }

    if ($response) {

        $eventdata = array(
        'objectid' => $via->id,
        'context' => $context
        );

        $event = \mod_via\event\course_module_viewed::create($eventdata);

        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->trigger();

        redirect($response);

    } else {
        // User is not enrolled and is not allowed to access the recordings; example an admin user.
        $PAGE->set_title($course->shortname . ': ' . format_string($via->name));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->box_start();
        $msg = get_string('notenrolled', 'via');
        notify($msg, $style = 'recordwarning', $return = false);
        echo $OUTPUT->box_end();
        echo $OUTPUT->footer($course);
    }

} catch (Exception $e) {

    if ($e->getMessage() == 'STATUS_INVALID') {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string($e->getMessage(), 'via'));
        echo $OUTPUT->box_start('notice');
        echo get_string('error:'.$e->getMessage(), 'via');
        echo $OUTPUT->footer($course);
    } else {
        print_error('error:'.$e->getMessage(), 'via');
        $result = false;
    }
}
