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
 * via restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 *
 * @package    mod_via
 * @subpackage task
 * @copyright  SVIeSolutions <jasmin.giroux@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
defined('MOODLE_INTERNAL') || die();
$tasks = array(
array(
    'classname' => 'mod_via\task\via_usersync_task',
    'blocking' => 0,
    'minute' => '*/10',
    'hour' => '*',
    'day' => '*',
    'dayofweek' => '*',
    'month' => '*'
    )
,
array(
    'classname' => 'mod_via\task\via_mail_task',
    'blocking' => 0,
    'minute' => '*/5',
    'hour' => '*',
    'day' => '*',
    'dayofweek' => '*',
    'month' => '*'
    )
,
array(
    'classname' => 'mod_via\task\via_ciesettings_task',
    'blocking' => 0,
    'minute' => '*',
    'hour' => '*/12',
    'day' => '*',
    'dayofweek' => '*',
    'month' => '*'
    )
,
array(
    'classname' => 'mod_via\task\via_notification_task',
    'blocking' => 0,
    'minute' => '*/1',
    'hour' => '*',
    'day' => '*',
    'dayofweek' => '*',
    'month' => '*'
    ),
array(
    'classname' => 'mod_via\task\via_branchsync_task',
    'blocking' => 0,
    'minute' => '*/10',
    'hour' => '*',
    'day' => '*',
    'dayofweek' => '*',
    'month' => '*'
    )
);