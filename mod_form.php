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

require_once($CFG->dirroot.'/course/moodleform_mod.php');


/** Inherited class used when editing via instances. */
class mod_via_mod_form extends moodleform_mod {

    /** Defines the form contents. */
    public function definition() {
        global $CFG, $DB;

        $mform =& $this->_form;

        // General info.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Title!
        $mform->addElement('text', 'name', get_string('activitytitle', 'via'), array('size' => '64'));
        $mform->setType('name', PARAM_CLEANHTML);
        $mform->addRule('name', null, 'required', null, 'client');

        // Description!
        $this->add_intro_editor(true);

        // DURATION!

        $mform->addElement('header', 'activityduration', get_string('headerduration', 'via'));

        // Permanent activity!
        if (get_config('via', 'via_permanentactivities') == 1) {
            $mform->addElement('checkbox', 'activitytype', get_string("permanent", "via"));
            $mform->disabledif ('activitytype', 'pastevent', 'eq', 1);
            $mform->addHelpButton('activitytype', 'permanent', 'via');
        }

        // Start Date!
        $mform->addElement('date_time_selector', 'datebegin', get_string('availabledate', 'via'), array('optional' => false));
        $mform->setDefault('datebegin', time());
        $mform->disabledif ('datebegin', 'activitytype', 'checked');
        $mform->disabledif ('datebegin', 'nowevent', 'eq', 1);
        $mform->disabledif ('datebegin', 'pastevent', 'eq', 1);

        // Duration!
        $mform->addElement('text', 'duration', get_string('duration', 'via'), array('size' => '4'));
        $mform->setType('duration', PARAM_INT);
        $mform->setDefault('duration', '60');
        $mform->disabledif ('duration', 'activitytype', 'checked');
        $mform->disabledif ('duration', 'pastevent', 'eq', 1);

        // Automatic reminders!
        $onehour = 60 * 60;
        $twohours = (60 * 60) * 2;
        $oneday = (60 * 60) * 24;
        $twosdays = (60 * 60) * 48;
        $oneweek = ((60 * 60) * 24) * 7;
        $roptions = array( 0 => get_string('norecall', 'via'),
                                $onehour => get_string('recallonehour', 'via'),
                                $twohours => get_string('recalltwohours', 'via'),
                                $oneday => get_string('recalloneday', 'via'),
                                $twosdays => get_string('recalltwodays', 'via'),
                                $oneweek => get_string('recalloneweek', 'via'));

        $mform->addElement('select', 'remindertime', get_string('sendrecall', 'via'), $roptions);
        $mform->setAdvanced('remindertime', true);
        $mform->setDefault('remindertime', 0);
        $mform->disabledif ('remindertime', 'activitytype', 'checked');// Cannot send reminder if permanent activity!
        $mform->disabledif ('remindertime', 'nowevent', 'eq', 1);
        $mform->disabledif ('remindertime', 'pastevent', 'eq', 1);
        $mform->addHelpButton('remindertime', 'sendrecall', 'via');

        // SESSION PARAMETERS!
        $mform->addElement('header', 'sessionparameters', get_string('sessionparameters', 'via'));

        // Activity type!
        $roomoptions = array( 1 => get_string('standard', 'via'), 2 => get_string('seminar', 'via'));

        $mform->addElement('select', 'roomtype', get_string('roomtype', 'via'), $roomoptions);
        $mform->setAdvanced('roomtype', true);
        $mform->setDefault('roomtype', 1);
        $mform->disabledif ('roomtype', 'nowevent', 'eq', 1);
        $mform->disabledif ('roomtype', 'pastevent', 'eq', 1);
        $mform->addHelpButton('roomtype', 'roomtype', 'via');

        $qualityoptions = via_get_list_profils();

        $mform->addElement('select', 'profilid', get_string('multimediaquality', 'via'), $qualityoptions);
        $mform->setAdvanced('profilid', true);

        $qualitydefault = array_search(end($qualityoptions), $qualityoptions);

        foreach ($qualityoptions as $key => $quality) {
            if ($quality == "Qualité standard") {
                $qualitydefault = $key;
            }
        }

        $mform->setDefault('profilid', $qualitydefault);
        $mform->disabledif ('profilid', 'pastevent', 'eq', 1);
        $mform->addHelpButton('profilid', 'multimediaquality', 'via');

        // Session recordings!
        $recordoptions = array( 0 => get_string('notactivated', 'via'),
                                1 => get_string('unified', 'via'),
                                2 => get_string('multiple', 'via'));

        $mform->addElement('select', 'recordingmode', get_string('recordingmode', 'via'), $recordoptions);
        $mform->setDefault('recordingmode', 0);
        $mform->disabledif ('recordingmode', 'pastevent', 'eq', 1);
        $mform->addHelpButton('recordingmode', 'recordingmode', 'via');

        $recordbehavioroptions = array( 1 => get_string('automatic', 'via'), 2 => get_string('manual', 'via'));
        $mform->addElement('select', 'recordmodebehavior', get_string('recordmodebehavior', 'via'), $recordbehavioroptions);
        $mform->setDefault('recordmodebehavior', 1);
        $mform->disabledif ('recordmodebehavior', 'recordingmode', 'eq', 0);
        $mform->disabledif ('recordmodebehavior', 'pastevent', 'eq', 1);
        $mform->addHelpButton('recordmodebehavior', 'recordmodebehavior', 'via');

        // Review playbacks!
        $mform->addElement('selectyesno', 'isreplayallowed', get_string('reviewacitvity', 'via'));
        $mform->setDefault('isreplayallowed', 0);
        $mform->disabledif ('isreplayallowed', 'recordingmode', 'eq', 0);
        $mform->addHelpButton('isreplayallowed', 'reviewacitvity', 'via');

        $waitingroomoptions = array(0 => get_string('donousewaitingroom', 'via'),
                                    1 => get_string('inpresentatorabsence', 'via'),
                                    2 => get_string('awaitingauthorization', 'via'));
        $mform->addElement('select', 'waitingroomaccessmode', get_string('waitingroomaccessmode', 'via'), $waitingroomoptions);
        $mform->setDefault('waitingroomaccessmode', 0);
        $mform->setAdvanced('waitingroomaccessmode', true);
        $mform->disabledif ('waitingroomaccessmode', 'pastevent', 'eq', 1);
        $mform->addHelpButton('waitingroomaccessmode', 'waitingroomaccessmode', 'via');

        if (get_config('via', 'via_participantmustconfirm')) {
            $mform->addElement('selectyesno', 'needconfirmation', get_string('needconfirmation', 'via'));
            $mform->setType('needconfirmation', PARAM_BOOL);
            $mform->setDefault('needconfirmation', 0);
            $mform->setAdvanced('needconfirmation', true);
            $mform->disabledif ('needconfirmation', 'pastevent', 'eq', 1);
            $mform->addHelpButton('needconfirmation', 'needconfirmation', 'via');
        } else {
            $mform->addElement('hidden', 'needconfirmation', 0);
            $mform->setType('needconfirmation', PARAM_BOOL);
        }

        // Categories!
        if (get_config('via', 'via_categories')) {
            $mform->addElement('header', 'categoriesheader', get_string('categoriesheader', 'via'));
            $viacatgeories = $DB->get_records('via_categories');
            $defaultcat = $DB->get_record('via_categories', array('isdefault' => 1));
            if ($defaultcat) {
                $catgeories = array($defaultcat->id_via => $defaultcat->name);
            }
            $catgeories[0] = get_string('nocategories', 'via');
            if ($viacatgeories) {
                foreach ($viacatgeories as $cat) {
                    if ($defaultcat && $cat->id_via != $defaultcat->id_via) {
                        $catgeories[$cat->id_via] = $cat->name;
                    } else {
                        $catgeories[$cat->id_via] = $cat->name;
                    }
                }
            }
            $mform->addElement('select', 'category', get_string('category', 'via'), $catgeories);
        }

        // Enrolment!
        $mform->addElement('header', 'enrolmentheader', get_string('enrolmentheader', 'via'));

        $level = $this->context->instanceid;
        // If at site level, we can only add people using manual enrol!
        if ($level == '1') {
            $enrolmentoptions = array( 1 => get_string('manualenrol', 'via'));
        } else {
            $enrolmentoptions = array( 0 => get_string('automaticenrol', 'via'), 1 => get_string('manualenrol', 'via'));
        }

        $mform->addElement('select', 'enroltype', get_string('enrolmenttype', 'via'), $enrolmentoptions);
        $mform->setDefault('enroltype', 0);
        $mform->addHelpButton('enroltype', 'enrolmenttype', 'via');

        $mform->addElement('checkbox', 'noparticipants',  get_string('noparticipantscheckbox', 'via'));
        $mform->setDefault('noparticipants', 0);
        $mform->disabledif ('noparticipants', 'enroltype', 'eq', 1);
        $mform->addHelpButton('noparticipants', 'noparticipants', 'via');

        // HIDDEN INFO!
        global $USER;
        $mform->addElement('hidden', 'creator', $USER->id);
        $mform->setType('creator', PARAM_INT);

        $mform->addElement('hidden', 'pastevent', 0);
        $mform->setType('pastevent', PARAM_BOOL);

        $mform->addElement('hidden', 'nowevent', 0);
        $mform->setType('nowevent', PARAM_BOOL);

        $mform->addElement('hidden', 'viaactivityid', 0);
        $mform->setType('viaactivityid', PARAM_INT);

        $mform->addElement('hidden', 'activitystate', 1);
        $mform->setType('activitystate', PARAM_INT);

        $mform->addElement('hidden', 'audiotype', 1);
        $mform->setType('audiotype', PARAM_INT);

        $mform->addElement('hidden', 'sendinvite', 0);
        $mform->setType('sendinvite', PARAM_INT);

        // GROUPS AND VISIBILITY!
        // Standard grouping features.
        $features = new stdClass();
        $features->groups = true;
        $features->groupings = true;
        $features->groupmembersonly = true;
        $this->standard_coursemodule_elements($features);

        $this->add_action_buttons();
    }

    // Sets default values for empty properties before the form is rendered.
    public function data_preprocessing(&$defaultvalues) {
        if (isset($defaultvalues['viaactivityid']) && $defaultvalues['viaactivityid']) {
            if ($sviinfos = update_info_database($defaultvalues)) {
                foreach ($sviinfos as $key => $svi) {
                    $defaultvalues[$key] = $svi;
                }
            }
            if (($defaultvalues['datebegin'] + ($defaultvalues['duration'] * 60)) < time() &&
                $defaultvalues['activitytype'] != 2) {
                $defaultvalues['pastevent'] = 1;
            } else {
                $defaultvalues['pastevent'] = 0;
            }
            if (time() > $defaultvalues['datebegin'] &&
                time() < ($defaultvalues['datebegin'] + ($defaultvalues['duration'] * 60)) &&
                $defaultvalues['nbConnectedUsers'] >= 1) {
                $defaultvalues['nowevent'] = 1;
            } else {
                $defaultvalues['nowevent'] = 0;
            }
        }
        if (isset($defaultvalues['activitytype'])) {
            switch($defaultvalues['activitytype']) {
                case 1:
                    $defaultvalues['activitytype'] = 0;
                    break;
                case 2:
                    $defaultvalues['activitytype'] = 1;
                    break;
                default:
                    $defaultvalues['activitytype'] = 0;
                    break;
            }
        }
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        if ((($data['datebegin'] + ($data['duration'] * 60) < time() && !$data['viaactivityid'] && $data['activitytype'] == 0) ||
        ($data['viaactivityid'] != 0 && ($data['datebegin'] != 0 && ($data['datebegin'] + ($data['duration'] * 60) < time())) &&
        $data['activitytype'] == 0)) && $data['pastevent'] == 0) {
            $errors['datebegin'] = get_string('passdate', 'via');
        }

        if (isset($data['activitytype'])
                && $data['activitytype'] == 1
                && isset($data['recordingmode'])
                && $data['recordingmode'] == 1 ) {
            $errors['recordingmode'] = get_string('nounifiedrecordpermanent', 'via');
        }

        if (($data['enroltype'] == 1) || (!isset($data['noparticipants']))) {
            $DB->set_field('via', 'noparticipants', '0', array('id' => $this->_instance));
        }
        return $errors;
    }

}
