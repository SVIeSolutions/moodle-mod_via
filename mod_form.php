<?php

/**
 * Creation/update form for Via instances.
 *
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions 
 */
 
require_once($CFG->dirroot.'/course/moodleform_mod.php');


/** Inherited class used when editing via instances. */
class mod_via_mod_form extends moodleform_mod {

    /** Defines the form contents. */
    function definition() {
		global $CFG, $DB;
		
        $mform =& $this->_form;

		//************************************************************************//
		//										GENERAL INFO
		//************************************************************************//
		$mform->addElement('header', 'general', get_string('general', 'form'));
		
		// Title
        $mform->addElement('text', 'name', get_string('activitytitle', 'via'), array('size' => '64'));
        $mform->setType('name', PARAM_CLEANHTML);
        $mform->addRule('name', null, 'required', null, 'client');
		
		// Description		
        $mform->addElement('htmleditor', 'description', get_string('description', 'via'));
        $mform->setType('description', PARAM_RAW);
        $mform->addRule('description', null, 'required', null, 'server');
		
		// Grade
		$mform->addElement('modgrade', 'grade', get_string('grade'));
        $mform->setDefault('grade', 100);
		//*************************************************************************//
		
		
		//************************************************************************//
		//										DURATION
		//************************************************************************//
		$mform->addElement('header', 'activityduration', get_string('headerduration', 'via'));
		
		// Permanent activity
		$mform->addElement('checkbox', 'activitytype', get_string("permanent", "via"));
		$mform->disabledIf('activitytype', 'pastevent', 'eq', 1);
		$mform->addHelpButton('activitytype', 'permanent', 'via');
		
		// Start Date
		$mform->addElement('date_time_selector', 'datebegin', get_string('availabledate', 'via'), array('optional'=>false));
		$mform->setDefault('datebegin', time());
		$mform->disabledIf('datebegin', 'activitytype', 'checked');
		$mform->disabledIf('datebegin', 'nowevent', 'eq', 1);			
		$mform->disabledIf('datebegin', 'pastevent', 'eq', 1);
	
		// Duration		
		$mform->addElement('text', 'duration', get_string('duration', 'via'), array('size' => '4'));
		$mform->setType('duration', PARAM_INT);
		$mform->setDefault('duration', '60');
		$mform->disabledIf('duration', 'activitytype', 'checked');
		$mform->disabledIf('duration', 'pastevent', 'eq', 1);
		
		//Automatic reminders
		$onehour = 60*60;
		$twohours = (60*60)*2;
		$oneday = (60*60)*24;
		$twosdays = (60*60)*48;
		$oneweek = ((60*60)*24)*7;
		$reminder_options = array( 0 => get_string('norecall', 'via'), $onehour => get_string('recallonehour', 'via'), $twohours => get_string('recalltwohours', 'via'), $oneday => get_string('recalloneday', 'via'), $twosdays => get_string('recalltwodays', 'via'), $oneweek => get_string('recalloneweek', 'via'));

		$mform->addElement('select', 'remindertime', get_string('sendrecall', 'via'), $reminder_options);
		$mform->setAdvanced('remindertime', true);
		$mform->setDefault('remindertime', 0);
		$mform->disabledIf('remindertime', 'activitytype', 'checked'); // cannot send reminder if permanent activity
		$mform->disabledIf('remindertime', 'nowevent', 'eq', 1);
		$mform->disabledIf('remindertime', 'pastevent', 'eq', 1);
		$mform->addHelpButton('remindertime', 'sendrecall', 'via');
		
		//************************************************************************//
		//										 SESSION PARAMETERS
		//************************************************************************//
		$mform->addElement('header', 'sessionparameters', get_string('sessionparameters', 'via'));
		
		// Activity type
		$room_type_options = array( 1 => get_string('standard', 'via'), 2 => get_string('seminar', 'via'));

        $mform->addElement('select', 'roomtype', get_string('roomtype', 'via'), $room_type_options);
		$mform->setAdvanced('roomtype', true);
        $mform->setDefault('roomtype', 1);
		$mform->disabledIf('roomtype', 'nowevent', 'eq', 1);
		$mform->disabledIf('roomtype', 'pastevent', 'eq', 1);
		$mform->addHelpButton('roomtype', 'roomtype', 'via');

		$quality_options = via_get_listProfils();
		
		$mform->addElement('select', 'profilid', get_string('multimediaquality', 'via'), $quality_options);
		$mform->setAdvanced('profilid', true);
		
		$quality_default = array_search(end($quality_options),$quality_options);
	
		foreach($quality_options as $key=>$quality){
			if($quality == "Qualité standard"){
				$quality_default = $key;
			}
		}
		
        $mform->setDefault('profilid', $quality_default);
		$mform->disabledIf('profilid', 'pastevent', 'eq', 1);
		$mform->addHelpButton('profilid', 'multimediaquality', 'via');
		
		//Session recordings	
		$record_options = array( 0 => get_string('notactivated', 'via'), 1 => get_string('unified', 'via'), 2 => get_string('multiple', 'via'));			
		
		$mform->addElement('select', 'recordingmode', get_string('recordingmode', 'via'), $record_options);
		$mform->setDefault('recordingmode', 0);				
		$mform->disabledIf('recordingmode', 'pastevent', 'eq', 1);
		$mform->addHelpButton('recordingmode','recordingmode', 'via');
		
		$recordbehavior_options = array( 1 => get_string('automatic', 'via'), 2 => get_string('manual', 'via'));		
		$mform->addElement('select', 'recordmodebehavior', get_string('recordmodebehavior', 'via'), $recordbehavior_options);
        $mform->setDefault('recordmodebehavior', 1);	
		$mform->disabledIf('recordmodebehavior', 'recordingmode', 'eq', 0);
		$mform->disabledIf('recordmodebehavior', 'pastevent', 'eq', 1);
		$mform->addHelpButton('recordmodebehavior', 'recordmodebehavior','via');
		
		//Review playbacks
        $mform->addElement('selectyesno', 'isreplayallowed', get_string('reviewacitvity', 'via'));
        $mform->setDefault('isreplayallowed', 0);
		$mform->disabledIf('isreplayallowed', 'recordingmode', 'eq', 0);
		$mform->addHelpButton('isreplayallowed', 'reviewacitvity','via');
		
		$waiting_room_options = array( 0 => get_string('donousewaitingroom', 'via'), 1 => get_string('inpresentatorabsence', 'via'), 2 => get_string('awaitingauthorization', 'via'));			
        $mform->addElement('select', 'waitingroomaccessmode', get_string('waitingroomaccessmode', 'via'), $waiting_room_options);
        $mform->setDefault('waitingroomaccessmode', 0);				
		$mform->setAdvanced('waitingroomaccessmode', true);
		$mform->disabledIf('waitingroomaccessmode', 'pastevent', 'eq', 1);
		$mform->addHelpButton('waitingroomaccessmode', 'waitingroomaccessmode','via');
		
		if(get_config('via','via_participantmustconfirm')){
			$mform->addElement('selectyesno', 'needconfirmation', get_string('needconfirmation', 'via'));
			$mform->setType('needconfirmation', PARAM_BOOL);
			$mform->setDefault('needconfirmation', 0);
			$mform->setAdvanced('needconfirmation', true);
			$mform->disabledIf('needconfirmation', 'pastevent', 'eq', 1);
			$mform->addHelpButton('needconfirmation', 'needconfirmation','via');
		}else{
			$mform->addElement('hidden', 'needconfirmation', 0);
			$mform->setType('needconfirmation', PARAM_BOOL);
		}	
		
		//************************************************************************//
		//							          		Categories
		//************************************************************************//
		if(get_config('via','via_categories')){
			$mform->addElement('header', 'categoriesheader', get_string('categoriesheader', 'via'));
			$via_catgeories = $DB->get_records('via_categories');
			$defaultcat = $DB->get_record('via_categories', array('isdefault'=>1));
			if($defaultcat){
				$catgeories = array($defaultcat->id_via => $defaultcat->name);
			}
			$catgeories[0] =  get_string('nocategories', 'via'); // was add no cats even if a default was added
			if($via_catgeories){
				foreach($via_catgeories as $cat){
					if($defaultcat && $cat->id_via != $defaultcat->id_via){
						$catgeories[$cat->id_via] = $cat->name;
					}else{
						$catgeories[$cat->id_via] = $cat->name;
					}
				}
			}
			$mform->addElement('select', 'category', get_string('category', 'via'), $catgeories);
		}
		
		
		//************************************************************************//
		//							          		Enrolment
		//************************************************************************//
		
		$mform->addElement('header', 'enrolmentheader', get_string('enrolmentheader', 'via'));
		
		$level = $this->context->instanceid;
		/* if at site level, we can only add people using manual enrol */
		if($level == '1'){
			$enrolment_options = array( 1 => get_string('manualenrol', 'via'));
		}else{
			$enrolment_options = array( 0 => get_string('automaticenrol', 'via'), 1 => get_string('manualenrol', 'via'));
		}

        $mform->addElement('select', 'enroltype', get_string('enrolmenttype', 'via'), $enrolment_options);
        $mform->setDefault('enroltype', 0);
		$mform->addHelpButton('enroltype', 'enrolmenttype','via');
		
		$mform->addElement('checkbox', 'noparticipants',  get_string('noparticipantscheckbox','via'));
		$mform->setDefault('noparticipants', 0);
		$mform->disabledIf('noparticipants', 'enroltype', 'eq', 1);		
		$mform->addHelpButton('noparticipants', 'noparticipants', 'via');
		
		//************************************************************************//
		//							          		HIDDEN INFO
		//************************************************************************//
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
		
		$mform->addElement('hidden', 'sendinvite', 0 /*$sendinvite*/);
		$mform->setType('sendinvite', PARAM_INT);
		
		
		//************************************************************************//
		//							          		GROUPS AND VISIBILITY
		//************************************************************************//
		
        // Standard grouping features.
        $features = new stdClass();
        $features->groups = true;
        $features->groupings = true;
        $features->groupmembersonly = true;
        $this->standard_coursemodule_elements($features);

        $this->add_action_buttons();
    }

    /** Sets default values for empty properties before the form is rendered. */
    function data_preprocessing(&$default_values) {
		if(isset($default_values['viaactivityid']) && $default_values['viaactivityid']){
			if($svi_infos = update_info_database($default_values)){		
				foreach($svi_infos as $key=>$svi){
					$default_values[$key] = $svi;
				}
			}
			if(($default_values['datebegin']+($default_values['duration']*60)) < time() && $default_values['activitytype'] != 2){
				$default_values['pastevent'] = 1;
			}else{
				$default_values['pastevent'] = 0;
			}
			if(time() > $default_values['datebegin'] && time() < ($default_values['datebegin']+($default_values['duration']*60)) && $default_values['nbConnectedUsers'] >= 1){
				$default_values['nowevent'] = 1;
			}else{
				$default_values['nowevent'] = 0;
			}
		}
		if(isset($default_values['activitytype'])){
			switch($default_values['activitytype']){
				case 1:
					$default_values['activitytype'] = 0;
					break;
				case 2:
					$default_values['activitytype'] = 1;
					break;
				default:
					$default_values['activitytype'] = 0;
					break;
			}
		}

	}
	
	function validation($data, $files) {
		global $DB;
		
        $errors = parent::validation($data, $files);
        if ((($data['datebegin'] + ($data['duration']*60) < time() && !$data['viaactivityid'] && $data['activitytype'] == 0) || ($data['viaactivityid'] != 0 && ($data['datebegin'] != 0 && ($data['datebegin'] + ($data['duration']*60) < time())) && $data['activitytype'] == 0)) && $data['pastevent']==0) {
			$errors['datebegin'] = get_string('passdate', 'via');
		}
		
		if(isset($data['activitytype']) && $data['activitytype'] == 1 && isset($data['recordingmode']) && $data['recordingmode']==1){
			$errors['recordingmode'] = get_string('nounifiedrecordpermanent', 'via');
		}
		
		if(($data['enroltype'] == 1) || (!isset($data['noparticipants']))) {
			$DB->set_field('via', 'noparticipants', '0', array('id'=>$this->_instance));
		}
       return $errors;
    }

}

