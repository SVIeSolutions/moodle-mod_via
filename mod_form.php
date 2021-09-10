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
 * Instance add/edit form
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/moodleform_mod.php');

require_once(get_vialib());

/** Inherited class used when editing via instances. */
class mod_via_mod_form extends moodleform_mod {

    /** Defines the form contents. */
    public function definition() {
        global $CFG, $DB, $USER, $PAGE;

        $PAGE->requires->jquery();
        if (!isset($this->_validated)) {
            $PAGE->requires->js('/mod/via/javascript/mod_form.js', true);
        }

        $mform =& $this->_form;

        $groupingid = optional_param('groupingid', null, PARAM_INT);
        $groupid = optional_param('groupid', null, PARAM_INT);
        $fromjs = optional_param('fromjs', null, PARAM_BOOL);

        // We come from a call ajax in this page.
        $ajax = isset($fromjs) || isset($groupid) || isset($groupingid);
        if (!isset($groupingid) && !isset($fromjs)) {
            if (!isset($groupid) && isset($this->_cm)) {
                $groupsarray = getgroupsfrommodule($this->_cm, $this->_cm->availability);
                $groupingid = $groupsarray[0];
                $groupid = $groupsarray[1];
            }
        }

        if (isset($_SESSION['ErrMaxSimActMessage'])) {
            $mform->addElement('html', '<div class="mform" style="text-align:center;">
            <span class="error" style="text-align:left;">' . $_SESSION['ErrMaxSimActMessage'] . '</span></div>');
            unset($_SESSION['ErrMaxSimActMessage']);
        }

        // General info.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Title!
        $mform->addElement('text', 'name', get_string('activitytitle', 'via'), array('size' => '64'));
        $mform->setType('name', PARAM_CLEANHTML);
        $mform->addRule('name', null, 'required', null, 'client');


        //Via HTML5.
        if (get_config('via', 'via_html5activation') == 1) {

            $versionoptions = array( 0 => get_string('via9', 'via'));
            try {
                $api = new mod_via_api();
                $cieinforesponse = $api->cieinfo();
                // 0 = Via6And9.
                // 1 = Via6.
                // 2 = Via9.
                // 3 = VroomAndVia.
                // 4 = VRoomOnly.
                if (isset($cieinforesponse) && ($cieinforesponse["ViaVersionRestriction"] == 3 || $cieinforesponse["ViaVersionRestriction"] == 4)) {
                    switch ($cieinforesponse["ViaVersionRestriction"]) {
                        case 3:
                            $versionoptions = array( 0 => get_string('via9', 'via'),
                                   1 => get_string('vroom', 'via'));
                            break;
                        case 4:
                            $versionoptions = array(1 => get_string('vroom', 'via'));
                            break;
                    }
                }
            } catch (Exception $e) {
                // Do Nothing.
            }
            $mform->addElement('select', 'activityversion', get_string('activityversion', 'via'), $versionoptions);
            $mform->setDefault('activityversion', 0);
            $mform->addHelpButton('activityversion', 'activityversion', 'via');
        }

        $this->standard_intro_elements();

        // DURATION!
        $mform->addElement('header', 'activityduration', get_string('durationheader', 'via'));

        // Permanent activity!
        if (get_config('via', 'via_permanentactivities') == 1) {
            $mform->addElement('advcheckbox', 'activitytype', get_string("permanent", "via"), '', array('group' => 0), array(0, 1));
            $mform->disabledif ('activitytype', 'pastevent', 'eq', 1);
            $mform->disabledif ('activitytype', 'wassaved', 'eq', 1);
            $mform->addHelpButton('activitytype', 'permanent', 'via');
        }

        // Start Date!
        if (isset($this->current->activitytype) && $this->current->activitytype == '3') {
            $mform->addElement('date_time_selector', 'datebegin', get_string('availabledate', 'via'), array('optional' => true));
        } else {
            $mform->addElement('date_time_selector', 'datebegin', get_string('availabledate', 'via'), array('optional' => false));
            $mform->setDefault('datebegin', (time() + (60 * 10)));
            $mform->disabledif ('datebegin', 'nowevent', 'eq', 1);
            $mform->disabledif ('datebegin', 'pastevent', 'eq', 1);
        }
        $mform->disabledif ('datebegin', 'activitytype', 'checked');

        // Duration!
        $mform->addElement('text', 'duration', get_string('duration', 'via'), array('size' => 4, 'maxlength' => 4));
        $mform->setType('duration', PARAM_INT);
        $mform->setDefault('duration', '60');
        $mform->disabledif ('duration', 'activitytype', 'checked');
        $mform->disabledif ('duration', 'pastevent', 'eq', 1);

        if (get_config('via', 'via_presencestatus')) {
            // Presence!
            $mform->addElement('text', 'presence', get_string('presence', 'via'), array('size' => 4, 'maxlength' => 4));
            $mform->addHelpButton('presence', 'presence', 'via');
            $mform->setType('presence', PARAM_INT);
            $mform->setDefault('presence', '30');
            $mform->hideIf('presence', 'activitytype', 'checked');
        } else {
            $mform->addElement('hidden', 'presence', get_string('presence', 'via'), array('size' => 4, 'maxlength' => 4));
            $mform->setType('presence', PARAM_INT);
            $mform->setDefault('presence', '0');
        }

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
        $mform->setDefault('roomtype', 1);
        $mform->disabledif ('roomtype', 'nowevent', 'eq', 1);
        $mform->disabledif ('roomtype', 'pastevent', 'eq', 1);
        $mform->addHelpButton('roomtype', 'roomtype', 'via');
        // If HTML5.
        $mform->hideIf('roomtype', 'activityversion', 'eq', 1);

        // Show Participants!
        $showoptions = array( 0 => get_string('hidelist', 'via'),
                              1 => get_string('showlist', 'via'));
        $mform->addElement('select', 'showparticipants', get_string('showparticipants', 'via'), $showoptions);
        $mform->setDefault('showparticipants', 1);
        $mform->disabledif ('showparticipants', 'roomtype', 'eq', 1);
        $mform->addHelpButton('showparticipants', 'showparticipants', 'via');
        // If HTML5.
        $mform->hideIf('showparticipants', 'activityversion', 'eq', 1);

        $qualityoptions = $DB->get_records('via_params', array('param_type' => 'multimediaprofil'));
        if (!$qualityoptions) {
            via_get_list_profils();
            $qualityoptions = $DB->get_records('via_params', array('param_type' => 'multimediaprofil'));
        }
        if ($qualityoptions) {
            $options = array();
            foreach ($qualityoptions as $option) {
                $options[$option->value] = via_get_profilname($option->param_name);
            }
            $mform->addElement('select', 'profilid', get_string('multimediaquality', 'via'), $options);
            $mform->disabledif ('profilid', 'pastevent', 'eq', 1);
            $mform->addHelpButton('profilid', 'multimediaquality', 'via');
            // If HTML5.
            $mform->hideIf('profilid', 'activityversion', 'eq', 1);
        }

        // Session recordings!
        $recordoptions = array( 0 => get_string('notactivated', 'via'),
                                1 => get_string('unified', 'via'),
                                2 => get_string('multiple', 'via'));
        $mform->addElement('select', 'recordingmode', get_string('recordingmode', 'via'), $recordoptions);
        $mform->setDefault('recordingmode', 0);
        $mform->disabledif ('recordingmode', 'pastevent', 'eq', 1);
        $mform->addHelpButton('recordingmode', 'recordingmode', 'via');
        // If HTML5.
        $mform->hideIf('recordingmode', 'activityversion', 'eq', 1);

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
                                    1 => get_string('inhostabsence', 'via'),
                                    2 => get_string('awaitingauthorization', 'via'));
        $mform->addElement('select', 'waitingroomaccessmode', get_string('waitingroomaccessmode', 'via'), $waitingroomoptions);
        $mform->setDefault('waitingroomaccessmode', 0);
        $mform->disabledif ('waitingroomaccessmode', 'pastevent', 'eq', 1);
        $mform->addHelpButton('waitingroomaccessmode', 'waitingroomaccessmode', 'via');
        // If HTML5.
        $mform->hideIf('waitingroomaccessmode', 'activityversion', 'eq', 1);

        if (get_config('via', 'via_participantmustconfirm')) {
            $mform->addElement('selectyesno', 'needconfirmation', get_string('needconfirmation', 'via'));
            $mform->setType('needconfirmation', PARAM_BOOL);
            $mform->setDefault('needconfirmation', 0);
            $mform->disabledif ('needconfirmation', 'pastevent', 'eq', 1);
            $mform->addHelpButton('needconfirmation', 'needconfirmation', 'via');
            // If HTML5.
            $mform->hideIf('needconfirmation', 'activityversion', 'eq', 1);
        } else {
            $mform->addElement('hidden', 'needconfirmation', 0);
            $mform->setType('needconfirmation', PARAM_BOOL);
        }

        $mform->addElement('checkbox', 'ish264', get_string("ish264", "via"));
        $mform->setDefault('ish264', 0);
        $mform->setType('ish264', PARAM_INT);
        $mform->disabledif ('ish264', 'pastevent', 'eq', 1);
        $mform->addHelpButton('ish264', 'ish264', 'via');
        // If HTML5.
        $mform->hideIf('ish264', 'activityversion', 'eq', 1);

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
            // If HTML5.
            $mform->hideIf('category', 'activityversion', 'eq', 1);
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
        $mform->disabledif('noparticipants', 'enroltype', 'eq', 1);
        $mform->addHelpButton('noparticipants', 'noparticipants', 'via');

        // Enrolled users lists.
        $ctx = context_course::instance($this->current->course);
        if (!isset($groupingid) || $groupingid == 0) {

            if (isset($groupid)) {
                $users = get_enrolled_users($ctx, null, $groupid);
            } else {
                $users = get_enrolled_users($ctx);
            }

            $pusers = array();
            foreach ($users as $key => $value) {
                $pusers[$key] = $value->lastname . ' ' . $value->firstname . ' (' . $value->username .')';
            }
        } else {

            $groups = groups_get_all_groups($this->current->course, 0, $groupingid);
            $i = 1;
            foreach ($groups as $g) {
                $groupusers = get_enrolled_users($ctx, null, $g->id);
                if ($i == 1) {
                    $users = $groupusers;
                } else {
                    $users = array_unique(array_merge($users, $groupusers), SORT_REGULAR);
                }
                $i++;
            }

            $pusers = array();
            if (isset($users)) {
                foreach ($users as $key => $value) {
                    $pusers[$value->id] = $value->lastname . ' ' . $value->firstname . ' (' . $value->username .')';
                }
            }
        }

        // If we are editing, we have a participants list.
        if ($this->current->instance != '' && !$ajax|| (isset($this->current->activitytype)
            && $this->current->activitytype == 3)) {
            $editing = true;
            $vusers = $DB->get_records_sql('SELECT vp.*, u.firstname, u.lastname, u.username
                                            FROM {via_participants} vp
                                            LEFT JOIN {user} u ON vp.userid = u.id
                                            WHERE activityid = ?
                                            ORDER BY u.lastname ASC', array($this->current->instance));
            if ($vusers) {
                foreach ($vusers as $u) {
                    if ($u->participanttype == 1) {
                        $participants[$u->userid] = $u->lastname . ' ' . $u->firstname . ' (' . $u->username .')';
                    } else if ($u->participanttype == 3) {
                        $animators[$u->userid]  = $u->lastname . ' ' . $u->firstname . ' (' . $u->username .')';
                    } else {
                        $host[$u->userid] = $u->lastname . ' ' . $u->firstname . ' (' . $u->username .')';
                    }
                    // Unset users from potential users list!
                    unset($pusers[$u->userid]);
                }

            }
            // If there are no users we set to empty rather than null, to avoid php errors.
            if (!isset($participants)) {
                $participants = '';
            }
            if (!isset($animators)) {
                $animators = '';
            }
            if (!isset($host)) {
                $host[$USER->id] = $USER->lastname . ' ' . $USER->firstname . ' (' . $USER->username .')';
            }
        } else {
            $editing = false;
            $host = array();
            $host[$USER->id] = $USER->lastname . ' ' . $USER->firstname . ' (' . $USER->username .')';
            $participants = '';
            $animators = '';
        }
        if (key($host)) {
            if ($pusers != '') {
                unset($pusers[key($host)]);
            }
            if ($participants != '') {
                unset($participants[key($host)]);
            }
            if ($animators != '') {
                unset($animators[key($host)]);
            }
        }

        $group = array();
        $group[] =& $mform->createElement('select', 'host',
                    get_string('host', 'via'), $host, array('class' => 'viahost'));
        $group[] =& $mform->createElement('button', 'add_host',
                    get_string('host_replace', 'via'), 'onclick="replace_host()"');
        $mform->addGroup($group, 'add_hostgroup', get_string('host', 'via'), array(' '), false);
        if (!$editing) {
            $mform->disabledif('host', 'enroltype', 'eq', 0);
            $mform->disabledif('add_host', 'enroltype', 'eq', 0);
        }

        $mform->addElement('html', '<div class="fitem viausers">
                            <i class="fa fa-spinner fa-spin fa-1x fa-fw margin-bottom"></i>
                            <p class="element three potentialusers">'.get_string('potentialusers', 'via').'</p>
                           <p class="three participants">'.get_string('participants', 'via').'</p>
                           <p class="three animators">'.get_string('animators', 'via').'</p></div>');

        $group = array();
        $select1 = $mform->createElement('select', 'potentialusers', '', $pusers, array('class' => 'viauserlists potentialusers'));
        $select1->setMultiple(true);
        $group[] =& $select1;
        $mform->setType('potentialusers', PARAM_TEXT);

        $group[] =& $mform->createElement('button', 'participants_remove_btn', '<', 'onclick="remove_participants()"');
        $group[] =& $mform->createElement('button', 'participants_add_btn', '>', 'onclick="add_participants()"');

        $select2 = $mform->createElement('select', 'participants', get_string('participants', 'via'),
                    $participants, array('class' => 'viauserlists participants'));
        $select2->setMultiple(true);
        $group[] =& $select2;
        $mform->setType('participants', PARAM_TEXT);

        $group[] =& $mform->createElement('button', 'animators_remove_btn', '<', 'onclick="remove_animators()"');
        $group[] =& $mform->createElement('button', 'animators_add_btn', '>', 'onclick="add_animators()"');

        $select3 = $mform->createElement('select', 'animators', get_string('animators', 'via'),
                    $animators, array('class' => 'viauserlists animators'));
        $select3->setMultiple(true);
        $group[] =& $select3;
        $mform->setType('animators', PARAM_TEXT);

        $mform->addGroup($group, 'add_users', get_string('manageparticipants', 'via'), array(' '), false);
        if (!$editing) {
            $mform->disabledif ('add_users', 'enroltype', 'eq', 0);
        }

        $mform->addElement('text', 'searchpotentialusers', get_string('users_search', 'via'), array('class' => 'search'));
        $mform->setType('searchpotentialusers', PARAM_TEXT);

        $mform->addElement('text', 'searchparticipants', get_string('participants_search', 'via'), array('class' => 'search'));
        $mform->setType('searchparticipants', PARAM_TEXT);

        // HIDDEN INFO!
        global $USER;
        $mform->addElement('hidden', 'creator', $USER->id);
        $mform->setType('creator', PARAM_INT);

        $mform->addElement('hidden', 'pastevent', 0);
        $mform->setType('pastevent', PARAM_BOOL);

        $mform->addElement('hidden', 'wassaved', 0);
        $mform->setType('wassaved', PARAM_BOOL);

        $mform->addElement('hidden', 'nowevent', 0);
        $mform->setType('nowevent', PARAM_BOOL);

        $mform->addElement('hidden', 'viaactivityid', 0);
        $mform->setType('viaactivityid', PARAM_INT);

        $mform->addElement('hidden', 'activitystate', 1);
        $mform->setType('activitystate', PARAM_INT);

        $mform->addElement('hidden', 'audiotype', 1);
        $mform->setType('audiotype', PARAM_INT);

        $mform->addElement('hidden', 'isnewvia', 1);
        $mform->setType('isnewvia', PARAM_INT);

        $mform->addElement('hidden', 'sendinvite', 0);
        $mform->setType('sendinvite', PARAM_INT);

        if (isset($this->current->activitytype) && $this->current->activitytype == '3') {
            $mform->addElement('hidden', 'template', 1);
            $mform->setType('template', PARAM_INT);
        }

        // Temporary!
        $mform->addElement('hidden', 'groupid', 0);
        $mform->setType('groupid', PARAM_INT);

        // We add the user id using jquery! To be saved in add_instance.
        $mform->addElement('text', 'save_participants', '');
        $mform->setType('save_participants', PARAM_TEXT);

        $mform->addElement('text', 'save_animators', '');
        $mform->setType('save_animators', PARAM_TEXT);

        $mform->addElement('text', 'save_host', '');
        $mform->setType('save_host', PARAM_TEXT);

        // GROUPS AND VISIBILITY!
        // Standard grouping features.
        // $features = new stdClass();
        $this->_features->groups = false;
        $this->_features->groupings = true;
        $this->_features->groupmembersonly = true;
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Load in existing data as form defaults. Usually new entry defaults are stored directly in
     * form definition (new entry form); this function is used to load in data where values
     * already exist and data is being edited (edit entry form).
     *
     * @param mixed $default_values object or array of default values
     */
    public function data_preprocessing(&$defaultvalues) {
        global $DB;

        if (isset($defaultvalues['viaactivityid']) && $defaultvalues['viaactivityid']) {
            if ($sviinfos = $DB->get_record('via', array('id' => $defaultvalues['id']))) {
                foreach ($sviinfos as $key => $svi) {
                    $defaultvalues[$key] = $svi;
                }
                $defaultvalues['wassaved'] = 1;
            }
            if (($defaultvalues['datebegin'] + ($defaultvalues['duration'] * 60)) < time() && $defaultvalues['activitytype'] == 1) {
                $defaultvalues['pastevent'] = 1;
            } else {
                $defaultvalues['pastevent'] = 0;
            }
            if (time() > $defaultvalues['datebegin'] && time() < ($defaultvalues['datebegin'] + ($defaultvalues['duration'] * 60)
                && $defaultvalues['activitytype'] == 1)) {
                $defaultvalues['nowevent'] = 1;
            } else {
                $defaultvalues['nowevent'] = 0;
            }
        } else if (isset($defaultvalues['activitytype']) && $defaultvalues['activitytype'] == 3) {
            if ($sviinfos = $DB->get_record('via', array('id' => $defaultvalues['id']))) {
                foreach ($sviinfos as $key => $svi) {
                    $defaultvalues[$key] = $svi;
                }
                $defaultvalues['wassaved'] = 0;
                $defaultvalues['pastevent'] = 0;
                $defaultvalues['nowevent'] = 0;
            }
        } else {
            // TEMPLATE VALUES.
            if ($sviinfos = $DB->get_records('via_params', array('param_type' => 'ActivityTemplate'))) {
                foreach ($sviinfos as $key => $svi) {
                    switch($svi->param_name) {
                        case 'RecordingMode' :
                            $defaultvalues['recordingmode'] = $svi->value;
                            break;

                        case 'SessionPresence' :
                            $defaultvalues['presence'] = $svi->value;

                        case 'RecordModeBehavior' :
                            $defaultvalues['recordmodebehavior'] = $svi->value;
                            break;

                        case 'ReminderTime' :
                            $defaultvalues['remindertime'] = $svi->value;
                            break;

                        case 'IsReplayAllowed' :
                            $defaultvalues['isreplayallowed'] = $svi->value;
                            break;

                        case 'ProfilID' :
                            $defaultvalues['profilid'] = $svi->value;
                            break;

                        case 'ActivityType' :
                            $defaultvalues['activitytype'] = $svi->value;
                            break;

                        case 'NeedConfirmation' :
                            $defaultvalues['needconfirmation'] = $svi->value;
                            break;

                        case 'RoomType' :
                            $defaultvalues['roomtype'] = $svi->value;
                            break;

                        case 'WaitingRoomAccessMode' :
                            $defaultvalues['waitingroomaccessmode'] = $svi->value;
                            break;

                        case 'IsH264' :
                            $defaultvalues['ish264'] = $svi->value;
                            break;
                    }
                }
            }
        }

        if (isset($_SESSION['ErrMaxSimActMessageVia'])) {
            foreach ($_SESSION['ErrMaxSimActMessageVia'] as $key => $svi) {
                if ((isset($defaultvalues[$key]) || $key == 'name' || $key == 'duration') && $key != 'coursemodule') {
                    $defaultvalues[$key] = $svi;
                }
            }
            unset($_SESSION['ErrMaxSimActMessageVia']);
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

        if (isset($defaultvalues['ish264'])) {
            switch($defaultvalues['ish264']) {
                case 0:
                    $defaultvalues['ish264'] = 1;
                    break;
                case 1:
                    $defaultvalues['ish264'] = 0;
                    break;
                default:
                    $defaultvalues['ish264'] = 0;
                    break;
            }
        }
    }

    /**
     * Some basic validation
     *
     * @param $data
     * @param $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        if ((($data['datebegin'] + ($data['duration'] * 60) < time() && !$data['viaactivityid'] && $data['activitytype'] == 0) ||
            ($data['viaactivityid'] != 0 && ($data['datebegin'] != 0 && ($data['datebegin'] + ($data['duration'] * 60) < time())) &&
            $data['activitytype'] == 0)) && $data['pastevent'] == 0 && !isset($data['template'])) {
            $errors['datebegin'] = get_string('passdate', 'via');
        }

        if (isset($data['activitytype'])
                && $data['activitytype'] == 1
                && isset($data['recordingmode'])
                && $data['recordingmode'] == 1
                && $data['activityversion'] == 0) {
            $errors['recordingmode'] = get_string('nounifiedrecordpermanent', 'via');
        }

        if (isset($data['template']) && $data['activitytype'] == 0 && $data['datebegin'] == 0) {
                $errors['datebegin'] = get_string('unplanned_error', 'via');
        }

        return $errors;
    }
}