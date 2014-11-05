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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/via/api.class.php');
require_once($CFG->dirroot.'/calendar/lib.php');

function via_supports($feature) {
    switch($feature) {

        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GROUPS:
            return true;
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

    if (get_config('via', 'via_moodleemailnotification')) {
        $via->moodleismailer = 1;
    } else {
        $via->moodleismailer = 0;
    }

    if (get_config('via', 'via_categories') == 0) {
        $via->category = 0;
    }

    $api = new mod_via_api();

    try {
        $response = $api->activity_create($via);
    } catch (Exception $e) {
        print_error(get_string("error:".$e->getMessage(), "via"));
        return false;
    }

    // We add the activity creator as presentor.
    if ($newactivity = $DB->insert_record('via', $via)) {

        // We add the presentor.
        // Via_add_participant($userid, $viaid, $type, $confirmationstatus, $checkuserstatus, $adddeleteduser).
        $presenteradded = via_add_participant($USER->id, $newactivity, 2, 1, 1);
        if ($presenteradded) {
            // We remove the moodle_admin from the activity.
            $moodleid = false;
            $api->removeuser_activity($via->viaactivityid, get_config('via', 'via_adminid'), $moodleid);
        }

        $context = context_course::instance($via->course);
        $query = null;

        if ($via->enroltype == 0 && $via->groupingid == 0) {// If automatic enrol.
            // We add users.
            $query = 'SELECT a.id as rowid, a.*, u.*
                      FROM mdl_role_assignments as a, mdl_user as u
                      WHERE contextid=' . $context->id . ' AND a.userid=u.id';
        } else if ($via->groupingid != 0) {
            $query = 'SELECT u.* FROM mdl_groupings_groups gg
                      LEFT JOIN mdl_groups_members gm ON gm.groupid = gg.groupid
                      LEFT JOIN mdl_user u ON u.id = gm.userid
                      WHERE gg.groupingid = '.$via->groupingid.'';
        }
        if ($query) {
            $users = $DB->get_records_sql( $query );
            foreach ($users as $user) {

                $type = get_user_type($user->id, $via->course, isset($via->noparticipants));

                try {
                    via_add_participant($user->id, $newactivity, $type);
                } catch (Exception $e) {
                    echo '<div class="alert alert-block alert-info">'
                    .get_string('error_user', 'via', $muser->firstname.' '.$muser->lastname).'</div>';
                }
            }
        }
    }

    $via->id = $newactivity;

    if ($via->activitytype != 2) {// Activitytype 2 = permanent activity, we do not add these to calendar.
        // Adding activity in calendar.
        $event = new stdClass();
        $event->name        = $via->name;
        $event->intro       = $via->intro;
        $event->courseid    = $via->course;
        $event->groupid     = 0;
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

function get_enrolid($course, $userid) {
    global $DB;

    $enrollments = $DB->get_records('enrol', array('courseid' => $course, 'status' => 0));
    $enrollmentids = array();
    foreach ($enrollments as $enrol) {
        $enrollmentids[] = $enrol->id;
    }
    foreach ($enrollmentids as $enrolid) {
        $enrollment = $DB->get_record('user_enrolments', array('userid' => $userid, 'enrolid' => $enrolid));
        if ($enrollment) {
            return $enrollment->enrolid;
            continue;
        }
    }
    // May be null in the case of a manager or admin user which can be
    // added as animator or presentor without being enrolled in the course.
    return null;
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

    via_data_postprocessing($via);
    $via->id = $via->instance;
    $via->lang = current_language();
    $via->timemodified = time();

    if (get_config('via', 'via_moodleemailnotification')) {
        $via->moodleismailer = 1;
    } else {
        $via->moodleismailer = 0;
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

    $api = new mod_via_api();

    try {
        $response = $api->activity_edit($via);
    } catch (Exception $e) {
        print_error(get_string("error:".$e->getMessage(), "via"));
        return false;
    }

    $cm = get_coursemodule_from_instance("via", $via->id, $via->course);

    // Update enrollements.
    // We add users!
    $context = context_course::instance($via->course);
    $query = null;
    $queryoldusers = null;

    if ($viaactivity->enroltype != $via->enroltype && $via->enroltype == 0 && $via->groupingid == 0) {// If automatic enrol.
        $query = 'select a.id as rowid, a.*, u.* from mdl_role_assignments as a, mdl_user as u
                  where contextid=' . $context->id . ' and a.userid=u.id';

    } else if ($via->groupingid != 0) {
        if ($viaactivity->groupingid != $via->groupingid) {

            $query = 'SELECT u.* FROM mdl_groupings_groups gg
                    LEFT JOIN mdl_groups_members gm ON gm.groupid = gg.groupid
                    LEFT JOIN mdl_user u ON u.id = gm.userid
                    WHERE gg.groupingid = '.$via->groupingid.'';

            $queryoldusers = 'SELECT u.* FROM mdl_groupings_groups gg
                            LEFT JOIN mdl_groups_members gm ON gm.groupid = gg.groupid
                            LEFT JOIN mdl_user u ON u.id = gm.userid
                            WHERE gg.groupingid = '.$viaactivity->groupingid.'';
        }
    }

    if ($query) {
        $users = $DB->get_records_sql( $query );
        if ($queryoldusers) {
            $oldusers = $DB->get_records_sql($queryoldusers);
        } else {
            $oldusers = null;
        }

        foreach ($users as $user) {

            $type = get_user_type($user->id, $via->course, isset($via->noparticipants));

            try {
                via_add_participant($user->id, $via->id, $type);
                unset($oldusers[$user->id]);// We don't want to add then remove.
            } catch (Exception $e) {
                echo '<div class="alert alert-block alert-info">'.
                get_string('error_user', 'via', $muser->firstname.' '.$muser->lastname).'</div>';
            }

        }
        if ($oldusers) {
            // We need to remove all the old group users from participants list.
            foreach ($oldusers as $olduser) {
                via_remove_participant($olduser->id, $via->id);
            }
        }
    }

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
        $event->groupid     = 0;
        $event->userid      = 0;
        $event->modulename  = 'via';
        $event->instance    = $via->id;
        $event->eventtype   = 'due';
        $event->timestart   = $via->datebegin;
        $event->timeduration = $via->duration * 60;

        calendar_event::create($event);
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
 * Converts certain form values before entering them in the database.
 *
 * @param object $via An object from the form in mod_form.php
 */
function via_data_postprocessing(&$via) {
    $via->profilid = $via->profilid;

    if (isset($via->activitytype)) {
        switch($via->activitytype) {
            case 0:
                $via->activitytype = 1;
                break;
            case 1:
                $via->activitytype = 2;
                break;
            default:
                $via->activitytype = 1;
                break;
        }
    } else {
        $via->activitytype = 1;
    }
}


/**
 * Gets the categories created in Via by the administrators 
 *
 * @return an array of the different categories to create drop down list in the mod_form.
 */
function get_via_categories() {
    global $CFG;

    $result = true;

    $api = new mod_via_api();

    try {
        $response = $api->via_get_categories();
    } catch (Exception $e) {
        notify(get_string("error:".$e->getMessage(), "via"));
    }

    return $response;
}

/**
 * Updates Moodle Via info with data coming from VIA server.
 *
 * @param object $values An object from view.php containg via activity infos
 * @return object containing new infos for activity.
 */
function update_info_database($values) {
    global $CFG, $DB;

    $result = true;
    $via = new stdClass();
    $svi = new stdClass();

    foreach ($values as $key => $value) {
        $via->$key = $value;
    }

    $api = new mod_via_api();

    try {
        $infosvi = $api->activity_get($via);

        foreach ($infosvi as $key => $info) {
            $svi->$key = $info;
        }
    } catch (Exception $e) {
        if ($e->getMessage() == "ACTIVITY_DOES_NOT_EXIST") {
            $error = get_string("activitywaserased", "via");
        } else {
            $error = get_string("error:".$e->getMessage(), "via");
        }
        $result = false;
    }

    if ($result) {

        $via->intro = $via->intro;
        $via->introformat = $via->introformat;
        $via->invitemsg = $via->invitemsg;
        $via->profilid = $svi->ProfilID;

        $via->isreplayallowed = $svi->IsReplayAllowed;
        $via->roomtype = $svi->RoomType;
        $via->audiotype = $svi->AudioType;
        $via->activitytype = $svi->ActivityType;
        $via->recordingmode = $svi->RecordingMode;
        $via->recordmodebehavior = $svi->RecordModeBehavior;

        if (!get_config('via', 'via_moodleemailnotification')) {
            // The reminder email is sent with Moodle, not VIA.
            $via->remindertime = via_get_remindertime_from_svi($svi->ReminderTime);
            $via->moodleismailer = 0;
        } else {
            $via->moodleismailer = 1;
        }

        $via->datebegin = strtotime($svi->DateBegin);

        $via->duration = $svi->Duration;
        $via->waitingroomaccessmode = $svi->WaitingRoomAccessMode;
        $via->timemodified = time();
        $via->nbConnectedUsers = $svi->NbConnectedUsers;

        if ($DB->update_record('via', $via)) {

            if ($via->activitytype != 2) {
                // Activitytype 2 = permanent activity, we do not add these to calendar!
                $event = new stdClass();

                if ($event->id = $DB->get_field('event', 'id', array('modulename' => 'via', 'instance' => $via->id))) {
                    $event->name        = $via->name;
                    $event->description = $via->intro;
                    $event->timestart   = $via->datebegin;
                    $event->timeduration = $via->duration * 60;

                    $calendarevent = calendar_event::load($event->id);
                    $calendarevent->update($event, $checkcapability = false);

                } else {
                    $event = new stdClass();
                    $event->name        = $via->name;
                    $event->description = $via->intro;
                    $event->courseid    = $via->course;
                    $event->groupid     = 0;
                    $event->userid      = 0;
                    $event->modulename  = 'via';
                    $event->instance    = $via->id;
                    $event->eventtype   = 'due';
                    $event->timestart   = $via->datebegin;
                    $event->timeduration = $via->duration * 60;

                    calendar_event::create($event);
                }
            }
        }

        return $via;

    } else {
        print_error($error, "$CFG->wwwroot/course/view.php?id=$via->course");
    }
}

/**
 * Get the list of potential participants to via. 
 *
 * @param object $viacontext the via context.
 * @param integer $groupid the id of a group, or 0 for all groups.
 * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
 * @param string $sort sort order. As for get_users_by_capability.
 * @return array list of users.
 */
function via_get_potential_participants($viacontext, $fields, $sort) {
    return get_users_by_capability($viacontext, 'mod/via:view', $fields, $sort, '', '', '', '', false, true);
}

/**
 * Returns list of user objects that are participants to this via activity
 *
 * @param object $course the course
 * @param forum $via the via activity
 * @param integer $groupid group id, or 0 for all.
 * @param object $context the via context, to save re-fetching it where possible.
 * @return array list of users.
 */

function via_participants($course, $via, $participanttype, $context = null) {
    global $CFG, $DB;

    $results = $DB->get_records_sql('SELECT distinct u.id, '.user_picture::fields('u').', u.username, u.firstname,
                                    u.lastname, u.maildisplay, u.mailformat, u.maildigest, u.emailstop, u.imagealt,
                                    u.idnumber, u.email, u.city, u.country, u.lastaccess, u.lastlogin, u.picture,
                                    u.timezone, u.theme, u.lang, u.trackforums, u.mnethostid
                                    FROM mdl_user u, mdl_via_participants s
                                    WHERE s.activityid = '.$via->id.'
                                    AND s.userid = u.id
                                    AND s.participanttype = '. $participanttype.'
                                    AND u.deleted = 0
                                    ORDER BY u.email ASC ');

    static $guestid = null;

    if (is_null($guestid)) {
        if ($guest = guest_user()) {
            $guestid = $guest->id;
        } else {
            $guestid = 0;
        }
    }

    // Guest user should never be subscribed to a via activity.
    unset($results[$guestid]);

    return $results;
}

/**
 * Adds user to the participant list
 *
 * @param integer $userid the user id we are updating his status
 * @param integer $viaid the via activity ID
 * @param integer $type the user type (presentator, animator or partcipant)
 * @param integer $confirmationstatus if enrol directly on Via, get his confirmation status 
 * @return bool Success/Fail.
 */
function via_add_participant($userid, $viaid, $type, $confirmationstatus=null, $checkuserstatus=null, $adddeleteduser = null) {
    global $CFG, $DB;

    $update = true;
    $sub = new stdClass();
    $sub->id = null;
    $api = new mod_via_api();

    if ($participant = $DB->get_record('via_participants', array('userid' => $userid, 'activityid' => $viaid))) {
        if ($type == $participant->participanttype) {
            if (!$adddeleteduser) {
                return true;
            }
            $sub->id = $participant->id;
        }
        if ($participant->participanttype == 2) {// Presentator!
            if ($adddeleteduser) {
                $sub->id = $participant->id;
            } else {
                $update = false;
                $added = 'presenter';
            }
        }
        if ($participant->participanttype != $type && $update) {// Animator!
            $sub->id = $participant->id;
        }
    }

    if ($checkuserstatus) {
        $u = $DB->get_record('via_users', array('userid' => $userid));
        if ($u) {// User does not exist yet, we go through the normal process!
            $viauser = $api->via_user_get($u, 1);

            if (!$viauser) {// There is no user!
                $muser = $DB->get_record('user', array('id' => $userid));
                $muser->viauserid = $u->viauserid;
                try {
                    $api->validate_via_user($muser, $u->id, true);
                } catch (Exception $e) {
                    echo '<div class="alert alert-block alert-info">'.
                    get_string('error_user', 'via', $muser->firstname.' '.$muser->lastname).'</div>';
                }
            } else {// The user is either deleted or deactivated.
                if ($viauser["Status"] != 0) {
                    $muser = $DB->get_record('user', array('id' => $userid));
                    $muser->status = 0;// Change status back to active.
                    $muser->viauserid = $u->viauserid;
                    if ($viauser["Status"] == 1 ) {// Deactivated!

                        $viauserdata = $api->via_user_create($muser, true);

                    } else if ($viauser["Status"] == 2) {// Deleted!

                        $viauserdata = $api->via_user_create($muser, false);
                        // Update information in via_users.
                        $participant = new stdClass();
                        $participant->id = $u->id;
                        $participant->timemodified = time();
                        $participant->viauserid = $viauserdata['UserID'];

                        $DB->update_record("via_users", $participant);
                    }
                    // We add the user into all the activities he/she is suppoed to be in.
                    $viaparticipant = $DB->get_records_sql('SELECT * FROM mdl_via_participants
                                                            WHERE userid = ' .$userid .'
                                                            AND activityid <> '. $viaid);
                    foreach ($viaparticipant as $participant) {
                        if ($participant) {
                            $type = $participant->participanttype;
                        } else {
                            $via = $DB->get_record('via', array('id' => $participant->activityid));
                            $type = get_user_type($muser->id, $via->course, $via->noparticipants);
                        }

                        try {
                            via_add_participant($muser->id, $participant->activityid, $type, null, null, 1);
                        } catch (Exception $e) {
                            echo '<div class="alert alert-block alert-info">'.
                            get_string('error_user', 'via', $muser->firstname.' '.$muser->lastname).'</div>';
                        }

                    }
                }
            }
        }
    }

    if ($update) { // Update only if given a higher role then the other (if there is) OR if user was deleted.
        $sub->userid  = $userid;
        $sub->activityid = $viaid;
        $viaactivity = $DB->get_record('via', array('id' => $viaid));
        $enrolid = get_enrolid($viaactivity->course, $userid);
        if ($enrolid) {
            $sub->enrolid = $enrolid;
        } else {
            // We need this 0 later to keep the user not enrolled in the coruse not to be deleted when synching users.
            $sub->enrolid = 0;
        }
        $sub->viaactivityid = $viaactivity->viaactivityid;
        $sub->participanttype = $type;
        $sub->timemodified = time();
        $sub->timesynched = null;

        if (!$confirmationstatus) {
            $sub->confirmationstatus = 1;// Not confirmed!
        } else {
            $sub->confirmationstatus = $confirmationstatus;
        }

        try {
            $response = $api->add_user_activity($sub);
            $sub->timesynched = time();
            if ($sub->id) {
                $added = $DB->update_record("via_participants", $sub);
            } else {
                $added = $DB->insert_record("via_participants", $sub);
            }

        } catch (Exception $e) {

            $added = false;
        }
    }

    return $added;
}

/**
 * Adds users to the participant list
 * @param array $users array of users
 * @param integer $viaid the via activity ID
 * @param integer $type the user type (presentator, animator or partcipant)
 * @return bool Success/Fail.
 */
function via_add_participants($users, $viaid, $type) {
    global $DB;

    foreach ($users as $user) {
        try {

            $result = via_add_participant($user->id, $viaid, $type);

        } catch (Exception $e) {

            echo get_string("error:".$e->getMessage(), "via");

        }
    }

    return $result;
}

/**
 * update participants list on moodle with via infos
 *
 * @param object $via the via
 * @param integer $userid the user id if updating only one participant
 * @return bool Success/Fail.
 */
function via_update_participants_list($via, $userid=null) {

    $result = true;

    $api = new mod_via_api();

    try {
        $response = $api->get_userslist_activity($via->viaactivityid);
    } catch (Exception $e) {
        notify(get_string("error:".$e->getMessage(), "via"));
        $result = false;
    }

    if (count($response) > 1 && $result) {
        if (!$userid) {
            add_new_via_participants($response, $via->id);
            remove_old_via_participants($response, $via->id);
        } else {
            return add_new_via_participants($response, $via->id, $userid);
        }
    } else {
        $result = false;
    }

    return $result;

}

/**
 * get participants list on via 
 *
 * @param object $via the via
 * @return list.
 * 
 */
function get_via_participants_list($via) {

    $result = true;

    $api = new mod_via_api();

    try {
        $result = $api->get_userslist_activity($via->viaactivityid);
    } catch (Exception $e) {
        notify(get_string("error:".$e->getMessage(), "via"));
        $result = false;
    }

    return $result;
}

/**
 * adding new participants that were added directly on via
 *
 * @param object $response list of users from Via
 * @param integer $activityid the via id
 * @param integer $userid user id if adding a specific user
 * @return bool Success/Fail.
 */
function add_new_via_participants($response, $activityid, $userid=null) {

    foreach ($response as $user) {
        if (count($user) > 0) {
            if (isset($user['UserID'])) {
                if ($userid && $user['UserID'] == $userid) {
                    add_new_via_participant($user, $activityid);
                    return true;
                } else if (!$userid) {
                    add_new_via_participant($user, $activityid);
                }
            }
        }
    }
    if ($userid) {
        return false;
    } else {
        return true;
    }
}

/**
 * adding a new participant that was added directly on via
 *
 * @param object $user user data from via
 * @param integer $activityid the via id
 */
function add_new_via_participant($user, $activityid) {
    global $CFG, $DB;

    if ($u = $DB->get_record('via_users', array('viauserid' => $user['UserID']))) {
        // Verifies if user is already enroled in this activity on moodle!
        if ($participants = $DB->get_records_sql("SELECT * FROM mdl_via_participants
                                                  WHERE userid={$u->userid} AND activityid=$activityid")) {
            // Already enrolled!
            foreach ($participants as $participant) {

                $modiftype = false;
                $modifstatus = false;

                if ($participant->participanttype != $user['ParticipantType'] && $modiftype != "ok") {
                    // Enroled but with a different role.
                    $participant->participanttype = $user['ParticipantType'];
                    $modiftype = true;
                } else if ($participant->participanttype == $user['ParticipantType'] && $modiftype) {
                    // Role is ok, user is enroled with more than one role.
                    $modiftype = "ok";
                    $participant->participanttype = $user['ParticipantType'];
                }
                if ($participant->confirmationstatus != $user['ConfirmationStatus'] && $user['ParticipantType'] == 1) {
                    // Need to update confirmation status of student.
                    $participant->confirmationstatus = $user['ConfirmationStatus'];
                    $modifstatus = true;
                }

                if ($modifstatus || $modiftype == true) {
                    $DB->update_record("via_participants", $participant);
                }
            }
        } else {
            // Only if the user exists in via_users + is enrolled in the course!
            $isenrolled = $DB->get_records_sql('SELECT e.id FROM mdl_via_participants vp
                            left join mdl_via v ON vp.activityid = v.id
                            left join mdl_enrol e ON v.course = e.courseid
                            left join mdl_user_enrolments ue ON ue.enrolid = e.id AND ue.userid = vp.userid
                            where vp.userid = '.$u->userid.' and vp.activityid = '.$activityid.' AND ue.id is not null');
            if ($DB->get_record('via_users', array('viauserid' => $u->viauserid)) && $isenrolled != null) {
                // Not enrolled in moodle via activity, so adding him.
                $newparticipant = new stdClass();
                $newparticipant->activityid = $activityid;
                $newparticipant->userid = $u->userid;
                $newparticipant->participanttype = $user['ParticipantType'];
                $newparticipant->confirmationstatus = $user['ConfirmationStatus'];
                $newparticipant->enrolid = $isenrolled;
                $newparticipant->timemodified = time();
                $added = $DB->insert_record("via_participants", $newparticipant);
                echo $added;
            }
        }
    } else {
        if ($newuser = $DB->get_record('user',
            array('email' => $user['Email']))) {// Should we vaildate that the user exists in via_users?
            // User is not in via_users table on Moodle DB. So adding him!
            $newviauser->userid = $newuser->id;
            $newviauser->viauserid = $user['UserID'];
            $newviauser->username = $user['Email'];
            if ($user['ParticipantType'] > 1) {
                $newviauser->usertype = 3;
            } else {
                $newviauser->usertype = 2;
            }
                $newviauser->timecreated = time();
            $DB->insert_record("via_users", $newviauser);

            $newparticipant->activityid = $activityid;
            $newparticipant->enrolid = $enrolid;
            $newparticipant->userid = $newuser->id;
            $newparticipant->participanttype = $user['ParticipantType'];
            $newparticipant->confirmationstatus = $user['ConfirmationStatus'];
            $newparticipant->timemodified = time();
            $DB->insert_record("via_participants", $newparticipant);
        }
        // User is not a moodle user at all.
    }
}

/**
 * removing participants that were deleted directly on via
 *
 * @param object $viaparticipants list of participants
 * @param integer $activityid the via id
 */
function remove_old_via_participants($viaparticipants, $activityid) {
    global $CFG, $DB;

    if ($moodleparticipants = $DB->get_records_sql("SELECT * FROM mdl_via_participants WHERE activityid=$activityid")) {

        foreach ($moodleparticipants as $mparticipant) {
            $removeuser = true;
            // Get userid on via!
            if ($viauserid = $DB->get_record('via_users', array('userid' => $mparticipant->userid))) {
                foreach ($viaparticipants as $vparticipant) {
                    if (count($vparticipant) > 0) {
                        if (isset($vparticipant['UserID']) && $vparticipant['UserID'] == $viauserid->viauserid) {
                            $removeuser = false;
                            continue;
                        }
                    }
                }
            }
            if ($removeuser) {
                // User wa unenroled on via, so remove from participants on moodle.
                $DB->delete_records_select("via_participants", "userid={$mparticipant->userid} AND activityid=$activityid");
            }
        }

    }
}

/**
 * Removes user from the participant list
 *
 * @param integer $userid the user id 
 * @param integer $viaid the via id
 * @param integer $type role we have to remove of user on via (if has more than 1 role for activity)
 * @return bool Success/Fail
 */
function via_remove_participant($userid, $viaid, $type=null) {
    global $CFG, $DB;

    $via = $DB->get_record('via', array('id' => $viaid));

    // Now we update it on via!
    $api = new mod_via_api();

    try {

        $response = $api->removeuser_activity($via->viaactivityid, $userid);
        return $DB->delete_records('via_participants', array('userid' => $userid, 'activityid' => $viaid));

    } catch (Exception $e) {
        notify(get_string("error:".$e->getMessage(), "via"));
        $result = false;
    }

}

/**
 * add all course's users in this activity 
 * (called when activity is created and, if automatic enrol is activated, when activity is updated)
 *
 * @param object $via the via 
 * @param integer $idactivity the via id
 */

function add_participants_to_activity($via, $idactivity) {
    global $DB, $COURSE;

    $cm = get_coursemodule_from_instance("via", $idactivity, $via->course, $fields = '', $sort = '', $groupid = '');
    $context = context_module::instance($cm->id);

    // Get all teachers and editing teachers!
    $allteachers = get_users_by_capability($context, 'mod/via:manage', $fields, $sort, '', '', $groupid, '', false, true);
    $creatorinfo = $DB->get_record('user', array('id' => $via->creator));
    $creator[$creatorinfo->id] = $creatorinfo;

    if ($via->groupingid) {
        // If grouping, only adding the grouping members!
        $groupusers = groups_get_grouping_members($via->groupingid, $fields = 'u.id');
        // All users except teachers.
        $users = array_diff_key($groupusers, $allteachers);
        // All participants, except creator.
        $participants = array_diff_key($users, $creator);
        via_add_participants($participants, $idactivity, 1);
    } else {
        $allusers = get_users_by_capability($context, 'mod/via:view', $fields, $sort, '', '', $groupid, '', false, true);
        $users = array_diff_key($allusers, $allteachers);
        $participants = array_diff_key($users, $creator);
        via_add_participants($participants, $idactivity, 1);
    }

    // Remove the creator from the teachers list. The creator has an other role (presentator)!
    $teachers = array_diff_key($allteachers, $creator);
    via_add_participants($teachers, $idactivity, 3);
}

/**
 *  Creates a random password
 *
 * @param string : a random password
 */
function via_create_user_password() {
        $password = via_get_random_letter().
                    via_get_random_letter().
                    via_get_random_letter().
                    rand(2, 9).rand(2, 9).
                    via_get_random_letter().
                    via_get_random_letter();
    return $password;
}

/**
 *  Select a random letter for password
 *  We do not want i, l or 0 because it can be confused with 1 or 0.
 *
 * @param string : a random letter
 */
function via_get_random_letter() {
        $lettres = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'j', 'k', 'm',
                        'n', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');
        return $lettres[rand(0, count($lettres) - 1)];
}

/**
 *  Find out if user is a presentaor for the activity
 *
 * @param integer $userid the user id
 * @param integer $activityid the via id
 * @return bool  presentator/not presentator
 */
function via_get_is_user_presentator($userid, $activityid) {
    global $DB;

        $presentator = $DB->count_records('via_participants',
                                          array('userid' => $userid, 'activityid' => $activityid, 'participanttype' => 2));
    if ($presentator >= 1) {
        return true;
    }

    return false;
}

/**
 *  Verifies what a user can view for an activity, depending of his role and
 *  if activity is done or not. If there's reviews ect.
 *
 * @param object $via the via 
 * @return integer what user can view
 */
function via_access_activity($via) {
    global $USER, $CFG, $DB;

    $participant = $DB->get_record('via_participants', array('userid' => $USER->id, 'activityid' => $via->id));
    $userid = $DB->get_record('via_users', array('userid' => $USER->id));

    if (!$participant) {
        // If user cant access this page, he should be able to access the activity.

        // If the user is an admin, he can access the activiy even though he is not added.
        if (has_capability('moodle/site:approvecourse',  context_system::instance() )) {
            return 7;
        }

        if ($via->enroltype == 0) {// If automatic enrol!

            // Verifying if user was enrolled directly on via, if so, we enrol him.
            if (!$userid || !via_update_participants_list($via, $userid->viauserid)
                && !has_capability('moodle/site:approvecourse',  context_system::instance() )) {
                if (!$userid) {
                    $viauserid = null;
                } else {
                    $viauserid = $userid->viauserid;
                }
                $type = get_user_type($USER->id, $via->course, $via->noparticipants);
                try {
                    $added = via_add_participant($USER->id, $via->id, $type, null, 1);
                    if ($added === 'presenter') {
                        echo "<div style='text-align:center; margin-top:0;' class='error'>
                        <h3>". get_string('userispresentor', 'via') ."</h3></div>";
                    }
                } catch (Exception $e) {
                    echo '<div class="alert alert-block alert-info">'.
                    get_string('error_user', 'via', $muser->firstname.' '.$muser->lastname).'</div>';
                }

            } else {
                // We tell him is doesn't have access to this activity and to contact his teacher if there's a problem.
                return 6;
            }
        } else {
            // Verifying if user was enrol directly on via.
            // If not enrol, we tell him is doesn't have access to this activity and to contact his teacher if there's a problem.
            if (!$userid || !via_update_participants_list($via, $userid->viauserid)) {
                return 6;
            }
        }
    }

    if ((time() >= ($via->datebegin - (30 * 60))
            && time() <= ($via->datebegin + ($via->duration * 60) + 60))
            || $via->activitytype == 2) {
            // If activity is hapening right now, show link to the activity.
            return 1;
    } else if (time() < $via->datebegin) {
            // Activity hasn't started yet.

        if ($participant->participanttype > 1) {
            // If participant, user can't access activity.
            return 2;
        } else {
            // If participant is animator or presentator, show link to prepare activity.
            return 3;
        }

    } else {
        // Activity is done. Must verify if there are any recordings of if.
        return 5;
    }
}

function get_participants_list_table($via, $context) {
    global $DB, $CFG;

    $participantslist = get_via_participants_list($via);

    if (get_config('via', 'via_participantmustconfirm') && $via->needconfirmation) {
        $conf = '<th>'. get_string("confirmationstatus", "via").'</th>';
    } else {
        $conf = '';
    }

    $userlist = "<h2 class='main'>".get_string("viausers", "via")."</h2>";
    $userlist .= "<table cellpadding='2' cellspacing='0' class='generaltable boxaligncenter' style='width: 99%;'>";
    $userlist .= '<tr><th style="text-align:left" >'.
                  get_string("role", "via").'</th><th style="text-align:left" >'. get_string("lastname").', '.
                  get_string("firstname").'</th><th style="text-align:left" >'.
                  get_string("email").'</th>'.$conf.'<th style="text-align:center">'. get_string("config", "via").'</th></tr>';

    $confstatus = '';

    if ($participantslist) {
        foreach ($participantslist as $key => $value) {
            if (strpos($key, 'attr') !== false ) {

                if ($value['ParticipantType'] == "1") {
                    $role = '<img src="' . $CFG->wwwroot . '/mod/via/pix/participant.png" width="25" height="25"
                              alt="participant" style="vertical-align: bottom;" /> ' . get_string("participant", "via");
                } else if ($value['ParticipantType'] == "2") {
                    $role = '<img src="' . $CFG->wwwroot . '/mod/via/pix/presentor.png" width="25" height="25"
                              alt="presentor" style="vertical-align: bottom;" /> ' . get_string("presentator", "via");
                } else {
                    $role = '<img src="' . $CFG->wwwroot . '/mod/via/pix/animator.png" width="25" height="25"
                              alt="animator" style="vertical-align: bottom;" /> ' . get_string("animator", "via");
                }

                if ($value['SetupState'] == "0") {
                    $state = '<span style="color:#72ba12;" >'.get_string("finish", "via"). '</span>';
                } else if ($value['SetupState'] == "1") {
                    $state = '<span style="color:#dab316;" >'. get_string("incomplete", "via"). '</span>';
                } else {
                    $state = '<span style="color:#da1616;" >'.get_string("neverbegin", "via"). '</span>';
                }

                if ($conf != '') {
                    $confstatus = get_confirmationstatus($value["ConfirmationStatus"]);
                }

                $vuser = $DB->get_record_sql('SELECT * FROM mdl_via_users vu
                                            LEFT JOIN mdl_user u ON u.id = vu.userid
                                            WHERE vu.viauserid = \''.$value['UserID'] .'\' AND u.deleted = 0');
                if (has_capability('mod/via:manage', $context)) {// Students can not see the user profiles.
                    if ($vuser) {
                        $userinfo = '<a href="'. $CFG->wwwroot .'/user/profile.php?id='.$vuser->userid.'">'.
                                    $value['LastName'].', '. $value['FirstName'].'</a>';
                    }
                } else {
                    $userinfo = $value['LastName'].', '. $value['FirstName'];
                }

                if ($vuser) {// Only add if the user is in via_users table.
                    $userlist .= '<tr><td>'. $role .'</td><td>'.$userinfo.'</td><td>'.
                    $value['Email'].'</td>'.$confstatus.'<td style="text-align:center">'. $state .'</td></tr>';
                }
            }
        }
    } else {
        $presenter = $DB->get_record_sql('SELECT u.*, vp.* FROM mdl_via_participants vp
                                        LEFT JOIN mdl_user u ON vp.userid = u.id
                                        WHERE activityid='.$via->id. ' AND vp.participanttype=2 ');
        if ($presenter) {
            $role = '<img src="' . $CFG->wwwroot . '/mod/via/pix/presentor.png" width="25" height="25"
                    alt="presentor" style="vertical-align: bottom;" /> ' . get_string("presentator", "via");
            if ($conf != '') {
                $confstatus = get_confirmationstatus($presenter->confirmationstatus);
            }
            if (has_capability('mod/via:manage', $context)) {// Students can not see the user profiles.
                $userinfo = '<a href="'. $CFG->wwwroot .'/user/profile.php?id='.$presenter->id.'">'.
                            $presenter->lastname.', '. $presenter->firstname.'</a>';
            } else {
                $userinfo = $presenter->lastname.', '. $presenter->firstname;
            }

                $userlist .= '<tr><td>'. $role .'</td><td>'.$userinfo.'</td><td>'. $presenter->email.'</td>'.
                            $confstatus .'<td style="text-align:center">--</td></tr>';
        } else {
            $userlist .= '<tr><td class="error">'.get_string('nousers', 'via').'</td></tr>';
        }
    }

    $userlist .= '</table>';

    return $userlist;
}

function get_confirmationstatus($status) {
    global $CFG;

    if ($status == 1) {
        $confirmimg = "waiting_confirm.gif";
        $confirmtitle = get_string("waitingconfirm", "via");
    } else if ($status == 2) {
        $confirmimg = "confirm.gif";
        $confirmtitle = get_string("confirmed", "via");
    } else {
        $confirmimg = "refuse.gif";
        $confirmtitle = get_string("refused", "via");
    }

    return "<td style='text-align:center'><img src='" . $CFG->wwwroot . "/mod/via/pix/".$confirmimg."' width='16'
    height='16' alt='".$confirmtitle . "' title='".$confirmtitle . "' align='absmiddle'/></td>";

}

/**
 *  Get the available profiles for the company on via
 *
 * @return obejct list of profiles
 */
function via_get_list_profils() {
    $result = true;

    $api = new mod_via_api();

    try {
        $response = $api->list_profils();
    } catch (Exception $e) {
        $result = false;
        notify(get_string("error:".$e->getMessage(), "via"));
    }
    $profil = array();
    foreach ($response['Profil'] as $info) {
        if (isset($info['ProfilID'])) {
            $profil[$info['ProfilID']] = $info['ProfilName'];
        }

    }
    return $profil;
}

/**
 *  Get all the playbacks for an acitivity
 *
 * @param object $via the via object
 * @return obejct list of playbacks
 */
function via_get_all_playbacks($via) {

    $result = true;
    $playbacksmoodle = false;
    $api = new mod_via_api();

    try {
        $playbacks = $api->list_playback($via);
    } catch (Exception $e) {
        $result = false;
        notify(get_string("error:".$e->getMessage(), "via"));
    }

    if (isset($playbacks['Playback']) && count($playbacks) == 1) {
        $aplaybacks = $playbacks['Playback'];
    } else {
        $aplaybacks = $playbacks;
    }
    if (gettype($aplaybacks == "array") && count($aplaybacks) > 1) {

        foreach ($aplaybacks as $playback) {
            if (gettype($playback) == "array") {

                if (isset($playback['BreackOutPlaybackList'])) {
                    foreach ($playback['BreackOutPlaybackList'] as $breakout) {
                        if (gettype($breakout) == "array") {
                            if (isset($breakout['PlaybackID'])) {
                                $playbacksmoodle[$breakout['PlaybackID']] = new stdClass();
                                $playbacksmoodle[$breakout['PlaybackID']]->title = $breakout['Title'];
                                $playbacksmoodle[$breakout['PlaybackID']]->duration = $breakout['Duration'];
                                $playbacksmoodle[$breakout['PlaybackID']]->creationdate = $breakout['CreationDate'];
                                $playbacksmoodle[$breakout['PlaybackID']]->ispublic = $breakout['IsPublic'];
                                $playbacksmoodle[$breakout['PlaybackID']]->playbackrefid = $breakout['PlaybackRefID'];
                            } else {
                                foreach ($breakout as $bkout) {
                                    if (gettype($bkout) == "array") {
                                        $playbacksmoodle[$bkout['PlaybackID']] = new stdClass();
                                        $playbacksmoodle[$bkout['PlaybackID']]->title = $bkout['Title'];
                                        $playbacksmoodle[$bkout['PlaybackID']]->duration = $bkout['Duration'];
                                        $playbacksmoodle[$bkout['PlaybackID']]->creationdate = $bkout['CreationDate'];
                                        $playbacksmoodle[$bkout['PlaybackID']]->ispublic = $bkout['IsPublic'];
                                        $playbacksmoodle[$bkout['PlaybackID']]->playbackrefid = $bkout['PlaybackRefID'];
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $playbacksmoodle[$playback['PlaybackID']] = new stdClass();
                    $playbacksmoodle[$playback['PlaybackID']]->title = $playback['Title'];
                    $playbacksmoodle[$playback['PlaybackID']]->duration = $playback['Duration'];
                    $playbacksmoodle[$playback['PlaybackID']]->creationdate = $playback['CreationDate'];
                    $playbacksmoodle[$playback['PlaybackID']]->ispublic = $playback['IsPublic'];
                }
            }
        }
    }

    return $playbacksmoodle;

}


/**
 *  Get the remindertime for an activity
 *
 * @param object $via the via object
 * @return object containing the remindertime
 */
function via_get_remindertime($via) {
    $remindertime = $via->datebegin - $via->remindertime;
    return $remindertime;
}

/**
 *  Get the remindertime integer for svi
 *
 * @param integer $remindertime time in seconds for the remindertime
 * @return integer the remindertime integer for via
 */
function via_get_remindertime_svi($remindertime) {
    switch($remindertime) {
        case 0:
            $reminder = 0;
            break;
        case 3600:
            $reminder = 1;
            break;
        case 7200:
            $reminder = 2;
            break;
        case 86400:
            $reminder = 3;
            break;
        case 172800:
            $reminder = 4;
            break;
        case 604800:
            $reminder = 5;
            break;
        default:
            $reminder = 0;
            break;
    }

    return $reminder;
}

/**
 * Get the remindertime integer from svi for moodle
 *
 * @param integer $remindertime remindertime integer from via
 * @return integer the time in seconds for the remindertime for moodle
 */
function via_get_remindertime_from_svi($remindertime) {
    switch($remindertime) {
        case 0:
            $reminder = 0;
            break;
        case 1:
            $reminder = 3600;
            break;
        case 2:
            $reminder = 7200;
            break;
        case 3:
            $reminder = 86400;
            break;
        case 4:
            $reminder = 172800;
            break;
        case 5:
            $reminder = 604800;
            break;
        default:
            $reminder = 0;
            break;
    }

    return $reminder;
}

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
        if (($via->datebegin + ($via->duration * 60) >= $now && ($via->datebegin - (30 * 60)) < $now) || $via->activitytype == 2) {
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
 * Create grade item for given activity
 *
 * @param object $via object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
/*function via_grade_item_update($via, $grades = null) {
    global $CFG, $DB;

    if (!function_exists('grade_update')) {// Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($via->courseid)) {
        $via->courseid = $via->course;
    }
    if (!isset($via->id)) {
        $via->id = $via->instance;
    }

    if (array_key_exists('cmidnumber', $via)) {// It may not be always present.
        $params = array('itemname' => $via->name, 'idnumber' => $via->cmidnumber);
    } else {
        $params = array('itemname' => $via->name);
    }

    if ($via->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $via->grade;
        $params['grademin']  = 0;

    } else if ($via->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$via->grade;

    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT;// Allow text comments only.
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/via', $via->courseid, 'mod', 'via', $via->id, 0, $grades, $params);
}*/

/**
 * Delete grade item for given activity
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
 * updates remindertime on VIA server if parameter via_moodleemailnotification is change
 * Updates participant list on Moodle, if changes directly on via server
 * Finds all invitation and reminders that have to be sent and send them
 * @return bool $result sucess/fail
 */
function via_cron() {
    global $CFG;
    $result = true;
    echo 'via';
    echo "\n";
    $result = via_change_reminder_sender() && $result;

    if (get_config('via', 'via_moodleemailnotification')) {
        echo "\n";
        $result = via_send_reminders() && $result;
    }

    if (get_config('via', 'via_sendinvitation')) {
        echo "\n";
        $result = via_send_invitations() && $result;
    }

    echo "\n";
    $result = add_enrolids() && $result;

    echo "synching users \n";
    $result = synch_via_users() && $result;

    echo "synching participants \n";
    $result = synch_participants() && $result;

    if (get_config('via', 'via_categories')) {
        echo "check categories \n";
        $result = check_categories() && $result;
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
function add_enrolids() {
    global $DB;
    $result = true;
    $participants = $DB->get_records('via_participants', array('enrolid' => null, 'timesynched' => null));
    if ($participants) {
        foreach ($participants as $participant) {
            $enrolid = $DB->get_record_sql('SELECT e.id FROM mdl_via_participants vp
                                left join mdl_via v ON vp.activityid = v.id
                                left join mdl_enrol e ON v.course = e.courseid
                                left join mdl_user_enrolments ue ON ue.enrolid = e.id AND ue.userid = vp.userid
                                where vp.userid = '.$participant->userid.' and
                                vp.activityid = '.$participant->activityid.' AND ue.id is not null');
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
function check_categories() {
    global $DB;

    $result = true;
    $via = array();
    $existing = array();

    $catgeories = get_via_categories();
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
        $from = get_presenter($r->id);

        if (!$muser) {
            echo "    User with ID {$r->userid} doesn't exist?!\n";
            $result = false;
            continue;
        }

        if (get_config('via', 'via_moodleemailnotification')) {
            // Send reminder with Moodle.
            $result = send_moodle_reminders($r, $muser, $from);
        } else {
            // Send reminder with VIA.
            $api = new mod_via_api();

            try {
                $response = $api->sendinvitation($user->id, $r->viaactivityid);
            } catch (Exception $e) {
                echo "   SVI could not send email to <".$muser->email.">  (<".$e.">)\n";
                echo get_string("error:".$e->getMessage(), "via")."\n";
                $result = false;
                continue;
            }

            echo "    Sent an email reminder to ".$muser->firstname . $muser->lastname . $muser->email. ">.\n";

            $record->id = $r->id;
            $record->mailed = 1;
            if (!$DB->update_record('via', $record)) {
                // If this fails, stop everything to avoid sending a bunch of dupe emails.
                echo "    Could not update via table!\n";
                $result = false;
                continue;
            }
        }
    }

    return $result;
}

/**
 * gets all activity that need reminders to ben sent
 *
 * @return object $via object
 */
function via_get_reminders() {
    global $CFG, $DB;
    $now = time();

    $sql = "SELECT p.id, p.userid, p.activityid, v.name, v.datebegin, v.duration, v.viaactivityid, v.course, v.activitytype ".
        "FROM mdl_via_participants p ".
        "INNER JOIN mdl_via v ON p.activityid = v.id ".
        "WHERE v.remindertime > 0 AND ($now  >= (v.datebegin - v.remindertime)) AND v.mailed = 0";

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
function send_moodle_reminders($r, $muser, $from) {
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
        $cm->id = 0;
    }

    $a->config = $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=7&viaid='.$r->activityid.'&courseid='.$r->course;
    $a->assist = $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=6&viaid='.$r->activityid.'&courseid='.$r->course;
    $a->activitylink = $CFG->wwwroot.'/mod/via/view.php?id='.$cm->id;
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
function via_send_invitations() {
    global $CFG, $DB;

    $invitations = via_get_invitations();
    if (!$invitations) {
        echo "    No email invitations need to be sent.\n";
        return true;
    }

    echo '    ', count($invitations), ' email invitation', (1 < count($invitations) ? 's have' : 'has'), " to be sent.\n";

    // If anything fails, we'll keep going but we'll return false at the end.
    $result = true;

    foreach ($invitations as $i) {

        $muser = $DB->get_record('user', array('id' => $i->userid));
        $from = get_presenter($i->activityid);

        if (!$muser) {
            echo "User with ID {$i->userid} doesn't exist?!\n";
            $result = false;
            continue;
        }

        if (get_config('via', 'via_moodleemailnotification')) {
            // Send reminder with Moodle.
            $result = send_moodle_invitations($i, $muser, $from);
        } else {
            // Send reminder with SVI.
            $api = new mod_via_api();
            try {
                $response = $api->sendinvitation($muser->id, $i->viaactivityid, $i->invitemsg);
            } catch (Exception $e) {
                echo "   SVI could not send email to <{$muser->email}>  (<{$e}>)\n";
                echo get_string("error:".$e->getMessage(), "via")."\n";
                $result = false;
                continue;
            }

            echo "Sent an email invitations to" .$muser->firstname . " " . $muser->lastname . " " . $muser->email ."\n";

            $record->id = $i->activityid;
            $record->sendinvite = 0;
            $record->invitemsg = null;
            if (!$DB->update_record('via', $record)) {
                // If this fails, stop everything to avoid sending a bunch of dupe emails.
                echo "    Could not update via table!\n";
                $result = false;
                continue;
            }

        }

    }

    return $result;
}

function  get_presenter($activityid) {
    global $DB;

        $presenter = $DB->get_record('via_participants', array('activityid' => $activityid, 'participanttype' => 2));
    if ($presenter) {
        $from = $DB->get_record('user', array('id' => $presenter->userid));
    } else {
        $from = get_admin();
    }

    return $from;
}

/**
 * Sends invitations with moodle
 *
 * @param object $i via object
 * @param object $user user to send reminder
 * @return bool $result sucess/fail
 */
function send_moodle_invitations($i, $user, $from) {
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
        $cm->id = 0;
    }

    $a->config = $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=7&viaid='.$i->activityid.'&courseid='.$i->course;
    $a->assist = $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=6&viaid='.$i->activityid.'&courseid='.$i->course;
    $a->activitylink = $CFG->wwwroot.'/mod/via/view.php?id='.$cm->id;
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

    echo "    Sent an email invitations to " . $muser->firstname ." " . $muser->lastname . " " .$muser->email. "\n";

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

function via_make_invitation_reminder_mail_html($courseid, $via, $muser, $reminder=false) {
    global $CFG, $DB;

    if ($muser->mailformat != 1) {// Needs to be HTML.
        return '';
    }

    $strvia = get_string('modulename', 'via');

    $posthtml = '<head></head>';
    $posthtml .= "\n<body id=\"email\">\n\n";

    $coursename = $DB->get_record('course', array('id' => $courseid));

    if (! $cm = get_coursemodule_from_instance("via", $via->activityid, $courseid)) {
        $cm->id = 0;
    }
    $a = new stdClass();
    $a->username = fullname($muser);
    $a->title = $via->name;
    $a->datebegin = userdate($via->datebegin, '%A %d %B %Y');
    $a->hourbegin = userdate($via->datebegin, '%H:%M');
    $a->hourend = userdate($via->datebegin + ($via->duration * 60), '%H:%M');

    if (!empty($via->invitemsg) && !$reminder) {
        $a->invitemsg = nl2br($via->invitemsg);
    } else {
        $a->invitemsg = "";
    }

    $posthtml .= '<div class="navbar">';
    $posthtml .= '<a target="_blank" href="'.$CFG->wwwroot.'/course/view.php?id='.$courseid.'">'.$coursename->shortname.'</a>';
    $posthtml .= ' &raquo; <a target="_blank" href="'.$CFG->wwwroot.'/mod/via/index.php?id='.$courseid.'">'.$strvia.'</a> &raquo; ';
    $posthtml .= '<a target="_blank" href="'.$CFG->wwwroot.'/mod/via/view.php?id='.$cm->id.'">'.$via->name.'</a>';
    $posthtml .= '</div>';

    $posthtml .= '<table border="0" cellpadding="3" cellspacing="0" class="forumpost">';

    $posthtml .= '<tr class="header"><td width="35" valign="top" class="picture left">';

    $posthtml .= '</td>';

    $posthtml .= '<td class="topic starter">';

    $b = new stdClass();
    $b->title = $a->title;

    if (!$reminder) {
        $posthtml .= '<div class="subject">'.get_string("inviteemailsubject", "via", $b).'</div>';
    } else {
        $posthtml .= '<div class="subject">'.get_string("reminderemailsubject", "via", $b).'</div>';
    }

    $posthtml .= '<div class="author">'.userdate(time()).'</div>';

    $posthtml .= '</td></tr>';

    $posthtml .= '<tr><td class="left side" valign="top">';
    $posthtml .= '&nbsp;';

    $posthtml .= '</td><td class="content">';

    if ($via->activitytype == 2) {
        $posthtml .= get_string("inviteemailhtmlpermanent", "via", $a);
    } else {
        $posthtml .= get_string("inviteemailhtml", "via", $a);
    }

    $posthtml .= "<div style='margin:20px;'>";

    $posthtml .= "<div style='border:1px solid #999; margin-top:10px; padding:10px;'>";

    $posthtml .= "<span style='font-size:1.2em; font-weight:bold;'>".get_string("invitepreparationhtml", "via")."</span>";

    $posthtml .= "<div style='text-align:center'>";

    $posthtml .= "<a href='" . $CFG->wwwroot ."/mod/via/view.assistant.php?redirect=7&viaid=". $via->activityid .
    "&courseid=". $via->course ."' style='background:#5c707c; padding:8px 10px; color:#fff;
    text-decoration:none; margin-right:20px' ><img src='" . $CFG->wwwroot ."/mod/via/pix/config.png' align='absmiddle'
    hspace='5' height='27px' width='27px'>". get_string("configassist", "via")."</a>";

    $posthtml .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

    $posthtml .= "<a href='" . $CFG->wwwroot ."/mod/via/view.assistant.php?redirect=6&viaid=". $via->activityid .
    "&courseid=". $via->course ."' style='background:#5c707c; padding:8px 10px; color:#fff; text-decoration:none;' >
    <img src='" . $CFG->wwwroot . "/mod/via/pix/assistance.png' align='absmiddle' hspace='5' height='27px' width='27px'>".
    get_string("technicalassist", "via")."</a>";

    $posthtml .= "</div>";

    $posthtml .= "</div>";

    $posthtml .= "<div style='border:1px solid #999; margin-top:10px; padding:10px;'>";

    $posthtml .= "<span style='font-size:1.2em; font-weight:bold;'>".get_string("invitewebaccesshtml", "via")."</span>
    <br/><br/>".get_string("inviteclicktoaccesshtml", "via")."";

    $posthtml .= "<div style='text-align:center'>";

    $posthtml .= "<a style='background:#6ab605; padding:8px 10px; color:#fff; text-decoration:none;'
    href='".$CFG->wwwroot."/mod/via/view.php?id=".$cm->id."' >
    <img src='" . $CFG->wwwroot ."/mod/via/pix/access.png' align='absmiddle' hspace='5' height='27px' width='27px'>".
    get_string("gotoactivity", "via")."</a>";

    $posthtml .= "<p><br/>". $CFG->wwwroot."/mod/via/view.php?id=".$cm->id ."</p>";

    $posthtml .= "</div>";

    $posthtml .= "</div>";

    $posthtml .= "<div style='border:1px solid #999; margin-top:10px; font-size:0.9em; padding:10px;'>";

    $posthtml .= get_string("invitewarninghtml", "via");

    $posthtml .= "</div>";

    $posthtml .= "</div>";

    $posthtml .= '</td></tr></table>'."\n\n";

    $posthtml .= '</body>';

    return $posthtml;
}

/**
 * gets all activity that need invitations to ben sent
 *
 * @return object $via object
 */
function via_get_invitations() {
    global $CFG, $DB;
    $now = time();

    $sql = "SELECT p.id, p.userid, p.activityid, v.name, v.course, v.datebegin,
            v.duration, v.viaactivityid, v.invitemsg, v.activitytype
            FROM mdl_via_participants p
            INNER JOIN mdl_via v ON p.activityid = v.id
            WHERE v.sendinvite = 1";

    $invitations = $DB->get_records_sql($sql);

    return $invitations;
}

/**
 * Change remindertime on VIA server if parameter via_moodleemailnotification
 * is change. If Moodle sends email, every activity on VIA server needs to have
 * 0 as the remindertime, so VIA won't send emails. Else, if VIA is the sender
 * change the remindertime back.
 *
 * @return object $via object
 */
function via_change_reminder_sender() {
    global $CFG, $DB;

    $result = true;
    echo "change reminder sender \n";
    if ($vias = $DB->get_records_sql("SELECT * FROM mdl_via
                                    WHERE remindertime != 0 AND mailed=0
                                    AND moodleismailer != ". get_config('via', 'via_moodleemailnotification') ." ")) {

        foreach ($vias as $via) {

            $api = new mod_via_api();
            try {
                $response = $api->activity_get($via);

                if ($response['ReminderTime'] == 0 && !get_config('via', 'via_moodleemailnotification')) {
                    echo "Changer le reminder sur VIA pour que VIA envoie les emails\n";

                    $update = $api->activity_edit($via);
                    $via->invitemsg = $via->invitemsg;
                    $via->moodleismailer = get_config('via', 'via_moodleemailnotification');
                    $DB->update_record("via", $via);

                } else if ($response['ReminderTime'] != 0 && get_config('via', 'via_moodleemailnotification')) {
                    echo "Enlever le reminder sur via, moodle envoie les emails\n";

                    $update = $api->activity_edit($via);
                    $via->invitemsg = $via->invitemsg;
                    $via->moodleismailer = get_config('via', 'via_moodleemailnotification');
                    $DB->update_record("via", $via);

                }
                // Else everything is OK, do not change anything.
            } catch (Exception $e) {
                notify(get_string("error:".$e->getMessage(), "via"));
                $result = false;
                continue;
            }

        }
    }
    // Else, no change, the sender is the same.
    return $result;

}

function synch_via_users() {
    global $DB, $CFG;

    $result = true;

    $deleted = $DB->get_records_sql('SELECT u.id FROM mdl_user u
                                            LEFT JOIN mdl_via_users vu ON vu.userid = u.id
                                            WHERE u.deleted = 1 AND vu.id IS NOT null' );

    foreach ($deleted as $vuser) {

        $activities = $DB->get_records_sql('SELECT v.id, v.viaactivityid FROM mdl_via_participants vp
                                            LEFT JOIN mdl_via v ON v.id = vp.activityid
                                            WHERE vp.userid = '. $vuser->id);
        $api = new mod_via_api();

        try {
            foreach ($activities as $via) {
                $api->removeuser_activity($via->viaactivityid, $vuser->id);
            }
            $DB->delete_records('via_participants', array('userid' => $vuser->id));
            $DB->delete_records('via_users', array('userid' => $vuser->id));

        } catch (Exception $e) {
            notify(get_string("error:".$e->getMessage(), "via"));
            $result = false;
            continue;
        }
    }

    // Else, no change, the sender is the same.
    return $result;

}

function synch_participants() {
    global $DB, $CFG;

    $result = true;

    $via = $DB->get_record('modules', array('name' => 'via'));
    $lastcron = $via->lastcron;

    // Add participants (with student roles only) that are in the ue table but not in via.
    $sql = 'from mdl_user_enrolments ue
            left join mdl_enrol e on ue.enrolid = e.id
            left join mdl_via v on e.courseid = v.course
            left join mdl_via_participants vp on vp.activityid = v.id AND ue.userid = vp.userid
            left join mdl_context c on c.instanceid = e.courseid
            left join mdl_role_assignments ra on ra.contextid = c.id AND ue.userid = ra.userid';
    $where = 'where (vp.activityid is null OR ra.timemodified > '.$lastcron.' )
              and c.contextlevel = 50 and v.enroltype = 0 and e.status = 0 and v.enroltype = 0 and v.groupingid = 0';

    $newenrollments = $DB->get_recordset_sql('select distinct ue.userid, e.courseid, v.id as viaactivity '.$sql.' '.$where);

    // Add users from automatic enrol type.
    foreach ($newenrollments as $add) {
        try {
            $activity = $DB->get_record('via', array('id' => $add->viaactivity));
            $type = get_user_type($add->userid, $add->courseid, $activity->noparticipants);

        } catch (Exception $e) {
            notify("error:".$e->getMessage());
        }
        try {
            if ($type != 2) { // Only add participants and animators.
                via_add_participant($add->userid, $add->viaactivity, $type);
            }

        } catch (Exception $e) {
            notify("error:".$e->getMessage());
            $result = false;
        }
    }

    // Add users from group synch.
    $newgroupmemberssql = ' FROM mdl_via v
                            LEFT JOIN mdl_groupings_groups gg ON v.groupingid = gg.groupingid
                            LEFT JOIN mdl_groups_members gm ON gm.groupid = gg.groupid
                            LEFT JOIN mdl_via_participants vp ON vp.activityid = v.id AND vp.userid = gm.userid ';
    $newgroupmemberswhere = ' WHERE v.groupingid != 0 AND vp.id is null ';

    $newgroupmembers = $DB->get_recordset_sql('select distinct v.id as activityid, v.course, v.noparticipants, gm.userid
                                              '.$newgroupmemberssql.' '.$newgroupmemberswhere);

    foreach ($newgroupmembers as $add) {
        try {
            $type = get_user_type($add->userid, $add->course, $add->noparticipants);
        } catch (Exception $e) {
            notify("error:".$e->getMessage());
            $result = false;
        }
        try {
            if ($type != 2) { // Only add participants and animators.
                via_add_participant($add->userid, $add->activityid, $type);
            }

        } catch (Exception $e) {
            notify("error:".$e->getMessage());
            $result = false;
        }
    }

    // Now we remove via participants that have been unerolled from a cours.
    $oldenrollments = $DB->get_records_sql('select vp.id, vp.activityid, vp.userid, ue.id as enrolid from mdl_via_participants vp
                                            left join  mdl_user_enrolments ue on ue.enrolid = vp.enrolid and ue.userid = vp.userid
                                            where ue.enrolid is null and vp.userid != 2 and vp.enrolid != 0');
                                            // 2== admin user which is never enrolled.

    // If we are using cohortes, removed groups are not removed from enrollements so we check if they have a role as well.
    $oldenrollments2 = $DB->get_records_sql('SELECT vp.id, vp.activityid, vp.userid
                                            FROM mdl_via_participants vp
                                            INNER JOIN mdl_via v ON v.id = vp.activityid
                                            LEFT JOIN mdl_context c ON v.course = c.instanceid
                                            LEFT JOIN mdl_role_assignments ra ON c.id = ra.contextid AND vp.userid = ra.userid
                                            WHERE c.contextlevel = 50 AND ra.roleid IS null and
                                            vp.userid != 2 and (vp.participanttype = 1 OR vp.participanttype = 3)');

    $oldgroupmembers = $DB->get_records_sql('SELECT distinct vp.id, vp.activityid, vp.userid
                                            FROM mdl_via_participants vp
                                            LEFT JOIN mdl_via v ON v.id = vp.activityid
                                            LEFT JOIN mdl_groupings_groups gg ON gg.groupingid = v.groupingid
                                            LEFT JOIN mdl_groups g ON gg.groupid = g.id AND v.course = g.courseid
                                            LEFT JOIN mdl_groups_members gm ON vp.userid = gm.userid
                                            WHERE  ( gm.id is null OR g.id is null )
                                            AND vp.participanttype != 2 AND v.groupingid != 0');

    $total = array_merge($oldenrollments, $oldenrollments2, $oldgroupmembers);
    foreach ($total as $remove) {
        try {
            // If user is not mananger, we remove him.
            if (!$DB->get_record('role_assignments', array('userid' => $remove->userid, 'contextid' => 1, 'roleid' => 1))) {
                via_remove_participant($remove->userid, $remove->activityid);
            }
        } catch (Exception $e) {
            notify("error:".$e->getMessage());
            $result = false;
        }
    }

    return $result;
}

function get_user_type($userid, $courseid, $noparticipants = null) {
    global $DB, $CFG;

    if (isset($noparticipants) || $noparticipants != null) {
        $noparticipants = $noparticipants;
    }

    $context = context_course::instance($courseid);
    if (has_capability('moodle/course:viewhiddenactivities', $context, $userid) || $noparticipants == 1) {
        $type = '3';// Animator!
    } else {
        $type = '1';// Participant!
    }

    return $type;
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

    $context = context_course::instance($COURSE->id);
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
        $participants = $DB->get_records_sql("SELECT * FROM mdl_via_participants
                                              WHERE activityid=$via->id AND participanttype != 2");
        foreach ($participants as $participant) {
            if (!via_remove_participant($participant->userid, $via->id)) {
                $result = false;
            }
        }
    }
    return $result;
}

function validate_api_version($required, $buildversion) {

    $req = explode(".", $required);
    $version = explode(".", $buildversion);

    if ($version[0] > $req[0]) {
        return true;
    }

    if ($version[0] == $req[0] && $version[1] >= $req[1]) {
        return true;
    }

    if ($version[0] == $req[0] && $version[1] == $req[1] && $version[2] >= $req[2]) {
        return true;
    }

    if ($version[0] == $req[0] && $version[1] == $req[1] && $version[2] == $req[2] && $version[3] >= $req[3]) {
        return true;
    }

    return false;

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
 */
function via_set_participant_confirmationstatus($viaid, $present) {
    global $CFG, $USER, $DB;

    if ($participanttypes = $DB->get_records('via_participants', array('userid' => $USER->id, 'activityid' => $viaid))) {
        foreach ($participanttypes as $type) {
            $type->confirmationstatus = $present;
            $DB->update_record("via_participants", $type);
        }
    }

    if ($via = $DB->get_record('via', array('id' => $viaid))) {
        $via->userid = $USER->id;
        $via->confirmationstatus = $present;

        $api = new mod_via_api();

        try {
            $response = $api->edituser_activity($via);
        } catch (Exception $e) {
            print_error(get_string("error:".$e->getMessage(), "via"));
            $result = false;
        }
    }

}
