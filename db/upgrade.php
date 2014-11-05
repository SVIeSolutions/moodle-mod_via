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

function xmldb_via_upgrade($oldversion = 0) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    $result = true;

    // Dropping all enums/check contraints from core. MDL-18577.
    if ($oldversion < 2009042700) {

        // Changing list of values (enum) of field type on table via to none.
        $table = new xmldb_table('via');
        $field = new xmldb_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'general', 'course');

        $dbman->drop_enum_from_field($table, $field);

        // Savepoint reached.
        upgrade_mod_savepoint($result, 2009042700, 'via');
    }

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

        // Savepoint reached.
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

        // Savepoint reached!
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

        // Savepoint reached!
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

        // Savepoint reached!
        upgrade_mod_savepoint($result, 2014080163, 'via');
    }

    if ($oldversion < 2014080167) {

        $table = new xmldb_table('via_users');

        $field = new xmldb_field('username', XMLDB_TYPE_TEXT, '200', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, 'viauserid');
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

        // Savepoint reached!
        upgrade_mod_savepoint($result, 2014080167, 'via');
    }

}
