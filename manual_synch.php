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
 * View activity details
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once('../../config.php');
global $CFG;
require_once($CFG->dirroot.'/mod/via/lib.php');
require_once(get_vialib());

global $DB, $CFG, $USER;

$id = required_param('id', PARAM_INT);
$via = $DB->get_record('via', array('id' => $id));
$module = $DB->get_record('modules', array('name' => 'via'));
$cm = $DB->get_record('course_modules', array('instance' => $id, 'course' => $via->course, 'module' => $module->id));
$string = null;
$ishtml5 = $via->activityversion == 1;
$usertosubscribe = new ArrayObject();

$notsynched = $DB->get_records('via_participants',  array('activityid' => $id, 'synchvia' => 0));

try {
    foreach ($notsynched as $s) {

        $type = via_user_type($s->userid, $via->course, $via->noparticipants);

        $added = via_add_participant($s->userid, $id, $type, !$ishtml5);
        if ($ishtml5) {            
            $usertosubscribe->append(array($s->userid, $type));
        }

    }
    if ($ishtml5) {
        $api = new mod_via_api();
        $api->set_users_activity_html5($usertosubscribe, $via, true);
        $added = true;
    }
    if (!$added) {
        $string = '1';
    }
} catch (Exception $e) {
    $string = '1';
}

if (!$string) {
    redirect('view.php?id='.$cm->id.'&synch=1');
} else {
    redirect('view.php?id='.$cm->id.'&synch=2');
}

exit;


