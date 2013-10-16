<?php 

/**
 * Configuration of Via
 *
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions 
 */
 
defined('MOODLE_INTERNAL') || die;

global $PAGE;

if ($ADMIN->fulltree) {

	require_once($CFG->dirroot.'/mod/via/lib.php');
	
	$PAGE->requires->js('/mod/via/conntest.js');	

	/****
	API infos
	****/
	$settings->add(new admin_setting_heading('pluginversion', get_string('pluginversion', 'via'),''));
	
	$settings->add(new admin_setting_heading('via_apiconfig',  '<strong>'.get_string('apiconfig', 'via').'</strong>',''));

	$settings->add(new admin_setting_configtext('via_apiurl', get_string('apiurl', 'via'),
					   get_string('apiurlsetting', 'via'), "", PARAM_TEXT));

	$settings->add(new admin_setting_configtext('via_cleid', get_string('cieid', 'via'),
					   get_string('cieidsetting', 'via'), "", PARAM_TEXT));

	$settings->add(new admin_setting_configtext('via_apiid', get_string('apiid', 'via'),
					   get_string('apiidsetting', 'via'), "", PARAM_TEXT));
			
	$settings->add(new admin_setting_heading('via_testconn',  '<input type="button" onclick="return testConnection(document.getElementById(\'adminsettings\'));" value="'.get_string('testconnection', 'via').'" />',''));
	
	/****
	API info - clé moodle
	****/
	
	$settings->add(new admin_setting_heading('moodle_admin', '<hr><strong>'.get_string('moodle_adminid', 'via').'</strong>',''));
	
	$settings->add(new admin_setting_configtext('via_adminid', get_string('moodle_adminid', 'via'),
						get_string('moodleidsetting', 'via'), "", PARAM_TEXT));

	$settings->add(new admin_setting_heading('testadminid',  '<input type="button" onclick="return testAdminId(document.getElementById(\'adminsettings\'));" value="'.get_string('testadminid', 'via').'" />',''));

	
	/****
	Categories
	****/
	$settings->add(new admin_setting_heading('categories', '<hr><strong>'.get_string('categoriesheader', 'via').'</strong>',''));
	
	$settings->add(new admin_setting_configcheckbox('via_categories', get_string('categoriesheader', 'via'), get_string('via_categoriesdesc', 'via'), 0));
	
	if(isset($CFG->via_categories) && $CFG->via_categories != 0){
		$hide = '';
	}else{
		$hide = 'hide';
	}
	$settings->add(new admin_setting_heading('choosecategories',  '<input id="choosecategories" type="button" class="'.$hide.'" onclick="return openpopup(null, { url: \'/mod/via/choosecategories.php\', name: \'choosecategories\', options: \'scrollbars=yes,resizable=no,width=760,height=400\' });" value="'.get_string('choosecategories', 'via').'" />',''));
	
	
	/****
	Audio mode settings
	****/
	$settings->add(new admin_setting_heading('via_options',  '<hr><strong>'.get_string('options', 'via').'</strong>',''));

	$settings->add(new admin_setting_configmultiselect('via_audio_types',  get_string('audiomodelabel', 'via'),
		/*get_string("viaaudiotypes", "via")*/ '', array(1), array(1 => get_string('modevoiceweb', 'via')
																									  /*, 2 => get_string('modewebphone', 'via'),
																									  3 => get_string('modephone', 'via')*/
																									  )));

	/****
	Email notifications settings
	****/
	$settings->add(new admin_setting_configcheckbox('via_moodleemailnotification', get_string('moodleemailnotification', 'via'), get_string('moodleemailnotificationdesc', 'via'), 1));

	/****
	Participant confirmations
	****/
	$settings->add(new admin_setting_configcheckbox('via_participantmustconfirm', get_string('participantmustconfirm', 'via'), get_string('participantmustconfirmdesc', 'via'), 0));

	/****
	Send personalized invitations
	****/
	$settings->add(new admin_setting_configcheckbox('via_sendinvitation', get_string('sendinvitation', 'via'), get_string('sendinvitationdesc', 'via'), 1));
	
	/****
	Participant information synchronization
	****/
	$settings->add(new admin_setting_configcheckbox('via_participantsynchronization', get_string('participantsynchronization', 'via'), get_string('participantsynchronizationdesc', 'via'), 0));
	
	
}

?>


