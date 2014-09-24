<?php
/**
 *  Visualization of a all via instances.
 *
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions last update 01/05/2013
 */
global $CFG, $DB; 

require_once('../../../../config.php');
header('content-type: text/xml');

$version = false;
$version = get_config('mod_via', 'version'); /* gets value directly from config_plugins table  */
if($version == false){
	$via = $DB->get_record('modules', array('name'=>'via'));
	$version = $via->version;
}

echo '<?xml version="1.0" encoding="UTF-8"?>
<Server>
<Result>
    <Status>SUCCESS</Status>
    <Message/>
</Result>
<ServerType>Moodle</ServerType>
<Version>'.$version.'</Version>
</Server>';





