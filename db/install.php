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
 * Post installation and migration code.
 * 
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

defined('MOODLE_INTERNAL') || die;

/**
 * This is function xmldb_via_install
 *
 * @return mixed This is the return value description
 *
 */
function xmldb_via_install() {
    global $CFG, $DB;

    $functions = array();
    $functions[] = array('name' => 'via_send_reminders', 'cron' => '600', 'lastcron' => 0);
    $functions[] = array('name' => 'via_send_invitations', 'cron' => '600', 'lastcron' => 0);
    $functions[] = array('name' => 'via_add_enrolids', 'cron' => '2400', 'lastcron' => 0);
    $functions[] = array('name' => 'via_synch_users', 'cron' => '1200', 'lastcron' => 0);
    $functions[] = array('name' => 'via_synch_participants', 'cron' => '1200', 'lastcron' => 0);
    $functions[] = array('name' => 'via_check_categories', 'cron' => '43200', 'lastcron' => 0); // Once every 12 hours!
    $functions[] = array('name' => 'via_send_export_notice', 'cron' => '1200', 'lastcron' => 0);
    $functions[] = array('name' => 'via_get_list_profils', 'cron' => '43200', 'lastcron' => 0); // Once every 12 hours!
    $functions[] = array('name' => 'via_get_cieinfo', 'cron' => '43200', 'lastcron' => 0); // Once every 12 hours!

    foreach ($functions as $function) {
        $DB->insert_record('via_cron', $function);
    }
}
