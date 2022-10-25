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
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

global $DB, $USER;

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/via/lib.php');
require_once(get_vialib());

$viaid = required_param('viaid', PARAM_INT);
$resourceid = optional_param('rid', null, PARAM_TEXT);

if (! $via = $DB->get_record('via', array('id' => $viaid))) {
    throw new invalid_parameter_exception('Activity ID was incorrect');
}

$PAGE->set_url('/mod/via/view.php', array('id' => $viaid));

$api = new mod_via_api();

try {
    $uservalidated = $api->validate_via_user($USER, true);
    $viauser = $DB->get_record('via_users', array('viauserid' => $uservalidated));
    $vuserid = $viauser->viauserid;
    $response = $api->get_resources_token_viahtml($vuserid,  $via->viaactivityid, $resourceid);
    if ($response) {
        redirect($response['urlToken']);
    }

} catch (Exception $e) {
    $PAGE->set_context(context_system::instance());
    echo $OUTPUT->header();
    echo $OUTPUT->box_start('generalbox', 'notice');
    echo get_error_message($e);
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
}
