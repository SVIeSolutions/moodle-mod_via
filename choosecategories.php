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
 * Permits user to manage Via categories available when creating activities
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once('../../config.php');
require_once('lib.php');

$PAGE->requires->js('/mod/via/javascript/list.js');

global $CFG, $DB;

$save = optional_param('save', null, PARAM_TEXT);
$category = optional_param_array('category', null, PARAM_RAW);
$isdefault = optional_param('isdefault', null, PARAM_INT);

require_login();

if ($site = get_site()) {
    if (function_exists('require_capability')) {
        require_capability('moodle/site:config', via_get_system_instance());
    } else if (!isadmin()) {
        print_error("You need to be admin to use this page");
    }
}

$PAGE->set_context(via_get_system_instance());

// Initialize $PAGE.
$PAGE->set_url('/mod/via/choosecategories.php');
$PAGE->set_heading("$site->fullname");
$PAGE->set_pagelayout('popup');

// Print the page header.
echo $OUTPUT->header();

echo $OUTPUT->box_start('center', '100%');

$viacatgeories = via_get_categories();

if (!$save) {
    $none = '';
    $i = 0;
    $existingcats = $DB->get_records('via_categories');
    $defaultcat = $DB->get_record('via_categories', array('isdefault' => 1));
    if (!$defaultcat) {
        $none = ' Checked';
    }

    $form = '<p>'.get_string('cat_intro', 'via').'</p>';
    $form .= '<table>';
    $form .= '<tr><td>'.get_string('cat_name', 'via').'</td>
              <td>'.get_string('add', 'via').'</td><td>'.get_string('cat_default', 'via').'</td></tr>';

    $form .= '<form name="category" method="post" action="" id="form" >';
    if (isset($viacatgeories["Category"]["CategoryID"])) {
        $checked = '';
        $picked = '';
        foreach ($existingcats as $exists) {
            if ($viacatgeories["Category"]["CategoryID"] == $exists->id_via) {
                $checked = ' checked';
                if ($defaultcat && $viacatgeories["Category"]["CategoryID"] == $defaultcat->id_via) {
                    $picked = ' Checked';
                }
            }

        }
        $form .= '<tr><td>'.$viacatgeories["Category"]["Name"].'</td>
                  <td><input type=\'checkbox\' name=\'category[]\' value=\''.
            $viacatgeories["Category"]["CategoryID"].'$'.$viacatgeories["Category"]["Name"].'\''.
            $checked.' onchange="change('.$i.');" /></td><td><input type="radio"
            name="isdefault" value="'.$viacatgeories["Category"]["CategoryID"].'" '.$picked.' id="'.$i.'" ></td></tr>';

    } else {
        if ($viacatgeories != "") {
            foreach ($viacatgeories["Category"] as $cat) {
                $checked = '';
                $picked = '';
                foreach ($existingcats as $exists) {
                    if ($cat['CategoryID'] == $exists->id_via) {
                        $checked = ' checked';
                        if ($defaultcat && $cat['CategoryID'] == $defaultcat->id_via) {
                            $picked = ' Checked';
                        }
                    }

                }
                $form .= '<tr><td>'.$cat['Name'].'</td><td><input type=\'checkbox\' name=\'category[]\'
                 value=\''.$cat['CategoryID'].'$'.$cat['Name'].'\''.$checked.' onchange="change('.$i.');" /></td>
                <td><input type="radio" name="isdefault" value="'.$cat['CategoryID'].'" '.$picked.' id="'.$i.'" ></td></tr>';
                $i++;
            }
        } else {
            $form .= '<tr><td>'.get_string('no_categories', 'via').'</td></tr>';
        }

    }
    $form .= '<tr><td>'.get_string('no_default', 'via') .'</td><td></td>';
    $form .= '<td><input type="radio" name="isdefault" value="0" '.$none.'></td></tr>';
    $form .= '<tr><td><input type="submit" name="save" class="btn_search" value="'. get_string('savechanges').'" /></td></tr>';
    $form .= '</form></table><br/><br/>';

    echo $form;

} else {
    $message = '';

    if ($save) {
        if ($category) {
            $chosencategories = $category;
        } else {
            $chosencategories = array('0');
        }
        // If there are old categories that are not being resaved then we need to to delete them.
        $existingcats = $DB->get_records('via_categories');
        if ($existingcats) {
            foreach ($existingcats as $existing) {
                if (!in_array($existing->id_via.'/'.$existing->name, $chosencategories)) {
                    $deleted = $DB->delete_records('via_categories', array('id' => $existing->id));
                    $message = get_string('cats_modified', 'via');
                }
            }
        }
        if ($category) {
            foreach ($category as $value) {
                $value = explode('$', $value);

                $category           = new stdclass();
                $category->id_via   = $value[0];
                $category->name     = $value[1];
                if ($isdefault == $value[0]) {
                    $category->isdefault = '1';
                } else {
                    $category->isdefault = '0';
                }

                $exists = $DB->get_record('via_categories', array('id_via' => $value[0]));
                if (!$exists) {
                    $added = $DB->insert_record('via_categories', $category);
                    $message = get_string('cats_modified', 'via');
                } else if ($exists->isdefault != $category->isdefault) {
                    $DB->set_field('via_categories', 'isdefault', $category->isdefault, array('id_via' => $exists->id_via));
                    $message = get_string('cats_modified', 'via');
                }
            }
        }
    }
    echo $message;
    echo '<center><input type="button" onclick="self.close();" value="' . get_string('closewindow') . '" /></center>';
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($site);
