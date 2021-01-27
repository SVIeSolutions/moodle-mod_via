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
 * Permits the moodle admin to test the moodle admin key provided
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
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
        print_error("You need to be admin to use this page");
    }
}

$PAGE->set_context(via_get_system_instance());

$site = get_site();

$apiurl = required_param('apiurl', PARAM_NOTAGS);
$cleid  = required_param('cleid', PARAM_NOTAGS);
$apiid  = required_param('apiid', PARAM_NOTAGS);
$adminid  = required_param('adminid', PARAM_NOTAGS);

// Initialize $PAGE!
$PAGE->set_url('/mod/via/testadminid.php');
$PAGE->set_heading("$site->fullname");
$PAGE->set_pagelayout('popup');

// Print the page header!
echo $OUTPUT->header();

echo $OUTPUT->box_start('center', '100%');

$result = true;
$api = new mod_via_api();

try {
    $response = $api->testadminid($apiurl, $cleid, $apiid, $adminid);

} catch (Exception $e) {
    $result = false;
    print_error(get_string("error:".$e->getMessage(), "via"));
}

if ($result) {
    if ($response['Result']['ResultState'] == 'SUCCESS') {
        if ($response['UserType'] >= '4') {
            echo '<div class="alert alert-block alert-info">'. get_string('adminid_success', 'via'). '</div>';
        } else {
            echo '<div class="alert alert-block alert-error">'.get_string('adminid_toolow', 'via').$required.'</div>';
        }
    } else {
        echo '<div class="alert alert-block alert-error">'.get_string('adminid_nosuccess', 'via').$required.'</div>';
    }
}

echo '<center><input type="button" onclick="self.close();" value="' . get_string('closewindow') . '" /></center>';

echo $OUTPUT->box_end();
echo $OUTPUT->footer($site);
