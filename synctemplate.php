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
 * via restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 *
 * @package    mod_via
 * @subpackage synctemplate
 * @copyright  SVIeSolutions <jasmin.giroux@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once('../../config.php');
require_once('lib.php');

$PAGE->requires->js('/mod/via/javascript/list.js');

global $CFG, $DB;

$save = optional_param('save', null, PARAM_TEXT);
$category = optional_param('category', array(), PARAM_RAW);
$isdefault = optional_param('isdefault', null, PARAM_INT);

require_login();

if ($site = get_site()) {
    if (function_exists('require_capability')) {
        require_capability('moodle/site:config', via_get_system_instance());
    } else if (!isadmin()) {
        print_error("You need to be admin to use this page");
    }
}

$PAGE->set_context(via_get_system_instance());

// Initialize $PAGE.
$PAGE->set_url('/mod/via/choosecategories.php');
$PAGE->set_heading("$site->fullname");
$PAGE->set_pagelayout('popup');

// Print the page header.
echo $OUTPUT->header();

echo $OUTPUT->box_start('center', '100%');

$api = new mod_via_api();

// Template stuff.
$template = $api->activitytemplate_get();

foreach ($template as $key => $value) {

    if ($key == 'Result' ||
        $key == 'CieID' ||
        $key == 'ApiID' ||
        $key == 'UserID' ||
        $value == '' ||
        $key == 'Periodicity' ||
        $key == 'ApplyPeriodicity') {
        continue;
    }

    $exists = $DB->get_record('via_params', array('param_name' => $key));
    if (!$exists) {
        $temp = new stdclass();
        $temp->param_type = 'ActivityTemplate';
        $temp->param_name = $key;
        $temp->value = $value;
        $temp->timemodified = time();

        $added = $DB->insert_record('via_params', $temp);
    } else {
        if ($exists->value != $value) {
            $DB->set_field('via_params', 'value', $value, array('param_name' => $key));
            $DB->set_field('via_params', 'timemodified', time(), array('param_name' => $key));
        }
    }
}

echo '<div class="alert alert-block alert-info">' . get_string("synctemplateend", 'via') .'</div>';

echo '<center><input type="button" onclick="self.close();" value="' . get_string('closewindow') . '" /></center>';

echo $OUTPUT->box_end();
echo $OUTPUT->footer($site);