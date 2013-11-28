<?php  
///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.org                                            //
//                                                                       //
// Copyright (C) 2005 Martin Dougiamas  http://dougiamas.com             //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 2 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

// This file to be included so we can assume config.php has already been included.
// We also assume that $user, $course, $currenttab have been set


    if (empty($currenttab) or empty($course)) {
        print_error('You cannot call this script in that way');
    }
	
	$context = context_module::instance($cm->id);

    $inactive = NULL;
    $activetwo = NULL;
    $tabs = array();
    $row = array();

    $row[] = new tabobject('participants', $CFG->wwwroot.'/mod/via/manage.php?id='.$via->id.'&t=1', get_string("participants", "via"));
    
    $row[] = new tabobject('animators', $CFG->wwwroot.'/mod/via/manage.php?id='.$via->id.'&t=3', get_string("animators", "via"));
	
	$row[] = new tabobject('presentator', $CFG->wwwroot.'/mod/via/manage.php?id='.$via->id.'&t=2', get_string("presentator", "via"));



    $tabs[] = $row;

	print_tabs($tabs, $currenttab);

?>
