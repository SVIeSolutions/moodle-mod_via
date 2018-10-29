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
 * This file keeps track of upgrades to the via module
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
/**
 * This is function xmldb_via_upgrade
 *
 * @param mixed $oldversion This is a description
 * @return mixed This is the return value description
 *
 */
function xmldb_via_upgrade($oldversion = 0) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    $result = true;

    // Define field introformat to be added to via.
    if ($oldversion < 2009042701) {
        $table = new xmldb_table('via');
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'intro');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Conditionally migrate to html format in intro.
        if ($CFG->texteditors !== 'textarea') {
            $rs = $DB->get_recordset('via', array('introformat' => FORMAT_MOODLE), '', 'id,intro,introformat');
            foreach ($rs as $q) {
                $q->intro       = text_to_html($q->intro, false, false, true);
                $q->introformat = FORMAT_HTML;
                $DB->update_record('via', $q);
                upgrade_set_timeout();
            }
            $rs->close();
        }

        // Via savepoint reached.
        upgrade_mod_savepoint($result, 2009042702, 'via');
    }

    if ($oldversion < 2013092002) {
        $table = new xmldb_table('via');
        $field = new xmldb_field('invitemsg', XMLDB_TYPE_TEXT, 'big', null, null, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        $field = new xmldb_field('usersynchronization', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0',  null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('category', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0',  null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        unset_config('overflowalert');
        unset_config('preventoverflowalert');
        unset_config('preventoverflownbr');
        unset_config('emails_alert_address');

        $table = new xmldb_table('via_participants');
        $field = new xmldb_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('timesynched', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('via_categories');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('id_via', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('isdefault', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, '0', '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Via savepoint reached.
        upgrade_mod_savepoint($result, 2013092002, 'via');
    }

    if ($oldversion < 2014040100) {
        $table = new xmldb_table('via');
        $field = new xmldb_field('noparticipants', XMLDB_TYPE_INTEGER, '2', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // These values are now in mdl_config_plugins!
        unset_config('via_apiurl');
        unset_config('via_cleid');
        unset_config('via_apiid');
        unset_config('via_audio_types');
        unset_config('via_moodleemailnotification');
        unset_config('via_participantmustconfirm');
        unset_config('via_sendinvitation');
        unset_config('via_participantsynchronization');
        unset_config('via_adminid');
        unset_config('via_categories');

        // Via savepoint reached.
        upgrade_mod_savepoint($result, 2014040100, 'via');
    }

    if ($oldversion < 2014080162) {
        $table = new xmldb_table('via');
        $field = new xmldb_field('backupvia');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $field = new xmldb_field('groupingid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $query = "SELECT v.*, cm.groupingid  FROM {modules} m
                LEFT JOIN {course_modules} cm ON m.id = cm.module
                LEFT JOIN {via} v ON v.id = cm.instance
                WHERE m.name = 'via' AND v.id is not null";
        $vias = $DB->get_records_sql($query);
        foreach ($vias as $via) {
            $DB->update_record("via", $via);
        }

        $table = new xmldb_table('via_log');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Via savepoint reached.
        upgrade_mod_savepoint($result, 2014080162, 'via');
    }
    if ($oldversion < 2014080163) {
        $table = new xmldb_table('via');
        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'name');
        // Launch rename field intro!
        $dbman->rename_field($table, $field, 'intro');
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'intro');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Via savepoint reached.
        upgrade_mod_savepoint($result, 2014080163, 'via');
    }

    if ($oldversion < 2014080167) {
        $table = new xmldb_table('via_users');

        $field = new xmldb_field('username', XMLDB_TYPE_TEXT, '200', XMLDB_UNSIGNED, null, null, null, 'viauserid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        $index = new xmldb_index('viauserid', XMLDB_INDEX_UNIQUE, array('viauserid'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        $index = new xmldb_index('viauserid', XMLDB_INDEX_NOTUNIQUE, array('viauserid'));
        $dbman->add_index($table, $index);

        require_once($CFG->libdir.'/gradelib.php');

        $query = "SELECT * FROM {via} WHERE activitytype = 2 OR datebegin > " . time();
        $vias = $DB->get_records_sql($query);
        foreach ($vias as $via) {
            grade_update('mod/via', $via->course, 'mod', 'via', $via->id, 0, null, array('deleted' => 1));
        }

        // Via savepoint reached.
        upgrade_mod_savepoint($result, 2014080167, 'via');
    }

    if ($oldversion < 2014110100) {
        $table = new xmldb_table('via');

        $field = new xmldb_field('presence', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '30', 'duration');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('via_presence');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('activityid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'userid');
        $table->add_field('connection_duration', XMLDB_TYPE_NUMBER, '10,2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null,
                          'activityid');
        $table->add_field('playback_duration', XMLDB_TYPE_NUMBER, '10,2', XMLDB_UNSIGNED, null, null, null, 'connection_duration');
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'connection_duration');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'status');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Via savepoint reached.
        upgrade_mod_savepoint($result, 2014110100, 'via');
    }

    if ($oldversion < 2014120101) {
        unset_config('via_sendinvitation', 'via');
        unset_config('via_moodleemailnotification', 'via');

        $table = new xmldb_table('via');
        $field = new xmldb_field('moodleismailer');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $field = new xmldb_field('grade');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $table = new xmldb_table('via_participants');
        $field = new xmldb_field('synchvia', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, null, 'participanttype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('via_users');
        $field = new xmldb_field('setupstatus', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, null, 'username');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Via savepoint reached.
        upgrade_mod_savepoint($result, 2014120101, 'via');
    }

    if ($oldversion < 2015012004) {
        $table = new xmldb_table('via');
        $index = new xmldb_index('course', XMLDB_INDEX_NOTUNIQUE, array('course'));
        // Conditionally launch add index.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('via_users');
        $index = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('via_participants');
        $index = new xmldb_index('activityid', XMLDB_INDEX_NOTUNIQUE, array('activityid'));
        // Conditionally launch add index.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('via_presence');
        $index = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        // Conditionally launch add index.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('activityid', XMLDB_INDEX_NOTUNIQUE, array('activityid'));
        // Conditionally launch add index.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Savepoint reached!
        upgrade_mod_savepoint($result, 2015012004, 'via');
    }

    if ($oldversion < 2015050101) {
        $table = new xmldb_table('via_cron');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('cron', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'name');
        $table->add_field('lastcron', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'cron');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Populate the table right away with default values.
        // These can be changed by the company/school to meet their needs!
        $functions = array();
        $functions[] = array('name' => 'via_send_reminders', 'cron' => '600', 'lastcron' => 0);
        $functions[] = array('name' => 'via_add_enrolids', 'cron' => '2400', 'lastcron' => 0);
        $functions[] = array('name' => 'via_synch_users', 'cron' => '1200', 'lastcron' => 0);
        $functions[] = array('name' => 'via_synch_participants', 'cron' => '1200', 'lastcron' => 0);
        $functions[] = array('name' => 'via_check_categories', 'cron' => '43200', 'lastcron' => 0); // Once every 12 hours!
        $functions[] = array('name' => 'via_send_export_notice', 'cron' => '1200', 'lastcron' => 0);
        $functions[] = array('name' => 'via_get_list_profils', 'cron' => '43200', 'lastcron' => 0); // Once every 12 hours!
        $functions[] = array('name' => 'via_get_cieinfo', 'cron' => '43200', 'lastcron' => 0); // Once every 12 hours!

        foreach ($functions as $function) {
            $DB->insert_record('via_cron', $function);
        }

        $table = new xmldb_table('via_params');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('param_type', XMLDB_TYPE_CHAR, '50', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('param_name', XMLDB_TYPE_CHAR, '50', XMLDB_UNSIGNED, null, null, null, 'param_type');
        $table->add_field('value', XMLDB_TYPE_CHAR, '200', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'param_name');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'value');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('via');
        $field = new xmldb_field('isnewvia', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'roomtype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('showparticipants', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, null, 'activitytype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Savepoint reached!
        upgrade_mod_savepoint($result, 2015050101, 'via');
    }

    if ($oldversion < 2016010101) {
        $table = new xmldb_table('via');
        $field = new xmldb_field('ish264', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, null, 'noparticipants');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Savepoint reached!
        upgrade_mod_savepoint($result, 2016010102, 'via');
    }

    if ($oldversion < 2016010112) {
        $table = new xmldb_table('via_cron');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $table = new xmldb_table('via');
        $field = new xmldb_field('usersynchronization', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        // Savepoint reached!
        upgrade_mod_savepoint($result, 2016010112, 'via');
    }

    if ($oldversion < 2016010116) {
        $table = new xmldb_table('via');
        $field = new xmldb_field('playbacksync', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, 'ish264');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, 'groupingid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('private');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $table = new xmldb_table('via_playbacks');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('playbackid', XMLDB_TYPE_CHAR, '100', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 'id');
        $table->add_field('playbackidref', XMLDB_TYPE_CHAR, '100', XMLDB_UNSIGNED, null, null, 'playbackid');
        $table->add_field('activityid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'playbackidref');
        $table->add_field('title', XMLDB_TYPE_CHAR, '100', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'activityid');
        $table->add_field('creationdate', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'title');
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'creationdate');
        $table->add_field('accesstype', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'duration');
        $table->add_field('isdownloadable', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'accesstype');
        $table->add_field('hasfullvideorecord', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null,
        'isdownloadable');
        $table->add_field('hasmobilevideorecord', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null,
        'hasfullvideorecord');
        $table->add_field('hasaudiorecord', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null,
        'hasmobilevideorecord');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $index = new xmldb_index('playbackid', XMLDB_INDEX_NOTUNIQUE, array('playbackid'));
        $dbman->add_index($table, $index);
        $index = new xmldb_index('activityid', XMLDB_INDEX_NOTUNIQUE, array('activityid'));
        $dbman->add_index($table, $index);

        // Savepoint reached!
        upgrade_mod_savepoint($result, 2016010116, 'via');
    }

    if ($oldversion < 2016042003) {

        $table = new xmldb_table('via_playbacks');
        $field = new xmldb_field('duration', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'creationdate');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        // Savepoint reached!
        upgrade_mod_savepoint($result, 2016042003, 'via');
    }

    if ($oldversion < 2017030106) {

        $table = new xmldb_table('via_params');
        if ($dbman->table_exists($table) && $DB->record_exists('via_params', array('param_type' => 'viaversion'))) {
            $DB->delete_records('via_params', array('param_type' => 'viaversion'));
        }

        $table = new xmldb_table('via_recyclebin');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('viaid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('viaactivityid', XMLDB_TYPE_CHAR, '50', XMLDB_UNSIGNED, null, null, 'viaid');
        $table->add_field('recyclebinid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'viaactivityid');
        $table->add_field('recyclebintype', XMLDB_TYPE_CHAR, '50', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'recyclebinid');
        $table->add_field('expiry', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'recyclebintype');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        if (!$dbman->table_exists($table)) {

            $table->add_index('recyclebinid', XMLDB_INDEX_NOTUNIQUE, array('recyclebinid'));
            $table->add_index('viaactivityid', XMLDB_INDEX_UNIQUE, array('viaactivityid'));

            $dbman->create_table($table);
        }

        $table = new xmldb_table('via_playbacks');
        $field = new xmldb_field('deleted', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'hasaudiorecord');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Savepoint reached!
        upgrade_mod_savepoint($result, 2017030106, 'via');
    }

    if ($oldversion < 2018042004) {
        upgrade_mod_savepoint($result, 2018042004, 'via');
    }
}