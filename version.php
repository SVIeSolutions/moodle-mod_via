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
 * Code fragment to define the version of the via module
 * 
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

if ($CFG->version >= 2013111800) {
    /* For moodle version 2.6 or greater */
    $plugin->component = 'mod_via';
    $plugin->version = 2015050104; // Needs API version 6.5 or greater!
    $plugin->requires = 2011033010; // Moodle2.0!
    $plugin->cron     = 300;
} else {
    /* For moodle version 2.5 or lower */
    $module->component = 'mod_via';
    $module->version   = 2015050104; // Needs API version 6.5 or greater!
    $module->requires  = 2011033010;    // Moodle2.0!
    $module->cron      = 300;
}

