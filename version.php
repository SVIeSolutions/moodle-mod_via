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
 * This file defines the version of Via - Virtual teaching
 * 
 * updated 19/09/2012
 * In this version the cron function : via_update_enrolment() was uncommented to synchronize users
 * but instructions were provided to run in only once before commenting it out again
 * also a last minute change was made in mod/via/lib.php in function via_update_participants_list
 * as an error was occurring in manual inscription when users were removed.
 *
 * updated 27/11/2012
 * Corrections were made to the reminder email.
 * Corrections were made in the automatic enrollment for the animators and presenters, these are now modifiable.
 * 
 * last update 01/05/2013
 * In the settings page we added a via_adminID, this id will be used to create and modify activities. 
 * In the settings we also added a check box to chose if the users' via information should be synchronized with the moodle's values.
 * If checked users' information will be updated when they connect to an activity, but the user name, password and user type will not be affected.
 * When synchronizing the information we validate if the user's email exists as email or as login, if it does we assume it is the same person. 
 * 
 * last update 06/02/2013
 * Corrections were brought to the send invite function.
 * 
 *  last update 27/03/2013
 *  Addition of a visible version to the settings page for quick reference.
 * 	Correction to sql search for user type, mssql vs. mysql. 
 * 
 *  last update 01/05/2013
 *  Modification to UserGetSSOtoken to create link accessible from mobiles.
 * 	We validate the user and the plugin version, needs via 5.2 or above to work.
 *  Validations were also added to the emails and reminders, reminders can now only be sent for activities with a fixed time and date. 
 *  The emails are different for the permanent activities.
 *  
 * last update 02/07/2013
 * In this version we have removed the added code to the moodle core. Users will be synchronised with the help of the cron.
 * So changes to the courses' participants will not be instananious, rather it can take up to 10 minutes before the changes are made to the via participants table.
 * For this to work a new column was added to the via participants table and new functions were added to via_cron. 
 * The categories in Via are reproduced in moodle, the admin can choose which categories will be available and can even add one as default. 
 * Then when an activity is created the teacher can chose from the available categories.
 * These are only helpful for invoicing; the category can only be seen when editing an activity.
 * The connection test was modified, we test the API connection, then we test the new moodle key, independently.
 * 
 * Last update 20/09/2013 Version 20130920
 * In this version we have made modifications to the cron synchronisation of users for all types of enrollments!
 * We have also added validations at many levels to add users that were added in moodle but not in via.
 * We have added a log to keep track of these errors and later additions.
 * We have also given it a new more modern look!!!
 * For connexion on mobiles using moodle with a mobile theme we have made modifications to the connexion
 *  * As well as improvements following feedback from moodle.org : 
 * - GPL licence
 * - changing the page encode from Western European (Windows) to UTF-8 with signature
 * 
 * Last update 06/11/2013 Version 2013092001
 * Correction to the playback list - recordings
 * 
 * Last update 21/11/2013
 * Mofications made for Moodle 2.6
 * Added proxy information
 * Made corrections after errors were reported
 */  

/**
 *
 * @package    mod-via
 * @copyright  2011 - 2013 SVIeSolutions http://sviesolutions.com
 * @author     Alexandra Dinan-Mitchell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */
defined('MOODLE_INTERNAL') || die();

$module = new stdClass();
$plugin = new stdClass();

$plugin->version = 2013092002;	
$plugin->component = 'mod_via'; 

$module->version  = 2013092002;	 // YYYYMMDDHH (year, month, day, 24-hr time)
$module->release  = 2.20130920;
$module->requires = 2011033003;  // Moodle version required to run it (2.0.3 )
$module->cron     = 300;         // Number of seconds between cron calls.
$module->component = 'mod_via'; 

$module->maturity  = MATURITY_STABLE;