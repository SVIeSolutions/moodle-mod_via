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
 * Download document
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <jasmin.giroux@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

global $DB, $USER;

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/via/lib.php');
require_once(get_vialib());

$viaid = required_param('viaid', PARAM_INT);
$fid = required_param('fid', PARAM_TEXT);// Edit via recording.

if (! $via = $DB->get_record('via', array('id' => $viaid))) {
    print_error('Activity ID was incorrect');
}


$PAGE->set_url('/mod/via/view.php', array('id' => $viaid));

$viauser = $DB->get_record('via_users', array('userid' => $USER->id));
$vuserid = $viauser->viauserid;


$api = new mod_via_api();

try {

    $response = $api->via_download_document($via, $vuserid, $fid);

    if ($response) {
        redirect($response['DownloadToken']);
    }

} catch (Exception $e) {
    echo $OUTPUT->header();
    echo $OUTPUT->box_start('notice');
    echo get_string('error:'.$e->getMessage(), 'via');
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
}
