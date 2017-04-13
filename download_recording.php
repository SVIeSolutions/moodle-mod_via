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
 * Download recording
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

global $DB, $USER;

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/via/lib.php');
require_once(get_vialib());

$id = optional_param('id', null, PARAM_INT);
$viaid = optional_param('viaid', null, PARAM_INT);
$fa = optional_param('fa', null, PARAM_INT);
$recordtype = required_param('type', PARAM_INT);
$playbackid = required_param('playbackid', PARAM_TEXT);// Edit via recording.

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
$PAGE->set_url('/mod/via/view.php', array('id' => $id));

if ($fa) {
    $vuserid = get_config('via', 'via_adminid');
} else {
    $viauser = $DB->get_record('via_users', array('userid' => $USER->id));
    $vuserid = $viauser->viauserid;
}

$api = new mod_via_api();

try {

    $response = $api->via_download_record($vuserid, $playbackid, $recordtype);

    if ($response) {

        via_playback_downloaded_log($viaurlparamvalue, $context, $course, $recordtype);

        redirect($response['DownloadToken']);
    }
} catch (Exception $e) {
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo '<p style="padding:20px 0"><a class="returnvia" href="'
        .$CFG->wwwroot.'/mod/via/view.php?'.$viaurlparam.'='.$viaurlparamvalue.'">'
        .get_string('return', 'via').'</a></p>';
    echo $OUTPUT->box_start('notice');
    echo get_string('error:'.$e->getMessage(), 'via');
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
}
