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
 * Delete resource
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
require_once("forms/delete_resource_form.php");
require_once(get_vialib());

$viaid = required_param('viaid', PARAM_INT);
$resourceid = required_param('rid', PARAM_TEXT);
$resourcename= urldecode(required_param('rname', PARAM_TEXT));
$subroomid = required_param('srid', PARAM_TEXT);
$filetype = required_param('ft', PARAM_INT); // Type of resource.

$data = [
    'viaid' => $viaid,
    'resourceid' => $resourceid,
    'resourcename' => $resourcename,
    'subroomid' => $subroomid,
    'filetype' => $filetype,
];

if (! $via = $DB->get_record('via', array('id' => $viaid))) {
    throw new invalid_parameter_exception('Activity ID was incorrect');
}
if (!$course = $DB->get_record('course', array('id' => $via->course))) {
    throw new ErrorException("Could not find this course!");
}
if (! $cm = get_coursemodule_from_instance("via", $via->id, null)) {
    $cm->id = 0;
}

require_login($course->id, false, $cm);
$context = via_get_module_instance($cm->id);
$redirecturl = "view.php?id=".$cm->id."&subroomid=".$subroomid;

via_validate_edition_capability($viaid, $context, 'You do not have the permission to delete resources');

$PAGE->set_url('/mod/via/edit_resource.php', array('viaid' => $viaid));
$PAGE->set_title($course->shortname.': '.$via->name.': '. $resourcename);
$PAGE->set_heading($course->fullname);

$form =  new delete_resource_form(null, $data);

$toform = [];
//$toform['viaid'] = $viaid;
$api = new mod_via_api();
$error = null;

try {
    if ($form->is_cancelled()) {
      //Handle form cancel operation, if cancel button is present on form
        redirect($redirecturl, '', 10);
    } else if ($fromform = $form->get_data()) {
        $response = $api->delete_resource_viahtml($via->viaactivityid, $subroomid, $resourceid);
        if ($response && $response["id"] == $resourceid) {
            redirect($redirecturl, get_string('resources_delete_success', 'via', $resourcename));
        } else {
            throw new ErrorException("Error during deletion");
        }
    }

} catch (Exception $e) {

    $error = $e;

}

$PAGE->set_context($context);
if ($CFG->version >= 2022041900) {
    // Activityheader doesn't exist for Moodle 3.11 and before.
    $PAGE->activityheader->disable();
}

echo $OUTPUT->header();

if($error){
    echo get_error_message($error);
} else {
    //displays the form
    $form->display();
}
echo $OUTPUT->footer();
