<?php

/**
 *  Visualization of a all via instances.
 *
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions last update 01/05/2013
 */

global $CFG; 

require_once('../../config.php');
header('content-type: text/xml');

$version = explode(':', get_string('pluginversion','via'));

echo '<?xml version="1.0" encoding="UTF-8"?>
<Server>
<Result>
    <Status>SUCCESS</Status>
    <Message/>
</Result>
<ServerType>Moodle</ServerType>
<Version>'.$version[1].'</Version>
</Server>';

