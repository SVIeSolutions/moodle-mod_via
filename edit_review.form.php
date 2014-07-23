
<form id="editreviewform" method="post" action="<?php echo 'edit_review.php?id='.$id;?>" class="mform">
<input type="hidden" name="id" value="<?php echo $id?>" />
<input type="hidden" name="playbackid" value="<?php echo $playbackid?>" />

<div align="center">

<table cellpadding="3">
<tr>
<td align="right"><label for="title"><?php echo get_string("recordingtitle", "via");?>

<td align="left"><input type="text" name="title" value="<?php echo $playback->title?>" id="title"/></label></td>

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


