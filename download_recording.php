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
global $DB, $USER;

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/via/lib.php');

$id = required_param('id', PARAM_INT);
$recordtype = required_param('type', PARAM_INT);
$playbackid = required_param('playbackid', PARAM_TEXT);// Edit via recording.

if (! $cm = get_coursemodule_from_id('via', $id)) {
    error('Course Module ID was incorrect');
}

if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
    error('Incorrect course id');
}

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
$PAGE->set_url('/mod/via/view.php', array('id' => $id));

$viauser = $DB->get_record('via_users', array('userid' => $USER->id));

$api = new mod_via_api();

try {

    $response = $api->via_download_record($viauser->viauserid, $playbackid, $recordtype);

    if ($response) {
        redirect($response['DownloadToken']);
    }

} catch (Exception $e) {

    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo '<p style="padding:20px 0"><a class="returnvia" href="'.$CFG->wwwroot.'/mod/via/view.php?id='.$cm->id.'">'
        .get_string('return', 'via').'</a></p>';
    echo $OUTPUT->box_start('notice');
    echo get_string('error:'.$e->getMessage(), 'via');
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();

}
