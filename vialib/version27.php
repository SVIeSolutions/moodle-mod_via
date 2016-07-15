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
 * @param int $instanceid
 * @return context_module context instance
 */
function via_get_module_instance($cmid) {
    return context_module::instance($cmid);
}

/**
 * This is function via_get_module_instance will call the correct fucntion
 * depending on the moodle version.
 *
 * Get the context instance as an object. This function will create the
 * context instance if it does not exist yet.
 * @param int $instanceid
 * @return context_course context instance
 */
function via_get_course_instance($courseid) {
    return context_course::instance($courseid);
}

/**
 * This is function via_get_module_instance will call the correct fucntion
 * depending on the moodle version.
 *
 * Get the context instance as an object. This function will create the
 * context instance if it does not exist yet.
 * @param int $instanceid
 * @return context_system context instance
 */
function via_get_system_instance() {
    return context_system::instance();
}

/**
 * This is function via_accessed_log will call use new events that replaces
 * add_to_log, which is deprecated since 2.7 depending on the moodle version.
 */
function via_accessed_log($via, $context = null) {

    $eventdata = array(
        'objectid' => $via->id,
        'context' => $context
        );
    $event = \mod_via\event\via_accessed::create($eventdata);
    $event->trigger();

}

/**
 * This is function via_playback_viewed_log will call use new events that replaces
 * add_to_log, which is deprecated since 2.7 depending on the moodle version.
 */
function via_playback_viewed_log($via, $context, $course, $playbackid) {

    $params = array(
        'objectid' => $via->id,
        'context' => $context,
        'courseid' => $course->id,
        'other' => array('playbackid' => $playbackid)
        );
    $event = \mod_via\event\playback_viewed::create($params);
    $event->trigger();

}

/**
 * This is function via_playback_downloaded_log will call use new events that replaces
 * add_to_log, which is deprecated since 2.7 depending on the moodle version.
 */
function via_playback_downloaded_log($id, $context, $course, $recordtype) {

    $params = array(
        'objectid' => $id,
        'context' => $context,
        'courseid' => $course->id,
        'other' => array('type' => $recordtype)
        );
    $event = \mod_via\event\playback_downloaded::create($params);
    $event->trigger();

}

/**
 * This is function via_viewed_log will call use new events that replaces
 * add_to_log, which is deprecated since 2.7 depending on the moodle version.
 */
function via_viewed_log($via, $context, $cm) {
    $eventdata = array(
        'objectid' => $via->id,
        'context' => $context
        );
    $event = \mod_via\event\course_module_viewed::create($eventdata);
    $event->trigger();
}

/**
 * This is function via_get_version
 *
 * @return string of pluginversion
 *
 */
function via_get_version() {
    return get_string('pluginversion', 'via') . get_config('mod_via', 'version');
}
