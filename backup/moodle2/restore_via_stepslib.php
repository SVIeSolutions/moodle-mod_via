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
 * Structure step to restore one via activity
 *
 * @package    mod_via
 * @subpackage backup-moodle2
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
require_once($CFG->dirroot . '/mod/via/lib.php');
require_once($CFG->dirroot . '/mod/via/api.class.php');

/**
 * Structure step to restore one via activity
 */
class restore_via_activity_structure_step extends restore_activity_structure_step {

    /**
     * This is method get_controller
     *
     * @return mixed This is the return value description
     *
     */
    public function get_controller() {
        global $DB;

        $restoreid = $this->task->get_restoreid();

        $controller = $DB->get_record('backup_controllers', array('backupid' => $restoreid));

        return $controller;

    }

    /**
     * This is method define_structure
     *
     * @return mixed This is the return value description
     *
     */
    protected function define_structure() {
        global $CFG;

        $controller = $this->get_controller();

        if ( $controller->type == "activity") {
            $userinfo = 1;
        } else {
            $userinfo = $this->get_setting_value('userinfo');
        }

        $paths = array();
        $paths[] = new restore_path_element('via', '/activity/via');
        if ($userinfo) {
            $paths[] = new restore_path_element('via_participant', '/activity/via/participants/participant');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * This is method process_via
     *
     * @param mixed $data This is a description
     * @return mixed This is the return value description
     *
     */
    protected function process_via($data) {
        global $DB, $CFG;

        $data = (object)$data;

        try {

            $controller = $this->get_controller();
            if ($controller->type == 'course' && $controller->interactive == 1
                && ($controller->purpose == 10 || $controller->purpose == 20) ||
                ( $controller->purpose == 40 && $controller->interactive == 0)|| // For the web service.
                ($controller->type == 'activity' && $controller->purpose == 20 && $controller->interactive == 0)) {
                // This is the RESTORE of a course OR DUPLICATION OR an IMPORT.
                // We do not create a viaactiviyid!
                // We change the activity type!
                // There is no start date!
                // And no users!

                if (strpos($data->name, get_string('unplanned', 'via')) == false) {
                    $data->name = $data->name . ' - ' . get_string('unplanned', 'via');
                } else {
                    $data->name = $data->name;
                }
                $data->include_userInfo = 0;
                $data->include_surveyandwboards = get_config('via', 'via_duplicatecontent');
                $data->activitytype = 3; // New type which is unplanned!
                $data->datebegin = 0;
                $data->playbacksynch = 0;
                $data->viaactivityid = 0;
                $data->course = $controller->itemid;
                // We no not want to have a group mode imposed on the unplanned activity!
                if ($data->groupingid != 0 || $data->groupid != 0) {
                    $data->groupingid = 0;
                    $data->groupid = 0;

                    $DB->set_field('course_modules', 'groupmode', 0, array('id' => $this->task->get_moduleid()));
                    $DB->set_field('course_modules', 'groupingid', 0, array('id' => $this->task->get_moduleid()));
                }

            } else {
                // Full restore!
                // This is the CATEGORY recylce bin
                // The $controller->type == course,
                // and the $controller->purpose == 10 and lastly the $controller->interactive == 0!

                // This is the COURSE recylce bin
                // The $controller->type == activity,
                // and the $controller->purpose == 10 and lastly the $controller->interactive == 0!

                // If the id and viaactivityid do not exist in via table,
                // we assume we are coming from the recylebin and therefore restore as is!
                $exists = $DB->get_record('via', array('id' => $data->id, 'viaactivityid' => $data->viaactivityid));

                // If it exists we are duplicating or restoring a course and therefor create a new id!
                if ($exists) {

                    if ( $controller->type == "activity") {
                        $data->include_userInfo = 1;
                        $data->include_surveyandwboards = get_config('via', 'via_duplicatecontent');
                    } else {
                        // Course backup/restore.
                        $data->include_userInfo = $this->get_setting_value('userinfo');
                        $data->include_surveyandwboards = get_config('via', 'via_backupcontent');
                        $data->course = $controller->itemid;

                        // We also need to update the groupingid if there is one.
                        if ($data->groupingid != 0) {
                            $oldgroup = $DB->get_record('groupings', array('id' => $data->groupingid));
                            $newgroup = $DB->get_record('groupings', array('courseid' => $data->course, 'name' => $oldgroup->name));
                            $data->groupingid = $newgroup->id;
                        }
                    }

                    $data->datebegin = time() + (3600 * 24 * 30);// We add a month to now.

                    // Creates new activity in Via too!
                    $api = new mod_via_api();
                    $newactivityid = $api->activity_duplicate($data);

                    $data->id = "";
                    $data->viaactivityid = $newactivityid;
                } else {
                    // If it is an unplanned activity, there's no reason to plan it!
                    if ($data->activitytype != 3) {
                        $api = new mod_via_api();
                        $newactivityid = $api->activity_edit($data, 1); // Activitystate = 1 (Active)!
                        // Empty via_recyclebin table if it's there!
                        $DB->delete_records('via_recyclebin', array('viaid' => $data->id, 'viaactivityid' => $data->viaactivityid));
                    }
                }
            }

        } catch (Exception $e) {
            print_error(get_string("error:".$e->getMessage(), "via"));
            return false;
        }

        // Insert the via record.
        $newitemid = $DB->insert_record('via', $data);// This is the old info with the new id.
        $this->apply_activity_instance($newitemid);

    }

    /**
     * This is method process_via_participant
     *
     * @param mixed $data This is a description
     * @return mixed This is the return value description
     *
     */
    protected function process_via_participant($data) {
        global $DB;

        $data = (object)$data;
        $data->id = "";

        $data->activityid = $this->get_new_parentid('via');
        $data->timemodified = time();
        $data->timesynched = time();

        $newitemid = $DB->insert_record('via_participants', $data);
    }

    /**
     * This is method after_execute
     *
     * @return mixed This is the return value description
     *
     */
    protected function after_execute() {
        // Add choice related files, no need to match by itemname (just internally handled context)!
        $this->add_related_files('mod_via', 'intro', null);
        $this->add_related_files('mod_via', 'content', null);
    }
}
