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
 * Language strings for the Via module
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

$string['absent'] = 'Absent';
$string['accessactivity'] = 'Access your activity: ';
$string['activity_deleted'] = 'The Via activity was deleted directly in the Via environment by a user. It is therefore impossible to access. We recommend that you delete this activity in Moodle and create a new one.';
$string['activitydeletion'] = 'Limit activity deletion';
$string['activitydeletiondesc'] = 'If checked, activities will be deleted in Moodle but not in Via.';
$string['activitydone'] = 'This activity is finished.';
$string['activitytemplateheader'] = "Activity template";
$string['activitytemplatebutton'] = "Synchronize activity template values";
$string['activitytemplatedesc'] = "The default values(recording mode, waiting room, multimedia profile, etc.) applied to the creation of a new virtual classe are the values that are set by default in the Via portal. You must connect to the portal and access the Activity Template section on the left side to validate the default values.";
$string['activitytitle'] = 'Activity title';
$string['activityversion'] = 'Version';
$string['activityversion_help'] = 'You can choose between Classic Via and Via HTML5. You can not change the version in edition mode.';
$string['activitywaserased'] = 'Could not find this activity on SVI server. It seems to have been erased.';
$string['add'] = 'Add';
$string['adminid_nosuccess'] = 'The mooodle key provided is not valid.';
$string['adminid_success'] = 'The moodle key is valid';
$string['adminid_toolow'] = 'The user\'s rights do not permit the creation of activities. Please contact the administrator to increase the rights.';
$string['adminnotrenrolled'] = 'As administrator you may access the activity although you are not enrolled.';
$string['and'] = 'and';
$string['animator'] = 'Animator';
$string['animators'] = 'Animators';
$string['apibranch'] = 'Lära branch';
$string['apibranchsetting'] = 'API identifier of the associated branch in Lära. Activities and users will be associated with this branch.';
$string['apiconfig'] = 'API configuration - Step 1';
$string['apiid'] = 'Via API ID (ApiID)';
$string['apiidsetting'] = 'Via API unique ID';
$string['apiportal'] = 'Lära Portal';
$string['apiportalsetting'] = 'API identifier of the associated portal in Lära. The redirects to the activities will be based on the URL of this portal.';
$string['apiurl'] = 'API\'s URL';
$string['apiurlsetting'] = 'API\'s base URL';
$string['attending'] = 'I will be attending';
$string['audiomodelabel'] = 'Audio type for the conference';
$string['audiorecord'] = 'Audio (MP3)';
$string['audiorecordinfo'] = 'Audio only';
$string['automatic'] = 'Automatic';
$string['automaticenrol'] = 'Automatic enrolment';
$string['availabledate'] = 'Available from';
$string['awaitingauthorization'] = 'Awaiting authorization (manual)';

$string['backup'] = 'Incude survey information in course backups';
$string['backup_options'] = 'Activity backup and duplication options';
$string['backupdesc'] = 'If checked survey information from an activity will be included in the new activty produced during the course backup and restoration process. Documents are always included in this process. Including user infomation is optional.';
$string['basedon'] = 'The presence status is based on {$a} minutes.';
$string['by'] = 'by';

$string['cancel'] = 'Cancel';
$string['cat_check'] = 'Add';
$string['cat_default'] = 'Set as default';
$string['cat_intro'] = 'Choose the Via categories that you wish to make available in Moodle.';
$string['cat_name'] = 'Category names';
$string['categoriesheader'] = 'Categories';
$string['category'] = 'Choose a category';
$string['cats_modified'] = 'The categories were successfully modified!';
$string['choosecategories'] = 'Configure the categories';
$string['choosehost'] = 'To chose a new host, simply add a new user to replace the existing host. The can only be one host.';
$string['cieid'] = 'Via ID (CieID)';
$string['cieidsetting'] = 'Company ID for VIA';
$string['config'] = 'Configuration';
$string['configassist'] = 'Setup wizard';
$string['confirmation'] = 'Participants confirmations';
$string['confirmationstatus'] = 'Confirmation status';
$string['confirmed'] = 'Attendance confirmed';
$string['confirmdelete'] = 'Are you sure you want to delete this recording permanently?';
$string['confirmneeded'] = 'Attendence confirmation is needed';
$string['connectsuccess'] = 'Success with the connection to the API';
$string['conntest'] = 'Connection test';
$string['createdby'] = 'Report created by: ';
$string['creationdate'] = 'Report created on: ';

$string['date'] = 'Activity date';
$string['delete'] = 'Delete';

$string['delete_activity'] = 'Delete this activity';
$string['deleteconfirm'] = 'Do you really want to delete this activity ?';

$string['desactivatedMessage'] = 'This activity and its associated records or documents have been deleted and are no longer accessible. Please delete this activity from the course';

/* downloadable files */
$string['df_button_manage'] = 'Manage the content';
$string['df_header_title'] = 'Title';
$string['df_header_type'] = 'Type';
$string['df_header_size'] = 'Size';
$string['df_header_nbpages'] = 'Pages';
$string['df_header_download'] = 'Download';
$string['df_nofiles'] = 'No content to download';

$string['df_type_0'] = "Uncategorised";
$string['df_type_1'] = "JPG Image";
$string['df_type_2'] = "BMP Image";
$string['df_type_3'] = "PNG Image";
$string['df_type_4'] = "GIF Image";
$string['df_type_5'] = "PICT Image";
$string['df_type_6'] = "PDF document";
$string['df_type_7'] = "Image TIFF";
$string['df_type_8'] = "SWF File";
$string['df_type_9'] = "Flash Video";
$string['df_type_10'] = "AVI Video";
$string['df_type_11'] = "MPG Video";
$string['df_type_12'] = "WMV Video";
$string['df_type_13'] = "MOV Video";
$string['df_type_14'] = "MP3 Audio";
$string['df_type_15'] = "WAV Audio";
$string['df_type_16'] = "WMA Audio";
$string['df_type_17'] = "OGG Audio";
$string['df_type_18'] = "Powerpoint Presentation";
$string['df_type_19'] = "URL";
$string['df_type_20'] = "MP4 File";
$string['df_type_21'] = "Word Document";
$string['df_type_22'] = "Excel Document";
$string['df_type_23'] = "OD File";
$string['df_type_24'] = "SXW File";
$string['df_type_25'] = "RTF Document";
$string['df_type_26'] = "WPD File";
$string['df_type_27'] = "Text File";
$string['df_type_28'] = "ODS File";
$string['df_type_29'] = "SXC File";
$string['df_type_30'] = "CSV File";
$string['df_type_31'] = "TSV File";
$string['df_type_32'] = "ODP File";
$string['df_type_33'] = "SXI File";
$string['df_type_34'] = "ODG File";
$string['df_type_35'] = "HTML Link";
$string['df_type_36'] = "Powerpoint Presentation";
$string['df_type_37'] = "Word Document";
$string['df_type_38'] = "Powerpoint Presentation";
$string['df_type_39'] = "Excel Document";
$string['df_type_40'] = "Powerpoint Presentation";
$string['df_type_41'] = "HTM Link";
$string['df_type_42'] = "Powerpoint Presentation";
$string['df_type_43'] = "Whiteboard";
$string['df_type_44'] = "Survey";
$string['df_type_45'] = "M4V Video";
$string['df_type_46'] = "M4A Audio";
$string['df_type_47'] = "MKV Video";
$string['df_type_48'] = "GIF Sequence";
/* */
$string['description'] = 'Description';
$string['displayuserlist'] = 'Display associated users list for all';
$string['displayuserlistdesc'] = 'If checked, all users will be able to see the other users assosiated with the activity and their confirmation status, if activated.';
$string['donousewaitingroom'] = 'Do not use (deactivated)';
$string['downloadablefiles'] = 'Downloadable content';
$string['downloadplaybacks'] = 'Download Recordings';
$string['downloadplaybacksdesc'] = 'If checked, users with editing roles will be permitted to download Via recordings. Before checking this option, please contact SVIesolutions to validate that your server permits it.';
$string['duplication'] = 'Incude survey information in activity duplication';
$string['duplicationdesc'] = 'If checked survey information from an activity will be included in the new activty produced during the duplication process. User infomation and documents are always included in this process.';
$string['duration'] = 'Duration (minutes)';
$string['durationheader'] = 'Duration';

$string['edit'] = 'Modify';
$string['editrecord'] = 'Recording edit';
$string['emails_alert_address'] = 'Emails to send alerts';
$string['emails_alert_addressdesc'] = 'Email addresses for overflow alerts. If more than one, use coma as a separtor.';

/* Settings for the email */

$string['emailheadercolor'] = 'Email title color';
$string['emailheadercolor_desc'] = 'This will be applied to the Via reminder and invitation emails\' title; the name of your moodle.';
$string['emailtextcolor'] = 'Email text color';
$string['emailtextcolor_desc'] = 'This will be applied to Via reminder and invitation emails\' text except for the links and title.';
$string['email_personnalised_options'] = 'Personnalise your Via reminder and invitation emails';
$string['emaillinkcolor'] = 'Link color';
$string['emaillinkcolor_desc'] = 'This will be applied to the links in your Via reminder and invitation emails, we suggest you add the same colors as your theme.';
$string['emailheaderbgcolor'] = 'Email title background color';
$string['emailheaderbgcolor_desc'] = 'This will be applied to the Via reminder and invitation emails\' title\'s background and behind the image.';
$string['emailheaderimage'] = 'Email header image';
$string['emailheaderimage_desc'] = 'A default image has been added, which you may override at any time. The default image\'s dimensions are 750 x 98 pixels and the right hand side of the image has the same color as the "emailheaderbgcolor" below. IMPORTANT : Emails do not support transperancy.';
$string['emailaccesslinkcolor'] = 'Email access link color';
$string['emailaccesslinkcolor_desc'] = 'This will be applied to the access link in your Via reminder and invitation emails. We suggest you use a different color as the other links as this is more important.';

$string['enddate'] = 'Ends on';
$string['enrolmentheader'] = 'Participant enrolments';
$string['enrolmenttype'] = 'Enrolment type';
$string['enrolmenttype_help'] = 'Automatic enrollment: all users enrolled in the course will be added to the Via activity.
 If a student is added after the creation of the Via activity, the student will be added during the next Cron synchronization.
 If the student accesses the activity before the cron has enrolled him/her, the student will be added and be displayed in the details
 page of the activity. Users with editing rights in the moodle course will be automatically synchronized as animators, but the list can still be edited.
 Manual Registration: Participants must be added from the list of participants choosing the participant from the list of
 "Potential participants" (right ) and added using the arrow into &quot;Existing participants&quot; (left).
 Note: In both modes of entry, the user who creates the activity is automatically added as host,
 but remains editable. It is not possible to have more than one host.';
/* Errors */
$string['error_user'] = 'User {$a} could not be added to the activity.';
$string['error:deletefailed'] = 'The removal of all activities has failed.';
$string['error:resetparticipants'] = 'The removal of all participants has failed.';
$string['error:disablereviews'] = 'Disabling review mode has failed.';
$string['error:allseatstaken'] = 'Sorry, all  available seats are taken on SVI server. You cannot conncet to this activity right now. A notice has already been sent to the administrators about this overflow. You can also send an email to <a href="mailto:$a->email?subject=Avis de debordement sur VIA">$a->email</a> to notify them.<br><br> Please try to reconnect later.';
$string['error:CIEID_NOT_FOUND'] = 'CIEID_NOT_FOUND - CieID doesn\'t exists. Please verify your settings. Without a valid CieID you can not create activities!';
$string['error:APIID_NOT_FOUND'] = 'APIID_NOT_FOUND - ApiID doesn\'t exists. Please verify your settings. Without a valid ApiID you can not create activities!';
$string['error:URL_ERROR'] = 'URL_ERROR - The API\'s url doesn\'t exist. Please verify your settings. Without an the API\'s URL you can not create activities!';
$string['error:ACTIVITY_ACCESS_FAILED'] = 'ACTIVITY_ACCESS_FAILED - When the user is not associated to the activity or the activity is no longer available.';
$string['error:ACTIVITY_DOES_NOT_EXIST'] = 'ACTIVITY_DOES_NOT_EXIST - Must represent a valid activity.';
$string['error:ACTIVITYID_EMPTY'] = 'ACTIVITYID_EMPTY - The ActivityID value is empty.';
$string['error:ACTIVITYID_INVALID'] = 'ACTIVITYID_INVALID - The value passed in the ActivityID is invalid.';
$string['error:ACTIVITY_EXCEED_MAX_USERS'] = 'Number max of users is reached';
$string['error:APPLY_PERIODICITY_INVALID'] = 'APPLY_PERIODICITY_INVALID - The value passed must be 0 or 1.';
$string['error:AUTH_FAILED_BAD_APIID'] = 'AUTH_FAILED_BAD_APIID - The APIID value is not authorised.';
$string['error:AUTH_FAILED_BAD_CIEID'] = 'AUTH_FAILED_BAD_CIEID - The CieID value is not authorised.';
$string['error:AUTH_FAILED_BAD_IP'] = 'AUTH_FAILED_BAD_IP - Contact SVIeSolutions to add this IP adresse to the permitted IP list.';
$string['error:CANNOT_CHANGE_STATE'] = 'CANNOT_CHANGE_STATE - The activity has changed state.';
$string['error:COMPANYNAME_TOO_LONG'] = 'COMPANYNAME_TOO_LONG - Le CompanyName value is longer than the maximum of 50 characters.';
$string['error:EMAIL_TOO_LONG'] = 'EMAIL_TOO_LONG - The Email value is longer than the maximum of 100 characters.';
$string['error:ERROR_AUTH_BAD_CIEID'] = 'ERROR_AUTH_BAD_CIEID - The IP of the caller is invalid.';
$string['error:ERROR_FAILED_EDIT_USER'] = 'ERROR_FAILED_EDIT_USER - Error during edition, the user was not modified.';
$string['error:ERROR_LOGIN_NO_SPACE_ALLOWED'] = 'ERROR_LOGIN_NO_SPACE_ALLOWED - the login name contains spaces.';
$string['error:FIRSTNAME_TOO_LONG'] = 'FIRSTNAME_TOO_LONG - The Firstname value exceeds the maximum of 50 characters.';
$string['error:FONCTIONTILE_TOO_LONG'] = 'FONCTIONTILE_TOO_LONG - The Fonction value exceeds the maximum of 50 characters.';
$string['error:INVALID_ACTIVITYID'] = 'INVALID_ACTIVITYID - The ID value of the activity is not valid, synchronisation was not possible.';
$string['error:INVALID_ACTIVITYSTATE'] = 'INVALID_ACTIVITYSTATE - The value passed in ActivityState is invalid.';
$string['error:INVALID_ACTIVITYTYPE'] = 'INVALID_ACTIVITYTYPE - The value passed in ActivityType is invalid.';
$string['error:INVALID_AUDIOTYPE'] = 'INVALID_AUDIOTYPE - The value passed in AudioType isinvalid.';
$string['error:INVALID_CIEID'] = 'INVALID_CIEID - The CIEID value must be numeric.';
$string['error:INVALID_CONFIRMATION_STATUS'] = 'INVALID_CONFIRMATION_STATUS - The value passed in ConfirmationStatus is invalid.';
$string['error:INVALID_DATE'] = 'INVALID_DATE - Date format AAAA-MM-JJ HH:MM:SS.';
$string['error:INVALID_DURATION'] = 'INVALID_DURATION - The DURATION value must be numeric.';
$string['error:INVALID_MONDAY_VALUE'] = 'INVALID_MONDAY_VALUE - The value of the property; Monday is invalid.';
$string['error:INVALID_TUESDAY_VALUE'] = 'INVALID_TUESDAY_VALUE - The value of the property;  Tuesday is invalid.';
$string['error:INVALID_WEDNESDAY_VALUE'] = 'INVALID_WEDNESDAY_VALUE - The value of the property;  Wednesday is invalid.';
$string['error:INVALID_THURSDAY_VALUE'] = 'INVALID_THURSDAY_VALUE -The value of the property; Thursday is invalid.';
$string['error:INVALID_FRIDAY_VALUE'] = 'INVALID_FRIDAY_VALUE - The value of the property; Friday is invalid.';
$string['error:INVALID_SATURDAY_VALUE'] = 'INVALID_SATURDAY_VALUE - The value of the property; Saturday is invalid.';
$string['error:INVALID_SUNDAY_VALUE'] = 'INVALID_SUNDAY_VALUE - The value of the property; Sunday is invalid.';
$string['error:INVALID_GENDER'] = 'INVALID_GENDER - The value passed in Genre is invalid.';
$string['error:INVALID_ISNUMBEREDTITLE'] = 'INVALID_ISNUMBEREDTITLE - The value passed in IsNumberedTitle is invalid.';
$string['error:INVALID_ISPUBLIC'] = 'INVALID_ISPUBLIC - The value passed in ISPUBLIC must be 0 or 1.';
$string['error:INVALID_ISRECORDED'] = 'INVALID_ISRECORDED - The value passed in IsRecorded is invalid.';
$string['error:INVALID_ISREPLAYALLOWED'] = 'INVALID_ISREPLAYALLOWED - The value passed in IsReplayAllowed is invalid.';
$string['error:INVALID_LANGUAGE'] = 'INVALID_LANGUAGE -The value passed in LANGAGE is invalid.';
$string['error:INVALID_MAIL_FORMAT'] = 'INVALID_MAIL_FORMAT - The email format is invalid.';
$string['error:INVALID_MONTHLYDAY'] = 'INVALID_MONTHLYDAY - The value MONTHLYDAY is invalid.';
$string['error:INVALID_NEEDCONFIRMATION'] = 'INVALID_NEEDCONFIRMATION - The value passed in NeedConfirmation is invalid.';
$string['error:INVALID_OBJECT_FORMAT'] = 'INVALID_OBJECT_FORMAT - The INNERXML of the soap:body is invalid.';
$string['error:INVALID_PARTICIPANT_TYPE'] = 'INVALID_PARTICIPANT_TYPE - The value passed in ParticipantType is invalid.';
$string['error:INVALID_PERIODICITY_ENDDATE'] = 'INVALID_PERIODICITY_ENDDATE - Date format AAAA-MM-JJ.';
$string['error:INVALID_PERIODICITY_STARTDATE'] = 'INVALID_PERIODICITY_STARTDATE - Date format AAAA-MM-JJ HH:MM:SS.';
$string['error:INVALID_PHONERIGHT'] = 'INVALID_PHONERIGHT - The conference brige is not valid for this company.';
$string['error:INVALID_PLAYBACK_ID'] = 'INVALID_PLAYBACK_ID - The playback ID is invalid.';
$string['error:INVALID_PROFILID'] = 'INVALID_PROFILID - The mutlimedia profile is either not valid or does not exist for this client.';
$string['error:_INVALID_PROFILID'] = 'INVALID_PROFILID - The mutlimedia profile is either not valid or does not exist for this client.';
$string['error:INVALID_RECNOTIFICATION'] = 'INVALID_RECNOTIFICATION - The value passed in LANGAGE is invalid.';
$string['error:INVALID_RECORDMODEBEHAVIOR'] = 'INVALID_RECORDMODEBEHAVIOR - The value passed in RecordModeBehaviour is invalid.';
$string['error:INVALID_REDIRECT_TYPE'] = 'INVALID_REDIRECT_TYPE - The value passed in RedirectType is invalid.';
$string['error:INVALID_REMINDERTIME'] = 'INVALID_REMINDERTIME - The value passed in ReminderTime is invalid.';
$string['error:INVALID_ROOMTYPE'] = 'INVALID_ROOMTYPE - The value passed in RoomType is invalid.';
$string['error:INVALID_SOAP_FORMAT'] = 'INVALID_SOAP_FORMAT - Le SOAP request was not properly instanced.';
$string['error:INVALID_STATUS'] = 'INVALID_STATUS - The value passed in Status is invalid.';
$string['error:INVALID_TIMEZONE'] = 'INVALID_TIMEZONE - The value passed in TIMEZONE is invalid.';
$string['error:INVALID_TITLE_TOO_LONG'] = 'INVALID_TITLE_TOO_LONG - The title exceeds the maximum of 100 characters.';
$string['error:INVALID_USER_RIGHT'] = 'INVALID_USER_RIGHT - The user does not have edition or creation rights for this activity.';
$string['error:INVALID_USERID'] = 'INVALID_USERID - The user ID is invalid.';
$string['error:INVALID_USERTYPE'] = 'INVALID_USERTYPE - The value passed in LANGAGE is invalid.';
$string['error:INVALID_WAITINGROOMACCESSMODE'] = 'INVALID_WAITINGROOMACCESSMODE - The value passed in WaitingRoomAccesMode is invalid.';
$string['error:LASTNAME_TOO_LONG'] = 'LASTNAME_TOO_LONG - The Lastname value exceeds the maximum of 50 characters.';
$string['error:LOGIN_EMPTY'] = 'LOGIN_EMPTY - The Login value is empty.';
$string['error:LOGIN_TOO_LONG'] = 'LOGIN_TOO_LONG - The Login value exceeds the maximum of 15 characters.';
$string['error:LOGIN_USED'] = 'LOGIN_USED - The Login value is already used.';
$string['error:PASSWORD_EMPTY'] = 'PASSWORD_EMPTY - The Password value is empty.';
$string['error:PASSWORD_TOO_LONG'] = 'PASSWORD_TOO_LONG - The Password value exceeds the maximum of 15 characters.';
$string['error:PERIODICITY_NODE_MISSING'] = 'PERIODICITY_NODE_MISSING - The periodicity value could not be passed.';
$string['error:PHONEBUS_TOO_LONG'] = 'PHONEBUS_TOO_LONG - The PHONEBUS value exceeds the maximum of 20 characters.';
$string['error:PHONECEL_TOO_LONG'] = 'PHONECEL_TOO_LONG - The PHONECEL value exceeds the maximum of 20 characters.';
$string['error:PHONEHOME_TOO_LONG'] = 'PHONEHOME_TOO_LONG - The PHONEHOME value exceeds the maximum of 20 characters.';
$string['error:TITLE_IS_REQUIRED'] = 'TITLE_IS_REQUIRED - A titile is required.';
$string['error:TITLE_TOO_LONG'] = 'TITLE_TOO_LONG - The Title value exceeds the maximum of 100 characters.';
$string['error:UNABLE_TO_CREATEDATE'] = 'UNABLE_TO_CREATEDATE - The was an error in the creation of the perioditcity dates.';
$string['error:USER_DOES_NOT_EXIST'] = 'USER_DOES_NOT_EXIST - The user is invalid.';
$string['error:USERID_EMPTY'] = 'USERID_EMPTY - The value UserID is invalid.';
$string['error:STATUS_INVALID'] = 'The connection to the web conference room was not possible as your user is either deleted or inactive.<br/>Please contact the Via administrator od your institution in order to reactivate your Via user.';
$string['STATUS_INVALID'] = 'The connection was impossible, the status is invalid.';
$string['error:INVALID_RECORD_TYPE'] = 'INVALID_RECORD_TYPE - The recording type requested is invalide.';
$string['error:RECORD_NOT_DOWNLOADABLE'] = 'RECORD_NOT_DOWNLOADABLE - The recording is not downloadable.';
$string['error:USER_DOWNLOAD_NOT_ALLOWED'] = 'USER_DOWNLOAD_NOT_ALLOWED - The user is not allowed to download this recording.';
$string['error:PLAYBACK_NOT_PUBLIC'] = 'PLAYBACK_NOT_PUBLIC - This playback is not public.';
$string['error:_CANNOT_CHANGE_ISNEWVIA'] = 'CANNOT_CHANGE_ISNEWVIA - Once an activity has been accesed, it is no longer possible to modify the version.';
/* */
$string['export'] = 'View/Export';

$string['finish'] = 'Done';
$string['fullvideo'] = 'Full video (MP4)';
$string['fullvideoinfo'] = 'As viewed in the recording. Resolution : 1024x768';

$string['gotoactivity'] = 'Access';
$string['gotorecording'] = 'Access the recording';
$string['groupusers'] = 'Users of the accosiated grouping: {$a} cannot be removed from the activity, but others may be added.';

$string['hasconfirmed'] = 'You have confirmed that you will attend this activity.';
$string['hasconfirmednot'] = 'You have confirmed that you won\'t attend this activity.';
$string['headerduration'] = 'Duration';
$string['hidelist'] = 'Hide';
$string['highquality'] = 'High quality';
$string['html5activation'] = 'Use Via HTML5';
$string['html5activationdesc'] = 'If this box is checked, you will have an option to choose between Via or Via HTML5 when creating a virtual class';
$string['host'] = 'Host';
$string['host_replace'] = 'Replace host';

$string['incomplete'] = 'Incomplete';
$string['inhostabsence'] = 'In host absence (automatic)';
$string['inviteemailhtml'] = '<p>Hello {$a->username},</p>
<p>Moodle invites you to participate in the activity &laquo; {$a->title} &raquo; that will take place on <b>{$a->datebegin}</b> between <b>{$a->hourbegin}</b> and <b>{$a->hourend}</b>.</p><p>{$a->invitemsg}</p>';
$string['invitewebaccesshtml'] = 'Web access';
$string['invitepreparationhtml'] = 'Activity preparation';
$string['inviteclicktoaccesshtml'] = 'To go to the activity, click this link below:';
$string['invitessend'] = 'Invitations will be sent in less than 15 minutes.';
$string['invitessent'] = 'Invitations have been sent.';
$string['inviteemailsubject'] = 'INVITATION: {$a->title}';
$string['inviteemail'] = '{$a->coursename} -> {$a->modulename} -> {$a->title}
---------------------------------------------------------------------
{$a->datesend}
---------------------------------------------------------------------

Hello {$a->username},

Moodle invites you to participate in the activity &quot; {$a->title} &quot; that will take place on {$a->datebegin} between {$a->hourbegin} and {$a->hourend}

{$a->invitemsg}

---------------------------------------------------------------------
Activity preparation

Click here for the setup wizard : {$a->config}
Clcik here to get technical support : {$a->assist}

---------------------------------------------------------------------
Web access

To go to the activity, follow this link : {$a->activitylink}

---------------------------------------------------------------------

Attention : This activity can be recorded. Please do not access this activity if you do not want to be recorded. This email contains personnal connection informations. Those informations must not be shared';
$string['inviteemail_viahtml5'] = '{$a->coursename} -> {$a->modulename} -> {$a->title}
---------------------------------------------------------------------
{$a->datesend}
---------------------------------------------------------------------

Hello {$a->username},

Moodle invites you to participate in the activity &quot; {$a->title} &quot; that will take place on {$a->datebegin} between {$a->hourbegin} and {$a->hourend}

{$a->invitemsg}

---------------------------------------------------------------------
Web access

To go to the activity, follow this link : {$a->activitylink}

---------------------------------------------------------------------';
$string['inviteemailhtml'] = '<p>Hello {$a->username},</p>
<p>Moodle invites you to participate in the activity &laquo; {$a->title} &raquo; that will take place on <b>{$a->datebegin}</b> between <b>{$a->hourbegin}</b> and <b>{$a->hourend}</b>.</p><p>{$a->invitemsg}</p>';

/* Invites modified for permanent activities*/
$string['inviteemailpermanent'] = '{$a->coursename} -> {$a->modulename} -> {$a->title}
---------------------------------------------------------------------
{$a->datesend}
---------------------------------------------------------------------

Hello {$a->username},

{$a->invitemsg}

---------------------------------------------------------------------
Activity preparation

Click here for the setup wizard : {$a->config}
Clcik here to get technical support : {$a->assist}

---------------------------------------------------------------------
Web access

To go to the activity, follow this link : {$a->activitylink}

---------------------------------------------------------------------

Attention : This activity can be recorded. Please do not access this activity if you do not want to be recorded. This email contains personnal connection informations. Those informations must not be shared';
$string['inviteemailpermanent_viahtml5'] = '{$a->coursename} -> {$a->modulename} -> {$a->title}
---------------------------------------------------------------------
{$a->datesend}
---------------------------------------------------------------------

Hello {$a->username},

{$a->invitemsg}

---------------------------------------------------------------------
Web access

To go to the activity, follow this link : {$a->activitylink}

---------------------------------------------------------------------';
$string['inviteemailhtmlpermanent'] = '<p>Hello {$a->username},</p>
<p>{$a->invitemsg}</p>';
$string['invitewarninghtml'] = 'Attention : This activity can be recorded. Please do not access this activity if you do not want to be recorded. This email contains personnal connection informations. Those informations must not be shared.';
$string['ish264'] = 'Activate video mode for mobile devices';
$string['ish264_help'] = 'If this box is checked, a less efficient video codec will be used and participants running mobiles will be able to see other people\'s videos. If this box is left blank, a more efficient video codec (H264) will be used and mobiles users won\'t be able to see other people\'s videos, but only their voice. They will however keep access to all other multimedia functionalities (documents, annotations, screen sharing, etc).';

$string['list_activities'] = 'List of all Via activities in this course';
$string['lowquality'] = 'Low quality';

$string['manageparticipants'] = 'Manage users';
$string['manual'] = 'Manual';
$string['manualenrol'] = 'Manual enrolment';
$string['mask'] = 'Hidden';
$string['mediumquality'] = 'Medium quality';
$string['mobilevideo'] = 'Mobile (MP4)';
$string['mobilevideoinfo'] = 'Optimised for mobile. Resolution : 480x320';
$string['mode'] = 'Audio mode';
$string['modephone'] = 'Phone conference only';
$string['modevoiceweb'] = 'Voice over the Web';
$string['modewebphone'] = 'Web and voice over the phone';
$string['modulename'] = 'Via';
$string['modulename_help'] = 'The Via module allows you to create synchronous meetings in a virtual classroom to share live using voice and video for: remote classes in real time, meetings, work-team meetings, tutoring, seminars, etc.

This tool allows you to manage sub-work-groups, annotations, screen sharing, import or present documents and to share by voice and/or video.

The \'Participant enrolments\' options allow you to manually enroll participants or automatically synchronize them according to their Moodle rights by selecting the option \'Automatic enrolment\'.

The \'Session parameters\' allow you to set the recording mode you wish and select the availability of these to your learners.';

$string['modulenameplural'] = 'Via';
$string['moodle_config'] = 'API configuration - Step 2';
$string['moodle_adminid'] = 'Moodle Admin unique ID';
$string['moodleidsetting'] = 'Unique admin user for this moodle';
$string['multimediaquality'] = 'Multimedia profiles';
$string['multimediaquality_help'] = 'Select the media profile to use in the activity for media exchange (webcam, microphone and multimedia documents). This option can have a significant impact on the fluidity of the exchanges and the bandwidth required for each participant. In general, for a better experience or if you are unsure, it is best to use a lower quality to ensure the smooth fluidity. It is possible to configure other multimedia profiles according to your specific needs (eg large video thumbnails excellent for use in local mode). Contact one of our experts Via for more on this.';
$string['multiple'] = 'Multiple';
$string['mustaccept'] = 'Check the box in order to acces the activity.';

$string['name'] = 'Name';
$string['needconfirmation'] = 'Request confirmation of availability ';
$string['needconfirmation_help'] = 'Request confirmation of availability ';
$string['neverbegin'] = 'Not done';
$string['no_default'] = 'No default category.';
$string['noanimators'] = 'There are no animators yet for this activity.';
$string['no_categories'] = 'There are no categories, these must be created in the Via portal by and administrator';
$string['nocategories'] = 'No categories';
$string['notactivatedfeminin'] = 'Not activated';
$string['noticeemailsubject'] = 'ALERT : export Via is ready';
$string['notificationemailsubject'] = 'ALERT : activity notification';
$string['noticeemail'] = '
Hello {$a->username},

This is an automated message.

The export you asked for, for recording &quot;{$a->playbacktitle}&quot; in {$a->type} is ready since {$a->date}.
You can download this export or make it available to the participants, by accessing the details\' page of your activity &quot;{$a->activitytitle}&quot;.

Web access
---------------------------------------------------------------------

Click the following link for direct access : {$a->activitylink}

---------------------------------------------------------------------
';
$string['notificationemail'] = '
Hello {$a->username},

This is an automated message.

The user {$a->userfrom} is trying to contact you by connecting to your activity : &quot;{$a->activitytitle}&quot;

Web access
---------------------------------------------------------------------

Click the following link for direct access : {$a->activitylink}

---------------------------------------------------------------------
';
$string['noticeemailhtml'] = '<p>Hello {$a->username},</p>
<p>This is an automated message.</p>
<p>The export you asked for, for recording &quot;{$a->playbacktitle}&quot; in {$a->type} is complete since {$a->date}.<br/>
You can download this export or make it available to the participants, by accessing the details page of your activity &quot;{$a->activitytitle}&quot;.</p>';
$string['notificationemailhtml'] = '<p>Hello {$a->username},</p>
<p>This is an automated message.</p>
<p>The user {$a->userfrom} is trying to contact you by connecting to your activity : &quot;{$a->activitytitle}&quot;.</p>';
$string['noticeclicktoaccesshtml'] = 'Click the following link for direct access :';
$string['notifysuccess_synch1'] = 'All users were successfully synched with Via.';
$string['notifysuccess_synch2'] = 'Certain users could not be sycnhed, please validate their information and try again.';
$string['noparticipants'] = 'There are no participants yet for this activity.';
$string['noparticipants_help'] = 'This option is available only with automatic enrollment and ensures that users with the student status are all added as animators in Via.';
$string['noparticipantscheckbox'] = 'Add all students as animators';
$string['norecall'] = 'No recall';
$string['notactivated'] = 'Do not record';
$string['notattending'] = 'I won\'t be attending';
$string['notenrolled'] = 'You are not enrolled in this activity. Please contact your teacher if you think you should have access.';
$string['notstarted'] = 'This activity hasn\'t started yet.';
$string['nounifiedrecordpermanent'] = 'Unified record mode is not possible for a permanent activity. Please choose an other option.';
$string['nousers'] = 'Warning - There are no users associated to this activity!';

$string['oldapiversion'] = 'You are using an API version that is older than $a';
$string['options'] = 'Activity options';
$string['overview'] = 'Starts on {$a->start} and ends {$a->end}';

$string['passdate'] = 'The selected date is passed';
$string['participant'] = 'Participant';
$string['participantmustconfirm'] = 'Participants must confirm participation';
$string['participantmustconfirmdesc'] = 'If checked, participants must confirm if they will be part of the activity.';
$string['participants'] = 'Participants';
$string['participants_search'] = 'search participants';
$string['participantsynchronization'] = 'Synchronize participants\' information';
$string['participantsynchronizationdesc'] = 'If checked, the participants\' information will by synchronized with those saved in Moodle. The only information that will not be updated are : the login, the password and the user type in Via.';
$string['permanent'] = 'Permanent activity';
$string['permanent_help'] = 'Check "Permanent" to make your activity accessible at all times. You will then be obliged to use the waiting room to restrict access to registered users with the "participant" status.';
$string['permanentactivities'] = 'Permanent activities';
$string['permanentactivitiesdesc'] = 'If checked, it will be possible to create permanent activities.';
$string['personalinvitemsg'] = 'Personalized message (optional): ';
$string['playback_downloaded'] = 'Playback downloaded';
$string['playback_viewed'] = 'Playback viewed';
$string['playbackaccesstype0'] = 'Visible for those with editing rights';
$string['playbackaccesstype1'] = 'Visible for those associated to the virtual class';
$string['playbackaccesstype2'] = 'Visible for those associated to the delegated activity';
$string['playbackaccesstypelbl'] = 'Access rights for the recording';
$string['playbackheader'] = 'Playback viewed (h:m:s)';
$string['playbackSynchronize'] = 'Synchronize playbacks';
$string['pluginadministration'] = 'Via administration';
$string['pluginname'] = 'Via - Virtual classroom';
$string['portalaccess'] = 'Via portal access.';
$string['portalaccessdesc'] = 'Permits users to access the Via portal without loging in.';
$string['potentialusers'] = 'Potential users';
$string['preparation'] = 'Prepare your activity ';
$string['prepareactivity'] = 'Click here prepare your activity';
$string['presence'] = 'Minimum attendance required';
$string['presence_help'] = 'Text must be changed!!!! Value corresponding to the time in minutes from which the user gets the presence status for the activity.';
$string['presenceheader'] = 'Online presence (h:m:s)';
$string['presenceheaderreport'] = 'Online presence';
$string['presencestatus'] = 'Display presence status';
$string['presencestatusdesc'] = 'If checked, a new option will apear in the activitie\'s parameters to set a minimum amount of time that users need to be present to be concidered present for an activity. A printable version will also be available.';
$string['presencetable'] = 'Presence status: ';
$string['presencewarning'] = 'Important: The online presence status is determined by the minimum time required to be considered present for the activity. The status influences the participant\'s progress bar. It is possible to adjust the status by changing the &laquo; Minimum attendance required &raquo; value in the activity\'s parameters.';
$string['present'] = 'Present';

/* Qualite multimedias */
$string['Qualitedebase'] = "Low quality";
$string['Qualitestandard'] = "Standard quality";
$string['Qualitesuperieure'] = "High quality";

$string['recallonehour'] = 'One hour before';
$string['recalltwohours'] = 'Two hours before';
$string['recalloneday'] = 'One day before';
$string['recalltwodays'] = 'Two days before';
$string['recalloneweek'] = 'One week before';
$string['recentrecordings'] = 'Recent recordings';
$string['recordaccept'] = ' I accept';
$string['recording'] = 'Recording';
$string['recordings'] = 'Available recording(s):';
$string['recordingmode'] = 'Recording mode';
$string['recordingmode_help'] = 'If you want to enable the recording option for your activities, two modes are available: "Unified" and "Multiple". The "unified"-type will produce a single unified recording regardless of the number of recordings made during the meeting; the "multiple"-type will produce recordings that are separated and segmented.';
$string['recordingtitle'] = 'Recording title';
$string['recordingisdownloadable'] = 'Recording is downloadable';
$string['recordingisdownloadableinfo'] = 'Note : you must first export the video in order for it to be avaiable for downlaod.';
$string['recordmodebehavior'] = 'Recording';
$string['recordmodebehavior_help'] = 'You can also choose to automatically start recording at access using the "Automatic" option. Choose "Manual" if you do not want the recording to starts automatically, you will then start the recording yourself by clicking on the record icon in the synchronous interface.';
$string['recordwarning'] = 'This activity may be recorded. When checking the box, you are accepting to be recorded.';
$string['refused'] = 'Won\'t attend';
$string['reminderemail'] = '{$a->coursename} -> {$a->modulename} -> {$a->title}
---------------------------------------------------------------------
{$a->datesend}
---------------------------------------------------------------------

Hello {$a->username},

Moodle invites you to participate in the activity &quot; {$a->title} &quot; that will take place on {$a->datebegin} between {$a->hourbegin} and {$a->hourend}

---------------------------------------------------------------------
Activity preparation

Click here for the setup wizard : {$a->config}
Clcik here to get technical support : {$a->assist}

---------------------------------------------------------------------
Web access

To go to the activity, follow this link : {$a->activitylink}

---------------------------------------------------------------------

Attention : This activity can be recorded. Please do not access this activity if you do not want to be recorded. This email contains personnal connection informations. Those informations must not be shared';
$string['reminderemail_viahtml5'] = '{$a->coursename} -> {$a->modulename} -> {$a->title}
---------------------------------------------------------------------
{$a->datesend}
---------------------------------------------------------------------

Hello {$a->username},

Moodle invites you to participate in the activity &quot; {$a->title} &quot; that will take place on {$a->datebegin} between {$a->hourbegin} and {$a->hourend}

---------------------------------------------------------------------
Web access

To go to the activity, follow this link : {$a->activitylink}

---------------------------------------------------------------------';

$string['reminderemailhtml'] = '<p>Hello {$a->username}reminderemailhtml,</p>
<p>This is a reminder for an activity coming soon:</p>
<p><b>Title:</b> {$a->title} <br/>
<b>Date and time</b>{$a->activitydate}<br/>
<b>Duration:</b> {$a->duration minutes}</p>';
$string['reminderemailsubject'] = 'REMINDER: {$a->title}';
$string['replace_host'] = 'Replace host';
$string['report'] = 'Presence report';
$string['resetdeletemodules'] = 'Delete all activities';
$string['resetparticipants'] = 'Delete all participants and animators (works only for activities with manuel enrollment)';
$string['resetdisablereviews'] = 'Disable reviews for all activities';
$string['return'] = 'Back';
$string['returnto'] = 'Back to activity';
$string['reviewacitvity'] = 'Make all recordings public by default';
$string['reviewacitvity_help'] = 'If you want to make the recordings available select yes. Otherwise, no participants will be able to see the recordings except if you check each one in the list. Please note that this option is editable anytime even when the activity has ended.';
$string['reviewactivity'] = 'Default access rights for recordings';
$string['reviewactivity_help'] = 'Select who can see the recordings.';
$string['role'] = 'Role';
$string['roomtype'] = 'Activity type';
$string['roomtype_help'] = 'The "Standard" type is an activity in which all participants are listed and can interact normally, depending on the role assigned. If instead you choose an activity type "Webinar",
 only the host and animators will see the names of the participants. In addition, they will only be able to interact through chatting. The latter type is ideal for activities including large audiences
 (over 100 participants) or confidential activities. When the "Permanent" option is selected, the activity will no longer have a fixed date and time.
 All participants associated with this activity can then access at any time. Please note that only the multiple recordings option is available for this type of activity';
$string['roomversion'] = 'Choose the Via version for this activity';
$string['roomversion_help'] = 'Choose the Via version for this activity';

$string['save'] = 'Save';
$string['sendinvitation'] = 'Email invitation';
$string['sendrecall'] = 'Send email recall';
$string['sendrecall_help'] = 'Allows you to set an automatic reminder. You can choose to automatically send a reminder to all participants 1 or 2 hours before, 1 or 2 days before, even one week prior to the activity. They will then receive a reminder by email.';
$string['seminar'] = 'Seminar';
$string['sessionparameters'] = 'Session parameters';
$string['show'] = 'Displayed';
$string['showlist'] = 'Display';
$string['showparticipants'] = 'Participants\' list';
$string['showparticipants_help'] = 'Lets you choose to show or hide the list of participants in Via when in webinar mode.';
$string['standard'] = 'Standard';
$string['startdate'] = 'Starts on';
$string['submitinvite'] = 'Send invitations';
$string['subscribeparticipants'] = 'Participants subscription';
$string['synctemplateend'] = "The activity template has been successfully synchronized.";

$string['technicalassist'] = 'Technical support ';
$string['technicalassist_url'] = 'Use a personalised technical support page.';
$string['technicalassist_urldesc'] = 'By default the technical support page will display the information provided in Via. You may add a personalised support page by calling the URL directly. This may be a page created in moodle or another site all together.';
$string['testadminid'] = 'Test the moodle key';
$string['testconnection'] = 'Test API connection';
$string['testconnectionhtml5'] = 'Test HTML5 API connection';
$string['title_exists'] = 'The title already exists, please try again.';
$string['timeduration'] = 'Duration :';


$string['typepInscriptionsetting'] = 'When creating users in Via, this right is granted by default.';
$string['typepInscriptionmember'] = 'user';
$string['typepInscriptioncollaborator'] = 'collaborator';
$string['typepInscriptionCoordinator'] = 'Coordinator';

$string['unified'] = 'Unified';
$string['unplanned'] = 'Unplanned';
$string['unplanned_error'] = 'You can only save your modifications if it is a planned activity!';
$string['unplanned_text'] = 'This activiy has not yet been planned.';
$string['unplannedsettings'] = 'Use unplanned activities';
$string['unplannedsettings_desc'] = 'If this box is checked, the restored activities will be "unplanned" by default. To activate them, you must modify their parameters and assign them a date or define them as permanent. Regardless of the other duplication options, users and documents will not be copied.<br/>If the box is not checked, the restored activities will be set to one month later by default.';

$string['updatethisvia'] = 'Update this Via';
$string['userishost'] = 'This user is the host, chose a new one in order to give this user a new role.';
$string['users_search'] = 'Search potential users';
$string['usersynch'] = 'Users will automatically be associated with the activity when they connect. <br/>If you want to pre-associate then in order to allocate them to workshops, click here:';
$string['usersynchbtn'] = 'Associate all users now';
$string['usersynchwarning'] = 'Please wait, this may take some time!';

$string['versionold'] = 'Use Via version 6';
$string['versionnew'] = 'Use the most recent version of Via';
$string['versions_not_compatible'] = 'The plugin and Via versions are not compatible. The plugin requires a minimum of ';
$string['versionscompatible'] = 'The plugin and Via versions are compatible.';

$string['via_typepInscription'] = 'Default right in Via';

/* permissions */
$string['via:addinstance'] = 'Add a new via activity';
$string['via:access'] = 'Access Via without being associated';
$string['via:manage'] = 'Manage Via Activities';
$string['via:viewpresence'] = 'View Presence Reports for Via Activities';
$string['via:view'] = 'View Via Activities';
/* task description */
$string['via_ciesettings_task'] = "Synchronisation settings VIA";
$string['via_mail_task'] = "VIA mail sending";
$string['via_usersync_task'] = "Via Moodle user synchronisation";
$string['via_notification_task'] = "Via activity notifications";
$string['via_branchsync_task'] = "Via branches update";

/* */
$string['viaaccessed'] = 'Via accessed';
$string['viaaudiotypes'] = 'Select audio mode that you want to keep.';
$string['viacategoriesdesc'] = 'If checked, categories created in Via may be added to the activity.';

$string['viauserreset'] = 'Cleanup Via users';
$string['viauserresetbutton'] = 'Cleanup Via users';
$string['viauserresetdesc'] = 'If your users are experiencing problems logging in, this removes link between Moodle users and Via users. New links will be created with no visible difference for users. Your site may appear slower until all links are created again';
$string['viausersresetend'] = 'Cleanup successfull';

$string['via9'] = 'Via';
$string['viausers'] = 'Users:';
$string['view'] = 'View';

$string['vroom'] = 'Via HTML5';

$string['waitingconfirm'] = 'Waiting for confirmation';
$string['waitingroomaccessmode'] = 'Waiting room';
$string['waitingroomaccessmode_help'] = 'The option "Pending Authorization" allows the host to allow individual participants\' access while the option "In the absence of the host" ensures that no user can access the activity until the speaker is not connected. This last option is particularly useful when using permanent activities.';