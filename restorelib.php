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


function via_restore_mods($mod, $restore) {
    global $CFG, $DB;

    $allvalues = array();

    $status = true;
    $restoreuserdata = restore_userdata_selected($restore, 'via', $mod->id);

    // Get record from backup_ids.
    $data = backup_getid($restore->backup_unique_code, $mod->modtype, $mod->id);
    if ($data) {
        // Now get completed xmlized object.
        $info = $data->info;

        // Check of older backupversion of via.
        $version = intval(backup_todb($info['MOD']['#']['VERSION']['0']['#']));

        // Now, build the via record structure.
        $via->course = $restore->course_id;
        $via->name = backup_todb($info['MOD']['#']['NAME']['0']['#']);
        $via->intro = backup_todb($info['MOD']['#']['DESCRIPTION']['0']['#']);
        $via->creator = backup_todb($info['MOD']['#']['CREATOR']['0']['#']);
        $via->viaactivityid = backup_todb($info['MOD']['#']['VIAACTIVITYID']['0']['#']);
        $via->datebegin = backup_todb($info['MOD']['#']['DATEBEGIN']['0']['#']);
        $via->duration = backup_todb($info['MOD']['#']['DURATION']['0']['#']);
        $via->audiotype = backup_todb($info['MOD']['#']['AUDIOTYPE']['0']['#']);
        $via->recordingmode = backup_todb($info['MOD']['#']['RECORDINGMODE']['0']['#']);
        $via->recordmodebehavior = backup_todb($info['MOD']['#']['RECORDMODEBEHAVIOR']['0']['#']);
        $via->isreplayallowed = backup_todb($info['MOD']['#']['ISREPLAYALLOWED']['0']['#']);
        $via->private = backup_todb($info['MOD']['#']['PRIVATE']['0']['#']);
        $via->profilid = backup_todb($info['MOD']['#']['PROFILID']['0']['#']);
        $via->activitytype = backup_todb($info['MOD']['#']['ACTIVITYTYPE']['0']['#']);
        $via->remindertime = backup_todb($info['MOD']['#']['REMINDERTIME']['0']['#']);
        $via->needconfirmation = backup_todb($info['MOD']['#']['NEEDCONFIRMATION']['0']['#']);
        $via->roomtype = backup_todb($info['MOD']['#']['ROOMTYPE']['0']['#']);
        $via->waitingroomaccessmode = backup_todb($info['MOD']['#']['WAITINGROOMACCESSMODE']['0']['#']);
        $via->activitystate = backup_todb($info['MOD']['#']['ACTIVITYSTATE']['0']['#']);
        $via->grade = backup_todb($info['MOD']['#']['GRADE']['0']['#']);
        $via->enroltype = backup_todb($info['MOD']['#']['ENROLTYPE']['0']['#']);
        $via->mailed = backup_todb($info['MOD']['#']['MAILED']['0']['#']);
        $via->sendinvite = backup_todb($info['MOD']['#']['SENDINVITE']['0']['#']);
        $via->invitemsg = backup_todb($info['MOD']['#']['INVITEMSG']['0']['#']);
        $via->moodleismailer = backup_todb($info['MOD']['#']['MOODLEISMAILER']['0']['#']);
        $via->timecreated = backup_todb($info['MOD']['#']['TIMECREATED']['0']['#']);
        $via->timemodified = backup_todb($info['MOD']['#']['TIMEMODIFIED']['0']['#']);

        // The structure is equal to the db, so insert the via.
        $newid = $DB->insert_record ("via", $via);

        // Create events!
        // The open-event!
        if ($via->datebegin > time()) {
            $event = null;

            $event->name        = $via->name;
            $event->description = $via->intro;
            $event->courseid    = $via->course;
            $event->groupid     = 0;
            $event->userid      = 0;
            $event->modulename  = 'via';
            $event->instance    = $newid;
            $event->eventtype   = 'due';
            $event->timestart   = $via->datebegin;
            $event->visible      = instance_is_visible('via', $via);

            if ($via->datebegin > 0) {
                $event->timeduration = $via->duration * 60;
            } else {
                $event->timeduration = 0;
            }

            add_event($event);
        }

        // Do some output.
        echo "<ul><li>".get_string("modulename", "via")." \"".$via->name."\"<br />";
        backup_flush(300);

        if ($newid) {
            // Now check if want to restore user data and do it.
            if ($restoreuserdata) {
                echo "<ul><li>".get_string("subscribeparticipants", "via")."</li></ul>";
                $items = $info['MOD']['#']['PARTICIPANTS']['0']['#']['PARTICIPANT'];
                for ($i = 0; $i < count($items); $i++) {
                    $valueinfo = $items[$i];
                    $value->id = '';
                    $value->activityid = $newid;
                    $value->userid = backup_todb($valueinfo['#']['USERID']['0']['#']);
                    $value->participanttype = backup_todb($valueinfo['#']['PARTICIPANTTYPE']['0']['#']);
                    $value->confirmationstatus = backup_todb($valueinfo['#']['CONFIRMATIONSTATUS']['0']['#']);
                    // Put this new value into the database.
                    require_once($CFG->dirroot . '/mod/via/lib.php');
                    $value->id = via_add_participant($value->userid, $value->activityid,
                    $value->participanttype, $value->confirmationstatus);
                    $allvalues[] = $value;
                }
            }
        }

        // We have the newid, update backup_ids.
        backup_putid($restore->backup_unique_code, $mod->modtype, $mod->id, $newid);
    } else {
        $status = false;
    }

    // Finalize ul!
    echo "</ul>";

    return $status;
}

// This function returns a log record with all the necessay transformations
// done. It's used by restore_log_module() to restore modules log.
function via_restore_logs($restore, $log) {

    $status = false;

    // Depending of the action, we recode different things.
    switch ($log->action) {
        case "add":
            if ($log->cmid) {
                // Get the new_id of the module (to recode the info field).
                $mod = backup_getid($restore->backup_unique_code, $log->module, $log->info);
                if ($mod) {
                    $log->url = "view.php?id=".$log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "update":
            if ($log->cmid) {
                // Get the new_id of the module (to recode the info field).
                $mod = backup_getid($restore->backup_unique_code, $log->module, $log->info);
                if ($mod) {
                    $log->url = "view.php?id=".$log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "view":
            if ($log->cmid) {
                // Get the new_id of the module (to recode the info field).
                $mod = backup_getid($restore->backup_unique_code, $log->module, $log->info);
                if ($mod) {
                    $log->url = "view.php?id=".$log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "add entry":
            if ($log->cmid) {
                // Get the new_id of the module (to recode the info field).
                $mod = backup_getid($restore->backup_unique_code, $log->module, $log->info);
                if ($mod) {
                    $log->url = "view.php?id=".$log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "update entry":
            if ($log->cmid) {
                // Get the new_id of the module (to recode the info field).
                $mod = backup_getid($restore->backup_unique_code, $log->module, $log->info);
                if ($mod) {
                    $log->url = "view.php?id=".$log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "view responses":
            if ($log->cmid) {
                // Get the new_id of the module (to recode the info field).
                $mod = backup_getid($restore->backup_unique_code, $log->module, $log->info);
                if ($mod) {
                    $log->url = "report.php?id=".$log->cmid;
                    $log->info = $mod->new_id;
                    $status = true;
                }
            }
            break;
        case "update via":
            if ($log->cmid) {
                $log->url = "report.php?id=".$log->cmid;
                $status = true;
            }
            break;
        case "view all":
            $log->url = "index.php?id=".$log->course;
            $status = true;
            break;
        default:
            if (!defined('RESTORE_SILENTLY')) {
                echo "action (".$log->module."-".$log->action.") unknown. Not restored<br />"; // Debug!
            }
            break;
    }

    if ($status) {
        $status = $log;
    }
    return $status;
}
