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
 * Provides some custom settings for the via module
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die;

global $PAGE, $CFG;

if ($ADMIN->fulltree) {

    require_once($CFG->dirroot.'/mod/via/lib.php');
    require_once(get_vialib());

    $PAGE->requires->js('/mod/via/javascript/conntest.js');

    $config = get_config('via');

    /****
    API infos
    ****/
    $settings->add(new admin_setting_heading('via_apiconfig',
        '<strong>'.get_string('apiconfig', 'via').'</strong>', ''));

    $settings->add(new admin_setting_configtext('via/via_apiurl', get_string('apiurl', 'via'),
        get_string('apiurlsetting', 'via'), "", PARAM_TEXT));

    $settings->add(new admin_setting_configtext('via/via_cleid', get_string('cieid', 'via'),
        get_string('cieidsetting', 'via'), "", PARAM_TEXT));

    $settings->add(new admin_setting_configtext('via/via_apiid', get_string('apiid', 'via'),
        get_string('apiidsetting', 'via'), "", PARAM_TEXT));

    if (isset($config->via_apiurl)) {
        $settings->add(new admin_setting_heading('via_testconn',  '<input type="button"
        onclick="testConnection(document.getElementById(\'adminsettings\'));" value="'.
            get_string('testconnection', 'via').'"/>', ''));
    }

    /****
    API info - clé moodle
    ****/
    $settings->add(new admin_setting_heading('moodle_admin', '<hr>
    <strong>'.get_string('moodle_config', 'via').'</strong>', ''));

    $settings->add(new admin_setting_configtext('via/via_adminid', get_string('moodle_adminid', 'via'),
        get_string('moodleidsetting', 'via'), "", PARAM_TEXT));

    if (isset($config->via_apiurl)) {
        $settings->add(new admin_setting_heading('testadminid',  '<input type="button"
        onclick="testAdminId(document.getElementById(\'adminsettings\'));"
        value="'.get_string('testadminid', 'via').'" />', ''));
    }

    /****
    Categories
    ****/
    $settings->add(new admin_setting_heading('categories', '<hr><strong>'.
        get_string('categoriesheader', 'via').'</strong>', ''));

    $settings->add(new admin_setting_configcheckbox('via/via_categories',
        get_string('categoriesheader', 'via'), get_string('viacategoriesdesc', 'via'), 0));

    if (isset($config->via_categories) && $config->via_categories == 0) {
        $hide = 'hide';
    } else {
        $hide = '';
    }
    $settings->add(new admin_setting_heading('choosecategories', '<input id="choosecategories" type="button" class="'.$hide.'"
    onclick="return openpopup(null, { url: \'/mod/via/choosecategories.php\', name: \'choosecategories\',
    options: \'scrollbars=yes,resizable=no,width=760,height=400\' });" value="'.get_string('choosecategories', 'via').'" />', ''));

    /****
    Activity Template
    ****/

    $settings->add(new admin_setting_heading('activitytemplatebutton', get_string('activitytemplateheader', 'via'),
        get_string('activitytemplatedesc', 'via'). '<br /><br /><input
    id="activitytemplatebutton" type="button"
    onclick="return openpopup(null, { url: \'/mod/via/synctemplate.php\', name: \'activitytemplatebutton\',
    options: \'scrollbars=yes,resizable=no,width=760,height=400\' });" value="'.
        get_string('activitytemplatebutton', 'via').'" />'));

    /****
    Audio mode settings
    ****/
    $settings->add(new admin_setting_heading('via_options',  '<hr><strong>'.
        get_string('options', 'via').'</strong>', ''));

    /****
    Audio type settings
    ****/
    $settings->add(new admin_setting_configmultiselect('via/via_audio_types',
        get_string('audiomodelabel', 'via'), '',
        array(1), array(1 => get_string('modevoiceweb', 'via') )));

    /****
    Portal access settings
    ****/
    $settings->add(new admin_setting_configcheckbox('via/via_portalaccess',
        get_string('portalaccess', 'via'), get_string('portalaccessdesc', 'via'), 0));

    /****
    Associated users list
    ****/
    $settings->add(new admin_setting_configcheckbox('via/via_displayuserlist',
        get_string('displayuserlist', 'via'), get_string('displayuserlistdesc', 'via'), 0));

    /****
    Participant confirmations
    ****/
    $settings->add(new admin_setting_configcheckbox('via/via_participantmustconfirm',
        get_string('participantmustconfirm', 'via'), get_string('participantmustconfirmdesc', 'via'), 0));

    /****
    Participant information synchronization
    ****/
    $settings->add(new admin_setting_configcheckbox('via/via_participantsynchronization',
        get_string('participantsynchronization', 'via'), get_string('participantsynchronizationdesc', 'via'), 0));

    /****
    Permit playback download
    ****/
    $settings->add(new admin_setting_configcheckbox('via/via_downloadplaybacks',
        get_string('downloadplaybacks', 'via'), get_string('downloadplaybacksdesc', 'via'), 0));

    /****
    Display user presence status
    ****/
    $settings->add(new admin_setting_configcheckbox('via/via_presencestatus',
        get_string('presencestatus', 'via'), get_string('presencestatusdesc', 'via'), 1));

    /****
    Permit permanent activities
    ****/
    $settings->add(new admin_setting_configcheckbox('via/via_permanentactivities',
        get_string('permanentactivities', 'via'), get_string('permanentactivitiesdesc', 'via'), 1));

    /****
    Activity deletion
    ****/
    $settings->add(new admin_setting_configcheckbox('via/via_activitydeletion',
        get_string('activitydeletion', 'via'), get_string('activitydeletiondesc', 'via'), 0));

    /****
    Add a personnalised assistance page
    ****/
    $settings->add(new admin_setting_configtext('via/via_technicalassist_url',
        get_string('technicalassist_url', 'via'), get_string('technicalassist_urldesc', 'via'), "", PARAM_TEXT));

    /****
    Backup and duplication settings
    ****/
    $settings->add(new admin_setting_heading('via_backup_options',  '<hr>
    <strong>'.get_string('backup_options', 'via').'</strong>', ''));

    /****
    Activity duplication settings
    ****/
    $settings->add(new admin_setting_configcheckbox('via/via_duplicatecontent',
        get_string('duplication', 'via'), get_string('duplicationdesc', 'via'), 1));

    /****
    Activity backup settings
    ****/
    $settings->add(new admin_setting_configcheckbox('via/via_backupcontent',
        get_string('backup', 'via'), get_string('backupdesc', 'via'), 1));

    /****
    Backup and duplication settings
    ****/
    $settings->add(new admin_setting_heading('via_personnalised_email',  '<hr>
    <strong>'.get_string('email_personnalised_options', 'via').'</strong>', ''));

    // Background image setting.
    $name = 'mod_via/emailheaderimage';
    $title = get_string('emailheaderimage', 'via');
    $description = get_string('emailheaderimage_desc', 'via');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'emailheaderimage', 0,
        array('maxfiles' => 1, 'accepted_types' => array('image')));
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // HeaderColor setting.
    $name = 'mod_via/emailheadercolor';
    $title = get_string('emailheadercolor', 'via');
    $description = get_string('emailheadercolor_desc', 'via');
    $default = '#fff';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // HeaderBGColor setting.
    $name = 'mod_via/emailheaderbgcolor';
    $title = get_string('emailheaderbgcolor', 'via');
    $description = get_string('emailheaderbgcolor_desc', 'via');
    $default = '#7f7f7f';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // TextColor setting.
    $name = 'mod_via/emailtextcolor';
    $title = get_string('emailtextcolor', 'via');
    $description = get_string('emailtextcolor_desc', 'via');
    $default = '#4a4545';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // LinkColor setting.
    $name = 'mod_via/emaillinkcolor';
    $title = get_string('emaillinkcolor', 'via');
    $description = get_string('emaillinkcolor_desc', 'via');
    $default = '#175E8F';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // LinkColor setting.
    $name = 'mod_via/emailaccesslinkcolor';
    $title = get_string('emailaccesslinkcolor', 'via');
    $description = get_string('emailaccesslinkcolor_desc', 'via');
    $default = '#96CB4D';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);
}
