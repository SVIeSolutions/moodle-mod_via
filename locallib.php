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
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
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

    $deleted = $DB->get_records_sql('SELECT u.id
                                    FROM {user} u
                                    LEFT JOIN {via_users} vu ON vu.userid = u.id
                                    WHERE u.deleted = 1 AND vu.id IS NOT null' );

    foreach ($deleted as $vuser) {

        $activities = $DB->get_records_sql('SELECT v.id, v.viaactivityid
                                            FROM {via_participants} vp
                                            LEFT JOIN {via} v ON v.id = vp.activityid
                                            WHERE vp.userid = '. $vuser->id);
        $api = new mod_via_api();

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
            notify(get_string("error:".$e->getMessage(), "via"));
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
function via_synch_participants() {
    global $DB, $CFG;

    $result = true;

    $via = $DB->get_record('modules', array('name' => 'via'));
    $lastcron = $via->lastcron;

    // Add participants (with student roles only) that are in the ue table but not in via.
    $sql = 'from {user_enrolments} ue
        left join {enrol} e on ue.enrolid = e.id
        left join {via} v on e.courseid = v.course
        left join {via_participants} vp on vp.activityid = v.id AND ue.userid = vp.userid
        left join {context} c on c.instanceid = e.courseid
        left join {role_assignments} ra on ra.contextid = c.id AND ue.userid = ra.userid';
    $where = 'where (vp.activityid is null OR ra.timemodified > '.$lastcron.' )
            and c.contextlevel = 50 and v.enroltype = 0 and e.status = 0 and v.enroltype = 0 and v.groupingid = 0';

    $ners = $DB->get_recordset_sql('select distinct ue.userid, e.courseid, v.id as viaactivity, v.noparticipants '.
        $sql.' '.$where, null, $limitfrom = 0, $limitnum = 0);

    // Add users from automatic enrol type.
    foreach ($ners as $add) {
        try {
            $type = via_user_type($add->userid, $add->courseid, $add->noparticipants);

        } catch (Exception $e) {
            notify("error:".$e->getMessage());
        }
        try {
            if ($type != 2) { // Only add participants and animators.
                via_add_participant($add->userid, $add->viaactivity, $type);
            }

        } catch (Exception $e) {
            notify("error:".$e->getMessage());
        }
    }

    // Add users from group synch.
    $newgroupmemberssql = ' FROM {via} v
                            LEFT JOIN {groupings_groups} gg ON v.groupingid = gg.groupingid
                            LEFT JOIN {groups_members} gm ON gm.groupid = gg.groupid
                            LEFT JOIN {via_participants} vp ON vp.activityid = v.id AND vp.userid = gm.userid ';
    $newgroupmemberswhere = ' WHERE v.groupingid != 0 AND vp.id is null ';

    $newgroupmembers = $DB->get_recordset_sql('select distinct v.id as activityid, v.course, v.noparticipants, gm.userid
                                            '.$newgroupmemberssql.' '.$newgroupmemberswhere);

    foreach ($newgroupmembers as $add) {
        try {
            $type = via_user_type($add->userid, $add->course, $add->noparticipants);
        } catch (Exception $e) {
            notify("error:".$e->getMessage());
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
            notify("error:".$e->getMessage());
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
        notify(get_string("error:".$e->getMessage(), "via"));
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
    // added as animator or presentor without being enrolled in the course.
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

    return "<td style='text-align:center'><img src='" . $CFG->wwwroot . "/mod/via/pix/".$confirmimg."' width='16'
    height='16' alt='".$confirmtitle . "' title='".$confirmtitle . "' align='absmiddle'/></td>";

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
                                                        WHERE profilid = \''.$exists->value.'\'
                                                        AND datebegin > ' . time() .' OR activitytype = 2');
                            foreach ($vias as $via) {
                                $via->profilid = $info['ProfilID'];
                                $DB->update_record('via', $via);
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
        notify(get_string("error:".$e->getMessage(), "via"));
    }

    return $result;
}

/**
 * Get the via version restriction available for the company on via
 *
 * @return integer
 */
function via_get_cieinfo() {
    global $DB;

    $api = new mod_via_api();

    try {
        $response = $api->cieinfo();
        if ($response) {
            $exists = $DB->get_record('via_params', array('param_type' => 'viaversion'));

            $param = new stdClass();
            $param->param_type = 'viaversion';
            $param->param_name = null;
            $param->value = $response["ViaVersionRestriction"];
            $param->timemodified = time();

            if ($exists) {
                if ($exists->value != $response["ViaVersionRestriction"]) {
                    $param->id = $exists->id;
                    $DB->update_record('via_params', $param);
                }
            } else {
                $newparam = $DB->insert_record('via_params', $param);
            }
        }
    } catch (Exception $e) {
        $result = false;
        notify(get_string("error:".$e->getMessage(), "via"));
    }

}

/**
 * Get all the playbacks for an acitivity
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
                                $playbacksmoodle[$breakout['PlaybackID']]->isdownloadable = $breakout['IsDownloadable'];
                                $playbacksmoodle[$breakout['PlaybackID']]->hasfullvideo = $breakout['HasFullVideoRecord'];
                                $playbacksmoodle[$breakout['PlaybackID']]->hasmobilevideo = $breakout['HasMobileVideoRecord'];
                                $playbacksmoodle[$breakout['PlaybackID']]->hasaudiorecord = $breakout['HasAudioRecord'];
                            } else {
                                foreach ($breakout as $bkout) {
                                    if (gettype($bkout) == "array") {
                                        $playbacksmoodle[$bkout['PlaybackID']] = new stdClass();
                                        $playbacksmoodle[$bkout['PlaybackID']]->title = $bkout['Title'];
                                        $playbacksmoodle[$bkout['PlaybackID']]->duration = $bkout['Duration'];
                                        $playbacksmoodle[$bkout['PlaybackID']]->creationdate = $bkout['CreationDate'];
                                        $playbacksmoodle[$bkout['PlaybackID']]->ispublic = $bkout['IsPublic'];
                                        $playbacksmoodle[$bkout['PlaybackID']]->playbackrefid = $bkout['PlaybackRefID'];
                                        $playbacksmoodle[$bkout['PlaybackID']]->isdownloadable = $bkout['IsDownloadable'];
                                        $playbacksmoodle[$bkout['PlaybackID']]->hasfullvideo = $bkout['HasFullVideoRecord'];
                                        $playbacksmoodle[$bkout['PlaybackID']]->hasmobilevideo = $bkout['HasMobileVideoRecord'];
                                        $playbacksmoodle[$bkout['PlaybackID']]->hasaudiorecord = $bkout['HasAudioRecord'];
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
                    $playbacksmoodle[$playback['PlaybackID']]->isdownloadable = $playback['IsDownloadable'];
                    $playbacksmoodle[$playback['PlaybackID']]->hasfullvideo = $playback['HasFullVideoRecord'];
                    $playbacksmoodle[$playback['PlaybackID']]->hasmobilevideo = $playback['HasMobileVideoRecord'];
                    $playbacksmoodle[$playback['PlaybackID']]->hasaudiorecord = $playback['HasAudioRecord'];
                }
            }
        }
    }

    return $playbacksmoodle;

}

/**
 * Button to call presence report
 *
 * @param integer $viaid
 * @return html string to display button
 */
function via_report_btn($viaid) {

    $btn = '<div class="reportbtndiv">';
    $btn .= '<a class="reportbtn" href="presence.php?id='.$viaid.'"
    onclick="window.open(this.href, \'Presence\', \'toolbar=yes, scrollbars=1, width=800, height=800\'); return false;" >';
    $btn .= get_string('report', 'via').'</a></div>';

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
    $table->attributes['class'] = 'generaltable boxaligncenter';
    $table->width = '90%';
    $table->tablealign = 'center';
    $table->cellpadding = 5;
    $table->cellspacing = 0;

    if ($presence) {
        $table->id = 'viapresence';
        if ($via->recordingmode != 0) {
            $table->head  = array (get_string("role", "via"),
                get_string("lastname").', '.get_string("firstname"),
                get_string("email"),
                get_string("presenceheader", "via"),
                get_string("playbackheader", "via")
                );
            $table->align = array ('left', 'left', 'left', 'center', 'center');
        } else {
            $table->head  = array (get_string("role", "via"),
                get_string("lastname").', '.get_string("firstname"),
                get_string("email"),
                get_string("presenceheader", "via"),
                );
            $table->align = array ('left', 'left', 'left', 'center');
        }

    } else {
        $table->id = 'viaparticipants';
        if (get_config('via', 'via_participantmustconfirm') && $via->needconfirmation) {
            $table->head  = array (get_string("role", "via"),
                get_string("lastname").', '.get_string("firstname"),
                get_string("email"),
                get_string("confirmationstatus", "via"),
                get_string("config", "via"));
            $table->align = array ('left', 'left', 'left', 'center', 'center');

        } else {
            $table->head  = array (get_string("role", "via"),
                get_string("lastname").', '.get_string("firstname"),
                get_string("email"),
                get_string("config", "via"));
            $table->align = array ('left', 'left', 'left', 'center');

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
    echo "<h2 class='main'>".$string." (".$total.")</h2>";

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
                vp.connection_duration, vp.playback_duration
                FROM {via_participants} vpp
                LEFT JOIN {via_users} vu ON vu.userid = vpp.userid
                LEFT JOIN {via} v ON v.id = vpp.activityid
                LEFT JOIN {user} u ON u.id = vpp.userid
                LEFT JOIN {via_presence} vp ON vpp.userid = vp.userid AND vpp.activityid = vp.activityid
                WHERE vpp.activityid = '.$via->id.' ORDER BY (vpp.participanttype+1)%3, u.lastname ASC ';
    } else {
        $sql = 'SELECT vp.*, u.firstname, u.lastname, u.email, vu.viauserid, vu.setupstatus
                FROM {via_participants} vp
                LEFT JOIN {user} u ON u.id = vp.userid
                LEFT JOIN {via_users} vu ON vu.userid = u.id ' .$where;
    }

    try {
        $participantslist = $DB->get_records_sql($sql, null, $offset * $limit, $limit);

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

                if ($via->needconfirmation) {
                    $info2 = via_get_confirmationstatus($participant->confirmationstatus);
                }
                if ((!$presence && isset($info2)) || ($presence && $via->recordingmode != 0)) {
                    $table->data[] = array ($role, $userlink, $participant->email, $info1, $info2);
                } else {
                    $table->data[] = array ($role, $userlink, $participant->email, $info1);
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

    $api = new mod_via_api();

    // We only update the information if the status was not already calcultated and
    // that we don't need to update the recording information!
    if (!isset($participant->status) || $participant->recordingmode != 0) {
        if ($participant->viauserid) {
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
                $presence->connection_duration = $userlog['ConnectionDuration'];
                $presence->playback_duration = $userlog['PlaybackDuration'];
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
        $playback = "";
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
function via_get_playbacks_table($playbacks, $via, $context) {
    global $CFG;

    $cmid = $context->instanceid;

    $table = "<table cellpadding='2' cellspacing='0' class='generaltable boxaligncenter' id='via_recordings'>";
    $formname = 0;
    foreach ($playbacks as $key => $playback) {
        // Lists all playbacks for acitivity.
        if (isset($playback->playbackrefid)) {
            $style = "atelier";
            $li = "<li style='list-style-image:url(".$CFG->wwwroot."/mod/via/pix/arrow.gif);'>";
            $endli = "</li>";
        } else {
            $style = "";
            $li = "";
            $endli = "";
        }

        $private = $playback->ispublic ? "" : "dimmed_text";

        if ($playback->ispublic || has_capability('mod/via:manage', $context)) {
            // If playback is public and/or if user is animator or presentator.
            if (!isset($header)) {// We only display it once.
                $header = "<h2 class='main'>".get_string("recordings", "via")."</h2>";
                echo $header;
            }
            $table .= "<tr class='$style'>";

            $table .= "<td class='title $style  $private'>$li";

            $table .= $playback->title;

            $table .= "$endli</td>";

            $table .= "<td class='duration  $private' style='text-align:left'>".
                userdate(strtotime($playback->creationdate))."<br/> ".
                get_string("timeduration", "via")." ".gmdate("H:i:s",  $playback->duration)."</td>";

            if (has_capability('mod/via:manage', $context)) {

                if ($playback->ispublic == 1) {
                    $checked = get_string("show", "via");
                    $ispublic = 0;
                    $class = 'showvia';
                } else {
                    $checked = get_string("mask", "via");
                    $ispublic = 1;
                    $class = 'maskvia';
                }

                $table .= "<td class='modify $private'>";
                // Show or hide the recording!
                $table .= '<a class="'.$class.'" href="edit_review.php?id='.$via->id.'&playbackid='.
                    urlencode($key).'&edit='.get_string('save', 'via').'&ispublic='.$ispublic.'">'.$checked.'</a><br/>';
                // Modify the name or permit the downloads.
                $table .= '<a class="modify" href="edit_review.php?id='.$via->id.'&playbackid='.
                    urlencode($key).'&edit=edit">'.get_string("edit", "via").'</a><br/>';
                // Delete recording!
                $table .= '<a class="deleteplayback" href="edit_review.php?id='.$via->id.'&playbackid='.
                    urlencode($key).'&edit=del">'.get_string("delete", "via").'</a>';
                $table .= "</td>";
            }

            if (get_config('via', 'via_downloadplaybacks')) {
                if ($playback->isdownloadable) {
                    $privaterecord = "";
                } else {
                    if (has_capability('mod/via:manage', $context)) {
                        $privaterecord = "dimmed_text";
                        $fa = '&fa=1';
                    } else {
                        $privaterecord = "hide";
                        $fa = '';
                    }
                }

                $table .= "<td class='download nowrap $private $privaterecord'>";

                if ($playback->hasfullvideo == 1) {
                    $table .= '<a class="download" href="download_recording.php?id='.$cmid.$fa.'&type=1&playbackid='.
                    urlencode($key).'" title="'. get_string('fullvideoinfo', 'via') .'">'.
                    get_string('fullvideo', 'via') .'</a><br/>';
                } else {
                    $table .= '<span class="dimmed_text">'.get_string('fullvideo', 'via').'</span><br/>';
                }
                if ($playback->hasmobilevideo == 1) {
                    $table .= '<a class="download" href="download_recording.php?id='.$cmid.$fa.'&type=2&playbackid='.
                    urlencode($key).'" title="'. get_string('mobilevideoinfo', 'via') .'">'.
                    get_string('mobilevideo', 'via') .'</a><br/>';
                } else {
                    $table .= '<span class="dimmed_text">'. get_string('mobilevideo', 'via').'</span><br/>';
                }
                if ($playback->hasaudiorecord == 1) {
                    $table .= '<a class="download" href="download_recording.php?id='.$cmid.$fa.'&type=3&playbackid='.
                    urlencode($key).'" title="'. get_string('audiorecordinfo', 'via') .'">'.
                    get_string('audiorecord', 'via') .'</a>';
                } else {
                    $table .= '<span class="dimmed_text">'.get_string('audiorecord', 'via').'</span><br/>';
                }

                $table .= "</td>";
            }

            $table .= "<td class='review $private'>";
            if (has_capability('mod/via:manage', $context)) {
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

            $table .= '<a class="viaaccessbutton" target="viewplayback" href="view.via.php"
                            onclick="this.target=\'viewplayback\';
                            return openpopup(null, {url:\'/mod/via/view.via.php?id='.$cmid.'&playbackid='.
                urlencode($key).'&review=1'.$param.'\',
                            name:\'viewplayback\', options:\'menubar=0,location=0,scrollbars=yes,resizable=yes\'});">
                            <img src="' . $CFG->wwwroot . '/mod/via/pix/access.png"
                            width="25" height="25" alt="' .$text . '" />'. $text .'</a>';

            $table .= "</td>";
            $table .= "</tr>";
        }
        $formname = $formname + 1;
    }

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
 * Get the presenter for an activity
 *
 * @param integer $activityid
 * @return object containing the user's information
 */
function  via_get_presenter($activityid) {
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

    $strvia = get_string('modulename', 'via');

    $posthtml = '<head></head>';
    $posthtml .= "\n<body>\n\n";

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

    $posthtml .= '<div style="font-family: Calibri,sans-serif;">';
    $posthtml .= '<a target="_blank" href="'.$CFG->wwwroot.'/course/view.php?id='.$courseid.'">'.$coursename->shortname.'</a>';
    $posthtml .= ' &raquo; <a target="_blank" href="'.$CFG->wwwroot.'/mod/via/index.php?id='.$courseid.'">'.$strvia.'</a> &raquo; ';
    $posthtml .= '<a target="_blank" href="'.$CFG->wwwroot.'/mod/via/view.php?id='.$cm->id.'">'.$via->name.'</a>';
    $posthtml .= '</div>';
    $posthtml .= '<table border="0" cellpadding="3" cellspacing="0">';
    $posthtml .= '<tr><td></td>';
    $posthtml .= '<td>';

    $b = new stdClass();
    $b->title = $a->title;

    if (!$reminder) {
        $posthtml .= '<div>'.get_string("inviteemailsubject", "via", $b).'</div>';
    } else {
        $posthtml .= '<div>'.get_string("reminderemailsubject", "via", $b).'</div>';
    }

    $posthtml .= '<div>'.userdate(time()).'</div>';

    $posthtml .= '</td></tr>';

    $posthtml .= '<tr><td valign="top">';
    $posthtml .= '&nbsp;';

    $posthtml .= '</td><td>';

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

    if (get_config('via', 'via_technicalassist_url') == null) {
        $posthtml .= "<a href='" . $CFG->wwwroot ."/mod/via/view.assistant.php?redirect=6&viaid=". $via->activityid .
            "&courseid=". $via->course ."' style='background:#5c707c; padding:8px 10px;
            color:#fff; text-decoration:none; font-family: Calibri,sans-serif;' >
            <img src='" . $CFG->wwwroot . "/mod/via/pix/assistance.png' align='absmiddle' hspace='5' height='27px' width='27px'>".
            get_string("technicalassist", "via")."</a>";
    } else {
        $posthtml .= "<a href='" . get_config('via', 'via_technicalassist_url')."?redirect=6&viaid=". $via->activityid .
            "&courseid=". $via->course ."' style='background:#5c707c; padding:8px 10px;
            color:#fff; text-decoration:none; font-family: Calibri,sans-serif;' >
            <img src='" . $CFG->wwwroot . "/mod/via/pix/assistance.png' align='absmiddle' hspace='5' height='27px' width='27px'>".
            get_string("technicalassist", "via")."</a>";
    }

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
                   href="'.$CFG->wwwroot.'/mod/via/index.php?id='.$a->courseid.'">'.$a->activitytitle.'</a> &raquo; ';
    $posthtml .= '<a target="_blank" href="'.$CFG->wwwroot.'/mod/via/view.php?id='.$a->cmid.'">'.$a->activitytitle.'</a>';
    $posthtml .= '</div>';
    $posthtml .= '<table border="0" cellpadding="3" cellspacing="0" style="font-family: Calibri,sans-serif; color:#505050">';
    $posthtml .= '<tr><td>'. get_string("noticeemailsubject", "via") .'</td></tr>';
    $posthtml .= '<tr><td>'. get_string("noticeemailhtml", "via", $a) .'</td></tr>';
    $posthtml .= '<tr><td><br/>'. get_string("noticeclicktoaccesshtml", "via") .'</td></tr>';
    $posthtml .= '<tr><td>';
    $posthtml .= "<a style='color:#fff; text-decoration:none; background:#6ab605; padding:8px;'
                  href='".$CFG->wwwroot."/mod/via/view.php?id=".$a->cmid."' >
                  <img style='vertical-align:middle'
                  src='" . $CFG->wwwroot ."/mod/via/pix/access_small.png' hspace='5' height='14px' width='15px'>".
                  get_string("gotorecording", "via")."</a>";
    $posthtml .= '</td></tr>';

    $posthtml .= '<tr><td>'.$CFG->wwwroot."/mod/via/view.php?id=".$a->cmid .'</td></tr>';

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
function via_add_button($recordingmode, $active = null, $cmid = null, $preperation = null, $forceaccess = null) {
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
            $url = '/mod/via/view.via.php?id='. $cmid . $fa;
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
        $url = '/mod/via/view.via.php?id='. $cmid . $fa;
        $script = 'target="viaaccess" onclick="this.target=\'viaaccess\';
        return openpopup(null, {url:\''.$url.'\', name:\'viaaccess\', options:\'menubar=0,location=0,,resizable=1,scrollbars\'});"';
    }
    if ($preperation) {
        $text = get_string("prepareactivity", "via");
    } else {
        $text = get_string("gotoactivity", "via");
    }

    $link = '<a '.$id.' class="viaaccessbutton '.$class.'" href="'.$url.'" '.$script.'>
            <img src="' . $CFG->wwwroot . '/mod/via/pix/access.png" width="27" height="27"
            alt="'.$text. '" title="'.$text. '" space="5"/>'
            .$text.'</a>';

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
                              alt="presentor" style="vertical-align: bottom;" /> ' . get_string("presentator", "via");
    } else {
        $role = '<img src="' . $CFG->wwwroot . '/mod/via/pix/animator.png" width="25" height="25"
                              alt="animator" style="vertical-align: bottom;" /> ' . get_string("animator", "via");
    }

    return $role;

}
