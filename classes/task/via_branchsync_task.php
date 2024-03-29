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
 * @copyright  SVIeSolutions <thomas.fauquignon@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
namespace mod_via\task;
class via_branchsync_task extends \core\task\scheduled_task
{

    public function get_name() {
        return get_string('via_branchsync_task', 'via');
    }

    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot.'/mod/via/lib.php');
        require_once($CFG->dirroot.'/config.php');

        // Update Branch when settings is edited
        via_synch_branches();
    }
}