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
 * Permits admin to test the API connection information.
 * As well as the API version.
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once('../../config.php');
require_once('lib.php');
global $CFG, $DB;

require_login();

if ($site = get_site()) {
    if (function_exists('require_capability')) {
        require_capability('moodle/site:config', via_get_system_instance());
    } else if (!isadmin()) {
        error("You need to be admin to use this page");
    }
}

$PAGE->set_context(via_get_system_instance());

$site = get_site();

$apiurl = required_param('apiurl', PARAM_NOTAGS);
$cleid  = required_param('cleid', PARAM_NOTAGS);
$apiid  = required_param('apiid', PARAM_NOTAGS);

// Initialize $PAGE!
$PAGE->set_url('/mod/via/conntest.php');
$PAGE->set_heading("$site->fullname");
$PAGE->set_pagelayout('popup');

// New with version 20140861.
// We validate the version.
$required = '8.6.0.0';

// Print the page header.
echo $OUTPUT->header();

echo $OUTPUT->box_start('center', '100%');

$result = true;
$api = new mod_via_api();

try {
    $response = $api->testconnection($apiurl, $cleid, $apiid);

} catch (Exception $e) {
    $result = false;
    notify(get_string("error:".$e->getMessage(), "via"));
}

if ($result) {

    echo '<div class="alert alert-block alert-info">'. get_string('connectsuccess', 'via'). '</div>';
    if (isset($response['BuildVersion'])) {
        $version = $response['BuildVersion'];
    } else {
        $version = 0;
    }
    if (via_validate_api_version($required, $version)) {
        echo '<div class="alert alert-block alert-info">'.get_string('versionscompatible', 'via').'</div>';
    } else {
        echo '<div class="alert alert-block alert-error">'.get_string('versions_not_compatible', 'via').$required.'</div>';
    }

}

echo '<center><input type="button" onclick="self.close();" value="' . get_string('closewindow') . '" /></center>';

echo $OUTPUT->box_end();
echo $OUTPUT->footer($site);
