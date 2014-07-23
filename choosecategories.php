<?php

/**
 * A simple Web Services connection test script for the configured via server.
 * 
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions 
 */

require_once('../../config.php');
require_once('lib.php');

$PAGE->requires->js('/mod/via/list.js');	

global $CFG, $DB;

require_login();

if ($site = get_site()) {
	if (function_exists('require_capability')) {
		require_capability('moodle/site:config', context_system::instance());
	} else if (!isadmin()) {
		error("You need to be admin to use this page");
	}
}

$PAGE->set_context(context_system::instance());

$site = get_site();

// Initialize $PAGE
$PAGE->set_url('/mod/via/choosecategories.php');
$PAGE->set_heading("$site->fullname");
$PAGE->set_pagelayout('popup');

/// Print the page header
echo $OUTPUT->header();

echo $OUTPUT->box_start('center', '100%');

$via_catgeories = get_via_categories();	

if (empty($_POST)) {
	$none = '';
	$i = 0;
	$existingcats = $DB->get_records('via_categories');
	$defaultcat = $DB->get_record('via_categories', array('isdefault'=>1));
	if(!$defaultcat){
		$none = ' Checked';
	}
	
	$form = '<p>'.get_string('cat_intro', 'via').'</p>';
	$form .= '<table>';
	$form .= '<tr><td>'.get_string('cat_name', 'via').'</td><td>'.get_string('cat_check', 'via').'</td><td>'.get_string('cat_default', 'via').'</td></tr>';
	
	$form .= '<form name="category" method="post" action="" id="form" >';
	if (isset($via_catgeories["Category"]["CategoryID"])){
			$checked = '';
			$picked = '';
			foreach($existingcats as $exists){
			if($via_catgeories["Category"]["CategoryID"] == $exists->id_via){
				$checked = ' checked';
				if($defaultcat && $$via_catgeories["Category"]["CategoryID"] == $defaultcat->id_via){
						$picked = ' Checked';
					}
				}
				
			}
		$form .= '<tr><td>'.$via_catgeories["Category"]["Name"].'</td><td><input type=\'checkbox\' name=\'category[]\' value=\''.$via_catgeories["Category"]["CategoryID"].'$'.$via_catgeories["Category"]["Name"].'\''.$checked.' onchange="change('.$i.');" /></td><td><input type="radio" name="isdefault" value="'.$via_catgeories["Category"]["CategoryID"].'" '.$picked.' id="'.$i.'" ></td></tr>';
			
					
	}else{
		if($via_catgeories != ""){
			foreach($via_catgeories["Category"] as $cat){
				$checked = '';
				$picked = '';
				foreach($existingcats as $exists){
					if($cat["Category"]['CategoryID'] == $exists->id_via){
						$checked = ' checked';
						if($defaultcat && $cat["Category"]['CategoryID'] == $defaultcat->id_via){
							$picked = ' Checked';
						}
					}
					
				}
				$form .= '<tr><td>'.$cat['Name'].'</td><td><input type=\'checkbox\' name=\'category[]\' value=\''.$cat['CategoryID'].'$'.$cat['Name'].'\''.$checked.' onchange="change('.$i.');" /></td><td><input type="radio" name="isdefault" value="'.$cat['CategoryID'].'" '.$picked.' id="'.$i.'" ></td></tr>';
				$i++;
			}
		}else{
			$form .= '<tr><td>'.get_string('no_categories','via').'</td></tr>';
		}
			
		
	}
	$form .= '<tr><td>'.get_string('no_default', 'via') .'</td><td></td><td><input type="radio" name="isdefault" value="0" '.$none.'></td></tr>';
	$form .= '<tr><td><input type="submit" name="save" class="btn_search" value="'. get_string('savechanges').'" /></td></tr>';
	$form .= '</form></table><br/><br/>';	

	echo $form;
	
}else{
	$message = '';
	
	if (isset($_POST['save'])){
		if(isset($_POST['category'])){
			$chosencategories = $_POST['category'];
		}else{
			$chosencategories = array('0');
		}
		// if there are old categories that are not being resaved then we need to to delete them
		$existingcats = $DB->get_records('via_categories');
		if($existingcats){
			foreach($existingcats as $existing){
				if(!in_array($existing->id_via.'/'.$existing->name, $chosencategories)){
					$deleted = $DB->delete_records('via_categories', array('id'=>$existing->id));
					$message = get_string('cats_modified', 'via');
				}
			}
		}
		if(isset($_POST['category'])){
			foreach($_POST['category'] as $value){
				$value = explode('$', $value);
				
				$category           = new stdclass();
				$category->id_via   = $value[0];
				$category->name		= $value[1];
				if($_POST['isdefault'] == $value[0]){
					$category->isdefault = '1';
				}else{
					$category->isdefault = '0';
				}

				$exists = $DB->get_record('via_categories', array('id_via'=>$value[0]));
				if(!$exists){
					$added = $DB->insert_record('via_categories', $category);
					$message = get_string('cats_modified', 'via');
				}elseif($exists->isdefault != $category->isdefault){
					$DB->set_field('via_categories', 'isdefault', $category->isdefault, array('id_via'=>$exists->id_via));
					$message = get_string('cats_modified', 'via');
				}
			}
			
			echo $message;	
			echo '<center><input type="button" onclick="self.close();" value="' . get_string('closewindow') . '" /></center>';
			
		}
	}
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($site);

?>


