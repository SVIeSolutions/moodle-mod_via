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
 * The EVENTNAME event.
 *
 * @package    Via
 * @copyright  2014 SVI e Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_via\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_via course module viewed event class.
 *
 * @since     Moodle 2.7
 * @package   mod_via
 * @copyright 2014 SVI e Solutions
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * This is method init
     *
     * @return mixed This is the return value description
     *
     */
    protected function init() {
        $this->data['objecttable'] = 'via';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    public static function get_objectid_mapping() {
        return array('db' => 'via', 'restore' => 'via');
    }
    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['viaid'] = array('db' => 'via', 'restore' => 'via');
        return $othermapped;
    }

}

