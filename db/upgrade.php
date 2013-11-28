<?php 

// This file keeps track of upgrades to
// the via module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installation to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the methods of database_manager class
//
// Please do not forget to use upgrade_set_timeout()
// before any action that may take longer time to finish.

function xmldb_via_upgrade($oldversion = 0) {
	global $CFG, $DB;
	
	$dbman = $DB->get_manager();

	$result = true;
	
	/// Dropping all enums/check contraints from core. MDL-18577
	if ($oldversion < 2009042700) {
		
		/// Changing list of values (enum) of field type on table via to none
		$table = new xmldb_table('via');
		$field = new xmldb_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'general', 'course');
		
		$dbman->drop_enum_from_field($table, $field);
		
		///  savepoint reached
		upgrade_mod_savepoint($result, 2009042700, 'via');
	}
	
	/// Define field introformat to be added to via
	if ($oldversion < 2009042701) {
		
		$table = new xmldb_table('via');
		$field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'intro');

		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}
		// conditionally migrate to html format in intro
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
		
		///  savepoint reached
		upgrade_mod_savepoint($result, 2009042702, 'via');
	}
	
	if($oldversion < 2013092001){
		
		$table = new xmldb_table('via');
		$field = new xmldb_field('invitemsg', XMLDB_TYPE_TEXT, 'big', null, null, null, null, null);
		if($dbman->field_exists($table, $field)){
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
		
		$table = new xmldb_table('config');
		$field = new xmldb_field('overflowalert');
		if ($dbman->field_exists($table, $field)) {
			$dbman->drop_field($table, $field);
		}
		$field = new xmldb_field('preventoverflowalert');
		if ($dbman->field_exists($table, $field)) {
			$dbman->drop_field($table, $field);
		}
		$field = new xmldb_field('preventoverflownbr');
		if ($dbman->field_exists($table, $field)) {
			$dbman->drop_field($table, $field);
		}
		$field = new xmldb_field('emails_alert_address');
		if ($dbman->field_exists($table, $field)) {
			$dbman->drop_field($table, $field);
		}
			
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
		
		$table = new xmldb_table('via_log');
		$table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
		$table->add_field('viauserid', XMLDB_TYPE_CHAR, '50', null, null, null, '0');
		$table->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
		$table->add_field('action', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '0');
		$table->add_field('result', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '0');
		$table->add_field('time', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, '0', '0');

		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		}
				
		upgrade_mod_savepoint($result, 2013092001, 'via');
	}
}