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
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
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

        // Creates new activity in Via too!
        $api = new mod_via_api();

        try {

            $controller = $this->get_controller();

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

            $data->name = $data->name . get_string('copied', 'via');

            if ($data->datebegin < time()) {
                $data->datebegin = time() + (3600 * 24 * 30);// We add a month to now.
            }

            $newactivityid = $api->activity_duplicate($data);

            $data->id = "";
            $data->viaactivityid = $newactivityid;

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
