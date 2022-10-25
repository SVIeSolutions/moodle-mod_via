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
 * Creates printable presence status page.
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

?><link href="styles.css" rel="stylesheet" type="text/css" /><?php

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/via/lib.php');
require_once(get_vialib());
header('Content-type: text/html; charset=utf-8;');

global $CFG, $DB;

$id    = required_param('id', PARAM_INT);// Via!
$showemail = optional_param('e', 0, PARAM_INT);

$via = $DB->get_record('via', array('id' => $id));
$ishtml5 = $via->activityversion == 1;

$cm = get_coursemodule_from_instance('via', $id, $via->course);

$PAGE->set_url('/via/presence.php');
$PAGE->set_context(via_get_system_instance());

require_login($via->course, false, $cm);

echo $OUTPUT->box_start('center', 'presence');

echo "<h2>".get_string("report", "via") . " : " . $via->name."</h2>";
echo "<hr>";
echo "<p><strong>" .get_string("startdate", "via")." :</strong> " . userdate($via->datebegin) .'</p>';
echo "<p><strong>" .get_string("enddate", "via"). " :</strong> " .  userdate($via->datebegin + ($via->duration * 60)) .'</p>';
echo "<p><strong>". get_string("duration", "via")." :</strong> " . $via->duration."</p>";

echo "<p class='basedon'>". get_string("basedon", "via", $via->presence)."</p>";

$table = new html_table();
$table->attributes['class'] = 'generaltable boxaligncenter';
$table->head  = array (get_string("role", "via"),
                        get_string("lastname").', '.get_string("firstname"),
                        get_string("email"),
                        get_string("presenceheaderreport", "via"));
$table->align = array ('left', 'left', 'left', 'center');
$table->width = '100%';

if ($ishtml5) {
    $api = new mod_via_api();
    $vroomlogdata =  $api->via_get_user_logs_html5($via->viaactivityid);
}

$participants = $DB->get_records_sql("SELECT v.id, v.userid, v.activityid, vu.viauserid, u.lastname, u.firstname,
                                      u.email, v.participanttype, vp.status, via.presence, via.recordingmode,
                                      via.viaactivityid,vp.connection_duration, vp.playback_duration
                                      FROM {via_participants} v
                                      LEFT JOIN {via_users} vu ON vu.userid = v.userid
                                      LEFT JOIN {via} via ON via.id = v.activityid
                                      LEFT JOIN {user} u ON u.id = v.userid
                                      LEFT JOIN {via_presence} vp ON v.userid = vp.userid AND v.activityid = vp.activityid
                                      WHERE v.activityid = ? ORDER BY (v.participanttype+1)%3 , u.lastname ASC", array($via->id));

foreach ($participants as $participant) {
    if (!isset($participant->connection_duration)) {
        if (isset($participant->viauserid)) {
            $userlogs = via_userlogs($participant, $vroomlogdata, $ishtml5);
            $completestring = explode('(', $userlogs['0']);
            $string = $completestring['0'];
        } else {
            // If there is no viauserid = the user never connected and is therefore absent.
            $string = get_string('absent', 'via');
            $presence = new stdClass();
            $presence->connection_duration = '0.00';
            $presence->status = 0;
            $presence->timemodified = time();
            $presence->userid = $participant->userid;
            $presence->activityid = $participant->activityid;

            // TODO Provisional : userid should never be  null.
            if (isset($participant->userid)) {
                $DB->insert_record('via_presence', $presence);
            }
        }
    } else if ($participant->connection_duration >= $via->presence) {
        $string = get_string('present', 'via');
        $status = 1;
    } else {
        $string = get_string('absent', 'via');
        $status = 0;
    }
    if ($participant->status != $status) {
        $DB->set_field('via_presence', 'status', $status, array('userid' => $participant->userid, 'activityid' => $via->id));
    }

    $role = via_get_role($participant->participanttype);
    if ($showemail == 1) {
        $email = $participant->email;
    } else {
        $email = '--';
    }

    $table->data[] = array ($role, $participant->lastname.', '. $participant->firstname, $email, $string);
}

// Add information to table that will be displayed.
echo html_writer::table($table);

echo "<p><span style='float:left; margin-top:20px'>". get_string("createdby", "via").
        $USER->firstname .' '. $USER->lastname ."</span>
        <span style='float:right; margin-top:20px'>". get_string("createdby", "via").
        userdate(time())."</span></p>";

echo $OUTPUT->box_end();