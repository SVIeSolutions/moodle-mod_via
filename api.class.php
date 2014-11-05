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
  * @package    mod
  * @subpackage via
  * @copyright  SVIeSolutions <alexandra.dinan@sviesolutions.com>
  * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  * 
  */

/** Data access class for the via module. **/
class mod_via_api {

    /**
     * Creates a user on VIA
     *
     * @param object $user an object user
     * @param bool $edit if true, edit an existing user, else create a new one
     * @param Array $infoplus additional info when creating/editing user
     * @return Array containing response from Via
     */
    public function via_user_create($muser, $edit=false, $infoplus=null) {
        global $CFG;

        require_once($CFG->dirroot . '/mod/via/lib.php');

        if ($edit) {
            // We are editing user.
            $url = 'UserEdit';
        } else {
            // We are creating a new user.
            $url = 'UserCreate';
        }
        if (!isset($muser->viausername)) {
            $muser->viausername = strtolower($muser->email);
        }

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiUsers>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        if ($edit) {
            $data .= "<ID>".$muser->viauserid."</ID>";
            if (isset($muser->usertype)) {
                $data .= "<UserType>".$muser->usertype."</UserType>";
            }
            if (isset($user->status)) {
                $data .= "<Status>".$muser->status."</Status>";
            }
        }
        if ($muser->lastname) {
            $data .= "<LastName>".$muser->lastname."</LastName>";
        } else {
            $data .= "<LastName>Utilisateur</LastName>";
        }
        if ($muser->firstname) {
            $data .= "<FirstName>".$muser->firstname."</FirstName>";
        } else {
            $data .= "<FirstName>Temporaire</FirstName>";
        }
        $data .= "<Login>".$muser->viausername."</Login>";
        if (!$edit) {
            $data .= "<Password>".via_create_user_password()."</Password>";
        }
        $data .= "<UniqueID>".$muser->username."</UniqueID>";
        $data .= "<Email>".strtolower($muser->email)."</Email>";

        if ($infoplus) {
            foreach ($infoplus as $name => $info) {
                $data .= "<".$name.">".$info."</".$name.">";
            }
        }
        if ($muser->lang == "en" || $muser->lang == "en_us" || $muser->lang == "en_utf8") {
            $data .= "<Language>2</Language>";
        } else if ($muser->lang == "fr_ca" ) {
            $data .= "<Language>1</Language>";
        } else if ($muser->lang == "es" || $muser->lang == "es_mx" || $muser->lang == "es_ve") {
            $data .= "<Language>4</Language>";
        } else {
            $data .= "<Language>3</Language>";
        }

        $data .= "</cApiUsers>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($muser, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"]) {
            throw new Exception("Problem adding new user to Via");
        }

        if (!$edit) {
            if ($response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"]["Result"]["ResultState"] == "ERROR") {
                if ($response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"]["Result"]["ResultDetail"] == "LOGIN_USED") {
                    $resultdata = 'LOGIN_USED';
                    return $resultdata;
                } else {
                    throw new Exception('user_create : ["ResultState"] == "ERROR")');
                    return false;
                }
            }
        }

        $muser->viauserid = $resultdata['ID'];

        $resultdata['UserID'] = $resultdata['ID'];

        return $resultdata;
    }

    /**
     * gets a user on Via
     *
     * @param object $user the user
     * @return Array containing response from Via
     */
    public function via_user_get($user, $checkingstatus=null) {
        global $CFG;

        $url = 'UserGet';

        $muser = $user;

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiUsersGet>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ID>".$user->viauserid."</ID>";
        $data .= "</cApiUsersGet>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe($user, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"]) {
            throw new Exception("Problem getting user on VIA");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            if ($checkingstatus) {
                return false;
            } else {
                throw new Exception($resultdata['Result']['ResultDetail']);
            }
        }
        return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"];
    }

    /**
     * Searches a user on VIA
     *
     * @param Array $search search items
     * @param Array $searchterm the info beeing searched (login, email, ect.)
     * @return Array containing search results or FALSE if nothing was found
     */
    public function via_user_search($search, $searchterm=null) {
        global $CFG;

        $url = 'UserSearch';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiUserSearch>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<".$searchterm.">".$search."</".$searchterm.">";
        $data .= "</cApiUserSearch>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserSearch"]) {
            throw new Exception("Problem searching user in Via");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        if ($resultdata["nbrResults"] == 0) {
            // The username is already in use. We need to create the user with a new username.
            return false;
            // Throw new Exception("Problem searching user in Via");.
        }

        if (isset($response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserSearch"]["Search"]["Match attr"])) {
            $searchedusers = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserSearch"]["Search"]["Match attr"];

            if ($searchedusers["Email"] == $search) {
                return $searchedusers;
            } else {
                return false;
            }

        } else {
            $searchedusers = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserSearch"]["Search"]["Match"];
            if ($searchedusers) {
                $l = count($searchedusers);
                $i = '';
                $lastusertype = '';
                foreach ($searchedusers as $searcheduser) {
                    if (!empty($searcheduser["UserID"])) {
                        if ($searcheduser["Email"] == $search) {// We need an exact match only!
                            $viauser = new stdClass();
                            $viauser->viauserid = $searcheduser["UserID"];
                            $viauserdata = $this->via_user_get($viauser);
                            $usertype = $viauserdata["UserType"];
                            if ($usertype > $lastusertype) {
                                $i = $searcheduser;
                                $lastusertype = $usertype;
                            }
                        }
                    }
                    $l -= 1;
                    if ($l == 1) {
                        // Return the user with the highest user rights!
                        return $i;
                    }
                }

                return false;
            }
        }

        return false;

    }

    /**
     * Creates a new acitivity on Via
     *
     * @param object $via the via object
     * @return Array containing response from Via
     */
    public function activity_create($via) {
        global $CFG;

        $url = 'ActivityCreate';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiActivity>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>".get_config('via', 'via_adminid')."</UserID>";
        $data .= "<Title>".$via->name."</Title>";
        $data .= "<ProfilID>".$via->profilid."</ProfilID>";
        if ($via->category != '0') {
            $data .= "<CategoryID>".$via->category."</CategoryID>";
        }
        $data .= "<IsReplayAllowed>".$via->isreplayallowed."</IsReplayAllowed>";
        $data .= "<ActivityState>1</ActivityState>";
        $data .= "<RoomType>".$via->roomtype."</RoomType>";
        $data .= "<AudioType>".$via->audiotype."</AudioType>";
        $data .= "<ActivityType>".$via->activitytype."</ActivityType>";
        $data .= "<NeedConfirmation>".$via->needconfirmation."</NeedConfirmation>";
        $data .= "<RecordingMode>".$via->recordingmode."</RecordingMode>";
        $data .= "<RecordModeBehavior>".$via->recordmodebehavior."</RecordModeBehavior>";
        $data .= "<WaitingRoomAccessMode>".$via->waitingroomaccessmode."</WaitingRoomAccessMode>";
        if (get_config('via', 'via_moodleemailnotification')) {
            $data .= "<ReminderTime>".'0'."</ReminderTime>";// The reminder email will be sent by Moodle, not VIA!
        } else {
            $data .= "<ReminderTime>".via_get_remindertime_svi($via->remindertime)."</ReminderTime>";
        }
        if ($via->activitytype != 2) {
            $data .= "<DateBegin>".$this->change_date_format($via->datebegin)."</DateBegin>";
            $data .= "<Duration>".$via->duration."</Duration>";
        } else {
            $data .= "<DateBegin>".'0'."</DateBegin>";
            $data .= "<Duration>".'0'."</Duration>";
        }
        $data .= "</cApiActivity>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivity"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        $via->viaactivityid = $resultdata['ActivityID'];

        return $response['viaresponse'];

    }

    /**
     * Dulpicates an existing acitivity on Via 
     *
     * @param object $via the via object
     * @return Array containing response from Via
     */
    public function activity_duplicate($via) {
        global $CFG;

        $url = 'ActivityDuplicate';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiActivityDuplicate>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>".get_config('via', 'via_adminid')."</UserID>";
        $data .= "<ActivityID>".$via->viaactivityid."</ActivityID> ";
        $data .= "<Title>".$via->name."</Title>";
        if ($via->activitytype != 2) {
            $data .= "<DateBegin>".$this->change_date_format($via->datebegin)."</DateBegin>";
            $data .= "<Duration>".$via->duration."</Duration>";
        }
        $data .= "<IncludeUsers>".$via->include_userInfo."</IncludeUsers>";// 0 = Non ; 1 = Oui!
        $data .= "<IncludeDocuments>1</IncludeDocuments>";// 1 = Yes : documents are always added!
        $data .= "<IncludeSurveyAndWBoards>".$via->include_surveyandwboards."</IncludeSurveyAndWBoards>";// 0 = Non ; 1 = Oui!
        $data .= "</cApiActivityDuplicate>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivityDuplicate"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata["ActivityIDDuplicate"];

    }

    /**
     * Edits an acitivity on Via
     *
     * @param object $via the via object
     * @param bool $delete if true, activity needs to be deleted on Via
     * @return Array containing response from Via
     */
    public function activity_edit($via, $activitystate=1) {
        global $CFG;

        $url = 'ActivityEdit';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiActivity>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>".get_config('via', 'via_adminid')."</UserID>";
        $data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
        $data .= "<Title>".$via->name."</Title>";
        $data .= "<ProfilID>".$via->profilid."</ProfilID>";
        if ($via->category != '0') {
            $data .= "<CategoryID>".$via->category."</CategoryID>";
        }
        $data .= "<IsReplayAllowed>".$via->isreplayallowed."</IsReplayAllowed>";
        $data .= "<ActivityState>".$activitystate."</ActivityState>";
        $data .= "<RoomType>".$via->roomtype."</RoomType>";
        $data .= "<AudioType>".$via->audiotype."</AudioType>";
        $data .= "<ActivityType>".$via->activitytype."</ActivityType>";
        $data .= "<NeedConfirmation>".$via->needconfirmation."</NeedConfirmation>";
        $data .= "<RecordingMode>".$via->recordingmode."</RecordingMode>";
        $data .= "<RecordModeBehavior>".$via->recordmodebehavior."</RecordModeBehavior>";
        $data .= "<WaitingRoomAccessMode>".$via->waitingroomaccessmode."</WaitingRoomAccessMode>";
        if (get_config('via', 'via_moodleemailnotification')) {
            $data .= "<ReminderTime>0</ReminderTime>";// The reminder email will be sent by Moodle.
        } else {
            // The reminder email will be sent by VIA.
            $data .= "<ReminderTime>".via_get_remindertime_svi($via->remindertime)."</ReminderTime>";
        }
        if ($via->activitytype != 2) {// If the activity isn't permanent.
            $data .= "<DateBegin>".$this->change_date_format($via->datebegin)."</DateBegin>";
            $data .= "<Duration>".$via->duration."</Duration>";
        }
        $data .= "</cApiActivity>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivity"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $response['viaresponse'];

    }

    /**
     * gets an acitivity on Via
     *
     * @param object $via the via object
     * @return Array containing response from Via
     */
    public function activity_get($via) {
        global $CFG;

        $url = 'ActivityGet';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiActivityGet>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>".get_config('via', 'via_adminid')."</UserID>";
        $data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
        $data .= "</cApiActivityGet>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivity"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            return $resultdata['Result']['ResultDetail'];
        }

        return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivity"];

    }

    /**
     * gets all categories for this company from Via
     *
     * @return Array containing response from Via
     */

    public function via_get_categories() {
        global $CFG;

        $url = 'GetCategories';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiCategoryGet>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "</cApiCategoryGet>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiCategoryGet"]) {
            throw new Exception("Problem getting VIA categories");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiCategoryGet"]["CategoriesList"];

    }

    /**
     * gets all participants (all roles) for an activity 
     *
     * @param object $viaid the VIA via id
     * @return Array containing response from Via
     */
    public function get_userslist_activity($viaid) {
        global $CFG;

        $url = 'GetUsersListActivity';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiUsersListActivityGet>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ActivityID>".$viaid."</ActivityID>";
        $data .= "</cApiUsersListActivityGet>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe($viaid, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsersListActivityGet"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsersListActivityGet"]['ActivityUsersList']['ActivityUser'];

    }

    /**
     * gets all profiles available for a given company
     *
     * @return Array containing response from Via
     */
    public function list_profils() {
        global $CFG;

        $url = 'ListProfils';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiListProfils>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "</cApiListProfils>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiListProfils"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiListProfils"]["ProfilList"];

    }


    /**
     * gets a token to redirect a user to VIA
     *
     * @param object $via the via
     * @param integer $redirect where to redirect
     * @param string $playback id of the playback to redirect to
     * @return string URL
     */
    public function userget_ssotoken($via=null, $redirect=null, $playback=null, $forceaccess=null, $forceedit=null, $mobile=null) {
        global $CFG, $USER;

        if (!$mobile) {
            $muser = $USER->id;
        } else {
            $muser = $mobile;
        }
        if ($via) {
            $viaid = $via->id;
        } else {
            $viaid = null;
        }

        $url = 'UserGetSSOToken';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiGetUserToken>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ID>".$this->get_user_via_id($muser, true, $viaid)."</ID>";
        if ($via) {
            $data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
        }
        if ($playback) {
            $data .= "<PlaybackID>".$playback."</PlaybackID>";
        }
        $data .= "<RedirectType>".$redirect."</RedirectType>";
        $data .= "<PortalAccess>".get_config('via', 'via_portalaccess')."</PortalAccess>";
        $data .= "<ForcedAccess>".$forceaccess."</ForcedAccess>";
        $data .= "<ForcedEditRights>".$forceedit."</ForcedEditRights>";
        $data .= "</cApiGetUserToken>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsersSSO"]) {
            throw new Exception("Problem reading getting VIA user token");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        $token = $resultdata['TokenURL'] . '&from='. $CFG->wwwroot;

        return $token;
    }

    /**
     * Adds a user to an activity
     *
     * @param object $via the via
     * @return Array containing response from Via
     */
    public function add_user_activity($via) {
        global $CFG;

        $url = 'AddUserActivity';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiUserActivity_AddUser>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>".$this->get_user_via_id($via->userid)."</UserID>";
        $data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
        $data .= "<ParticipantType>".$via->participanttype."</ParticipantType>";
        $data .= "<ConfirmationStatus>".$via->confirmationstatus."</ConfirmationStatus>";
        $data .= "</cApiUserActivity_AddUser>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivity_AddUser"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $response['viaresponse'];

    }

    /**
     * Edits a user enrolment in an activity
     *
     * @param object $via the via
     * @return Array containing response from Via
     */
    public function edituser_activity($via) {
        global $CFG;

        $url = 'EditUserActivity';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiUserActivity_AddUser>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>".$this->get_user_via_id($via->userid)."</UserID>";
        $data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
        $data .= "<ConfirmationStatus>".$via->confirmationstatus."</ConfirmationStatus>";
        $data .= "</cApiUserActivity_AddUser>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivity_AddUser"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivity_AddUser"];

    }

    /**
     * Get a user enrolment info for an activity
     *
     * @param object $via the via
     * @return Array containing response from Via
     */
    public function getuser_activity($via) {
        global $CFG;

        $url = 'GetUserActivity';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiUserActivityGet>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>".$this->get_user_via_id($via->userid)."</UserID>";
        $data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
        $data .= "</cApiUserActivityGet>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivityGet"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivityGet"];

    }


    /**
     * remove a user in an activity
     *
     * @param string $viaid the VIA via id
     * @param integer $userid the id of the user to remove
     * @return Array containing response from Via
     */
    public function removeuser_activity($viaid, $userid, $moodleid = true) {
        global $CFG;

        $url = 'RemoveUser';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiUserActivity_RemoveUser>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        if ($moodleid == false) {
            $data .= "<UserID>".$userid."</UserID>";
        } else {
            $data .= "<UserID>".$this->get_user_via_id($userid)."</UserID>";
        }
        $data .= "<ActivityID>".$viaid."</ActivityID>";

        $data .= "</cApiUserActivity_RemoveUser>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($viaid, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivity_RemoveUser"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $response['viaresponse'];

    }

    /**
     * gets a list of playback available for a given activity
     *
     * @param object $via the via object
     * @return Array containing response from Via
     */
    public function list_playback($via) {
        global $CFG;

        $url = 'ListPlayback';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiListPlayback>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
        $data .= "</cApiListPlayback>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiListPlayback"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiListPlayback"]["PlaybackList"];

    }

    /**
     * edit a givent playback for a given activity
     *
     * @param object $via the via object
     * @param string $playbackid the id of the playback 
     * @param object $playback the playback object 
     * @return Array containing response from Via
     */
    public function edit_playback($via, $playbackid, $playback) {
        global $CFG;

        $url = 'EditPlayback';

        $title = str_replace("'", '&#39;', $playback->title);

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiListPlayback>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<PlaybackList>";
        $data .= "<Playback PlaybackID='".$playbackid."' Title='".$title."' IsPublic='".$playback->ispublic."'/>";
        $data .= "</PlaybackList>";
        $data .= "</cApiListPlayback>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiListPlayback"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiListPlayback"]["PlaybackList"];
    }


    /**
     * delete a given playback for a given activity
     *
     * @param object $via the via object
     * @param string $playbackid the id of the playback 
     * @param object $playback the playback object 
     * @return Array containing response from Via
     */
    public function delete_playback($viaactivityid, $playbackid) {
        global $CFG;

        $url = 'DeletePlayback';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';

        $data .= '<soap:Body>';
        $data .= "<cApiPlaybackDelete>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>".get_config('via', 'via_adminid')."</UserID>";
        $data .= "<ActivityID>".$viaactivityid."</ActivityID>";
        $data .= "<PlaybackID>".$playbackid."</PlaybackID>";
        $data .= "</cApiPlaybackDelete>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiPlaybackDelete"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiPlaybackDelete"];
    }


    /**
     * Sends invitation to a user for an activity
     *
     * @param integer $userid the id of the user to invite
     * @param string $activityid the VIA id of the activity 
     * @param string $msg the message to write in the invitation
     * @return Array containing response from Via
     */
    public function sendinvitation($userid, $activityid, $msg=null) {
        global $CFG;

        $url = 'SendInvitation';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiSendInvitation>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>".get_config('via', 'via_adminid')."</UserID>";
        $data .= "<ActivityID>".$activityid."</ActivityID>";
        if ($msg && !empty($msg)) {
            $data .= "<Msg>".$msg."</Msg>";
        }
        $data .= "</cApiSendInvitation>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiSendInvitation"]) {
            throw new Exception("Problem sending invitation");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiSendInvitation"];
    }


    /**
     * Changes the date format for Via
     *
     * @param integer $date the date in timestamp
     * @return string date
     */
    public function change_date_format($date) {
        return date("Y-m-d H:i:s", $date);
    }

    /**
     * Tests the given informations for connection to Via server
     *
     * @param string $apiurl the url
     * @param string $cleid the cle id
     * @param string $apiid th api id
     * @return Array containing response from Via
     */
    public function testconnection($apiurl, $cleid, $apiid) {
        global $CFG;

        $url = 'Test';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiTest>";
        $data .= "<ApiID>".$apiid."</ApiID>";
        $data .= "<CieID>".$cleid."</CieID>";
        $data .= "</cApiTest>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe(null, $data, $url, $apiurl);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiTest"]) {
            throw new Exception("Problem testing connexion");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }
        return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiTest"];
    }

    /**
     * Tests the given informations for connection to Via server
     *
     * @param string $apiurl the url
     * @param string $cleid the cle id
     * @param string $apiid th api id
     * @return Array containing response from Via
     */
    public function testadminid($apiurl, $cleid, $apiid, $adminid) {
        global $CFG;

        $url = 'UserGet';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiUsersGet>";
        $data .= "<ApiID>".$apiid."</ApiID>";
        $data .= "<CieID>".$cleid."</CieID>";
        $data .= "<ID>".$adminid."</ID>";
        $data .= "</cApiUsersGet>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url, $apiurl);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"]) {
            throw new Exception("Problem getting user on VIA");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }
        return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"];

    }

    /**
     * validates user
     *
     * @return string via user id
     */
    public function validate_via_user($muser, $viauserid = null, $update = null) {
        global $DB, $CFG, $SITE;

        $info["UserType"] = 2;// Usertype is always 2!
        $info["CompanyName"] = $SITE->shortname;
        $info["PhoneHome"] = $muser->phone1;

        $viauser = $this->via_user_search(strtolower($muser->email), "Email");
        if (!$viauser) {
            $viauser = $this->via_user_search(strtolower($muser->email), "Login");
            if (!$viauser) {
                // False = create new user!
                $i = 1;
                $viauserdata = $this->via_user_create($muser, false, $info);

                if ($viauserdata == 'LOGIN_USED') {
                    $muser->viausername = $user->viausername. '_'. $i++;
                    $viauserdata = $this->via_user_create($muser, false, $info);
                    if (!$viauserdata) {
                        return false;
                    }
                }
            }
        }
        if ($viauser) {// We found a match, but we check if this user was not already associated with another.
            $exists = $DB->get_record('via_users', array('userid' => $muser->id, 'viauserid' => $viauser['UserID']));
            if ($exists) {
                $update = true;
                $viauserid = $exists->userid;
            } else {
                $viauserdata = $viauser;
            }
        }
        if (isset($viauserdata['Status'])) {
            $validatestatus = $viauserdata['Status'];
        } else {
            $validatestatus = $this->via_user_get($muser);
        }

        $muser->viauserid = $viauserdata['UserID'];

        if ($validatestatus != 0) {
            $muser->status = 0;// Change status back to active.
            $viauserdata = $this->user_create($muser, true);
        }

        $participant = new stdClass();

        if ($update) {

            $participant->id = $viauserid;
            $participant->timemodified = time();
            $participant->viauserid = $viauserdata['UserID'];
            $participant->username = $viauserdata['Login'];

            $DB->update_record("via_users", $participant);

        } else {

            $participant->userid = $muser->id;
            $participant->timecreated = time();
            $participant->timemodified = time();
            $participant->viauserid = $viauserdata['UserID'];
            $participant->username = $viauserdata['Login'];
            $participant->usertype = 2;// We only create participants.

            $DB->insert_record("via_users", $participant, true, true);

        }

        return $muser->viauserid;
    }


    /**
     * Gets the Via id of a user. If not found, we create a new user
     *
     * @param integer $u the moodle id of the user
     * @param bool $teacher true if the user is a teacher
     * @return string via user id
     */
    public function get_user_via_id($u, $connection=false, $activityid=false) {
        global $CFG, $DB;
        $info = null;

        $user = $DB->get_record('user', array('id' => $u));

        $muser = $user;
        $viauser = $DB->get_record('via_users', array('userid' => $u));
        if (!$viauser) {
            // The user doesn't exists yet. We need to create it.
            try {
                // We validate if the user already exisits on via with the email as email OR as login.
                // Yes - we associate the user!
                // No  - we create a user!
                $uservalidated = $this->validate_via_user($user);
                return $uservalidated;
            } catch (Exception $e) {
                echo '<div class="alert alert-block alert-info">'.
                get_string('error_user', 'via', $muser->firstname.' '.$muser->lastname).'</div>';
            }
        } else {

            $user->viauserid = $viauser->viauserid;
            $viauserid = $viauser->id;

            if ($connection == true) {// We only synchronize if/when the participant is trying to connect to an activity.
                $viauser = $this->via_user_get($user);

                if ($viauser["Status"] == 0) {// Active.
                    if (get_config('via', 'via_participantsynchronization')) {
                        // Synchronizing info but we not not change the user type.
                        global $SITE;

                        $user->viausername = $viauser["Login"];
                        $user->usertype = $viauser["UserType"];
                        $user->CompanyName = $SITE->shortname;
                        $user->PhoneHome = $user->phone1;
                        $response = $this->via_user_create($user, true);
                    }
                } else {
                    if ($viauser["Status"] == 1) {// Deactivated.
                        $user->status = 0;// Change status back to active!
                        $response = $this->via_user_create($user, true);

                    } else {// Deleted - we go throught the whole process of creating a user
                        // we try to reassosiate him or create a new user and update his viauserid
                        // if there are no other accounts, we create a new via user.
                        try {
                            $uservalidated = $this->validate_via_user($user, $viauserid, 1);
                        } catch (Exception $e) {
                            echo '<div class="alert alert-block alert-info">'.
                            get_string('error_user', 'via', $muser->firstname.' '.$muser->lastname).'</div>';
                        }
                    }

                    // Then we reassociate the user to all activities in which they were associanted with.
                    $viaparticipant = $DB->get_records('via_participants', array('userid' => $user->id));
                    foreach ($viaparticipant as $participant) {
                        if ($participant) {
                            $type = $participant->participanttype;
                        } else {
                            $via = $DB->get_record('via', array('id' => $participant->activityid));
                            $type = get_user_type($user->id, $via->course);
                        }
                        try {
                            via_add_participant($user->id, $participant->activityid, $type, null, null, 1);
                        } catch (Exception $e) {
                            echo '<div class="alert alert-block alert-info">'.
                            get_string('error_user', 'via', $muser->firstname.' '.$muser->lastname).'</div>';
                        }
                    }

                }
            }
            return $user->viauserid;
        }
    }

    /**
     * Sends request to VIA server and treat it's response
     *
     * @param object $via the via object
     * @param string $data the data of the request
     * @param string $url the url (function) to send to the Via server
     * @param string $apiurl the url of the api
     * @return object via server response
     */
    public function send_saop_enveloppe($via, $data, $url, $apiurl=null) {
        global $CFG;

        if (!$apiurl) {
            $apiurl = get_config('via', 'via_apiurl');
        }

        $apiurl .= (substr($apiurl, -1, 1) == "/") ? "" : "/";

        $apiurl .= $url;

        $params = array('http' => array( 'method' => 'POST',
            'header' => 'Content-Type: text/xml; charset=utf-8',
            'content' => $data ));

        if (isset($CFG->proxyhost[0])) {
            if ($CFG->proxyport === '0') {
                $params['http']['proxy'] = $CFG->proxyhost;
            } else {
                $params['http']['proxy'] = $CFG->proxyhost.':'.$CFG->proxyport;
            }
            $params['http']['request_fulluri'] = true;
        }

        $ctx = stream_context_create($params);

        $fp = fopen($apiurl, 'rb', false, $ctx);

        if (!$fp) {
            throw new Exception("URL_ERROR");
        }

        $response['viaresponse'] = stream_get_contents($fp);
        if ($response === false) {
            throw new Exception("Problem reading data from $url, $php_errormsg");
        }
        require_once($CFG->dirroot .'/mod/via/phpxml.php');

        if (!$dataxml = xml_unserialize($response['viaresponse'])) {
            throw new Exception("Problem reading result xml");
        }

        $response['dataxml'] = $dataxml;

        return $response;
    }
}
