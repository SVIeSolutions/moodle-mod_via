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
 * Form to edit a resource
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
require_once("$CFG->libdir/formslib.php");
class edit_resource_form extends moodleform
{

    // Add eleemts to form
    public function definition(){
        global $CFG, $DB;

        $mform = $this->_form;

        $mform->addElement('hidden', 'viaid', $this->_customdata["viaid"]);
        $mform->setType('viaid', PARAM_INT);
        $mform->addElement('hidden', 'rid', $this->_customdata["resourceid"]);
        $mform->setType('rid', PARAM_TEXT);
        $mform->addElement('hidden', 'rname', $this->_customdata["resourcename"]);
        $mform->setType('rname', PARAM_TEXT);
        $mform->addElement('hidden', 'srid', $this->_customdata["subroomid"]);
        $mform->setType('srid', PARAM_TEXT);
        $mform->addElement('hidden', 'ft', $this->_customdata["filetype"]);
        $mform->setType('ft', PARAM_TEXT);
        $mform->addElement('hidden', 'ibo', $this->_customdata["isbreakout"]);
        $mform->setType('ibo', PARAM_BOOL);

        $mform->addElement('html', $this->_customdata["resourcename"].'<br/><br/>');

        if (in_array($this->_customdata["filetype"], array("5", "10", "11"))) {
            $mform->addElement('html', get_string('resources_survey_visibility_warning', 'via').'<br/>' );

            $mform-> addElement('checkbox','visibilitytype', get_string('resources_visibility_public', 'via'));
            $mform->setType('visibilitytype', PARAM_INT);

            $mform->addElement('hidden', 'isdownloadable', null);
            $mform->setType('isdownloadable', PARAM_BOOL);

        } else {
            $mform->addElement('checkbox', 'isdownloadable', $this->_customdata["isbreakout"] ? get_string('resources_downloadable_subroom', 'via') :
                get_string('resources_downloadable', 'via'));
            $mform->setType('isdownloadable', PARAM_BOOL);
        }

        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'Submit', get_string('save', 'via'));
        $buttonarray[] = $mform->createElement('cancel', '', get_string('cancel', 'via'));
        $mform ->addGroup($buttonarray, 'buttonar', '', '', false);
    }

    public function validation($data, $files) {
        return array();
    }
}