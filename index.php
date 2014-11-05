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
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

global $DB;

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/via/lib.php');

$id = optional_param('id', null, PARAM_INT);    // Course ID.
if (!($course = $DB->get_record('course', array('id' => $id)))) {
    print_error('course ID is incorrect');
}

$context = context_course::instance($course->id);
$vias = get_all_instances_in_course('via', $course);

require_login($course->id);

$url = new moodle_url('/mod/via/index.php?id=$course->id');
// In original url there was also , 'type'-> 'activity'.
$PAGE->set_url($url);
$PAGE->set_pagelayout('course');

// Strings needed to render the page...
$strvia = get_string('modulename', 'via');
$strvias = get_string('modulenameplural', 'via');
$strname = get_string('name', 'via');
$strdate = get_string('date', 'via');

$strtopic = get_string('topic');
$strweek = get_string('week');
$stryes = get_string('yes');

$PAGE->set_url('/mod/via/index.php', array('id' => $course->id));
$PAGE->navbar->add($strvias);
$PAGE->set_title($strvia );
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

$table = new html_table();
$table->width = "60%";
$table->data = array();

// Table header, depending on the course format.
if ('weeks' == substr($course->format, 0, 5)) {
    $table->head = array($strweek, $strname, $strdate);
    $table->align = array('center', 'left', 'left');
} else if ('topics' == $course->format) {
    $table->head = array($strtopic, $strname, $strdate);
    $table->align = array('center', 'left', 'left');
} else {
    $table->head = array($strname, $strdate);
    $table->align = array('left', 'center');
}

// Loop to build each row of the table...
foreach ($vias as $via) {
    if (!$via->visible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
        continue;
    }

    $link = '<a '.($via->visible ? '' : ' class="dimmed"').
        'href="view.php?id='.$via->coursemodule.'">'.s($via->name).'</a>';
    $datebegin = $via->activitytype != 2 ? userdate( $via->datebegin) : get_string('permanent', 'via');

    if ('weeks' == substr($course->format, 0, 5)) {
        $weekday = userdate($course->startdate + 604800 * ($via->section - 1), '%d %B');
        $table->data[] = array($weekday, $link, $datebegin);
    } else if ('topics' == $course->format) {
        $table->data[] = array($via->section, $link, $datebegin);
    } else {
        $table->data[] = array($link, $datebegin);
    }
}

// We have to wait until the end to determine if we need to show the notice. Even though
// instances exist and were returned by get_all_instances_in_course(), they might be all hidden.
if (!count($table->data)) {
    notice(get_string('thereareno', 'moodle', $strvias), '../../course/view.php?id='.$course->id);
    die();
}

echo '<br />';
echo html_writer::table($table);
echo $OUTPUT->footer();
