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
 * Library of internal classes and functions for module Via
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/mod/via/lib.php');
require_once($CFG->dirroot.'/mod/via/api.class.php');
require_once(get_vialib());

/**
 * Called by via_cron to remove users in table via_users
 * if they have been deleted in moodle
 *
 * @return true or false
 */
function via_synch_users() {
    global $DB, $CFG;

    $result = true;

    $deleted = $DB->get_records_sql('SELECT u.id FROM {user} u
                                     LEFT JOIN {via_users} vu ON vu.userid = u.id
                                     WHERE u.deleted = 1 AND vu.id IS NOT null');

    foreach ($deleted as $vuser) {
        $activities = $DB->get_records_sql('SELECT v.id, v.viaactivityid FROM {via_participants} vp
                                            LEFT JOIN {via} v ON v.id = vp.activityid
                                            WHERE vp.userid = ?', array($vuser->id));

        try {
            foreach ($activities as $via) {
                if ($via->synchvia == 1 || $via->timesynched && is_null($via->synchvia)) {
                    $synch = true;
                } else {
                    $synch = false;
                }
                $response = via_remove_participant($vuser->id, $via->viaid, $synch);
            }
            $DB->delete_records('via_participants', array('userid' => $vuser->id));
            $DB->delete_records('via_users', array('userid' => $vuser->id));
        } catch (Exception $e) {
            print_error(get_string("error:".$e->getMessage(), "via"));
            $result = false;
            continue;
        }
    }

    // Else, no change, the sender is the same.
    return $result;
}

/**
 * Called by via_cron to synch participants in via activities
 * with automaitic enrollment
 *
 * @return true or false
 */
function via_synch_participants($userid, $activityid) {
    global $DB, $CFG;

    $result = true;

    $viatask = $DB->get_record('task_scheduled', array('classname' => '\mod_via\task\via_usersync_task'));

    if ($activityid <> null) {
        $via = $DB->get_record('via', array('id' => $activityid));
        $viatask->lastruntime = $via->usersynchronization;
        if ($viatask->lastruntime == null) {
            $viatask->lastruntime = 0;
        }
    }

    // Add participants (with student roles only) that are in the ue table but not in via.
    $sql = 'from {user_enrolments} ue
        left join {enrol} e on ue.enrolid = e.id
        left join {via} v on e.courseid = v.course
        left join {via_participants} vp on vp.activityid = v.id AND ue.userid = vp.userid
        left join {context} c on c.instanceid = e.courseid
        left join {role_assignments} ra on ra.contextid = c.id AND ue.userid = ra.userid';
    $where = 'where (vp.activityid is null OR ra.timemodified > ? )
            and c.contextlevel = 50 and v.enroltype = 0 and e.status = 0 and v.enroltype = 0 and v.groupingid = 0 ';
    $array = array($viatask->lastruntime);

    if ($activityid <> null) {
        $where .= " and v.id = ?";
        $array[] = $activityid;
    } else {
        // If no activity ID is sent, we only check perm and activities in the futur. (CALLED FROM TASK)!
        $where .= " and (v.activitytype = 2 or (v.activitytype = 1 and (v.datebegin + v.duration*60) > " . time() . ")) ";
    }

    if ($userid <> null) {
        $where .= " and ue.userid = ?";
        $array[] = $userid;
    }

    $ners = $DB->get_recordset_sql('SELECT distinct ue.userid, e.courseid, v.id as viaactivity, v.noparticipants '.
        $sql.' '.$where, $array, $limitfrom = 0, $limitnum = 0);

    // Add users from automatic enrol type.
    foreach ($ners as $add) {
        try {
            $type = via_user_type($add->userid, $add->courseid, $add->noparticipants);
        } catch (Exception $e) {
            print_error("error:".$e->getMessage());
        }
        try {
            if ($type != 2) { // Only add participants and animators.
                via_add_participant($add->userid, $add->viaactivity, $type, false);
            }
        } catch (Exception $e) {
            print_error("error:".$e->getMessage());
        }
    }

    // Add users from group synch.
    $newgroupmemberssql = ' FROM {via} v
                            LEFT JOIN {groupings_groups} gg ON v.groupingid = gg.groupingid
                            LEFT JOIN {groups_members} gm ON gm.groupid = gg.groupid
                            LEFT JOIN {via_participants} vp ON vp.activityid = v.id AND vp.userid = gm.userid ';
    $newgroupmemberswhere = ' WHERE v.groupingid != 0 AND v.enroltype = 0 AND vp.id is null AND gm.timeadded > ?
     OR gg .timeadded > ?';
    $array = array($viatask->lastruntime, $viatask->lastruntime);

    if ($activityid <> null) {
        $newgroupmemberswhere .= " and v.id = ?";
        $array[] = $activityid;
    } else {
        // If no activity ID is sent, we only check perm and activities in the futur. (CALLED FROM TASK)!
        $newgroupmemberswhere .= " and (v.activitytype = 2 or (v.activitytype = 1 and v.datebegin > " . time() . ")) ";
    }

    if ($userid <> null) {
        $newgroupmemberswhere .= " and gm.userid = ?";
        $array[] = $userid;
    }

    $newgroupmembers = $DB->get_recordset_sql('select distinct v.id as activityid, v.course, v.noparticipants, gm.userid
                                            '.$newgroupmemberssql.' '.$newgroupmemberswhere, $array);

    foreach ($newgroupmembers as $add) {
        try {
            $type = via_user_type($add->userid, $add->course, $add->noparticipants);
        } catch (Exception $e) {
            print_error("error:".$e->getMessage());
        }
        try {
            if ($type != 2) { // Only add participants and animators.
                via_add_participant($add->userid, $add->activityid, $type, false);
            }
        } catch (Exception $e) {
            print_error("error:".$e->getMessage());
            $result = false;
        }
    }

    // If we are not in the task we do not do the user removal sync.
    if ($activityid <> null) {
        return $result;
    }
    // Now we remove via participants that have been unerolled from a cours.
    $oldenrollments = $DB->get_records_sql('SELECT vp.id, vp.activityid, vp.userid, vp.timesynched, vp.synchvia
                                        FROM {via_participants} vp
                                        LEFT JOIN {user_enrolments} ue ON ue.enrolid = vp.enrolid and ue.userid = vp.userid
                                        WHERE ue.enrolid is null AND vp.userid != 2 and vp.enrolid != 0');
    // 2== admin user which is never enrolled.

    // If we are using groups.
    $oldgroupmembers = $DB->get_records_sql('SELECT distinct vp.id, vp.activityid, vp.userid, vp.timesynched, vp.synchvia
                                        FROM {via_participants} vp
                                        LEFT JOIN {via} v ON v.id = vp.activityid
                                        LEFT JOIN {groupings_groups} gg ON gg.groupingid = v.groupingid
                                        LEFT JOIN {groups} g ON gg.groupid = g.id AND v.course = g.courseid
                                        LEFT JOIN {groups_members} gm ON vp.userid = gm.userid
                                        WHERE  ( gm.id is null OR g.id is null )
                                        AND vp.participanttype = 1 AND v.groupingid != 0');

    $totalmerge = array_merge($oldenrollments, $oldgroupmembers);
    $total = array_unique($totalmerge, SORT_REGULAR);
    foreach ($total as $remove) {
        try {
            // If user is not mananger, we remove him.
            if (!$DB->get_record('role_assignments', array('userid' => $remove->userid, 'contextid' => 1, 'roleid' => 1))) {
                // By adding the info here, we are cutting on calls to the DB later on.
                if ($remove->synchvia == 1 || $remove->timesynched && is_null($remove->synchvia)) {
                    $synch = true;
                } else {
                    $synch = false;
                }
                via_remove_participant($remove->userid, $remove->activityid, $synch);
            }
        } catch (Exception $e) {
            print_error("error:".$e->getMessage());
            $result = false;
        }
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

    if (isset($via->ish264)) {
        switch($via->ish264) {
            case 0:
                $via->ish264 = 1;
                break;
            case 1:
                $via->ish264 = 0;
                break;
            default:
                $via->ish264 = 1;
                break;
        }
    } else {
        $via->ish264 = 1;
    }
}

/**
 * Gets the categories created in Via by the administrators
 *
 * @return an array of the different categories to create drop down list in the mod_form.
 */
function via_get_categories() {
    global $CFG;

    $result = true;

    $api = new mod_via_api();

    try {
        $response = $api->via_get_categories();
    } catch (Exception $e) {
        print_error(get_string("error:".$e->getMessage(), "via"));
    }

    return $response;
}

/**
 * Gets user enroleid.
 *
 * @param object $course
 * @param integer $userid
 * @return integer enrolid.
 */
function via_get_enrolid($course, $userid) {
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
    // added as animator or host without being enrolled in the course.
    return null;
}

/**
 * Updates Moodle Via info with data coming from VIA server.
 *
 * @param object $values An object from view.php containg via activity infos
 * @return object containing new infos for activity.
 */
function via_update_info_database($values) {
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
 * @param object $via the via activity
 * @param integer $participanttype, users role in the activity.
 * @param object $context the via context, to save re-fetching it where possible.
 * @return array list of users.
 */
function via_participants($course, $via, $participanttype, $context = null) {
    global $CFG, $DB;

    $results = $DB->get_records_sql('SELECT distinct u.id, '.user_picture::fields('u').', u.username, u.firstname,
                                    u.lastname, u.maildisplay, u.mailformat, u.maildigest, u.emailstop, u.imagealt,
                                    u.idnumber, u.email, u.city, u.country, u.lastaccess, u.lastlogin, u.picture,
                                    u.timezone, u.theme, u.lang, u.trackforums, u.mnethostid
                                    FROM {user} u, {via_participants} s
                                    WHERE s.activityid = ?
                                    AND s.userid = u.id
                                    AND s.participanttype = ?
                                    AND u.deleted = 0
                                    ORDER BY u.email ASC ', array($via->id, $participanttype));

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
 * Creates displayable status with image and text
 *
 * @param integer $status
 * @return string with image and string
 */
function via_get_confirmationstatus($status) {
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

    return "<img src='" . $CFG->wwwroot . "/mod/via/pix/".$confirmimg."' width='16'
    height='16' alt='".$confirmtitle . "' title='".$confirmtitle . "' align='absmiddle'/>";
}

/**
 * Get the available multi media quality profiles available for the company on via
 *
 * @return obejct list of profiles
 */
function via_get_list_profils() {
    global $DB;

    $api = new mod_via_api();
    $result = false;

    try {
        $names = array();

        $response = $api->list_profils();

        if ($response) {
            foreach ($response['Profil'] as $info) {
                if (isset($info['ProfilName'])) {
                    // Create array to see if there are profiles to be deleted.

                    $names[] = $info['ProfilName'];

                    $param = new stdClass();
                    $param->param_type = 'multimediaprofil';
                    $param->param_name = $info['ProfilName'];
                    $param->value = $info['ProfilID'];
                    $param->timemodified = time();

                    $exists = $DB->get_record('via_params',
                    array('param_type' => 'multimediaprofil', 'param_name' => $info['ProfilName']));

                    if ($exists) {
                        if ($exists->value != $info['ProfilID']) {
                            // If the profile has changed we need to update all vias using the old id.
                            $vias = $DB->get_records_sql('SELECT * FROM {via}
                                                        WHERE profilid = ? AND datebegin > ' . time() .'
                                                        OR activitytype = 2', array($exists->value));
                            foreach ($vias as $via) {
                                $via->profilid = $info['ProfilID'];
                                $DB->update_record('via', $via);
                            }

                            try {
                                // If the profile has changed we need to update all viaassign using the old id.
                                $viasss = $DB->get_records_sql('SELECT * FROM {via_assign}
                                                            WHERE multimediaquality = ?', array($exists->value));
                                foreach ($viasss as $via) {
                                    $via->multimediaquality = $info['ProfilID'];
                                    $DB->update_record('via_assign', $via);
                                }
                            } catch (Exception $e) {
                                $result = false;
                            }

                            // We only update if the value is different.
                            // We do this after updating the activities otherwise we no longer have the correct value.
                            $param->id = $exists->id;
                            $DB->update_record('via_params', $param);
                        }
                    } else {
                        $newparam = $DB->insert_record('via_params', $param);
                    }
                }
            }

            // All existing profiles!
            $profiles = $DB->get_records('via_params', array('param_type' => 'multimediaprofil'));
            foreach ($profiles as $profil) {
                if (!in_array($profil->param_name, $names)) {
                    // If not in new names array array created above we delete it!
                    $DB->delete_records('via_params', array('param_type' => 'multimediaprofil', 'param_name' => $profil->name));
                }
            }

            $result = true;
        }
    } catch (Exception $e) {
        print_error(get_string("error:".$e->getMessage(), "via"));
    }

    return $result;
}

/**
 * Deletes all elements that are in the via_recyclebin table.
 *
 * @return boolean
 */
function via_delete_recylebin_instances() {
    global $DB;

    $return = true;

    // Join to see if null, just in case the activity has been restored!
    // It should have been redeleted from the via_recycleboin table at the restoration, but we validate anyways, just to be sure!
    $todelete = $DB->get_records_sql('SELECT vr.id, vr.viaid, vr.viaactivityid, vr.recyclebinid, vr.recyclebintype, vr.expiry
                                      FROM {via_recyclebin} vr
                                      LEFT JOIN {via} v ON v.viaactivityid = vr.viaactivityid
                                      WHERE v.viaactivityid IS NULL AND expiry < ' . time());

    if ($todelete) {
        $api = new mod_via_api();

        foreach ($todelete as $d) {
            try {
                // We delete the activity in Via, for this we need the viaactivityid.
                $response = $api->via_activity_delete($d->viaactivityid);
                if (isset($response['Result']) && $response['Result']["ResultState"] == 'SUCCESS') {
                    $deleted = $DB->delete_records('via_recyclebin', array('id' => $d->id));
                    $return = $deleted;
                }
            } catch (Exception $e) {
                $return = false;
                print_error(get_string("error:".$e->getMessage(), "via"));
            }
        }
    }
    return $return;
}
/**
 * Get all the playbacks for an acitivity
 *
 * @param object $via the via object
 * @return obejct list of playbacks
 */
function via_sync_activity_playbacks($via) {
    global $DB;
    $api = new mod_via_api();

    try {
        $playbacks = $api->list_playback($via);
    } catch (Exception $e) {
        print_error(get_string("error:".$e->getMessage(), "via"));
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
                                if (!$DB->record_exists('via_playbacks', array("playbackid" => $breakout['PlaybackID']))) {
                                    $param = new stdClass();
                                    $param->playbackid = $breakout['PlaybackID'];
                                    $param->title = $breakout['Title'];
                                    $param->duration = $breakout['Duration'];
                                    $param->creationdate = strtotime($breakout['CreationDate']);
                                    $param->accesstype = $via->isreplayallowed;
                                    // In old version = $breakout['IsPublic']; not sure which is correct!
                                    $param->isdownloadable = $breakout['IsDownloadable'];
                                    $param->hasfullvideorecord = $breakout['HasFullVideoRecord'];
                                    $param->hasmobilevideorecord = $breakout['HasMobileVideoRecord'];
                                    $param->hasaudiorecord = $breakout['HasAudioRecord'];
                                    $param->activityid = $via->id;
                                    $param->playbackidref = $breakout['PlaybackRefID'];
                                    $param->deleted = "0";
                                    $newparam = $DB->insert_record('via_playbacks', $param);
                                }
                            } else {
                                foreach ($breakout as $bkout) {
                                    if (gettype($bkout) == "array") {
                                        if (!$DB->record_exists('via_playbacks', array("playbackid" => $bkout['PlaybackID']))) {
                                            $param = new stdClass();
                                            $param->playbackid = $bkout['PlaybackID'];
                                            $param->title = $bkout['Title'];
                                            $param->duration = $bkout['Duration'];
                                            $param->creationdate = strtotime($bkout['CreationDate']);
                                            $param->accesstype = $via->isreplayallowed;
                                            $param->isdownloadable = $bkout['IsDownloadable'];
                                            $param->hasfullvideorecord = $bkout['HasFullVideoRecord'];
                                            $param->hasmobilevideorecord = $bkout['HasMobileVideoRecord'];
                                            $param->hasaudiorecord = $bkout['HasAudioRecord'];
                                            $param->activityid = $via->id;
                                            $param->playbackidref = $bkout['PlaybackRefID'];
                                            $param->deleted = "0";
                                            $newparam = $DB->insert_record('via_playbacks', $param);
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    if (!$DB->record_exists('via_playbacks', array("playbackid" => $playback['PlaybackID']))) {
                        $param = new stdClass();
                        $param->playbackid = $playback['PlaybackID'];
                        $param->title = $playback['Title'];
                        $param->duration = $playback['Duration'];
                        $param->creationdate = strtotime($playback['CreationDate']);
                        $param->accesstype = $via->isreplayallowed;
                        $param->isdownloadable = $playback['IsDownloadable'];
                        $param->hasfullvideorecord = $playback['HasFullVideoRecord'];
                        $param->hasmobilevideorecord = $playback['HasMobileVideoRecord'];
                        $param->hasaudiorecord = $playback['HasAudioRecord'];
                        $param->activityid = $via->id;
                        $param->playbackidref = null;
                        $param->deleted = "0";
                        $newparam = $DB->insert_record('via_playbacks', $param);
                    }
                }
            }
        }
    }

    $param = new stdClass();
    $param->id = $via->id;
    $param->playbacksync = (time() - 300);
    $DB->update_record('via', $param);
}

/**
 * Button to call presence report
 *
 * @param integer $viaid
 * @return html string to display button
 */
function via_report_btn($id, $viaid = false) {
    global $CFG, $USER;

    $usercontext   = context_user::instance($USER->id, IGNORE_MISSING);

    if (!$viaid || has_capability('moodle/user:viewdetails', $usercontext)) {
        $showemail = '&e=1';
    } else {
        $showemail = '&e=0';
    }

    $btn = '<a style="float:right;padding-top:10px;padding-left:20px;" class="viabtnlink" href="presence.php?id='.$id.$showemail.'"
    onclick="window.open(this.href, \'Presence\', \'toolbar=yes, scrollbars=1, width=800, height=800\');
    return false;" ><i class="fa fa-file-text via"></i>' .
    get_string("report", "via").'</a></>';

    return $btn;
}

/**
 * Builds table head for presence or user list table
 *
 * @param object $via
 * @param integer $presence
 * @return html string to display table head
 */
function via_get_table_head($via, $presence = null) {
     $table = new html_table();
    $table->attributes['class'] = 'generaltable boxalignleft';
    $table->tablealign = 'center';
    $table->cellpadding = 5;
    $table->cellspacing = 0;

    // Common values to both tables!
    $table->head  = array();
    $table->head[] = get_string("role", "via");
    $table->head[] = get_string("lastname").', '.get_string("firstname");

    if ($presence) {
        $table->id = 'viapresence';
        $table->head[] = get_string("presenceheader", "via");
        $table->align = array ('left', 'left', 'center');

        if ($via->recordingmode != 0) {
            $table->head[] = get_string("playbackheader", "via");
            $table->align[] = 'center';
        }
        if (get_config('via', 'via_participantmustconfirm') && $via->needconfirmation) {
            $table->head[] = get_string("confirmationstatus", "via");
            $table->align[] = 'center';
        }

    } else {
        $table->id = 'viaparticipants';
        $table->head[] = get_string("config", "via");
        $table->align = array ('left', 'left', 'center');

        if (get_config('via', 'via_participantmustconfirm') && $via->needconfirmation) {
            $table->head[] = get_string("confirmationstatus", "via");
            $table->align[] = 'center';
        }
    }

    return $table;
}

/**
 * Builds presence status or user list table
 *
 * @param object $via
 * @param object $conext
 * @param integer $presence
 * @return populated table
 */
function via_get_participants_table($via, $context, $presence = null) {
    global $DB, $CFG;

    if ($presence) {
        $string = get_string("presencetable", "via");
    } else {
        $string = get_string("viausers", "via");
    }
    $total = $DB->count_records('via_participants', array('activityid' => $via->id));

    $partbtn = "";
    if ($via->enroltype == 0 && has_capability('mod/via:manage', $context)) {
        $cmid = $context->instanceid;
        $partbtn = '<a style="float:right;padding-top:10px;" class="viabtnlink"
             href="'.$CFG->wwwroot.'/course/modedit.php?update='.$cmid.
            '#id_enrolmentheader"><i class="fa fa-users via"></i>' . get_string("manageparticipants", "via") .  "</a>";
    }

    echo $partbtn . "<h2 class='main' style='vertical-align: top;'>".$string." (".$total.")</h2>";

    if ($via->enroltype == 1) {
        $enroltype  = get_string('manualenrol', 'via');
    } else {
        $enroltype  = get_string('automaticenrol', 'via');
    }
    echo '<p style="display: inline;">'.$enroltype.'</p>';

    $table = via_get_table_head($via, $presence);

    $limit = 50; // How many items to list per page?
    $pages = ceil($total / $limit); // How many pages will there be?
    // What page are we currently on?
    $page = min($pages, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, array(
        'options' => array(
                        'default'   => 1,
                        'min_range' => 1,
                        ),
                    )));
    // Calculate the offset for the query.
    $offset = ($page - 1);

    if ($via->enroltype == 0 && has_capability('mod/via:manage', $context)) {
        $notsynched = $DB->count_records('via_participants', array('activityid' => $via->id, 'synchvia' => '0'));
        if ($total >= 50 && $page == 1 && $notsynched > 0) {
            // We display a button to synch the users.
            echo '<div class="usersynch">';
            echo '<p>'.get_string('usersynch', 'via');
            echo '<a href="manual_synch.php?id='.$via->id.'" id="btnsynch" >
                  <i class="fa fa-refresh" aria-hidden="true"></i>'.get_string('usersynchbtn', 'via').'</a> </p>';
            echo '<p id="wait" class="hide wait">
                  <img src="'.$CFG->wwwroot.'/mod/via/pix/4.gif">'.get_string('usersynchwarning', 'via').'</p>';
            echo '</div>';
        }
    }

    echo '<div class="viapaging">Page : ';
    for ($i = 1; $i <= $pages; ++$i) {
        if ($page == ($i)) {
            echo ($i);
        } else {
            echo '  <a class="viapage" href="?id='.$context->instanceid.'&page=' . ($i) . '" title="Page">'.($i).'</a>  ';
        }
    }
    echo '</div>';

    // Prepare the paged query.
    $where = 'WHERE activityid = ' . $via->id .' ORDER BY (participanttype+1)%3 , u.lastname ASC ';
    if ($presence) {
        $sql = 'SELECT vpp.userid, v.id as activityid, vu.viauserid, u.lastname, u.firstname,
                u.email, vpp.participanttype, vp.status, v.presence, v.recordingmode, v.viaactivityid,
                vp.connection_duration, vp.playback_duration, vpp.confirmationstatus
                FROM {via_participants} vpp
                LEFT JOIN {via_users} vu ON vu.userid = vpp.userid
                LEFT JOIN {via} v ON v.id = vpp.activityid
                LEFT JOIN {user} u ON u.id = vpp.userid
                LEFT JOIN {via_presence} vp ON vpp.userid = vp.userid AND vpp.activityid = vp.activityid
                WHERE vpp.activityid = ? ORDER BY (vpp.participanttype+1)%3, u.lastname ASC ';

    } else {
        $sql = 'SELECT vp.*, u.firstname, u.lastname, u.email, vu.viauserid, vu.setupstatus, vp.confirmationstatus
                FROM {via_participants} vp
                LEFT JOIN {user} u ON u.id = vp.userid
                LEFT JOIN {via_users} vu ON vu.userid = u.id
                WHERE activityid = ? ORDER BY (participanttype+1)%3 , u.lastname ASC';
    }

    try {
        $participantslist = $DB->get_records_sql($sql, array($via->id), $offset * $limit, $limit);

        if ($participantslist) {
            foreach ($participantslist as $participant) {
                $role = via_get_role($participant->participanttype);

                if (has_capability('mod/via:manage', $context)) {// Students can not see the user profiles.
                    $userlink = '<a href="'. $CFG->wwwroot .'/user/profile.php?id='.$participant->userid.'">'.
                        $participant->lastname.', '.$participant->firstname.'</a>';
                } else {
                    $userlink = $participant->lastname.', '. $participant->firstname;
                }

                if ($presence) {
                    $userlogs = via_userlogs($participant);

                    $i = 1;
                    // Values; $info2 = live time, $info1 = playback time!
                    foreach ($userlogs as $logs) {
                        ${"info" . $i} = $logs;
                        $i++;
                    }
                } else {
                    if (is_null($participant->synchvia) || $participant->synchvia == 0 ||is_null($participant->timesynched)) {
                        try {
                            via_add_participant($participant->userid, $via->id, $participant->participanttype, true);
                        } catch (Exception $e) {
                            echo $e->getMessage();
                        }
                    }
                    if (isset($participant->setupstatus)) {
                        if ($participant->setupstatus == "0") {
                            $info1 = '<span class="viagreen" >'.get_string("finish", "via"). '</span>';
                        } else if ($participant->setupstatus == "1") {
                            $info1 = '<span class="viayellow" >'. get_string("incomplete", "via"). '</span>';
                        } else {
                            $info1 = '<span class="viared" >'.get_string("neverbegin", "via"). '</span>';
                        }
                    } else {
                        $info1 = '<span class="viared" >'.get_string("neverbegin", "via"). '</span>';
                    }
                }

                if ($via->needconfirmation && get_config('via', 'via_participantmustconfirm')) {
                    $info3 = via_get_confirmationstatus($participant->confirmationstatus);
                }

                if ((!$presence && isset($info2)) || ($presence && $via->recordingmode != 0)) {
                    if (isset($info3)) {
                        $table->data[] = array ($role, $userlink, $info1, $info2, $info3);
                    } else {
                        $table->data[] = array ($role, $userlink, $info1, $info2);
                    }
                } else {
                    if (isset($info3)) {
                        $table->data[] = array ($role, $userlink, $info1, $info3);
                    } else {
                        $table->data[] = array ($role, $userlink, $info1);
                    }
                }
            }
        } else {
            $table->data[] = array (get_string('nousers', 'via'), '', '', '');
        }
    } catch (Exception $e) {
        $table->data[] = $e->getMessage();
    }

    return html_writer::table($table);
}

/**
 * Get user logs from via.
 *
 * @param object $participant
 * @return string presence and playback times.
 */
function via_userlogs($participant) {
    global $DB;

    $playback = "";

    // We only update the information if the status was not already calcultated and
    // that we don't need to update the recording information!
    if (!isset($participant->status) || $participant->recordingmode != 0) {
        if ($participant->viauserid) {
            $api = new mod_via_api();
            $userlog = $api->via_get_user_logs($participant->viauserid, $participant->viaactivityid);

            if (isset($userlog["Result"]["ResultState"])) {
                if ($userlog['ConnectionDuration'] >= $participant->presence) {
                    $status = 1;
                    $duration = via_get_converted_time($userlog['ConnectionDuration']);
                    $live = get_string('present', 'via') .' (<span class="viagreen">'.$duration.'</span>)';
                } else {
                    $status = 0;
                    if ($userlog['ConnectionDuration']) {
                        $duration = via_get_converted_time($userlog['ConnectionDuration']);
                    } else {
                        $duration = '00:00';
                    }
                    $live = get_string('absent', 'via') .' (<span class="viared">'.$duration.'</span>)';
                }

                if ($participant->recordingmode != 0 && $userlog['PlaybackDuration']) {
                    $duration = via_get_converted_time($userlog['PlaybackDuration']);
                    $playback = '<span class="viagreen">'.$duration. '</span>';
                } else {
                    $playback = "";
                }

                $exists = $DB->get_record('via_presence',
                    array('userid' => $participant->userid, 'activityid' => $participant->activityid));

                // Insert OR update via_presence table!
                $presence = new stdClass();
                $presence->connection_duration = str_replace(',', '.', $userlog['ConnectionDuration']);
                $presence->playback_duration = str_replace(',', '.', $userlog['PlaybackDuration']);
                $presence->status = $status;
                $presence->timemodified = time();

                if ($exists) {
                    $presence->id = $exists->id;
                    $DB->update_record('via_presence', $presence);
                } else {
                    $presence->userid = $participant->userid;
                    $presence->activityid = $participant->activityid;
                    $DB->insert_record('via_presence', $presence);
                }
            } else {
                $live = $userlog;
            }
        } else {
            // The $participant->viauserid is empty, which means that the user never connected to Via.
            $live = get_string('absent', 'via') .' (<span class="viared">00:00</span>)';
            $playback = "";

            $exists = $DB->get_record('via_presence',
                array('userid' => $participant->userid, 'activityid' => $participant->activityid));
            // Insert OR update via_presence table!
            $presence = new stdClass();
            $presence->connection_duration = '0.00';
            $presence->playback_duration = '0.00';
            $presence->status = 0;
            $presence->timemodified = time();

            if ($exists) {
                $presence->id = $exists->id;
                $DB->update_record('via_presence', $presence);
            } else {
                $presence->userid = $participant->userid;
                $presence->activityid = $participant->activityid;
                $DB->insert_record('via_presence', $presence);
            }
        }
    } else {
        // The presence status was already added and there are no recordings so we do not update.
        if ($participant->status == 1) {
            $duration = via_get_converted_time($participant->connection_duration);
            $live = get_string('present', 'via') .' (<span class="viagreen">'.$duration.'</span>)';
        } else {
            if ($participant->connection_duration) {
                $duration = via_get_converted_time($participant->connection_duration);
            } else {
                $duration = '00:00';
            }
            $live = get_string('absent', 'via') .' (<span class="viared">'.$duration.'</span>)';
        }
        $playback = "-";
    }

    return array($live, $playback);
}

/**
 * Create playback table for via activity details page.
 *
 * @param object $playbacks list of via playbacks assiciated to the activity
 * @param object $via the via object
 * @param object $context moodle object
 * @return table
 */
function via_get_playbacks_table($via, $context, $viaurlparam = 'id', $cancreatevia) {
    global $CFG, $DB;

    $cmid = $context->instanceid;
    if ($viaurlparam == 'viaid') {
        $cmid = $via->id;
    }
    $playbacks = $DB->get_records_sql('SELECT * FROM {via_playbacks}
                                    WHERE activityid = ? AND deleted = 0
                                    ORDER BY playbackidref asc, creationdate asc',
                                    array($via->id));

    if (count($playbacks) == 0) {
        return "";
    }

    $table = "<table cellpadding='2' cellspacing='0' class='generaltable boxalignleft' id='via_recordings'>";
    $formname = 0;
    foreach ($playbacks as $playback) {
        // Lists all playbacks for acitivity.
        if (isset($playback->playbackidref)) {
            $style = "atelier";
            $li = "<li style='list-style-image:url(".$CFG->wwwroot."/mod/via/pix/arrow.gif);'>";
            $endli = "</li>";
        } else {
            $style = "";
            $li = "";
            $endli = "";
        }

        $private = $playback->accesstype > 0 ? "" : "dimmed_text";

        if ($playback->accesstype > 0 || $cancreatevia) {
            // If playback is public and/or if user is animator or host.
            if (!isset($header)) {// We only display it once.
                $header = '<h2 class="main">'.get_string("recordings", "via").'</h2>';
                echo $header;
            }
            $table .= "<tr class='$style'>";

            $table .= "<td class='title $style  $private'>$li";

            $table .= $playback->title;

            $table .= "$endli</td>";

            $table .= "<td class='duration  $private' style='text-align:left'>".
                userdate($playback->creationdate)."<br/> ".
                get_string("durationheader", "via")." : ".gmdate("H:i:s",  $playback->duration)."<br />".
                get_string("playbackaccesstype".$playback->accesstype , "via")."</td>";

            if ($cancreatevia) {
                if ($playback->accesstype > 0) {
                    $checked = get_string("show", "via");
                    $ispublic = 0;
                    $class = 'showvia';
                } else {
                    $checked = get_string("mask", "via");
                    $ispublic = 1;
                    $class = 'maskvia';
                }

                $table .= "<td class='modify $private'>";
                $table .= '<a class="modify" href="edit_review.php?'.$viaurlparam.'='.$via->id.'&playbackid='.
                urlencode($playback->playbackid).'&edit=edit">
                <i class="fa fa-pencil via"></i>'.get_string("edit", "via").'</a><br/>';

                    // Delete recording!
                    $table .= '<a class="deleteplayback" href="edit_review.php?'.$viaurlparam.'='.$via->id.'&playbackid='.
                        urlencode($playback->playbackid).'&edit=del">
                        <i class="fa fa-times via"></i>'.get_string("delete", "via").'</a>';

                $table .= "</td>";
            }

            if (get_config('via', 'via_downloadplaybacks')) {
                if ($playback->isdownloadable) {
                    $privaterecord = "";
                    $fa = '';
                } else {
                    if ($cancreatevia) {
                        $privaterecord = "dimmed_text";
                        $fa = '&fa=1';
                    } else {
                        $privaterecord = "hide";
                        $fa = '';
                    }
                }

                $table .= "<td class='download nowrap $private $privaterecord'>";

                if ($playback->hasfullvideorecord == 1) {
                    $table .= '<a class="download" href="download_recording.php?'.
                        $viaurlparam.'='.$via->id.$fa.'&type=1&playbackid='.
                        urlencode($playback->playbackid).'" title="'. get_string('fullvideoinfo', 'via') .'">'.
                        get_string('fullvideo', 'via') .'</a><br/>';
                }
                if ($playback->hasmobilevideorecord == 1) {
                    $table .= '<a class="download" href="download_recording.php?'.
                        $viaurlparam.'='.$via->id.'&type=2&playbackid='.
                        urlencode($playback->playbackid).'" title="'. get_string('mobilevideoinfo', 'via') .'">'.
                        get_string('mobilevideo', 'via') .'</a><br/>';
                }
                if ($playback->hasaudiorecord == 1) {
                    $table .= '<a class="download" href="download_recording.php?'.
                        $viaurlparam.'='.$via->id.$fa.'&type=3&playbackid='.
                        urlencode($playback->playbackid).'" title="'. get_string('audiorecordinfo', 'via') .'">'.
                        get_string('audiorecord', 'via') .'</a>';
                }

                $table .= "</td>";
            }

            $table .= "<td class='review $private'>";
            if ($cancreatevia) {
                $param = '&fa=1';
                if (get_config('via', 'via_downloadplaybacks')) {
                    $text = get_string("export", "via");
                } else {
                    $text = get_string("view", "via");
                }
            } else {
                $param = '';
                $text = get_string("view", "via");
            }

            $table .= '<input type="button" target="viewplayback" href="view.via.php"
                            onclick="this.target=\'viewplayback\';
                            return openpopup(null, {url:\'/mod/via/view.via.php?'.$viaurlparam.'='.$cmid.'&playbackid='.
                            urlencode($playback->playbackid).'&review=1'.$param.'\',
                            name:\'viewplayback\', options:\'menubar=0,location=0,scrollbars=yes,resizable=yes\'});"
                            value="'.$text.'"/>';

            $table .= "</td>";
            $table .= "</tr>";
        }
        $formname = $formname + 1;
    }

    $table .= "</table>";

    return $table;
}

/**
 * Create file table for via activity details page.
 *
 * @param object $files list of via downloadable files to the activity
 * @param object $via the via object
 * @param object $context moodle object
 * @return table
 */
function via_get_downlodablefiles_table($files, $via, $context, $viaurlparam = 'id', $cancreatevia) {
    global $CFG;

    $cmid = $context->instanceid;
    if ($viaurlparam == 'viaid') {
        $cmid = $via->id;
    }

    $table = "<table cellpadding='2' cellspacing='0' class='generaltable boxalignleft' id='via_downloadablefiles'>";

    $formname = 0;

    $downloadbtntemplate = '<a class="download" href="'.$CFG->wwwroot.'/mod/via/download_document.php?viaid='.$via->id;
    $downloadbtntemplate .= '&fid=%FILEID%"><img src="'.$CFG->wwwroot.'/pix/a/download_all.png" /></a>';

    if ($files) {
        $table .= "<thead><th>" . get_string("df_header_title", "via") . "</th>
            <th>".get_string("df_header_type", "via")."</th><th>".get_string("df_header_size", "via")."</th>
            <th class=\"tdcenter\">".get_string("df_header_nbpages", "via"). "</th>
            <th class=\"tdcenter\">".get_string("df_header_download", "via")."</th></thead>";

        if (isset($files["Document"]["Title"])) {
            $container = $files;
        } else {
            $container = $files["Document"];
        }

        foreach ($container as $key => $file) {
            $table .= "<tr>";

            $table .= "<td class='title'>";

            $table .= $file["Title"];

            $table .= "</td>";

            $table .= "<td class='type'>";

            $table .= get_string("df_type_".$file["Type"], "via");

            $table .= "</td>";

            $table .= "<td class='size'>";

            $size = round($file["Size"] / (float)1024, 2);
            if ($size > 1024) {
                $size = round($size / (float)1024, 2);
                $size .= " Mo";
            } else {
                $size .= " Ko";
            }

            $table .= $size;

            $table .= "</td>";

            $table .= "<td class='tdcenter'>";

            $table .= $file["NbPage"];

            $table .= "</td>";

            $table .= "<td class='tdcenter'>";

            if ($file["Type"] == "19") {
                $table .= '<a target="_blank" class="download" href="'.$file["URL"];
                $table .= '"><img src="'.$CFG->wwwroot.'/pix/a/download_all.png" /></a>';
            } else {
                $table .= str_replace("%FILEID%", $file["FileID"], $downloadbtntemplate);
            }

            $table .= "</td>";

            $table .= "</tr>";

            $formname = $formname + 1;
        }
    } else {
        $table .= "<tr><td>".get_string("df_nofiles", "via")."</td></tr>";
    }

    // Header!
    $managebtn = "";
    if ($cancreatevia) {
        $size = ($via->isnewvia == 1 ? "width=1030,height=600" : "width=960,height=580");
        $managebtn = '<a style="float:right;padding-top:10px;cursor:pointer;" class="viabtnlink"
                        target="managevia" onclick="var new_window = window.open(\''.
                        $CFG->wwwroot.'/mod/via/view.assistant.php?redirect=9&fa=1&'.$viaurlparam.'='.$cmid.
                        '\', \'_blank\', \'menubar=0,location=0,scrollbars=0,resizable,'.$size.
                        '\'); new_window.onbeforeunload = function(){
                        setTimeout(function () {window.location = window.location.href;}, 100);}">
                         <i class="fa fa-folder via"></i>' . get_string("df_button_manage", "via") . "</a>";
    }

    echo $managebtn. "<h2 class='main'>".get_string("downloadablefiles", "via"). " (".$formname.")</h2>";

    $table .= "</table>";

    return $table;
}

/**
 * Get the remindertime for an activity
 *
 * @param object $via the via object
 * @return object containing the remindertime
 */
function via_get_remindertime($via) {
    $remindertime = $via->datebegin - $via->remindertime;
    return $remindertime;
}

/**
 * Get the host for an activity
 *
 * @param integer $activityid
 * @return object containing the user's information
 */
function  via_get_host($activityid) {
    global $DB;

    $host = $DB->get_record('via_participants', array('activityid' => $activityid, 'participanttype' => 2));
    if ($host) {
        $from = $DB->get_record('user', array('id' => $host->userid));
    } else {
        $from = get_admin();
    }

    return $from;
}

/**
 * Build the invitation mail html that will be sent be the cron
 *
 * @param integer $courseid
 * @param object $via
 * @param object $muser
 * @param boolean is reminder or invite message
 * @return html mail
 */
function via_make_invitation_reminder_mail_html($courseid, $via, $muser, $reminder=false) {
    global $CFG, $DB;

    if ($muser->mailformat != 1) {// Needs to be HTML.
        return '';
    }

    // Basic values!
    $fs = get_file_storage();
    $files = $fs->get_area_files('1', 'mod_via', 'emailheaderimage');
    $imageurl = false;
    if ($files) {
        foreach ($files as $file) {
            $info = $file->get_imageinfo();
            if ($info) {
                $filepath   = $file->get_filepath();
                $filename   = $file->get_filename();
                $imageurl = moodle_url::make_pluginfile_url($file->get_contextid(),
                                                            $file->get_component(),
                                                            $file->get_filearea(),
                                                            $file->get_itemid(),
                                                            $file->get_filepath(),
                                                            $file->get_filename());
                $height = $info['height'];
            }

        }
    }
    if (!$imageurl) {
        // Not super that this is hardcoded, but avoids never leaving the space blank.
        $imageurl = $CFG->wwwroot . '/mod/via/pix/emailheaderimage.png';
        $height = '98';
    }

    $headercolor = get_config('mod_via', 'emailheadercolor');
    $bgcolor = get_config('mod_via', 'emailheaderbgcolor');
    $textcolor = get_config('mod_via', 'emailtextcolor');
    $linkcolor = get_config('mod_via', 'emaillinkcolor');
    $accesslinkcolor = get_config('mod_via', 'emailaccesslinkcolor');
    $site = get_site();
    $sitename = clean_param(format_string($site->fullname), PARAM_NOTAGS);

    $posthtml = '<head></head>';
    $posthtml .= "\n<body>\n\n";

    $coursename = $DB->get_record('course', array('id' => $courseid));

    if (! $cm = get_coursemodule_from_instance("via", $via->activityid, $courseid)) {
        $viaurlparam = 'viaid';
        $viaurlparamvalue = $via->activityid;
    } else {
        $viaurlparam = 'id';
        $viaurlparamvalue = $cm->id;
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

    $posthtml .= '<table style="font-family: Calibri,sans-serif; font-size:18px; color:'.$textcolor.';"
                     border="0" cellpadding="0" cellspacing="0" width="100%">';

    $posthtml .= '<tr height="'.$height.'">
                    <td style="background-color:'.$bgcolor.'; border-top:0; border-right:0;
                    border-left:0; border-bottom:0; padding;0;margin:0;"
                    bgcolor="'.$bgcolor.'" height="'.$height.'">
                        <img src="'.$imageurl.'" height="'.$height.'" style="display:block"/>
                    </td>
                </tr>';

    $posthtml .= '<tr height="35">
                    <td style="background-color:'.$bgcolor.';border-top:1px solid #fff; padding-left:10px;"
                     bgcolor="'.$bgcolor.'" height="35" >
                        <h2 style="font-family: Calibri,sans-serif; color:'.$headercolor.';">'.$sitename.'</h2>
                    </td>
                </tr>';

    $posthtml .= '<tr><td style="padding:10px">';
    $posthtml .= '<a style="font-family: Calibri,sans-serif; font-size:18px; color:'.$linkcolor.'; "
                    target="_blank" href="'. $CFG->wwwroot.'/course/view.php?id='.$courseid.'">'.$coursename->shortname.'</a>
                      &raquo;  ';
        $posthtml .= '<a style="font-family: Calibri,sans-serif; font-size:18px; color:'.$linkcolor.'"
                    target="_blank" href="'. $CFG->wwwroot.'/mod/via/view.php?'. $viaurlparam.'='.$viaurlparamvalue.'">'.
                    $via->name.'</a>';
    $posthtml .= '</td></tr>';

    $posthtml .= '<tr><td style="padding:10px">';
    if ($via->activitytype == 2) {
        $posthtml .= '<div>'. get_string("inviteemailhtmlpermanent", "via", $a).'</div>';
    } else {
        $posthtml .= '<div>'. get_string("inviteemailhtml", "via", $a).'</div>';
    }
    $posthtml .= '</td></tr>';

    $posthtml .= '<tr><td style="border:1px solid #c5c5c5; margin-top:10px; padding:10px;">';
    $posthtml .= "<span style='font-weight:bold;'>".get_string("invitepreparationhtml", "via")."</span>";
    $posthtml .= "<div style='text-align:center;margin:10px;'>";
    $posthtml .= "
        <a href='" . $CFG->wwwroot ."/mod/via/view.assistant.php?redirect=7&".$viaurlparam."=". $viaurlparamvalue ."&courseid=".
        $via->course ."' style='background:".$linkcolor."; color:#fff; font-size:1.2em; text-decoration:none;
        border:8px solid ".$linkcolor."; margin-right:20px;'>
        <img src='" . $CFG->wwwroot ."/mod/via/pix/config.png' align='top'
        height='25px' width='25px' style='border:none; margin-right:5px;'>".
        get_string("configassist", "via")."</a>";

    $posthtml .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
    if (get_config('via', 'via_technicalassist_url') == null) {
        $posthtml .= "
            <a href='" . $CFG->wwwroot ."/mod/via/view.assistant.php?redirect=6&".$viaurlparam."=". $viaurlparamvalue .
            "&courseid=". $via->course ."'
            style='background:".$linkcolor."; color:#fff; font-size:1.2em;
            text-decoration:none; border:8px solid ".$linkcolor.";'>
            <img src='" . $CFG->wwwroot . "/mod/via/pix/assistance.png'
            align='top' height='25px' width='25px' style='border:0;  margin-right:5px;'>".
            get_string("technicalassist", "via")."</a>";
    } else {
        $posthtml .= "
            <a href='" . get_config('via', 'via_technicalassist_url')."?redirect=6&".$viaurlparam."=". $viaurlparamvalue .
            "&courseid=". $via->course ."'
            style='background:".$linkcolor."; color:#fff; font-size:1.2em;
            text-decoration:none; border:8px solid ".$linkcolor.";'>
            <img src='" . $CFG->wwwroot . "/mod/via/pix/assistance.png'
            align='top' height='25px' width='25px' style='border:0; margin-right:5px;'>".
            get_string("technicalassist", "via")."</a>";
    }
    $posthtml .= "</div>";
    $posthtml .= '</td><td>';

    $posthtml .= '<tr><td  style="border:1px solid #c5c5c5; margin-top:10px; padding:10px;">';
    $posthtml .= "<span style='font-weight:bold;'>".get_string("invitewebaccesshtml", "via")."</span>
    <br/><br/>".get_string("inviteclicktoaccesshtml", "via")."";

    $posthtml .= "<div style='text-align:center'>";
        $posthtml .= "<a href='".$CFG->wwwroot."/mod/via/view.php?".$viaurlparam."=".$viaurlparamvalue."'
        style='background:".$accesslinkcolor."; color:#fff; font-size:1.2em; text-decoration:none;
        border:8px solid ".$accesslinkcolor.";'>
        <img src='" . $CFG->wwwroot ."/mod/via/pix/access.png' align='top'
        height='25px' width='25px' style='border:0; margin-right:5px;'>".
        get_string("gotoactivity", "via")."</a>";

    $posthtml .= "<p><br/>
        <a href=". $CFG->wwwroot."/mod/via/view.php?".$viaurlparam."=".$viaurlparamvalue ."
        style='color:".$linkcolor."'>".
            $CFG->wwwroot."/mod/via/view.php?".$viaurlparam."=".$viaurlparamvalue .
        "</a></p>";

    $posthtml .= "</div>";
    $posthtml .= '</td><td>';

    $posthtml .= '<tr><td>';
    $posthtml .= get_string("invitewarninghtml", "via");

    $posthtml .= '</table>'."\n\n";

    $posthtml .= '</body>';

    return $posthtml;
}

/**
 * Build the invitation mail html that will be sent be the cron
 *
 * @param integer $courseid
 * @param object $via
 * @param object $muser
 * @param boolean is reminder or invite message
 * @return html mail
 */
function via_make_notice_mail_html($a, $muser) {
    global $CFG, $DB;

    if ($muser->mailformat != 1) {// Needs to be HTML.
        return '';
    }

    $posthtml = '<head></head>';
    $posthtml .= "\n<body>\n\n";

    $posthtml .= '<div style="font-family: Calibri,sans-serif;">';
    $posthtml .= '<a target="_blank" href="'.$CFG->wwwroot.'/course/view.php?id='.$a->courseid.'">'.$a->coursename.'</a>';
    $posthtml .= ' &raquo; <a target="_blank"
                   href="'.$CFG->wwwroot.'/mod/via/index.php?id='.$a->courseid.'">'.$a->modulename.'</a> &raquo; ';
    $posthtml .= '<a target="_blank"
                  href="'.$CFG->wwwroot.'/mod/via/view.php?'.$a->viaurlparam.'='.$a->viaurlparamvalue.'">'.$a->activitytitle.'</a>';
    $posthtml .= '</div>';
    $posthtml .= '<table border="0" cellpadding="3" cellspacing="0" style="font-family: Calibri,sans-serif; color:#505050">';
    $posthtml .= '<tr><td>'. get_string("noticeemailsubject", "via") .'</td></tr>';
    $posthtml .= '<tr><td>'. get_string("noticeemailhtml", "via", $a) .'</td></tr>';
    $posthtml .= '<tr><td><br/>'. get_string("noticeclicktoaccesshtml", "via") .'</td></tr>';
    $posthtml .= '<tr><td>';
    $posthtml .= "<a style='color:#fff; text-decoration:none; background:#6ab605; padding:8px;'
                  href='".$CFG->wwwroot."/mod/via/view.php?".$a->viaurlparam."=".$a->viaurlparamvalue."' >
                  <img style='vertical-align:middle'
                  src='" . $CFG->wwwroot ."/mod/via/pix/access_small.png' hspace='5' height='14px' width='15px'>".
        get_string("gotorecording", "via")."</a>";
    $posthtml .= '</td></tr>';

    $posthtml .= '<tr><td>'.$CFG->wwwroot."/mod/via/view.php?".$a->viaurlparam."=".$a->viaurlparamvalue.'</td></tr>';

    $posthtml .= '</table>'."\n\n";

    $posthtml .= '</body>';

    return $posthtml;
}

/**
 * Build the activity notification mail html that will be sent be the cron
 *
 * @param object $a
 * @param object $muser
 * @return html mail
 */
function via_make_notification_mail_html($a, $muser) {
    global $CFG, $DB;

    if ($muser->mailformat != 1) {// Needs to be HTML.
        return '';
    }

    $posthtml = '<head></head>';
    $posthtml .= "\n<body>\n\n";

    $posthtml .= '<div style="font-family: Calibri,sans-serif;">';
    $posthtml .= '<a target="_blank" href="'.$CFG->wwwroot.'/course/view.php?id='.$a->courseid.'">'.$a->coursename.'</a>';
    $posthtml .= ' &raquo; <a target="_blank"
                   href="'.$CFG->wwwroot.'/mod/via/index.php?id='.$a->courseid.'">'.$a->modulename.'</a> &raquo; ';
    $posthtml .= '<a target="_blank" href="'.
                 $CFG->wwwroot.'/mod/via/view.php?'.$a->viaurlparam.'='.$a->viaurlparamvalue.'">'.$a->activitytitle.'</a>';
    $posthtml .= '</div>';
    $posthtml .= '<table border="0" cellpadding="3" cellspacing="0" style="font-family: Calibri,sans-serif; color:#505050">';
    $posthtml .= '<tr><td>'. get_string("notificationemailsubject", "via") .'</td></tr>';
    $posthtml .= '<tr><td>'. get_string("notificationemailhtml", "via", $a) .'</td></tr>';
    $posthtml .= '<tr><td><br/>'. get_string("noticeclicktoaccesshtml", "via") .'</td></tr>';
    $posthtml .= '<tr><td>';
    $posthtml .= "<a style='color:#fff; text-decoration:none; background:#6ab605; padding:8px;'
                  href='".$CFG->wwwroot."/mod/via/view.php?".$a->viaurlparam."=".$a->viaurlparamvalue."' >
                  <img style='vertical-align:middle'
                  src='" . $CFG->wwwroot ."/mod/via/pix/access_small.png' hspace='5' height='14px' width='15px'>".
                  get_string("gotorecording", "via")."</a>";
    $posthtml .= '</td></tr>';

    $posthtml .= '<tr><td>'.$CFG->wwwroot."/mod/via/view.php?".$a->viaurlparam."=".$a->viaurlparamvalue.'</td></tr>';

    $posthtml .= '</table>'."\n\n";

    $posthtml .= '</body>';

    return $posthtml;
}

/**
 * Gets user type for automatic enrollement
 *
 * @param integer $userid
 * @param integer $courseid
 * @param boolean noparticipants, if true all users will be added as animators
 * @return integer representing the user's type for the activity within the course.
 */
function via_user_type($userid, $courseid, $noparticipants = null) {
    global $DB, $CFG;

    if ($noparticipants) {
        $noparticipants = $noparticipants;
    } else {
        $noparticipants = "0";
    }

    $context = via_get_course_instance($courseid);
    if (has_capability('moodle/course:viewhiddenactivities', $context, $userid) || $noparticipants == "1") {
        $type = '3';// Animator!
    } else {
        $type = '1';// Participant!
    }

    return $type;
}

/**
 * Validates the API version with plugin version
 *
 * @param string $required
 * @param string $buildversion
 * @return true or false
 */
function via_validate_api_version($required, $buildversion) {
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
 * Validates which type of access button to add, with with text and permissions
 *
 * @param boolean $recordingmode
 * @param boolean $active
 * @param interger $cmid context id
 * @param boolean $preperation
 * @param boolean $forceaccess
 * @return html link
 */
function via_add_button($recordingmode,
                        $active = null,
                        $cmid = null,
                        $preperation = null,
                        $forceaccess = null,
                        $viaurlparam = 'id') {
    global $CFG;

    if ($forceaccess) {
        // Foreced access can be used for recordings, but also for admins that are not enrolled in the activity.
        $fa = '&fa=1';
    } else {
        $fa = '';
    }

    if ($recordingmode) {
        if ($active) {
            $id = 'id="active"';
            $class = "active hide";
            $url = '/mod/via/view.via.php?'.$viaurlparam.'='. $cmid . $fa;
            $script = 'target="viaaccess" onclick="this.target=\'viaaccess\';
            return openpopup(null, {url:\''.$url.'\',
            name:\'viaaccess\', options:\'menubar=0,location=0,,resizable=1,scrollbars\'});"';
        } else {
            $id = 'id="inactive"';
            $class = "inactive";
            $url = '';
            $script = '';
        }
    } else {
        $id = '';
        $class = '';
        $url = '/mod/via/view.via.php?'.$viaurlparam.'='. $cmid . $fa;
        $script = 'target="viaaccess" onclick="this.target=\'viaaccess\';
        return openpopup(null, {url:\''.$url.'\', name:\'viaaccess\', options:\'menubar=0,location=0,,resizable=1,scrollbars\'});"';
    }
    if ($preperation) {
        $text = get_string("prepareactivity", "via");
    } else {
        $text = get_string("gotoactivity", "via");
    }

    $link = '<input type="button" '.$id.' '.$class.'" href="'.$url.'" '.$script.' value="'.$text.'" />';

    return $link;
}

/**
 * Validates if the user is on a mobile device
 * if on a mobile device the VIA moobile application should be used
 *
 * @return true or false
 */
function is_mobile_phone() {
    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
    $mobiles = array("iphone", "ipod", "blackberry", "nokia", "phone",
        "mobile safari", "iemobile", "ipad", "android");
    foreach ($mobiles as $mobile) {
        if (strpos($ua, $mobile)) {
            return true;
        }
    }
    return false;
}

/**
 * Creates html button to call presence status printable report
 *
 * @param integer $viaid
 * @return html string
 */
function via_get_report_btn($viaid) {
    $btn = '<div class="reportbtndiv">';
    $btn .= '<a class="reportbtn" href="presence.php?id='.$viaid.'"
            onclick="window.open(this.href, \'Presence\', \'toolbar=yes, scrollbars=1, width=800, height=800\');
            return false;" >';
    $btn .= get_string('report', 'via').'</a></div>';

    return $btn;
}

/**
 * Gets printable time from Unix time for display in tables
 *
 * @param integer $time unix time
 * @return html string (00:00:00) hh mm ss
 */
function via_get_converted_time($time) {
    $minutes = floor($time);
    $decimals = $time - $minutes;
    $seconds = ($decimals * 60);
    $s = round($seconds);
    if ($minutes >= 60) {
        $hours = $minutes / 60;
        $h = floor($hours) . ':';
    } else {
        $h = '';
    }
    if (isset($hours)) {
        $m = ($minutes - (floor($hours) * 60));
    } else {
        $m = $minutes;
    }
    if ($m < 10) {
        $m = '0'.$m;
    }
    if ($s < 10) {
        $s = '0'.$s;
    }

    $duration = $h . $m .':'. $s;

    return $duration;
}

/**
 * Gets html string to display the user type.
 *
 * @param integer$type user type within the activity
 * @return html string with image and string
 */
function via_get_role($type) {
    global $CFG;

    if ($type == "1") {
        $role = '<img src="' . $CFG->wwwroot . '/mod/via/pix/participant.png" width="25" height="25"
                              alt="participant" style="vertical-align: bottom;" /> ' . get_string("participant", "via");
    } else if ($type == "2") {
        $role = '<img src="' . $CFG->wwwroot . '/mod/via/pix/presentor.png" width="25" height="25"
                              alt="host" style="vertical-align: bottom;" /> ' . get_string("host", "via");
    } else {
        $role = '<img src="' . $CFG->wwwroot . '/mod/via/pix/animator.png" width="25" height="25"
                              alt="animator" style="vertical-align: bottom;" /> ' . get_string("animator", "via");
    }

    return $role;
}

function via_get_profilname($profilnameorig) {
    $profilname = $profilnameorig;
    try {
        $str = htmlentities($profilname, ENT_NOQUOTES, 'UTF-8');

        $str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
        $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str); // Pour les ligatures e.g. '&oelig;' !
        $str = preg_replace('#&[^;]+;#', '', $str); // Supprime les autres caractères.

        $str = str_replace(' ', '', $str);

        $profilname = get_string($str, 'via');
    } catch (exception $e) {
        $profilname = $profilnameorig;
    }

    return $profilname;
}