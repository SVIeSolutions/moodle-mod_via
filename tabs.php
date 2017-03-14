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
 * Creates tabs (participant, animator, host) for the manage page.
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
defined('MOODLE_INTERNAL') || die();
if (empty($currenttab) or empty($course)) {
    print_error('You cannot call this script in that way');
}

$context = via_get_module_instance($cm->id);

$inactive = null;
$activetwo = null;
$tabs = array();
$row = array();

if ($via->noparticipants != 1) {
    $row[] = new tabobject('participants', $CFG->wwwroot.'/mod/via/manage.php?id='.
    $via->id.'&t=1', get_string("participants", "via"));
}

$row[] = new tabobject('animators', $CFG->wwwroot.'/mod/via/manage.php?id='.$via->id.'&t=3', get_string("animators", "via"));

$row[] = new tabobject('host', $CFG->wwwroot.'/mod/via/manage.php?id='.$via->id.'&t=2', get_string("host", "via"));

$tabs[] = $row;

print_tabs($tabs, $currenttab);
