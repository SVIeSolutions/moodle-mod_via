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
  * Data access class for the via module.
  *
  * @package    mod
  * @subpackage via
  * @copyright  SVIeSolutions <support@sviesolutions.com>
  * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  *
  */

/** Data access class for the via module. **/
class mod_via_api {
    /**
     * Creates a user on VIA
     *
     * @param object $muser an object user
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
            if (isset($muser->status)) {
                $data .= "<Status>".$muser->status."</Status>";
            }
        }
        // Possibilités de renforcer la synchronisation
        if (!$edit || get_config('via', 'via_participantsynchronization')) {
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
		}
        $data .= "<Login>".$muser->viausername."</Login>";
        if (!$edit) {
            $data .= "<Password>".via_create_user_password()."</Password>";
        }
        $data .= "<UniqueID>".$muser->username."</UniqueID>";
        $data .= "<Email>".strtolower($muser->email)."</Email>";

		if ($infoplus && get_config('via', 'via_participantsynchronization')) {
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
                    throw new Exception($resultdata['Result']['ResultDetail']);
                }
            }
        }

        $resultdata['UserID'] = $resultdata['ID'];

        return $resultdata;
    }

    /**
     * gets a user on Via
     *
     * @param integer $viauserid
     * @return Array containing response from Via
     */
    public function via_user_get($viauserid) {
        global $CFG;

        $url = 'UserGet';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiUsersGet>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ID>".$viauserid."</ID>";
        $data .= "</cApiUsersGet>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe($viauserid, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"]) {
            throw new Exception("Problem getting user on VIA");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }
        return $resultdata;
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
                            $viauserdata = $this->via_user_get($viauser->viauserid);
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
        $data .= "<IsNewVia>".$via->isnewvia."</IsNewVia>"; /* Always 1 for new activities. */
        $data .= "<AudioType>".$via->audiotype."</AudioType>";
        $data .= "<ActivityType>".$via->activitytype."</ActivityType>";
        $data .= "<ShowParticipants>".$via->showparticipants."</ShowParticipants>";
        $data .= "<NeedConfirmation>".$via->needconfirmation."</NeedConfirmation>";
        $data .= "<RecordingMode>".$via->recordingmode."</RecordingMode>";
        $data .= "<RecordModeBehavior>".$via->recordmodebehavior."</RecordModeBehavior>";
        $data .= "<WaitingRoomAccessMode>".$via->waitingroomaccessmode."</WaitingRoomAccessMode>";
        $data .= "<ReminderTime>".'0'."</ReminderTime>";// The reminder email will be sent by Moodle, not VIA!
        if ($via->activitytype != 2) {
            $data .= "<DateBegin>".$this->change_date_format($via->datebegin)."</DateBegin>";
            $data .= "<Duration>".$via->duration."</Duration>";
        } else {
            $data .= "<DateBegin>".'0'."</DateBegin>";
            $data .= "<Duration>".'0'."</Duration>";
        }
        $data .= "<IsH264>".$via->ish264."</IsH264>";
        $data .= "</cApiActivity>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivity"]) {
            throw new Exception("Problem creating VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata['ActivityID'];
    }

    /**
     * Duplicates an existing activity on Via
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
        $data .= "<IncludeUsers>".$via->include_userInfo."</IncludeUsers>";// 0 = No; 1 = Yes!
        $data .= "<IncludeDocuments>1</IncludeDocuments>";// 1 = Yes : documents are always added!
        $data .= "<IncludeSurveyAndWBoards>".$via->include_surveyandwboards."</IncludeSurveyAndWBoards>";// 0 = No; 1 = Yes!
        $data .= "</cApiActivityDuplicate>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivityDuplicate"]) {
            throw new Exception("Problem duplicating VIA activity id");
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
     * @param integer $activitystate if 2, activity needs to be deleted on Via
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
        $data .= "<IsNewVia>".$via->isnewvia."</IsNewVia>";  /* Always 1 for new activities*/
        $data .= "<AudioType>".$via->audiotype."</AudioType>";
        $data .= "<ActivityType>".$via->activitytype."</ActivityType>";
        $data .= "<ShowParticipants>".$via->showparticipants."</ShowParticipants>";
        $data .= "<NeedConfirmation>".$via->needconfirmation."</NeedConfirmation>";
        $data .= "<RecordingMode>".$via->recordingmode."</RecordingMode>";
        $data .= "<RecordModeBehavior>".$via->recordmodebehavior."</RecordModeBehavior>";
        $data .= "<WaitingRoomAccessMode>".$via->waitingroomaccessmode."</WaitingRoomAccessMode>";
        $data .= "<ReminderTime>0</ReminderTime>";// The reminder email will be sent by Moodle.
        if ($via->activitytype != 2) {// If the activity isn't permanent.
            $data .= "<DateBegin>".$this->change_date_format($via->datebegin)."</DateBegin>";
            $data .= "<Duration>".$via->duration."</Duration>";
        }
        $data .= "<IsH264>".$via->ish264."</IsH264>";
        $data .= "</cApiActivity>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivity"]) {
            throw new Exception("Problem editing VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata;
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

        return $resultdata;
    }

    /**
     * Deletes an acitivity on Via
     *
     * @param object $viaactivityid
     * @return Array containing response from Via
     */
    public function via_activity_delete($viaactivityid) {
        global $CFG;

        $url = 'ActivityDelete';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiActivityDelete>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>".get_config('via', 'via_adminid')."</UserID>";
        $data .= "<ActivityID>".$viaactivityid."</ActivityID>";
        $data .= "</cApiActivityDelete>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe($viaactivityid, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivityDelete"]) {
            throw new Exception("Problem deleting activity on Via");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            return $resultdata['Result']['ResultDetail'];
        }

        return $resultdata;
    }

    /**
     * gets an acitivity on Via
     *
     * @param object $via the via object
     * @return Array containing response from Via
     */
    public function activitytemplate_get() {
        global $CFG;

        $url = 'GetCieActivityTemplate';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiActivityGet>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>".get_config('via', 'via_adminid')."</UserID>";
        $data .= "</cApiActivityGet>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivity"]) {
            throw new Exception("Problem reading getting VIA activity id");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            return $resultdata['Result']['ResultDetail'];
        }

        return $resultdata;
    }

    /**
     * Gets all categories for this company from Via
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

        return $resultdata["CategoriesList"];
    }

    /**
     * gets user logs for one user at a time
     *
     * @param integer $viauserid the VIA via id
     * @param integer $viaactivityid the VIA via id
     * @return Array containing response from Via
     */
    public function via_get_user_logs($viauserid, $viaactivityid) {
        global $CFG;

        $url = 'UserGetLogs';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiGetUserLogs>";
        $data .= "<ID>".$viauserid."</ID>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ActivityID>".$viaactivityid."</ActivityID>";
        $data .= "</cApiGetUserLogs>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe($viaactivityid, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserLogs"]) {
            throw new Exception("Problem getting user logs for VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata;
    }

    /**
     * Gets all profiles available for a given company
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
            throw new Exception("Problem getting list of profils for VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata["ProfilList"];
    }

    /**
     * Gets all profiles available for a given company
     *
     * @return Array containing response from Via
     */
    public function cieinfo() {
        global $CFG;

        $url = 'GetCieInfo';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiCieInfo>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "</cApiCieInfo>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiCieInfo"]) {
            throw new Exception("Problem getting cie info for via version restrictions");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata;
    }

    /**
     * Gets a token to redirect a user to VIA
     *
     * @param object $via the via
     * @param integer $redirect where to redirect
     * @param string $playback id of the playback to redirect to
     * @param boolean $forceaccess permits those with editing rights in moodle to view recording taht are not public
     * @param boolean $forceedit permits those with editing rights in moodle to edit recording
     * @param integer $mobile is userid already validated by uapi/auth
     * @return string URL for redirect
     */
    public function userget_ssotoken($via=null, $redirect=null, $playback=null, $forceaccess=null, $forceedit=null, $mobile=null) {
        global $CFG, $USER;

        if (!$mobile) {
            $muser = $USER->id;
        } else {
            $muser = $mobile;
        }

        if (!$userid = $this->get_user_via_id($muser, true)) {
            return false;
        }

        $url = 'UserGetSSOToken';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiGetUserToken>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ID>" . $userid . "</ID>";
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

        if (!$userid = $this->get_user_via_id($via->userid)) {
            return false;
        }

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiUserActivity_AddUser>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>" . $userid . "</UserID>";
        $data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
        $data .= "<ParticipantType>".$via->participanttype."</ParticipantType>";
        $data .= "<ConfirmationStatus>".$via->confirmationstatus."</ConfirmationStatus>";
        $data .= "</cApiUserActivity_AddUser>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivity_AddUser"]) {
            throw new Exception("Problem adding user to VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata;
    }

    /**
     * Edits a user enrolment in an activity
     *
     * @param object $via the via
     * @param integer $participanttype; 1=pariticipant, 2=host , 3=animator
     * @return Array containing response from Via
     */
    public function edituser_activity($via, $participanttype = null) {
        global $CFG;

        $url = 'EditUserActivity';

        if (!$userid = $this->get_user_via_id($via->userid)) {
            return false;
        }

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiUserActivity_AddUser>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>" . $userid . "</UserID>";
        $data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
        if ($participanttype) {
            $data .= "<ParticipantType>".$participanttype."</ParticipantType>";
        }
        $data .= "<ConfirmationStatus>".$via->confirmationstatus."</ConfirmationStatus>";
        $data .= "</cApiUserActivity_AddUser>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivity_AddUser"]) {
            throw new Exception("Problem editing user in VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata;
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

        if (!$userid = $this->get_user_via_id($via->userid)) {
            return false;
        }

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiUserActivityGet>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<UserID>".$userid."</UserID>";
        $data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
        $data .= "</cApiUserActivityGet>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($via, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivityGet"]) {
            throw new Exception("Problem getting user VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata;
    }

    /**
     * Remove a user in an activity
     *
     * @param string $viaid the VIA via id
     * @param integer $userid the id of the user to remove
     * @param boolean $moodleid used to remove the moodle admin key once the activity is created.
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
            if (!$muserid = $this->get_user_via_id($userid)) {
                return false;
            }
            $data .= "<UserID>".$muserid."</UserID>";
        }
        $data .= "<ActivityID>".$viaid."</ActivityID>";

        $data .= "</cApiUserActivity_RemoveUser>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";

        $response = $this->send_saop_enveloppe($viaid, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivity_RemoveUser"]) {
            throw new Exception("Problem removing user from VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata;
    }

    /**
     * Gets a list of downloadable files available for a given activity
     *
     * @param object $via the via object
     * @return Array containing response from Via
     */
    public function list_downloadablefiles($via) {
        global $CFG, $USER;

        if (!$userid = $this->get_user_via_id($USER->id)) {
            return false;
        }

        $url = 'GetDocumentList';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiDocumentList>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
        $data .= "<UserID>".$userid."</UserID>";
        $data .= "<OnlyDownloadableDocument>1</OnlyDownloadableDocument>";
        $data .= "</cApiDocumentList>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiDocumentList"]) {
            throw new Exception("Problem getting list of playbacks for VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata["DocumentList"];
    }

    /**
     * Gets a list of playback available for a given activity
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
        if ($via->playbacksync) {
            // Minus 5 minutes just in case!
            $data .= "<DateFrom>".$this->change_date_format($via->playbacksync - 300)."</DateFrom>";
        }
        $data .= "</cApiListPlayback>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiListPlayback"]) {
            throw new Exception("Problem getting list of playbacks for VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata["PlaybackList"];
    }

    /**
     * Edit a given playback for a given activity
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
        $data .= "<Playback PlaybackID='".$playbackid."' Title='".$title."'
                            IsPublic='".$playback->accesstype."'
                            IsDownloadable='".$playback->isdownloadable."'/>";
        $data .= "</PlaybackList>";
        $data .= "</cApiListPlayback>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiListPlayback"]) {
            throw new Exception("Problem editing playback for VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata["PlaybackList"];
    }

    /**
     * delete a given playback for a given activity
     *
     * @param string $viaactivityid the id of the via activity
     * @param string $playbackid the id of the playback
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
            throw new Exception("Problem deleteing playback for VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata;
    }

    /**
     * download playback for a given activity
     *
     * @param string $viauserid the via userid
     * @param string $playbackid the id of the playback
     * @param integer $recordtype; 1=fullvideo, 2=mobile video, 3=audio only.
     * @return string with download token for redirect
     */
    public function via_download_record($viauserid, $playbackid, $recordtype) {
        global $CFG;

        $url = 'RecordDownload';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiRecordDownload>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<ID>".$viauserid."</ID>";
        $data .= "<RecordType>".$recordtype."</RecordType>";
        $data .= "<PlaybackID>".$playbackid."</PlaybackID>";
        $data .= "</cApiRecordDownload>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiRecordDownload"]) {
            throw new Exception("Problem downloading playback for VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata;
    }

    /**
     * download document for a given activity
     *
     * @param string $viauserid the via userid
     * @param string $playbackid the id of the playback
     * @param integer $recordtype; 1=fullvideo, 2=mobile video, 3=audio only.
     * @return string with download token for redirect
     */
    public function via_download_document($via, $viauserid, $fileid) {
        global $CFG;

        $url = 'DocumentDownload';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiDocumentDownload>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<UserID>".$viauserid."</UserID>";
        $data .= "<FileID>".$fileid."</FileID>";
        $data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
        $data .= "</cApiDocumentDownload>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiDocumentDownload"]) {
            throw new Exception("Problem downloading document for VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata;
    }

    /**
     * Get downloaded notices, an email is sent when a recording download is ready
     *
     * @param timestamp ($lastcron)
     * @return list of playbacks with recordings ready for download
     */
    public function get_notices($lastcron) {
        global $CFG;

        $url = 'GetLatestExports';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiGetLatestExports>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<DateFrom>".$this->change_date_format($lastcron)."</DateFrom>";
        $data .= "</cApiGetLatestExports>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiGetLatestExports"]) {
            throw new Exception("Problem getting download notices for VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata;
    }

    /**
     * Get activity notification when a user enteres an activity with a waiting room
     *
     * @param timestamp ($lastcron)
     * @return list of notifications
     */
    public function get_activity_notifications($lastcron) {
        global $CFG;

        $url = 'GetLatestSessionNotifications';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiGetLatestSessionNotification>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<DateFrom>".$this->change_date_format($lastcron)."</DateFrom>";
        $data .= "</cApiGetLatestSessionNotification>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiGetLatestSessionNotification"]) {
            throw new Exception("Problem getting activity notifications for VIA activity");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        return $resultdata;
    }

    /**
     * Get a list of the playbacks added after a certain date.
     *
     * @param timestamp ($lastcron)
     * @return list of notifications
     */
    public function get_latest_added_playbacks($fromdate, $playbackarray = null) {
        global $CFG;

        static $resultdata;

        $url = 'PlaybackSearch';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiPlaybackSearch>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<DateFrom>".$this->change_date_format($fromdate)."</DateFrom>";
        $data .= "<DateTo>".$this->change_date_format(time())."</DateTo>";
        $data .= "</cApiPlaybackSearch>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe(null, $data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiPlaybackSearch"]) {
            throw new Exception("Problem getting new playbacks for VIA assign");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        if ($playbackarray) {
            if ($resultdata['PlaybackSearch']['PlaybackMatch']) {
                $newplaybackarray = array_filter($resultdata['PlaybackSearch']['PlaybackMatch']);
            } else {
                $newplaybackarray = array($resultdata['PlaybackSearch']['PlaybackMatch attr']);
                unset($resultdata['PlaybackSearch']['PlaybackMatch attr']);
            }
            if ($newplaybackarray) {
                $temparray = array_merge(array_values($playbackarray), $newplaybackarray);
                $resultdata['PlaybackSearch']['PlaybackMatch'] = array_unique($temparray, SORT_REGULAR);
            } else {
                $resultdata['PlaybackSearch']['PlaybackMatch'] = array_unique($playbackarray, SORT_REGULAR);
            }
        }

        if ($resultdata['Result']["ResultDetail"]) {
            $playbacks = array_filter($resultdata['PlaybackSearch']['PlaybackMatch']);
            $lastrecording = end($playbacks);
            return $this->get_latest_added_playbacks(strtotime($lastrecording["DateAdded"]), $playbacks);
        } else {
            return $resultdata;
        }
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

        return $resultdata;
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
        return $resultdata;
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
        return $resultdata;
    }

    /**
     * validates user to see if the uesr already exists on Via
     * If the user exists we associate him/her
     * If the user does not exist we create him/her
     *
     * @param object $muser moodle user
     * @return string via user id
     */
    public function validate_via_user($muser) {
        global $DB, $CFG, $SITE;

        $viauser = $this->via_user_search(strtolower($muser->email), "Email");
        if (!$viauser) {
            $viauser = $this->via_user_search(strtolower($muser->email), "Login");
            if (!$viauser) {
                // False = create new user!
                // $context = context_system::instance();
                if ( get_config('via', 'via_typepInscription')) {// || !has_capability('moodle/site:config',$context,$muser)) {.
                    $info["UserType"] = get_config('via', 'via_typepInscription'); // Usertype is always 2!
                } else { // si c'est un admin et qu'on a choisi un autre rôle par défaut que Member.
                        $info["UserType"] = 2;
                }
                $companyname = str_replace('<,>', '', $SITE->shortname);
                $info["CompanyName"] = str_replace('&', get_string('and', 'via'), $companyname);
                $info["PhoneHome"] = $muser->phone1;
                $usericon = $this->via_get_user_picture($muser->id);
                $info["ImageData"] = base64_encode($usericon);

                $i = 1;
                $viauserdata = $this->via_user_create($muser, false, $info);

                while ($viauserdata == 'LOGIN_USED') {
                    $muser->viausername = $muser->viausername. '_'. $i++;
                    $viauserdata = $this->via_user_create($muser, false, $info);
                    if (!$viauserdata) {
                        return false;
                    }
                }
                $validatestatus = $viauserdata['Status'];
                $viauserid = $viauserdata['UserID'];
                $setupstatus = $viauserdata['SetupState'];
                $login = $viauserdata['Login'];
            }
        }
        if ($viauser) {
            $validatestatus = $viauser['Status'];
            $viauserid = $viauser['UserID'];
            $setupstatus = null;
            $login = $viauser['Login'];
        }

        // We found a match, but we check if this user was not already associated with another.
        $update = false;
        $exists = $DB->get_record('via_users', array('userid' => $muser->id));
        if ($exists) {
            $userid = $exists->userid;
            $update = true;
        }

        if (!isset($validatestatus)) {
            $revalidatestatus = $this->via_user_get($muser->viauserid);
            $validatestatus = $revalidatestatus['Status'];
        }
        if ($validatestatus != 0) {
            $muser->status = 0;// Change status back to active.
            $viauserdata = $this->via_user_create($muser, true);
        }

        $participant = new stdClass();
        $participant->userid = $muser->id;
        $participant->viauserid = $viauserid;
        $participant->setupstatus = $setupstatus;

        $participant->timemodified = time();

        if ($update) {
            $participant->id = $exists->id;

            $DB->update_record("via_users", $participant);
        } else {
            $participant->timecreated = time();
            $participant->usertype = 2;// We only create participants.
            $participant->username = $login;

            $DB->insert_record("via_users", $participant, true, true);
        }
        return $participant->viauserid;
    }

    /**
     * Gets the moodle usericon to be added on Via;
     * Only if the option to synchronise user information has been checked in the plugins parameters
     *
     * @param string $muserid the userid
     * @return image file
     */
    public function via_get_user_picture($muserid) {
        global $DB, $CFG;

        $usericon = "";

        $usercontext = context_user::instance($muserid);
        if ($usercontext) {
            $file = $DB->get_record('files', array('contextid' => $usercontext->id,
                'component' => 'user',
                'filearea' => 'icon',
                'itemid' => '0',
                'filename' => 'f3.png'));
            // Prise en compte des jpg.
            if (!$file) {
                $file = $DB->get_record('files', array('contextid' => $usercontext->id,
                    'component' => 'user',
                    'filearea' => 'icon',
                    'itemid' => '0',
                    'filename' => 'f3.jpg'));
            }

            if ($file) {
                $usericon = file_get_contents($CFG->dataroot .'/filedir/'.substr($file->contenthash, 0, 2) . '/'
                    . substr($file->contenthash, 2, 2) .'/' .$file->contenthash);
            }
        }

        return $usericon;
    }

    /**
     * Gets the Via id of a user. If not found, we create a new user
     *
     * @param integer $u the moodle id of the user
     * @param bool $connection true if the this is function is called on ssotoken user validation
     * @param bool $forceupdate user information is only updated after 30 minutes unless we force the update.
     * @return string via user id
     */
    public function get_user_via_id($u, $connection=false, $forceupdate = null) {
        global $CFG, $DB;
        $info = null;

        $muser = $DB->get_record('user', array('id' => $u));
        $viauser = $DB->get_record('via_users', array('userid' => $u));
        if (!$viauser) {
            // The user doesn't exists yet. We need to create it.
            try {
                // We validate if the user already exisits on via with the email as email OR as login.
                // Yes - we associate the user!
                // No  - we create a user!
                $uservalidated = $this->validate_via_user($muser);
                return $uservalidated;
            } catch (Exception $e) {
                return false;
            }
        } else {
            $muser->viauserid = $viauser->viauserid;

            if ($connection == true || $viauser->timemodified < (time() - (30 * 60)) || $forceupdate) {
                // We only synchronize if/when the participant is trying to connect to an activity.
                $viau = $this->via_user_get($muser->viauserid);
                // These values should never change!
                $muser->viausername = $viau["Login"];
                $muser->usertype = $viau["UserType"];

                if ($viau["Status"] == 0 || $viau["Status"] == 1) {
                    // 0 = Active, 1= Deactivates, we need to reactive!
                    if ($viau["Status"] == 1) {
                        $muser->status = 0;
                    }

                    if (get_config('via', 'via_participantsynchronization')) {
                        // Synchronizing info, but we not not change the user type.
                        global $SITE;

                        $info["CompanyName"] = $SITE->shortname;
                        $info["PhoneHome"] = $muser->phone1;
                        $usericon = $this->via_get_user_picture($muser->id);
                        $info["ImageData"] = base64_encode($usericon);

                        $response = $this->via_user_create($muser, true, $info);
                    } else {
                        $response = $this->via_user_create($muser, true);
                    }

                    $DB->set_field('via_users', 'setupstatus', $response['SetupState'],
                                    array('userid' => $muser->id, 'viauserid' => $muser->viauserid));
                } else {
                    // Deleted - we go throught the whole process of creating a user
                    // we try to reassosiate him or create a new user and update his viauserid
                    // if there are no other accounts, we create a new via user.
                    $muser->viausername = null;
                    try {
                        $uservalidated = $this->validate_via_user($muser);
                        $muser->viauserid = $uservalidated;
                        if ($uservalidated) {
                            // Then we reassociate the user to all activities in which they were associanted with.
                            $viaparticipant = $DB->get_records('via_participants', array('userid' => $muser->id));
                            foreach ($viaparticipant as $participant) {
                                // We update all activities in which the user was enrolled, synched or not.
                                try {
                                    via_add_participant($muser->id, $participant->activityid,
                                    $participant->participanttype, true);
                                } catch (Exception $e) {
                                    return false;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        return false;
                    }
                }
                if (isset($response) || isset($uservalidated)) {
                    // We update the field timemodified, so as to avoid calls to Via if the user has just been validated.
                    $DB->set_field('via_users', 'timemodified', time(),
                                    array('userid' => $muser->id, 'viauserid' => $muser->viauserid));
                }
            }
            return $muser->viauserid;
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

        $headers = array("Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Content-length: ".strlen($data),
            );

        // Setting the curl parameters.
        $soap = curl_init();
        curl_setopt($soap, CURLOPT_URL, $apiurl);
        curl_setopt($soap, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($soap, CURLOPT_POSTFIELDS, $data);
        curl_setopt($soap, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($soap, CURLOPT_TIMEOUT,        60);
        curl_setopt($soap, CURLOPT_RETURNTRANSFER, true );
        curl_setopt($soap, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($soap, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($soap, CURLOPT_POST,           true );
        if (!empty($CFG->proxyhost) && !is_proxybypass($CFG->proxyhost)) {
            if ($CFG->proxyport === '0') {
                curl_setopt($soap, CURLOPT_PROXY, $CFG->proxyhost);
            } else {
                curl_setopt($soap, CURLOPT_PROXY, $CFG->proxyhost.':'.$CFG->proxyport);
            }
        }

        $fp = curl_exec($soap);
        curl_close($soap);

        if (!$fp) {
            throw new Exception("URL_ERROR");
        }

        require_once($CFG->dirroot .'/mod/via/phpxml.php');

        if (!$dataxml = xml_unserialize($fp)) {
            throw new Exception("Problem reading result xml");
        }

        $response['dataxml'] = $dataxml;

        return $response;
    }
}