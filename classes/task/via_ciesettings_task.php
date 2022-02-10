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
 * via task
 *
 * @package    mod_via
 * @subpackage task
 * @copyright  SVIeSolutions <jasmin.giroux@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
namespace mod_via\task;
class via_ciesettings_task extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('via_ciesettings_task', 'via');
    }

    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot.'/mod/via/lib.php');
        require_once($CFG->dirroot.'/config.php');

        $viaapiurl = get_config('via', 'via_apiurl');

        if (!isset($viaapiurl) || $viaapiurl == '') {
            return true;
        }

        // Categories.
        if (get_config('via', 'via_categories')) {
            via_check_categories();
        }

        // Profils.
        via_get_list_profils();

        // Valider si les activités existent, si non, met activityID à 0.
        via_synch_activity();

        // Version restriction : via_get_cieinfo() This has been removed.

        // Delete deactivitated actities in Via, they have been emptied from recycle bin!
        via_delete_recylebin_instances();
    }
}