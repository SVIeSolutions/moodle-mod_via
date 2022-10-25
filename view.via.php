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
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/via/lib.php');
require_once(get_vialib());

global $DB, $USER;

$id = optional_param('id', null, PARAM_INT);
$viaid = optional_param('viaid', null, PARAM_INT);
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

// Modified to access via from a normal via activity or a delegated via activity!
if ($id) {
    if (!($cm = get_coursemodule_from_id('via', $id))) {
        print_error("Course module ID is incorrect");
    }
    if (!($via = $DB->get_record('via', array('id' => $cm->instance)))) {
        print_error("Via ID is incorrect");
    }
    $PAGE->set_url('/mod/via/view.php', array('id' => $id));
} else if ($viaid) {
    $viaassign = $DB->get_record('viaassign_submission', array('viaid' => $viaid));
    if (!($cm = get_coursemodule_from_instance('viaassign', $viaassign->viaassignid, null, false, MUST_EXIST))) {
        print_error("Course module ID is incorrect");
    }
    if (!($via = $DB->get_record('via', array('id' => $viaid)))) {
        print_error("Via ID is incorrect");
    }
    $PAGE->set_url('/mod/via/view.php', array('viaid' => $viaid));
}

if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error('Incorrect course id');
}

require_login($course, false, $cm);

$context = via_get_module_instance($cm->id);
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
$ishtml5 = $via->activityversion == 1;

// In case the user still has not been added.
// If user_participants field timesynched is not null we add him.
// Otherwise we check if he is in the activity if he isn't we add him.

$viaparticipant = $DB->get_record('via_participants', array('userid' => $USER->id, 'activityid' => $via->id));
if (!has_capability('mod/via:access', $context) && !has_capability('moodle/site:approvecourse', via_get_system_instance())) {
    // Only users with a lower role are added.
    if (isset($viaparticipant->synchvia) && $viaparticipant->synchvia == 1) {
        $connexion = true;
    } else {
        $viauser = $DB->get_record('via_users', array('userid' => $USER->id));
        // The user doesn't exists yet. We need to create it.
        if (!$viauser) {
            try {
                $uservalidated = $api->validate_via_user($USER, $ishtml5);
                $viauser = $DB->get_record('via_users', array('viauserid' => $uservalidated));
            } catch (Exception $e) {
                throw new moodle_exception(get_error_message($e). $muser->firstname.' '.$muser->lastname);
            }
        }
        try {
            $type = via_user_type($viauser->userid, $course->id, $via->noparticipants);
            $added = via_add_participant($viauser->userid, $via->id, $type, $via->activityversion == 0);

            if ($ishtml5) {
                $userarray = new ArrayObject();
                $userarray->append(array($viauser->userid, $type));
                $added = $api->set_users_activity_html5($userarray, $via, true);
            }
            $connexion = true;
        } catch (Exception $e) {
            throw new moodle_exception(get_error_message($e). $muser->firstname.' '.$muser->lastname);
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
            if ($via->activityversion == 0) {
                $response = $api->userget_ssotoken($via, 3, null, $forcedaccess);
            } else {
                $response = $api->userget_ssotoken_html5($via, 8, null, $forcedaccess);
            }

        } else {
            if ($via->activityversion == 0) {
                $response = $api->userget_ssotoken($via, 3, $playbackid, $forcedaccess, $forcededit);
            } else {
                $response = $api->userget_ssotoken_html5($via, 8, $playbackid, $forcedaccess);
            }
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
        print_error($msg, $style = 'recordwarning', $return = false);
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
            $error = get_error_message($e);
        }
    } else {
        $error = get_error_message($e);
    }

    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->box_start('notice');
    echo $error;
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer($course);
}