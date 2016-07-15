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
 * Redirects user with token to the via activity or playback after validating him/her.
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

global $CFG;
require_once($CFG->dirroot.'/mod/via/lib.php');

/**
 * This is function via_get_module_instance will call the correct fucntion
 * depending on the moodle version.
 *
 * Get the context instance as an object. This function will create the
 * context instance if it does not exist yet.
 * @deprecated since 2.2
 * @param integer $instance The instance id. for $level = CONTEXT_MODULE, this would be $cm->id.
 * @return context The context object.
 */
function via_get_module_instance($cmid) {
    return get_context_instance(CONTEXT_MODULE, $cmid);
}

/**
 * This is function via_get_course_instance will call the correct fucntion
 * depending on the moodle version.
 *
 * Get the context instance as an object. This function will create the
 * context instance if it does not exist yet.
 * @deprecated since 2.2
 * @param integer $instance The instance id. For $level = CONTEXT_COURSE, this would be $course->id,
 * @return context The context object.
 */
function via_get_course_instance($courseid) {
    return get_context_instance(CONTEXT_COURSE, $courseid);
}

/**
 * This is function via_get_system_instance will call the correct fucntion
 * depending on the moodle version.
 *
 * Get the context instance as an object. This function will create the
 * context instance if it does not exist yet.
 * @deprecated since 2.2
 * @param integer $instance The instance id. Defaults to 0
 * @return context The context object.
 */
function via_get_system_instance() {
    return get_context_instance(CONTEXT_SYSTEM);
}

/**
 * This is following functions will call the correct fucntions
 * depending on the moodle version.
 *
 * @deprecated since 2.7 use new events instead
 *
 * @param    int     $courseid  The course id
 * @param    string  $module  The module name  e.g. forum, journal, resource, course, user etc
 * @param    string  $action  'view', 'update', 'add' or 'delete', possibly followed by another word to clarify.
 * @param    string  $url     The file and parameters used to see the results of the action
 * @param    string  $info    Additional description information
 * @param    int     $cm      The course_module->id if there is one
 * @param    int|stdClass $user If log regards $user other than $USER
 * @return void
 *
 */
function via_accessed_log($via, $context) {
    add_to_log($via->course, "via", get_string('viaaccessed', 'via'), "view.php?id=$context->id", $via->id, $context->id);
}

function via_playback_viewed_log($via, $context, $course, $playbackid) {
    add_to_log($course->id, "via",  get_string('playback_viewed', 'via'), "view.php?id=$context->id", $via->id, $context->id);
}

function via_playback_downloaded_log($id, $context, $course, $recordtype) {
    add_to_log($course->id, "via", get_string('playback_downloaded', 'via'), "view.php?id=$context->id", $via->id, $context->id);
}

function via_viewed_log($via, $context, $cm) {
    add_to_log($via->course, "via", get_string('playback_viewed', 'via'), "view.php?id=$cm->id", $via->id, $cm->id);
}

/**
 * This is function via_get_version
 *
 * @return string of pluginversion
 */
function via_get_version() {
    global $DB;

    $via = $DB->get_record('modules', array('name' => 'via'));
    return get_string('pluginversion', 'via') . $via->version;
}

