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
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

defined('MOODLE_INTERNAL') || die;

global $PAGE;

if ($ADMIN->fulltree) {

    require_once($CFG->dirroot.'/mod/via/lib.php');

    $PAGE->requires->js('/mod/via/javascript/conntest.js');

    $config = get_config('via');

    /****
    API infos
    ****/
    $settings->add(new admin_setting_heading('pluginversion', get_string('pluginversion', 'via') .
    get_config('mod_via', 'version'), ''));

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
    get_string('categoriesheader', 'via'), get_string('via_categoriesdesc', 'via'), 0));

    if (isset($config->via_categories) && $config->via_categories == 0) {
        $hide = 'hide';
    } else {
        $hide = '';
    }
    $settings->add(new admin_setting_heading('choosecategories', '<input id="choosecategories" type="button" class="'.$hide.'"
    onclick="return openpopup(null, { url: \'/mod/via/choosecategories.php\', name: \'choosecategories\',
    options: \'scrollbars=yes,resizable=no,width=760,height=400\' });" value="'.get_string('choosecategories', 'via').'" />', ''));

    /****
    Audio mode settings
    ****/
    $settings->add(new admin_setting_heading('via_options',  '<hr><strong>'.
    get_string('options', 'via').'</strong>', ''));

    /****
    Portal access settings
    ****/
    $settings->add(new admin_setting_configcheckbox('via/via_portalaccess',
    get_string('portalaccess', 'via'), get_string('portalaccessdesc', 'via'), 0));


    $settings->add(new admin_setting_configmultiselect('via/via_audio_types',
            get_string('audiomodelabel', 'via'),
    '', array(1), array(1 => get_string('modevoiceweb', 'via') )));

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
}
