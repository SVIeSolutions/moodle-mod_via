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
 * Handles viewing a the technical assistant page
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/via/api.class.php');

$redirect = optional_param('redirect', null, PARAM_INT);

$id = optional_param('id', null, PARAM_INT);
$viaid = optional_param('viaid', null, PARAM_INT);
$courseid = optional_param('courseid', null, PARAM_INT);
$fa = optional_param('fa', null, PARAM_INT);

$via = null;

if ($id) {
    $cm = get_coursemodule_from_id('via', $id);

    if (! $via = $DB->get_record('via', array('id' => $cm->instance))) {
        error('Activity ID was incorrect');
    }
} else if ($viaid) {
    $viaassign = $DB->get_record('viaassign_submission', array('viaid' => $viaid));
    if (!($cm = get_coursemodule_from_instance('viaassign', $viaassign->viaassignid, null, false, MUST_EXIST))) {
        error("Course module ID is incorrect");
    }
    if (!($via = $DB->get_record('via', array('id' => $viaid)))) {
        error("Via ID is incorrect");
    }

}

if ($courseid) {
    require_login($courseid, false, $cm);
}

$api = new mod_via_api();

try {
    if ($response = $api->userget_ssotoken($via, $redirect, null, $fa)) {
        echo '<style>body {margin:0;}</style>';
        echo '<iframe style="border:none;" width="100%" height="100%" scrolling="auto" src="'.$response.'"></iframe>';
    } else {
        print_error("You can't access this activity for now.");
    }
} catch (Exception $e) {
    $result = false;
    print_error($e->getMessage());
}
