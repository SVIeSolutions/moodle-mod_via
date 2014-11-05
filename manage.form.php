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
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

?>

<form id="subscriberform" method="post" action="<?php echo 'manage.php?id='.$id.'&t='.$participanttype;?>">
<input type="hidden" name="id" value="<?php echo $id?>" />
  <table align="center" border="0" cellpadding="5" cellspacing="0" class="participantsmanagementtable" >
    <tr>
      <td valign="top">
          <?php echo count($subscribers) . " ". $strexistingparticipants ?>
      </td>
      <td></td>
      <td valign="top">
          <?php echo count($users) . " " . $strpotentialparticipants;  ?>
      </td>
    </tr>
    <tr>
      <td valign="top">
          <select name="removeselect[]" size="20" id="removeselect" multiple="multiple"
                  onFocus=<?php if ($participanttype == 2) {?>
                                    "getElementById('subscriberform').remove.disabled=true;<?php 
} ?>
                            getElementById('subscriberform').add.disabled=false;
                            getElementById('subscriberform').addselect.selectedIndex=-1;">
<?php
foreach ($subscribers as $subscriber) {
    $fullname = fullname($subscriber, true);
    if ($participanttype != 2 && $groupingid != 0 && $subscriber->groupingid == $groupingid) {
        echo "<option value=\"$subscriber->id\" disabled>".$fullname.", ".$subscriber->email. "</option>\n";
    } else {
        echo "<option value=\"$subscriber->id\" >".$fullname.", ".$subscriber->email. "</option>\n";
    }
}
?>

          </select></td>
      <td valign="top">
        <br />
        <input name="add" type="submit" id="add" value="&larr;"/>
        <br />
        <input name="remove" type="submit" id="remove" value="&rarr;"  <?php if ($participanttype == 2) {?>
                                                                                disabled <?php 
} ?> />
        <br />
      </td>
      <td valign="top">
          <select name="addselect[]" size="20" id="addselect" <?php if ($participanttype != 2) {?>
                                                                        multiple="multiple"<?php
}?>
                  onFocus=<?php if ((count($subscribers) < 1 && $participanttype == 2) ||
                                    $participanttype != 2) {?>
                                    "getElementById('subscriberform').add.disabled=false; <?php 
} ?>
                           getElementById('subscriberform').remove.disabled=true;
                           getElementById('subscriberform').removeselect.selectedIndex=-1;">
<?php
if (isset($searchusers)) {
    echo "<optgroup label=\"$strsearchresults (" . count($searchusers) . ")\">\n";
    foreach ($searchusers as $user) {
        $fullname = fullname($user, true);
        echo "<option value=\"$user->id\">".$fullname.", ".$user->email."</option>\n";
    }
    echo "</optgroup>\n";
}
if (!empty($users)) {
    foreach ($users as $user) {
        $fullname = fullname($user, true);
        echo "<option value=\"$user->id\">".$fullname.", ".$user->email."</option>\n";
    }
} else {
    echo "<option value=\"\">&nbsp;</option>\n";
}
?>
         </select>
         <br />
         <input type="text" name="searchtext" size="30" value="<?php p($searchtext, true) ?>" />
         <input name="search" id="search" type="submit" value="<?php p($strsearch) ?>" />
<?php
if (isset($searchusers)) {
    echo '<input name="showall" id="showall" type="submit" value="'.$strshowall.'" />'."\n";
}
?>
       </td>
    </tr>
  </table>
</form>
