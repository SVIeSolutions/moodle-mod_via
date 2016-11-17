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
 * This file contains a library of functions and constants for the via module
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/mod/via/locallib.php');
require_once($CFG->dirroot .'/mod/via/api.class.php');
require_once($CFG->dirroot .'/calendar/lib.php');
require_once(get_vialib());

/**
 * Return the list if Moodle features this module supports
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function via_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;

        default:
            return null;
    }
}

/**
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will create a new instance and return its ID number.
 *
 * @param object $via An object from the form in mod_form.php
 * @return int The ID of the newly inserted via record
 */
function via_add_instance($via) {
    global $CFG, $DB, $USER;

    via_data_postprocessing($via);

    $via->lang = current_language();
    $via->timecreated  = time();
    $via->timemodified = time();
    if (!isset($via->noparticipants)) {
        $via->noparticipants = "0";
    }

    if (!isset($via->introformat)) {
        $via->introformat = "1";
    }

    if (!isset($via->intro)) {
        $via->intro = "";
    }

    if (get_config('via', 'via_categories') == 0) {
        $via->category = 0;
    }

    $api = new mod_via_api();

    try {
        $response = $api->activity_create($via);
        $via->viaactivityid = $response;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'INVALID_PROFILID') !== false) {
            $profil = $DB->get_record('via_params', array('param_type' => 'multimediaprofil', 'value' => $via->profilid));
            via_get_list_profils();

            if ($profil) {
                $exists = $DB->get_record('via_params', array('id' => $profil->id));
            } else {
                $exists = false;
            }

            if ($exists) {
                $via->profilid = $exists->value;
            } else {
                $defaultprofil = $DB->get_record('via_params', array('param_type' => 'multimediaprofil'));
                $via->profilid = $defaultprofil->value;
            }
            // Set back activity type, otherwise it will be changed again.
            switch($via->activitytype) {
                case 2:
                    $via->activitytype = 1;
                    break;
                case 1:
                    $via->activitytype = 0;
                    break;
                default:
                    $via->activitytype = 0;
                    break;
            }

            return via_add_instance($via);

        } else {
            handle_createactivityapierror($e);
            return false;
        }
    }

    // We add the activity creator as host.
    if ($newactivity = $DB->insert_record('via', $via)) {
        // We add the host.
        if ($via->enroltype == 1 && isset($via->save_host)) {
            $host = $via->save_host;
        } else {
            $host = $USER->id;
        }
        $hostadded = via_add_participant($host, $newactivity, 2, true);
        if ($hostadded) {
            // We remove the moodle_admin from the activity.
            $moodleid = false;
            try {
                $response = $api->removeuser_activity($via->viaactivityid, get_config('via', 'via_adminid'), $moodleid);
            } catch (Exception $e) {
                notify(get_string("error:".$e->getMessage(), "via"));
            }
        }

        $context = via_get_course_instance($via->course);
        $query = null;
        $callvia = true;

        if ($via->enroltype == 1) {
            // If manual enrol.
            $count = 1;
            if ($via->save_participants != '') {
                $participants = explode(', ', $via->save_participants);
                if ($participants) {
                    foreach ($participants as $p) {
                        /* We only add the 50 first users, the rest will be synched on access */
                        if ($callvia && $count > 50) {
                            $callvia = false;
                        }

                        try {
                            via_add_participant($p, $newactivity, 1, $callvia);
                        } catch (Exception $e) {
                            echo get_string("error:".$e->getMessage(), "via");
                        }
                        $count ++;
                    }
                }
            }

            if ($via->save_animators != '') {
                $animators = explode(', ', $via->save_animators);
                if ($animators) {
                    foreach ($animators as $a) {
                        /* We only add the 50 first users, combined participants and animators the rest will be synched on access */
                        if ($callvia && $count > 50) {
                            $callvia = false;
                        }

                        try {
                            via_add_participant($a, $newactivity, 3, $callvia);
                        } catch (Exception $e) {
                            echo get_string("error:".$e->getMessage(), "via");
                        }
                        $count ++;
                    }
                }
            }
        } else {
            // Automatic enrol.
            if ($via->groupingid != 0) {
                $query = 'SELECT u.* FROM {groupings_groups} gg
                        LEFT JOIN {groups_members} gm ON gm.groupid = gg.groupid
                        LEFT JOIN {user} u ON u.id = gm.userid
                        WHERE gg.groupingid = ? ORDER BY u.lastname ASC';
                $param = array($via->groupingid);
            } else if ($via->groupid != 0) {
                $query = 'SELECT u.* FROM {groups_members} gm
                        LEFT JOIN {user} u ON u.id = gm.userid
                        WHERE gm.groupid = ? ORDER BY u.lastname ASC';
                $param = array($via->groupingid);
            } else {
                // We add users.
                $query = 'SELECT a.id as rowid, a.*, u.*
                            FROM {role_assignments} a, {user} u
                            WHERE contextid= ? AND a.userid=u.id ORDER BY u.lastname ASC';
                $param = array($context->id);
            }
        }

        if (isset($query)) {
            $count = 1;
            $users = $DB->get_records_sql($query, $param);
            foreach ($users as $user) {
                $type = via_user_type($user->id, $via->course, $via->noparticipants);
                /* We only add the 50 first users, the rest will be synched on access */
                if ($callvia && $count > 50) {
                    $callvia = false;
                }

                try {
                    via_add_participant($user->id, $newactivity, $type, $callvia);
                } catch (Exception $e) {
                    echo get_string("error:".$e->getMessage(), "via");
                }
                $count ++;
            }
        }
    }

    $via->id = $newactivity;

    if ($via->activitytype != 2 && !isset($via->viaassignid)) {
        // Activitytype 2 = permanent activity, we do not add these to calendar.
        // Plus if activities are created in Viaassign the event cannot be added to the calendar as there is no cm id!

        // Adding activity in calendar.
        $event = new stdClass();
        $event->name        = $via->name;
        $event->intro       = $via->intro;
        $event->courseid    = $via->course;
        $event->groupid     = $via->groupid;
        $event->userid      = 0;
        $event->modulename  = 'via';
        $event->instance    = $newactivity;
        $event->eventtype   = 'due';
        $event->timestart   = $via->datebegin;
        $event->timeduration = $via->duration * 60;

        calendar_event::create($event);
    }

    return $newactivity;
}

/**
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $via An object from the form in mod_form.php
 * @return bool Success/Fail.
 */
function via_update_instance($via) {
    global $CFG, $DB;

    if (!isset($via->id)) {
        // The via id is already added when comming from a viaassignment!
        $cm = get_coursemodule_from_instance("via", $via->instance, $via->course);
        via_data_postprocessing($via);
        $via->id = $via->instance;
        $isviaassign = false;
    } else {
        $isviaassign = true;
    }
    $via->lang = current_language();
    $via->timemodified = time();
    if (!isset($via->noparticipants)) {
        $via->noparticipants = "0";
    }

    if (get_config('via', 'via_categories') == 0) {
        $via->category = 0;
    }

    $viaactivity = $DB->get_record('via', array('id' => $via->id));
    if ($via->pastevent == 1) {
        $via->datebegin = $viaactivity->datebegin;
        $via->activitytype = $viaactivity->activitytype;
    }

    $via->viaactivityid = $viaactivity->viaactivityid;
    $via->playbacksync = 0; // We want to force it to sync again!

    $api = new mod_via_api();

    try {
        $response = $api->activity_edit($via);
    } catch (Exception $e) {
        handle_createactivityapierror($e);
        return false;
    }

    // Update enrollements.
    // We add users!
    $context = via_get_course_instance($via->course);
    $query = null;
    $queryoldusers = null;
    $callvia = true;

    // Grouping is selected!
    if ($viaactivity->groupingid == 0 && $via->groupingid != 0) {
        $query = 'SELECT u.* FROM {groupings_groups} gg
                LEFT JOIN {groups_members} gm ON gm.groupid = gg.groupid
                LEFT JOIN {user} u ON u.id = gm.userid
                WHERE gg.groupingid = ?';
        $param = array($via->groupingid);
        $queryoldusers = 'SELECT u.* FROM {via_participants} vp
                        LEFT JOIN {user} u ON u.id = vp.userid
                        WHERE vp.activityid = ? AND vp.participanttype != 2';
        $oldparams = array($via->id);
    } else if ($viaactivity->groupingid != $via->groupingid) {
        $query = 'SELECT u.* FROM {groupings_groups} gg
                LEFT JOIN {groups_members} gm ON gm.groupid = gg.groupid
                LEFT JOIN {user} u ON u.id = gm.userid
                WHERE gg.groupingid = ?';
        $param = array($via->groupingid);
        $queryoldusers = 'SELECT u.* FROM {groupings_groups} gg
                        LEFT JOIN {groups_members} gm ON gm.groupid = gg.groupid
                        LEFT JOIN {user} u ON u.id = gm.userid
                        WHERE gg.groupingid = ?';
        $oldparams = array($viaactivity->groupingid);
    } else if ($viaactivity->groupid != $via->groupid) {
        // Group is selected!
        $query = 'SELECT u.* FROM {groups_members} gm
                LEFT JOIN {user} u ON u.id = gm.userid
                WHERE gm.groupid = ?';
        $param = array($via->groupid);

        $queryoldusers = 'SELECT u.* FROM {groups_members} gm
                        LEFT JOIN {user} u ON u.id = gm.userid
                        WHERE gg.groupid = ?';
        $oldparams = array($viaactivity->groupid);
    } else {
        // Update roles for all both manual and automatic enrollement!
        $viausers = $DB->get_records('via_participants', array('activityid' => $via->id));
        $vusers = array();
        // We need to add the userid as key!
        foreach ($viausers as $vu) {
            $vusers[$vu->userid] = $vu;
        }
        $savedhost = $DB->get_record('via_participants', array('activityid' => $via->id, 'participanttype' => 2));

        // If host is different we need to remove the old host!
        if ($via->save_host != '' && $savedhost->userid != $via->save_host ) {
            $hostadded = via_add_participant($via->save_host, $via->id, 2, true);
            $oldhostremoved = via_remove_participant($savedhost->userid, $via->id);
            unset($vusers[$via->save_host]);
        } else {
            unset($vusers[$savedhost->userid]);
        }

        $count = 1;
        if ($via->save_participants != '') {
            $participants = explode(', ', $via->save_participants);
            if ($participants) {
                foreach ($participants as $p) {
                    /* We only add the 50 first users, the rest will be synched on access */
                    if ($callvia && $count > 50) {
                        $callvia = false;
                    }

                    try {
                        via_add_participant($p, $via->id, 1, $callvia);
                    } catch (Exception $e) {
                        echo get_string("error:".$e->getMessage(), "via");
                    }
                    $count ++;
                    unset($vusers[$p]);
                }
            }
        }
        if ($via->save_animators != '') {
            $animators = explode(', ', $via->save_animators);
            if ($animators) {
                foreach ($animators as $a) {
                    /* We only add the 50 first users, combined participants and animators the rest will be synched on access */
                    if ($callvia && $count > 50) {
                        $callvia = false;
                    }

                    try {
                        via_add_participant($a, $via->id, 3, $callvia);
                    } catch (Exception $e) {
                        echo get_string("error:".$e->getMessage(), "via");
                    }
                    $count ++;
                    unset($vusers[$a]);
                }
            }
        }
        if ($vusers) {
            foreach ($vusers as $v) {
                // We need to remove these users!
                // Wish automatic enrolement; we shouldn't need/be able to remove anyone!
                try {
                    via_remove_participant($v->userid, $via->id);
                } catch (Exception $e) {
                    notify(get_string("error:".$e->getMessage(), "via"));
                }
            }
        }
    }

    if (isset($query)) {
        // Should only do this if groups or groupings are active.
        $users = $DB->get_records_sql($query, $param);
        if ($queryoldusers) {
            $oldusers = $DB->get_records_sql($queryoldusers, $oldparams);
        } else {
            $oldusers = null;
        }

        $count = 1;
        foreach ($users as $user) {
            $type = via_user_type($user->id, $via->course, $via->noparticipants);
            /* We only add the 50 first users, the rest will be synched on access */
            if ($callvia && $count > 50) {
                $callvia = false;
            }

            try {
                via_add_participant($user->id, $via->id, $type, $callvia);
                unset($oldusers[$user->id]);// We don't want to add then remove.
            } catch (Exception $e) {
                echo get_string("error:".$e->getMessage(), "via");
            }
            $count ++;
        }

        if ($oldusers) {
            // We need to remove all the old group users from participants list.
            foreach ($oldusers as $olduser) {
                try {
                    via_remove_participant($olduser->id, $via->id);
                } catch (Exception $e) {
                    echo get_string('error:'.$e->getMessage(), 'via') . $muser->firstname.' '.$muser->lastname;
                }
            }
        }
    }

    // Only if it's a normal Via activity.
    if (!$isviaassign) {
        // Updates activity in calendar.
        $event = new stdClass();

        if ($event->id = $DB->get_field('event', 'id', array('modulename' => 'via', 'instance' => $via->id))) {
            $event->name        = $via->name;
            $event->intro       = $via->intro;
            $event->timestart   = $via->datebegin;
            $event->timeduration = $via->duration * 60;

            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, $checkcapability = false);
        } else {
            $event = new stdClass();
            $event->name        = $via->name;
            $event->intro       = $via->intro;
            $event->courseid    = $via->course;
            $event->groupid     = $via->groupid;
            $event->userid      = 0;
            $event->modulename  = 'via';
            $event->instance    = $via->id;
            $event->eventtype   = 'due';
            $event->timestart   = $via->datebegin;
            $event->timeduration = $via->duration * 60;

            calendar_event::create($event);
        }
    }

    return $DB->update_record('via', $via);
}

/**
 * Given an ID of an instance of a job via, this function will
 * permanently delete the instance and any data that depends on it.
 *
 * @param int $id ID of the via instance.
 * @return bool Success/Failure.
 */
function via_delete_instance($id) {
    global $DB;
    $result = true;

    $api = new mod_via_api();

    try {
        $via = $DB->get_record('via', array('id' => $id));
        if (!get_config('via', 'via_activitydeletion')) {
            $activitystate = '2';
            $response = $api->activity_edit($via, $activitystate);
        }
    } catch (Exception $e) {
        $result = false;
        print_error(get_string("error:".$e->getMessage(), "via"));
        return false;
    }

    if ($result) {
        if (!$DB->delete_records('via', array('id' => $id))) {
            $result = false;
        }
        if (!$DB->delete_records('via_participants', array('activityid' => $id))) {
            $result = false;
        }
        if (!$DB->delete_records('event', array('modulename' => 'via', 'instance' => $id))) {
            $result = false;
        }
        via_grade_item_delete($via);
    }

    return $result;
}

/**
 * Adds users to the participant list
 * @param array $users array of users
 * @param integer $viaid the via activity ID
 * @param integer $type the user type (host, animator or partcipant)
 * @return bool Success/Fail.
 */
function via_add_participants($users, $viaid, $type) {
    global $DB;

    $count = 1;
    $callvia = true;

    foreach ($users as $user) {
        try {
            /* We only add the 50 first users, the rest will be synched on access */
            if ($callvia && $count > 50) {
                $callvia = false;
            }
            $result = via_add_participant($user->id, $viaid, $type, $callvia);
        } catch (Exception $e) {
            echo get_string("error:".$e->getMessage(), "via");
        }
        $count ++;
    }

    return $result;
}

/**
 * Adds user to the participant list
 *
 * @param integer $userid the user id we are updating his status
 * @param integer $viaid the via activity ID
 * @param integer $type the user type (host, animator or partcipant)
 * @param integer $callvia to lighten the amount of calls to via we do not add all users to via only the first 50.
 * @param integer $confirmationstatus if enrol directly on Via, get his confirmation status
 * @return bool Success/Fail.
 */
function via_add_participant($userid, $viaid, $type, $callvia = null, $confirmationstatus=null) {
    global $CFG, $DB;

    $update = true;
    $sub = new stdClass();
    $sub->id = null;

    if ($participant = $DB->get_record('via_participants', array('userid' => $userid, 'activityid' => $viaid))) {
        if ($type == $participant->participanttype && !isset($callvia)) {
            // Nothing needs to be done.
            return true;
        }
        if ($type == $participant->participanttype) {
            // We need to add the user to via, but not update the users' general information, it has remained the same.
            $sub->id = $participant->id;
        }
        if ($participant->participanttype == 2) {// Host!
            // We do not modify the host!
            return 'host';
        }
        if ($participant->participanttype != $type && $update) {
            // The users' role has changed we need to update and maybe add to via, depending on the $callvia value.
            $sub->id = $participant->id;
        }
    }

    if ($update) {
        $sub->userid  = $userid;
        $sub->activityid = $viaid;
        $viaactivity = $DB->get_record('via', array('id' => $viaid));
        $sub->viaactivityid = $viaactivity->viaactivityid;
        $sub->participanttype = $type;
        $sub->timemodified = time();

        if ($participant == false || !$participant->enrolid) { // Avoid making DB calls if not required.
            $enrolid = via_get_enrolid($viaactivity->course, $userid);
            if ($enrolid) {
                $sub->enrolid = $enrolid;
            } else {
                // We need this 0 later to keep the user not enrolled in the coruse not to be deleted when synching users.
                $sub->enrolid = 0;
            }
        }

        if (!$confirmationstatus) {
            $sub->confirmationstatus = 1;// Not confirmed!
        } else {
            $sub->confirmationstatus = $confirmationstatus;
        }

        if ($callvia) {
            try {
                $api = new mod_via_api();
                $response = $api->add_user_activity($sub);
                if ($response != false) {
                    $sub->synchvia = 1;
                    $sub->timesynched = time();
                } else {
                    return false;
                }
            } catch (Exception $e) {
                return false;
            }
        } else {
            $sub->synchvia = 0;
            $sub->timesynched = null;
        }
    }
    try {
        if ($sub->id) {
            $added = $DB->update_record("via_participants", $sub);
        } else {
            $added = $DB->insert_record("via_participants", $sub);
        }

        return $added;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Removes user from the participant list
 *
 * @param integer $userid the user id
 * @param integer $viaid the via id
 * @param integer $synched if the user was synched (added to Via) we need to remove them from Via too
 * otherwise we can simply remove them from the participants list in moodle
 * @return bool Success/Fail
 */
function via_remove_participant($userid, $viaid, $synched = null) {
    global $CFG, $DB;

    $via = $DB->get_record('via', array('id' => $viaid));

    try {
        if (is_null($synched)) { // Was not yet validated!
            $vp = $DB->get_record('via_participants', array('userid' => $userid, 'activityid' => $viaid));
            if ($vp) {
                if (($vp->timesynched && is_null($vp->synchvia )) || $vp->synchvia == 1) {
                    $synched = true;
                } else {
                    $synched = false;
                }
            }
        }

        // For old versions!
        if ($synched == true) {
            // Now we update it on via!
            $api = new mod_via_api();
            $response = $api->removeuser_activity($via->viaactivityid, $userid);
        }
        if ($synched == false || isset($response)) {
            return $DB->delete_records('via_participants', array('userid' => $userid, 'activityid' => $viaid));
        }
    } catch (Exception $e) {
        notify(get_string("error:".$e->getMessage(), "via"));
    }
}

/**
 *  Find out if user is a presentaor for the activity
 *
 * @param integer $userid the user id
 * @param integer $activityid the via id
 * @return bool  host/not host
 */
function via_get_is_user_host($userid, $activityid) {
    global $DB;

    $host = $DB->count_records('via_participants',
        array('userid' => $userid, 'activityid' => $activityid, 'participanttype' => 2));
    if ($host >= 1) {
        return true;
    }

    return false;
}

/**
 * Verifies what a user can view for an activity, depending of his role and
 * if activity is done or not. If there are playbacks etc.
 *
 * @param object $via the via
 * @return integer what user can view
 */
function via_access_activity($via) {
    global $USER, $CFG, $DB;

    // If the user is an admin, he can access the activiy even though he is not added.

    $participant = $DB->get_record('via_participants', array('userid' => $USER->id, 'activityid' => $via->id));

    if ($participant || $via->enroltype == 0) {// If automatic enrol!
        if (!$participant && has_capability('moodle/site:approvecourse', via_get_system_instance())) {
            return 7;
        } else {
            if ((time() >= ($via->datebegin - (30 * 60))
                    && time() <= ($via->datebegin + ($via->duration * 60) + 60))
                || $via->activitytype == 2) {
                // If activity is hapening right now, show link to the activity.
                return 1;
            } else if (time() < $via->datebegin) {
                // Activity hasn't started yet.
                if ($participant->participanttype == 1) {
                    // If participant, user can't access activity.
                    return 3;
                } else {
                    // If participant is animator or host, show link to prepare activity.
                    return 2;
                }
            } else {
                // Activity is done. Must verify if there are any recordings of if.
                return 5;
            }
        }
    } else {
        if (get_config('viaassign')) {
            //This is just to validate that viaassign is installed, not to get an error the table does not exist.
            $viaassign = $DB->get_record('viaassign_submission', array('viaid' => $via->id));
            if ($viaassign) {
                $cm = get_coursemodule_from_instance('viaassign', $viaassign->viaassignid, null, false, MUST_EXIST);
                $cangrade = has_capability('mod/viaassign:grade', context_module::instance($cm->id));
            } else {
                $cangrade = false;
            }
            if (has_capability('moodle/site:approvecourse', via_get_system_instance()) || $cangrade) {
                return 7;
            } else {
                return 6;
            }
        } else {
            return 6;
        }
    }
}

/**
 * Print an overview of all vias
 * for the courses.
 *
 * @param mixed $courses The list of courses to print the overview for
 * @param array $htmlarray The array of html to return
 */
function via_print_overview($courses, &$htmlarray) {
    global $USER, $CFG;
    // These next 6 Lines are constant in all modules (just change module name).
    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$vias = get_all_instances_in_courses('via', $courses)) {
        return;
    }

    // Fetch some language strings outside the main loop.
    $strvia = get_string('modulename', 'via');

    $now = time();
    $time = new stdClass();
    foreach ($vias as $via) {
        if ($via->activitytype == 1 && ($via->datebegin + ($via->duration * 60) >= $now && ($via->datebegin - (30 * 60)) < $now)) {
            // Give a link to via.
            $str = '<div class="via overview"><div class="name"><img src="'.$CFG->wwwroot.'/mod/via/pix/icon.gif"
        "class="icon" alt="'.$strvia.'">';

            $str .= '<a ' . ($via->visible ? '' : ' class="dimmed"') .
            ' href="' . $CFG->wwwroot . '/mod/via/view.php?id=' . $via->coursemodule . '">' .
            $via->name . '</a></div>';
            if ($via->activitytype != 2) {
                $time->start = userdate($via->datebegin);
                $time->end = userdate($via->datebegin + ($via->duration * 60));
                $str .= '<div class="info_dev">' . get_string('overview', 'via', $time) . '</div>';
            }

            // Add the output for via to the rest.
            $str .= '</div>';
            if (empty($htmlarray[$via->course]['via'])) {
                $htmlarray[$via->course]['via'] = $str;
            } else {
                $htmlarray[$via->course]['via'] .= $str;
            }
        } else {
            continue;
        }
    }
}

/**
 * Delete grade item for given activity
 * All referece to grades have been removed.
 * This function is called upon upgrade only from a version that permitted grades.
 *
 * @param object $via object
 * @return object va
 */
function via_grade_item_delete($via) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($via->courseid)) {
        $via->courseid = $via->course;
    }

    return grade_update('mod/via', $via->courseid, 'mod', 'via', $via->id, 0, null, array('deleted' => 1));
}

/**
 * Function to be run periodically according to the moodle cron
 * Sends reminder for actvities.
 * Sends invitations with personnalised text.
 * Adds enrolids - only for versions before enrolids were added
 * synch_users - Deletes users from via_users if deleted in moodle,
 * synch_participants - Adds or removes users from activities with automatic enrollement
 * check_categories - checks that the categories still exist in Via, if not they are deleted
 * These categories refer to via categories that are only used for invvoicing perpouses.
 * @return bool $result sucess/fail
 */
function via_cron() {
    return;

    global $CFG, $DB;
    $result = true;

    $viacron = $DB->get_records('via_cron');
    $update = '';
    $params = array();

    foreach ($viacron as $function) {
        $lastcron = $function->lastcron;

        if (($function->cron + $lastcron) < time()) {
            if ($function->name == 'via_send_reminders') {
                echo "\n";
                $result = via_send_reminders() && $result;
                if ($result) {
                    $update .= 'id= ?';
                    $params[] = $function->id;
                }
            } else if ($function->name == 'via_add_enrolids') {
                echo "add enrolids \n";
                $result = via_add_enrolids() && $result;
                if ($result) {
                    if ($update == '') {
                        $update .= 'id= ?';
                    } else {
                        $update .= ' OR id= ?';
                    }
                    $params[] = $function->id;
                }
            } else if ($function->name == 'via_synch_users') {
                echo "synching users \n";
                $result = via_synch_users() && $result;
                if ($result) {
                    if ($update == '') {
                        $update .= 'id= ?';
                    } else {
                        $update .= ' OR id= ?';
                    }
                    $params[] = $function->id;
                }
            } else if ($function->name == 'via_synch_participants') {
                echo "synching participants \n";
                $result = via_synch_participants() && $result;
                if ($result) {
                    if ($update == '') {
                        $update .= 'id= ?';
                    } else {
                        $update .= ' OR id= ?';
                    }
                    $params[] = $function->id;
                }
            } else if ($function->name == 'via_send_export_notice') {
                echo "send export notice \n";
                if ($lastcron == 0) {
                    $lastcron = time();
                }
                $result = via_send_export_notice($lastcron) && $result;
                if ($result) {
                    if ($update == '') {
                        $update .= 'id= ?';
                    } else {
                        $update .= ' OR id= ?';
                    }
                    $params[] = $function->id;
                }
            }
        }
    }

    try {
        if ($update) {
            $updated = $DB->execute('UPDATE {via_cron} SET lastcron='.time().' WHERE ' . $update);
        }
    } catch (Exception $e) {
        echo $e->getMessage();
    }

    return $result;
}

/**
 * Called by the cron job to add enrolids to the via_participants table,
 * this will only happen once when the plugin is updated and the core adds in removed
 * afterwards the enrolid will be added when the activity is created or the user added to the course
 *
 * @return bool $result sucess/fail
 */
function via_add_enrolids() {
    global $DB;
    $result = true;
    $participants = $DB->get_records('via_participants', array('enrolid' => null, 'timesynched' => null));
    if ($participants) {
        foreach ($participants as $participant) {
                $enrolid = $DB->get_record_sql('SELECT e.id FROM {via_participants} vp
                            left join {via} v ON vp.activityid = v.id
                            left join {enrol} e ON v.course = e.courseid
                            left join {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = vp.userid
                            where vp.userid = ? and
                            vp.activityid = ? AND ue.id is not null', array($participant->userid, $participant->activityid));
            try {
                if ($enrolid) {
                    $DB->set_field('via_participants', 'enrolid', $enrolid->id, array('id' => $participant->id));
                    $DB->set_field('via_participants', 'timemodified', time(), array('id' => $participant->id));
                }
            } catch (Exception $e) {
                echo get_string("error:".$e->getMessage(), "via")."\n";
                $result = false;
                continue;
            }
        }
    }

    return $result;
}

/**
 * Called by the cron job to check if categories added to activities still exits,
 * if they don't we remove them from the via_catgoires table so that no new activity can be added to it
 * activites already created with the old category keep it though
 *
 * @return bool $result sucess/fail
 */
function via_check_categories() {
    global $DB;

    $result = true;
    $via = array();
    $existing = array();

    $catgeories = via_get_categories();
    if ($catgeories) {
        foreach ($catgeories['Category'] as $cat) {
            $via[$cat["CategoryID"]] = $cat["Name"];
        }

        $existingcats = $DB->get_records('via_categories');
        foreach ($existingcats as $cats) {
            $existing[$cats->id_via] = $cats->name;
        }

        $differences = array_diff($existing, $via);
        if ($differences) {
            foreach ($differences as $key => $value) {
                $delete = $DB->delete_records('via_categories', array('id_via' => $key));
            }
        }
    }

    return $result;
}

/**
 * Called by the cron job to send email reminders
 *
 * @return bool $result sucess/fail
 */
function via_send_reminders() {
    global $CFG, $DB;

    $reminders = via_get_reminders();
    if (!$reminders) {
        echo "    No email reminders need to be sent.\n";
        return true;
    }

    echo '    ', count($reminders), ' email reminder', (1 < count($reminders) ? 's have' : 'has'), " to be sent.\n";

    // If anything fails, we'll keep going but we'll return false at the end.
    $result = true;

    foreach ($reminders as $r) {
        $muser = $DB->get_record('user', array('id' => $r->userid));
        $from = via_get_host($r->activityid);

        if (!$muser) {
            echo "    User with ID {$r->userid} doesn't exist?!\n";
            $result = false;
            continue;
        }

        $result = via_send_moodle_reminders($r, $muser, $from);
    }

    return $result;
}

/**
 * Gets all activity that need reminders to ben sent
 *
 * @return object $reminders - which inclued the user's and activity's information.
 */
function via_get_reminders() {
    global $CFG, $DB;
    $now = time();

    $sql = "SELECT p.id, p.userid, p.activityid, v.name, v.datebegin, v.duration, v.viaactivityid, v.course, v.activitytype ".
    "FROM {via_participants} p ".
    "INNER JOIN {via} v ON p.activityid = v.id ".
    "WHERE v.remindertime > 0 AND ($now  >= (v.datebegin - v.remindertime)) AND v.mailed = 0 AND v.activitytype = 1";

    $reminders = $DB->get_records_sql($sql);

    return $reminders;
}

/**
 * Sends reminders with moodle
 *
 * @param object $r via object
 * @param object $user user to send reminder
 * @return bool $result sucess/fail
 */
function via_send_moodle_reminders($r, $muser, $from) {
    global $CFG, $DB;

    $result = true;
    // Recipient is self.
    $a = new stdClass();
    $a->username = fullname($muser);
    $a->title = $r->name;
    $a->datebegin = userdate($r->datebegin, '%A %d %B %Y');
    $a->hourbegin = userdate($r->datebegin, '%H:%M');
    $a->hourend = userdate($r->datebegin + ($r->duration * 60), '%H:%M');
    $a->datesend = userdate(time());

    $coursename = $DB->get_record('course', array('id' => $r->course));
    if (! $cm = get_coursemodule_from_instance("via", $r->activityid, $r->course)) {
        $viaurlparam = 'viaid';
        $viaurlparamvalue = $r->activityid;
    } else {
        $viaurlparam = 'id';
        $viaurlparamvalue = $cm->id;
    }

    $a->config = $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=7&viaid='.$r->activityid.'&courseid='.$r->course;
    if (get_config('via', 'via_technicalassist_url') == null) {
        $a->assist = $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=6&viaid='.$r->activityid.'&courseid='.$r->course;
    } else {
        $a->assist = get_config('via', 'via_technicalassist_url').'?redirect=6&viaid='.$r->activityid.'&courseid='.$r->course;
    }

    $a->activitylink = $CFG->wwwroot.'/mod/via/view.php?'.$viaurlparam.'='.$viaurlparamvalue;
    $a->coursename = $coursename->shortname;
    $a->modulename = get_string('modulename', 'via');

    // Fetch the subject and body from strings.
    $subject = get_string('reminderemailsubject', 'via', $a);

    $body = get_string('reminderemail', 'via', $a);

    $bodyhtml = utf8_encode(get_string('reminderemailhtml', 'via', $a));

    $bodyhtml = via_make_invitation_reminder_mail_html($r->course, $r, $muser, true);

    if (!isset($user->emailstop) || !$user->emailstop) {
        if (true !== email_to_user($muser, $from, $subject, $body, $bodyhtml)) {
            echo "    Could not send email to <{$muser->email}> (unknown error!)\n";
            $result = false;
        } else {
            echo "Sent an email reminder to {$muser->firstname} {$muser->lastname} <{$muser->email}>.\n";
        }
    }

    $record = new stdClass();
    $record->id = $r->activityid;
    $record->mailed = 1;
    if (!$DB->update_record('via', $record)) {
        // If this fails, stop everything to avoid sending a bunch of dupe emails.
        echo "    Could not update via table!\n";
        $result = false;
        continue;
    }

    return $result;
}

/**
 * Called by the cron job to send email invitation
 *
 * @return bool $result sucess/fail
 */
function via_send_invitations($activityid) {
    global $CFG, $DB;

    $invitations = via_get_invitations($activityid);
    if (!$invitations) {
        echo "    No email invitations need to be sent.\n";
        return true;
    }

    // If anything fails, we'll keep going but we'll return false at the end.
    $result = true;

    foreach ($invitations as $i) {
        $muser = $DB->get_record('user', array('id' => $i->userid));
        $from = via_get_host($i->activityid);

        if (!$muser) {
            $result = false;
            continue;
        }

        // Send reminder.
        try {
            $result = via_send_moodle_invitations($i, $muser, $from);
        } catch (Exception $e) {
            notify(get_string("error:".$e->getMessage(), "via"));
            $result = false;
            continue;
        }
    }

    return $result;
}

/**
 * Called by the cron job to send export notices
 *
 * @return bool $result sucess/fail
 */
function via_send_export_notice($lastcron) {
    global $CFG, $DB;
    // If anything fails, we'll keep going but we'll return false at the end.
    $result = true;

    $api = new mod_via_api();
    $notices = $api->get_notices($lastcron);

    if (!$notices['ExportList']) {
        echo "    No export noticies need to be sent.\n";
        return true;
    } else {
        if (isset($notices['ExportList']['Export']) && count($notices['ExportList']) == 1) {
            $notices = $notices['ExportList']['Export'];
        } else {
            $notices = $notices['ExportList'];
        }
    }

    foreach ($notices as $i) {
        if (isset($i['UserID'])) {
                $muser = $DB->get_record_sql('SELECT u.* FROM {via_users} vu
                                    LEFT JOIN {user} u ON vu.userid = u.id
                                    WHERE vu.viauserid = ?', array($i['UserID']));

            if (!$muser) {
                echo "User with Via user ID {$i['UserID']} doesn't exist?!\n";
                continue;
            }
            $activity = $DB->get_record('via', array('viaactivityid' => $i['ActivityID']));
            if (!$activity) {
                echo "Activity with Via activity ID {$i['ActivityID']} doesn't exist?!\n";
                continue;
            }

            // Send notice.
            try {
                $sql = "";
                if ($i["RecordingType"] == 1) {
                    $sql = "hasfullvideorecord = 1";
                } else if ($i["RecordingType"] == 2) {
                    $sql = "hasmobilevideorecord = 1";
                } else if ($i["RecordingType"] == 3) {
                    $sql = "hasaudiorecord = 1";
                }

                $DB->execute('UPDATE {via_playbacks} SET '.$sql.' WHERE playbackid = ?', array($i['PlaybackID']));
                $result = via_send_notices($i, $muser, $activity);
            } catch (Exception $e) {
                notify(get_string("error:".$e->getMessage(), "via"));
                continue;
            }
        }
    }

    return $result;
}

    /**
     * Called by the cron job to send activity notices
     *
     * @return bool $result sucess/fail
     */
function via_send_activity_notifications($lastcron) {
    global $CFG, $DB;
    // If anything fails, we'll keep going but we'll return false at the end.
    $result = true;

    $api = new mod_via_api();
    $notifications = $api->get_activity_notifications($lastcron);

    if (!$notifications['NotificationList']) {
        echo "    No activity notification needs to be sent.\n";
        return true;
    } else {
        if (isset($notifications['NotificationList']['Notification']) && count($notifications['NotificationList']) == 1) {
            $notifications = $notifications['NotificationList']['Notification'];
        } else {
            $notifications = $notifications['NotificationList'];
        }
    }

    foreach ($notifications as $i) {
        if (isset($i['HostID'])) {
            $muser = $DB->get_record_sql('SELECT u.* FROM {via_users} vu
                                    LEFT JOIN {user} u ON vu.userid = u.id
                                    WHERE vu.viauserid = ?', array($i['HostID']));

            if (!$muser) {
                echo "User with Via user ID {$i['HostID']} doesn't exist?!\n";
                continue;
            }
            $activity = $DB->get_record('via', array('viaactivityid' => $i['ActivityID']));
            if (!$activity) {
                echo "Activity with Via activity ID {$i['ActivityID']} doesn't exist?!\n";
                continue;
            }

            // Send notification.
            try {
                $result = via_send_notification($i, $muser, $activity);
            } catch (Exception $e) {
                notify(get_string("error:".$e->getMessage(), "via"));
                continue;
            }
        }
    }

    return $result;
}

/**
 * Sends invitations with moodle
 *
 * @param object $i via object
 * @param object $user user to send reminder
 * @return bool $result sucess/fail
 */
function via_send_moodle_invitations($i, $user, $from) {
    global $CFG, $DB;

    $muser = $user;
    // Recipient is self!
    $result = true;
    $a = new stdClass();

    $a->username = fullname($muser);
    $a->title = $i->name;
    $a->datebegin = userdate($i->datebegin, '%A %d %B %Y');
    $a->hourbegin = userdate($i->datebegin, '%H:%M');
    $a->hourend = userdate($i->datebegin + ($i->duration * 60), '%H:%M');
    $a->datesend = userdate(time());

    $coursename = $DB->get_record('course', array('id' => $i->course));
    if (! $cm = get_coursemodule_from_instance("via", $i->activityid, $i->course)) {
        $viaurlparam = 'viaid';
        $viaurlparamvalue = $i->activityid;
    } else {
        $viaurlparam = 'id';
        $viaurlparamvalue = $cm->id;
    }

    $a->config = $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=7&viaid='.$i->activityid.'&courseid='.$i->course;
    if (get_config('via', 'via_technicalassist_url') == null) {
        $a->assist = $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=6&viaid='.$i->activityid.'&courseid='.$i->course;
    } else {
        $a->assist = get_config('via', 'via_technicalassist_url') .'?redirect=6&viaid='.$i->activityid.'&courseid='.$i->course;
    }
    $a->activitylink = $CFG->wwwroot.'/mod/via/view.php?'.$viaurlparam.'='.$viaurlparamvalue;
    $a->coursename = $coursename->shortname;
    $a->modulename = get_string('modulename', 'via');

    if (!empty($i->invitemsg)) {
        if ($muser->mailformat != 1) {
            $a->invitemsg = $i->invitemsg;
        } else {
            $a->invitemsg = nl2br($i->invitemsg);
        }
    } else {
        $a->invitemsg = "";
    }

    // Fetch the subject and body from strings.
    $subject = get_string('inviteemailsubject', 'via', $a);
    if ($i->activitytype == 2) {
        $body = get_string('inviteemailpermanent', 'via', $a);
    } else {
        $body = get_string('inviteemail', 'via', $a);
    }

    $bodyhtml = via_make_invitation_reminder_mail_html($i->course, $i, $user);

    if (!isset($muser->emailstop) || !$muser->emailstop) {
        if (true !== email_to_user($user, $from, $subject, $body, $bodyhtml)) {
            echo "    Could not send email to <{$user->email}> (unknown error!)\n";
            $result = false;
            continue;
        }
    }

    $record = new stdClass();
    $record->id = $i->activityid;
    $record->sendinvite = 0;
    $record->invitemsg = "";
    if (!$DB->update_record('via', $record)) {
        // If this fails, stop everything to avoid sending a bunch of dupe emails.
        echo "    Could not update via table!\n";
        $result = false;
        continue;
    }

    return $result;
}

    /**
     * Sends export notices
     *
     * @param object $i via object
     * @param object $muser user to send reminder
     * @return bool $result sucess/fail
     */
function via_send_notices($i, $muser, $activity) {
    global $CFG, $DB, $SITE;

    $course = $DB->get_record('course', array('id' => $activity->course));
    if (! $cm = get_coursemodule_from_instance("via", $activity->id, $activity->course)) {
        $viaurlparam = 'viaid';
        $viaurlparamvalue = $activity->id;
    } else {
        $viaurlparam = 'id';
        $viaurlparamvalue = $cm->id;
    }

    if ($i['RecordingType'] == 1) {
        $type = get_string('fullvideo', 'via');
    } else if ($i['RecordingType'] == 2) {
        $type = get_string('mobilevideo', 'via');
    } else {
        $type = get_string('audiorecord', 'via');
    }

    $from = $SITE->fullname;

    // Recipient is self!
    $result = true;
    $a = new stdClass();
    $a->username = fullname($muser);
    $a->playbacktitle = $i['PlaybackTitle'];
    $a->date = userdate(strtotime($i['ExportEndDate']), '%d %B, %H:%M');
    $a->type = $type;
    $a->activitytitle = $activity->name;
    $a->coursename = $course->shortname;
    $a->modulename = get_string('modulename', 'via');
    $a->activitylink = $CFG->wwwroot.'/mod/via/view.php?'.$viaurlparam.'='.$viaurlparamvalue;
    $a->viaurlparam = $viaurlparam;
    $a->viaurlparamvalue = $viaurlparamvalue;
    $a->courseid = $course->id;

    // Fetch the subject and body from strings.
    $subject = get_string('noticeemailsubject', 'via');

    $body = get_string('noticeemail', 'via', $a);

    $bodyhtml = via_make_notice_mail_html($a, $muser);

    if (!isset($muser->emailstop) || !$muser->emailstop) {
        if (true !== email_to_user($muser, $from, $subject, $body, $bodyhtml)) {
            echo "    Could not send email to <{$muser->email}> (unknown error!)\n";
        } else {
            echo "    An export notice was sent to " . $muser->firstname ." " . $muser->lastname . " " .$muser->email. "\n";
        }
    }

    return $result;
}

    /**
     * Sends activity notification
     *
     * @param object $i via object
     * @param object $muser user to send reminder
     * @return bool $result sucess/fail
     */
function via_send_notification($i, $muser, $activity) {
    global $CFG, $DB, $SITE;
	
	$result = true;

    $course = $DB->get_record('course', array('id' => $activity->course));
    if (! $cm = get_coursemodule_from_instance("via", $activity->id, $activity->course)) {
        $viaurlparam = 'viaid';
        $viaurlparamvalue = $activity->id;
    } else {
        $viaurlparam = 'id';
        $viaurlparamvalue = $cm->id;
    }

    $from = $SITE->fullname;

    $muserfrom = $DB->get_record_sql('SELECT u.* FROM {via_users} vu
                                LEFT JOIN {user} u ON vu.userid = u.id
                                WHERE vu.viauserid = ?', array($i['UserID']));

    if (!$muserfrom) {
		$muserfrom = '';
    }

    // Recipient is self!
    $a = new stdClass();
    $a->username = fullname($muser);
	$a->userfrom = fullname($muserfrom);
    $a->date = userdate(strtotime($i['DateSent']), '%d %B, %H:%M');
    $a->activitytitle = $activity->name;
    $a->coursename = $course->shortname;
    $a->modulename = get_string('modulename', 'via');
    $a->activitylink = $CFG->wwwroot.'/mod/via/view.php?'.$viaurlparam.'='.$viaurlparamvalue;
    $a->viaurlparam = $viaurlparam;
    $a->viaurlparamvalue = $viaurlparamvalue;
    $a->courseid = $course->id;

    // Fetch the subject and body from strings.
    $subject = get_string('notificationemailsubject', 'via');

    $body = get_string('notificationemail', 'via', $a);

    $bodyhtml = via_make_notification_mail_html($a, $muser);

    if (!isset($muser->emailstop) || !$muser->emailstop) {
        if (true !== email_to_user($muser, $from, $subject, $body, $bodyhtml)) {
            echo "    Could not send email to <{$muser->email}> (unknown error!)\n";
        } else {
            echo "    An activity notification was sent to " . $muser->firstname ." " . $muser->lastname . " " .$muser->email. "\n";
        }
    }

    return $result;
}

/**
 * gets all activity that need invitations to ben sent
 *
 * @return object $invitations - which inclues the user's and activity information
 */
function via_get_invitations($activityid) {
    global $CFG, $DB;
    $now = time();

    $sql = "SELECT p.id, p.userid, p.activityid, v.name, v.course, v.datebegin,
        v.duration, v.viaactivityid, v.invitemsg, v.activitytype, v.id viaid
        FROM {via_participants} p
        INNER JOIN {via} v ON p.activityid = v.id
        WHERE v.sendinvite = 1";

    if ($activityid <> null) {
        $sql .= " AND v.id = ? ";
    }

    $invitations = $DB->get_records_sql($sql, array($activityid));

    return $invitations;
}

/**
 * Adds the necessary elements to the course reset form (called by course/reset.php)
 * @param object $mform The form object (passed by reference).
 */
function via_reset_course_form_definition(&$mform) {
    global $COURSE;

    $mform->addElement('header', 'viaheader', get_string('modulenameplural', 'via'));

    $mform->addElement('checkbox', 'delete_via_modules', get_string('resetdeletemodules', 'via'));
    $mform->addElement('checkbox', 'reset_via_participants', get_string('resetparticipants', 'via'));
    $mform->addElement('checkbox', 'disable_via_reviews', get_string('resetdisablereviews', 'via'));
    $mform->disabledif ('reset_via_participants', 'delete_via_modules', 'checked');
    $mform->disabledif ('disable_via_reviews', 'delete_via_modules', 'checked');
}

/**
 * Performs course reset actions, depending on the checked options.
 * @param object $data Reset form data.
 * @param Array with status data.
 * @return Array component, item and error message if any
 */
function via_reset_userdata($data) {
    global $COURSE, $CFG;

    $context = via_get_course_instance($COURSE->id);
    $vias = get_all_instances_in_course('via', $COURSE);

    $status = array();

    // Deletes all via activities.
    if (!empty($data->delete_via_modules)) {
        $result = via_delete_all_modules($vias);
        $status[] = array(
            'component' => get_string('modulenameplural', 'via'),
            'item' => get_string('resetdeletemodules', 'via'),
            'error' => $result ? false : get_string('error:deletefailed', 'via'));
    }

    // Unenrol all participants on via activities.
    if (!empty($data->reset_via_participants)) {
        $result = via_remove_participants($vias);
        $status[] = array(
            'component' => get_string('modulenameplural', 'via'),
            'item' => get_string('resetparticipants', 'via'),
            'error' => $result ? false : get_string('error:resetparticipants', 'via'));
    }

    // Disables all playback so participants cannot review activities.
    if (!empty($data->disable_via_reviews)) {
        $result = via_disable_review_mode($vias);
        $status[] = array(
            'component' => get_string('modulenameplural', 'via'),
            'item' => get_string('resetdisablereviews', 'via'),
            'error' => $result ? false : get_string('error:disablereviews', 'via'));
    }

    return $status;
}

/**
 * Delete all via activities
 * @param object $vias all via activiites for a given course
 * @return bool sucess/fail
 */
function via_delete_all_modules($vias) {
    global $CFG, $COURSE, $DB;

    $result = true;

    foreach ($vias as $via) {
        // Delete svi!
        if (!via_delete_instance($via->id)) {
            $result = false;
        }

        require_once($CFG->dirroot.'/course/lib.php');

        if (!$cm = $DB->get_record('course_modules', array('id' => $via->coursemodule))) {
            $result = false;
        }

        if (!delete_course_module($via->coursemodule)) {
            $result = false;
        }

        if (!delete_mod_from_section($via->coursemodule, $cm->section)) {
            $result = false;
        }

        if (!$DB->delete_records('via_participants', array('activityid' => $via->id))) {
            $result = false;
        }
    }

    rebuild_course_cache($COURSE->id);

    return $result;
}

/**
 * Unenrol all participants for all activities
 * @param object $vias all via activiites for a given course
 * @return bool sucess/fail
 */
function via_remove_participants($vias) {
    global $CFG, $DB;

    $result = true;

    foreach ($vias as $via) {
        // Unenrol all participants on VIA server only if we do not delete activity on VIA, since this activity was backuped.
        $participants = $DB->get_records_sql("SELECT * FROM {via_participants}
                                            WHERE activityid= ? AND participanttype != 2", array($via->id));
        foreach ($participants as $participant) {
            if (!via_remove_participant($participant->userid, $via->id)) {
                $result = false;
            }
        }
    }
    return $result;
}

/**
 * Disables the review mode for all activities
 * @param object $vias all via activiites for a given course
 * @return bool sucess/fail
 */
function via_disable_review_mode($vias) {
    global $DB;

    $result = true;

    foreach ($vias as $via) {
        $result = true;

        $via->timemodified = time();
        $via->isreplayallowed = 0;
        $api = new mod_via_api();

        // Disables review mode on VIA server.
        try {
            $response = $api->activity_edit($via);
        } catch (Exception $e) {
            print_error(get_string("error:".$e->getMessage(), "via"));
            $result = false;
        }

        // Disables review mode on Moodle.
        $via->name = $via->name;
        $via->invitemsg = $via->invitemsg;
        $via->intro = $via->intro;
        $via->introformat = $via->introformat;
        if (!$DB->update_record("via", $via)) {
            $result = false;
        }
    }
    return $result;
}

/**
 * Changes confirmation status of a participant
 * @param integer $viaid Via id on Moodle DB
 * @param integer $present status
 * @return true / false
 */
function via_set_participant_confirmationstatus($viaid, $present) {
    global $CFG, $USER, $DB;

    $via = $DB->get_record('via', array('id' => $viaid));
    $via->userid = $USER->id;
    $via->confirmationstatus = $present;

    if ($participanttypes = $DB->get_records('via_participants', array('userid' => $USER->id, 'activityid' => $viaid))) {
        $api = new mod_via_api();
        foreach ($participanttypes as $type) {
            $type->confirmationstatus = $present;
            $DB->update_record("via_participants", $type);
            try {
                $response = $api->edituser_activity($via, $type->participanttype);
                $result = true;
            } catch (Exception $e) {
                print_error(get_string("error:".$e->getMessage(), "via"));
                $result = false;
            }
        }
    }
    return $result;
}

/**
 * Inserts correct document for moodle version
 * This document calls functions that have been changed depending on the moodle version.
 *
 * @returns string of document name
 */
function get_vialib() {
    global $CFG;

    if ($CFG->version < 2014051200) {
        return $CFG->dirroot.'/mod/via/vialib/version24.php';
    } else {
        return $CFG->dirroot.'/mod/via/vialib/version27.php';
    }
}

function handle_createactivityapierror($e) {
    if (strpos($e->getMessage(), 'SIMULTANEOUS_ROOM_MAX') !== false) {
        $msg = $e->getMessage();
        $msg = substr($msg, strpos($e->getMessage(), ' ') + 1);
        $conflicts = explode(": ", $msg)[1];
        $msg = substr($msg, 0, strpos($msg, ':') + 1);

        foreach (explode(',', $conflicts) as $conflict) {
            $conf = explode('|', $conflict);
            $msg .= '<br />';
            $msg .= $conf[0] . ' - ' . $conf[1] . ' / ' . $conf[2];
        }

        $_SESSION['ErrMaxSimActMessage'] = $msg;
        $_SESSION['ErrMaxSimActMessageVia'] = $via;
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    } else {
        print_error(get_string("error:".$e->getMessage(), "via"));
    }
}

    require_once($CFG->dirroot.'/lib/formslib.php');
/**
 * The form used by users to send instant messages
 *
 * @package   core_message
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class via_send_invite_form extends moodleform {
    public function definition () {
        $mform =& $this->_form;
        $msg = $this->_customdata['message'];
        if (isset($this->_customdata['id'])) {
            $mform->addElement('hidden', 'id', $this->_customdata['id']);
            $mform->setType('id', PARAM_INT);
        }

        if (isset($this->_customdata['viaid'])) {
            $mform->addElement('hidden', 'viaid', $this->_customdata['viaid']);
                $mform->setType('viaid', PARAM_INT);
        }
        $mform->setType('id', PARAM_INT);
        $editoroptions = array('maxfiles' => 0, 'maxbytes' => 0);

        $mform->addElement('editor', 'msg', get_string("personalinvitemsg", "via"), null, $editoroptions);
        $mform->setDefault('msg', array('text' => $msg, 'format' => FORMAT_HTML));
        $mform->setType('editor', PARAM_CLEANHTML);

        $this->add_action_buttons(true, get_string('submitinvite', 'via'));
    }
}