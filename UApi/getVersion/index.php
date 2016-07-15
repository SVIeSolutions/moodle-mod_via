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
 * Gets moodle version for authenitifcation coming from the via mobile app.
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

global $CFG, $DB;

require_once('../../../../config.php');
header('content-type: text/xml');

$version = false;
$version = get_config('mod_via', 'version');// Gets value directly from config_plugins table.
if ($version == false) {
    $via = $DB->get_record('modules', array('name' => 'via'));
    $version = $via->version;
}

echo '<?xml version="1.0" encoding="UTF-8"?>
<Server>
<Result>
    <Status>SUCCESS</Status>
    <Message/>
</Result>
<ServerType>Moodle</ServerType>
<Version>'.$version.'</Version>
</Server>';
