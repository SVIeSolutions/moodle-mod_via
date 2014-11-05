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

<form id="editreviewform" method="post" action="<?php echo 'edit_review.php?id='.$id;?>" class="mform">
<input type="hidden" name="id" value="<?php echo $id?>" />
<input type="hidden" name="playbackid" value="<?php echo $playbackid?>" />

<div align="center">

<table cellpadding="3">
<tr>
<td align="right"><label for="title"><?php echo get_string("recordingtitle", "via");?>

<td align="left"><input type="text" name="title" maxlength="100" value="<?php echo $playback->title?>" id="title" /></label></td>

</tr>

<tr>
<td colspan="2" align="center"><p>

  <input name="edit" value="Enregistrer" type="submit" id="edit" />

  <input name="cancel" value="Annuler" type="submit" id="cancel" onclick="skipClientValidation = true; return true;"/>
</p>
</td>
</tr>

</table>
</div>
   
</form>
