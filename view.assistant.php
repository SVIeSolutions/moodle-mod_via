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

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/via/api.class.php');

$redirect = optional_param('redirect', null, PARAM_INT);

$viaid = optional_param('viaid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

if (isset($viaid)) {
    $cm = get_coursemodule_from_id('via', $viaid);
    require_login($courseid, false, $cm);
}

$api = new mod_via_api();

try {
    if ($response = $api->userget_ssotoken(null, $redirect)) {
        redirect($response);
    } else {
        print_error("You can't access this activity for now.");
    }
} catch (Exception $e) {
    $result = false;
    print_error(get_string("error:".$e->getMessage(), "via"));
}
