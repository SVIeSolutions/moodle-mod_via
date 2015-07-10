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
 * Redirects user with token to the via activity or playback after validating him/her.
 * 
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/via/lib.php');
require_once(get_vialib());

global $DB, $USER;

$id = required_param('id', PARAM_INT);
$review = optional_param('review', null, PARAM_INT);
$fa = optional_param('fa', null, PARAM_INT);
$playbackid = optional_param('playbackid', null, PARAM_PATH);
// We only reload once, if the user was not fixed on the first reload we display the error.
$reload = optional_param('r', null, PARAM_PATH);

$connexion = false;
$response = false;
$forcedaccess = 0;
$forcededit = 0;
$msg = null;

if ($fa) {
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

$context = via_get_module_instance($cm->id);
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
if (!has_capability('moodle/site:approvecourse', via_get_system_instance())) {
    // Only users with a lower role are added.
    if (isset($viaparticipant->synchvia) && $viaparticipant->synchvia == 1) {
        $connexion = true;
    } else {
        $viauser = $DB->get_record('via_users', array('userid' => $USER->id));
        // The user doesn't exists yet. We need to create it.
        if (!$viauser) {

            try {
                $uservalidated = $api->validate_via_user($USER);
                $viauser = $DB->get_record('via_users', array('viauserid' => $uservalidated));

            } catch (Exception $e) {
                print_error('error:'.$e->getMessage(), 'via'). $muser->firstname.' '.$muser->lastname;
            }
        }
        try {
            $type = via_user_type($viauser->userid, $course->id, $via->noparticipants);

            $added = via_add_participant($viauser->userid, $via->id, $type, true);
            $connexion = true;

        } catch (Exception $e) {
            print_error('error:'.$e->getMessage(), 'via'). $muser->firstname.' '.$muser->lastname;
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
        if (!$review) {
            via_accessed_log($via, $context);
        } else {
            via_playback_viewed_log($via, $context, $course, $playbackid);
        }

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
    if ($e->getMessage() == 'INVALID_USER_ID' && !isset($reload)) {
        // Changes were made very recently in Via and the userinformation in Moodle has not yet been updated.
        $uservalidated = $api->get_user_via_id($USER->id, true, true);
        if ($uservalidated) {
            $reload = $CFG->wwwroot.'/mod/via/view.via.php?id='.$id.'&review='.$review.'&fa='.$fa.'&playbackid='.$playbackid.'&r=1';
            redirect($reload);
        } else {
            $error = get_string('error:'.$e->getMessage(), 'via');
        }

    } else {
        $error = get_string('error:'.$e->getMessage(), 'via');
    }

    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->box_start('notice');
    echo $error;
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer($course);
}
