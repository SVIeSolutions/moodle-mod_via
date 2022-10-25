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
 * Form to delete a resource
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
require_once("$CFG->libdir/formslib.php");
class delete_resource_form extends moodleform
{

    // Add eleemts to form
    public function definition() {
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

        $mform->addElement('html', $this->_customdata["resourcename"].'<br/><br/>');

        switch ($this->_customdata["filetype"]) {
            case 5:
            case 10:
            case 11:
                // Survey = 5 || Quiz = 10 || FormativeQuiz = 11.
                $mform->addElement('html', get_string("resources_survey_warning", "via")."<br/>");
                break;
            case 1:
            case 14:
                // 1 = File_document_.
                // 14 = Whiteboard.
                 $mform->addElement('html', get_string("resources_document_warning", "via")."<br/>");
                break;
        }

        $mform->addElement('html', get_string('resources_confirmdelete', 'via', $this->_customdata["resourcename"]). '<br/><br/>');

        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'delete', get_string('delete', 'via'));
        $buttonarray[] = $mform->createElement('cancel', 'cancel', get_string('cancel', 'via'));
        $mform->addGroup($buttonarray, 'buttonar', '', '', false);
    }

    public function validation($data, $files) {
        return array();
    }
}