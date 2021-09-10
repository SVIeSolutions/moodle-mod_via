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
    public function via_user_create($muser, $edit=false, $infoplus=null, $viauser=null) {
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
        // Possibilités de renforcer la synchronisation.
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
        } else if (isset($viauser)) {
            $data .= "<LastName>".$viauser["LastName"]."</LastName>";
            $data .= "<FirstName>".$viauser["FirstName"]."</FirstName>";
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
        } else if (isset($viauser)) {
            $data .= "<PhoneHome>". $viauser["PhoneHome"]."</PhoneHome>";
            $data .= "<PhoneBus>". $viauser["PhoneBus"]."</PhoneBus>";
            $data .= "<PhoneCel>". $viauser["PhoneCel"]."</PhoneCel>";
            $data .= "<CompanyName>". $viauser["CompanyName"]."</CompanyName>";
            $data .= "<FunctionTitle>". $viauser["FunctionTitle"]."</FunctionTitle>";
            $data .= "<Gender>". $viauser["Gender"]."</Gender>";
            $data .= "<ImageData>". $viauser["ImageData"]."</ImageData>";
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

        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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

        $response = $this->send_saop_enveloppe($data, $url);

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
        if (isset($via->category) && $via->category != '0') {
            $data .= "<CategoryID>".$via->category."</CategoryID>";
        } else {
            $data .= "<CategoryID>0</CategoryID>";
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

        $response = $this->send_saop_enveloppe($data, $url);

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

        $response = $this->send_saop_enveloppe($data, $url);

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
        if (isset( $via->category) && $via->category != '0') {
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

        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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

        $response = $this->send_saop_enveloppe($data, $url);

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

        $response = $this->send_saop_enveloppe($data, $url);

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

        $response = $this->send_saop_enveloppe($data, $url);

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

        $response = $this->send_saop_enveloppe($data, $url);

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

        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe( $data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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
        $response = $this->send_saop_enveloppe($data, $url);

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

        $response = $this->send_saop_enveloppe($data, $url, $apiurl);

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
        $response = $this->send_saop_enveloppe($data, $url, $apiurl);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"]) {
            throw new Exception("Problem getting user on VIA");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }
        return $resultdata;
    }

    /**
     * validates user to see if the user already exists on Via
     * If the user exists we associate him/her
     * If the user does not exist we create him/her
     *
     * @param object $muser moodle user
     * @param boolean $ishtml5
     * @return string via user id
     */
    public function validate_via_user($muser, $ishtml5 = false) {
        global $DB, $CFG, $SITE;

        if ($ishtml5) {
            $viauser = $this->viahtml5_user_search(strtolower($muser->email), "Email");
        } else {
            $viauser = $this->via_user_search(strtolower($muser->email), "Email");
        }
        if (!$viauser) {
            if ($ishtml5) {
                $viauser = $this->viahtml5_user_search(strtolower($muser->email), "Login");
            } else {
                $viauser = $this->via_user_search(strtolower($muser->email), "Login");
            }
            if (!$viauser) {
                // False = create new user!
                if ( get_config('via', 'via_typepInscription')) {
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
                if ($ishtml5) {
                    $viauserdata = $this->via_user_create_html5($muser, false, $info);
                } else {
                    $viauserdata = $this->via_user_create($muser, false, $info);
                }

                 // Cas CREATE_USER_ERROR_OR_EXISTS : Erreur chez l'UEB à cause de login tronqué.
                 // Limite i = 10 arbitraire pour éviter des boucles infinies.
                while ($i < 10 && ($viauserdata == 'LOGIN_USED' || $viauserdata == 'CREATE_USER_ERROR_OR_EXISTS')) {
                    $muser->viausername = $muser->viausername. '_'. $i++;
                    if ($ishtml5) {
                        $viauserdata = $this->via_user_create_html5($muser, false, $info);
                    } else {
                        $viauserdata = $this->via_user_create($muser, false, $info);
                    }
                    if (!$viauserdata) {
                        return false;
                    }
                }

                $validatestatus = ($ishtml5 ? 0 : $viauserdata['Status']); // Pas de gestion des désactivés avec ViaHTML5.
                $viauserid = ($ishtml5 ? $viauserdata['id'] : $viauserdata['UserID']);
                $setupstatus = ($ishtml5 ? null : $viauserdata['SetupState']);
                $login = ($ishtml5 ? $muser->viausername : $viauserdata['Login']);
            }
        }
        if ($viauser) {
            $validatestatus = ($ishtml5 ? 0 : $viauser['Status']); // Pas de gestion des désactivés avec ViaHTML5.
            $viauserid = ($ishtml5 ? $viauser['id'] : $viauser['UserID']);
            $setupstatus = null;
            $login = ($ishtml5 ? $viauser['login'] : $viauser['Login']);
        }

        // We found a match, but we check if this user was not already associated with another.
        $update = false;
        $exists = $DB->get_record('via_users', array('userid' => $muser->id));
        if ($exists) {
            $userid = $exists->userid;
            $update = true;
        }

        if (!isset($validatestatus) && !$ishtml5) {
            $revalidatestatus = $this->via_user_get($muser->viauserid);
            $validatestatus = $revalidatestatus['Status'];
        }
        if ($validatestatus != 0) {
            $muser->status = 0;// Change status back to active.
            if (!get_config('via', 'via_participantsynchronization')) {
                $viauser = new stdClass();
                // Need to get the lastname and firstNAme of the Via User.
                $viauser = $this->via_user_get($muser->viauserid);
                if ($this->via_user_changed($viauser, $muser)) {
                    if ($ishtml5) {
                        $viauserdata = $this->via_user_create_html5($muser, true, null, $viauser);
                    } else {
                        $viauserdata = $this->via_user_create($muser, true, null, $viauser);
                    }
                }
            } else {
                if ($ishtml5) {
                    $viauserdata = $this->via_user_create_html5($muser, true);
                } else {
                    $viauserdata = $this->via_user_create($muser, true);
                }
            }
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
     * @param bool $ishtml5 use new API for HTML5
     * @return string via user id
     */
    public function get_user_via_id($u, $connection=false, $forceupdate = null, $ishtml5 = false) {
        global $CFG, $DB;
        $info = null;

        $muser = $DB->get_record('user', array('id' => $u));
        $moodleviauser = $DB->get_record('via_users', array('userid' => $u));
        if (!$moodleviauser) {
            // The user doesn't exists yet. We need to create it.
            try {
                // We validate if the user already exists on via with the email as email OR as login.
                // Yes - we associate the user!
                // No  - we create a user!
                $uservalidated = $this->validate_via_user($muser, $ishtml5);
                return $uservalidated;
            } catch (Exception $e) {
                // Revoir éventuellement la gestion de l'erreur ici.
                return false;
            }
        } else {
            $muser->viauserid = $moodleviauser->viauserid;

            if ($connection == true || $forceupdate) {
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
                        // Synchronizing info, but we do not change the user type.
                        global $SITE;

                        $info["CompanyName"] = $SITE->shortname;
                        $info["PhoneHome"] = $muser->phone1;
                        $usericon = $this->via_get_user_picture($muser->id);
                        $info["ImageData"] = base64_encode($usericon);
                        $info["PhoneBus"] = $viau["PhoneBus"];
                        $info["PhoneCel"] = $viau["PhoneCel"];
                        $info["FunctionTitle"] = $viau["FunctionTitle"];
                        $info["Gender"] = $viau["Gender"];

                        if ($ishtml5) {
                            $response = $this->via_user_create_html5($muser, true, $info, null, $moodleviauser->branchid);
                        } else {
                            $response = $this->via_user_create($muser, true, $info);
                        }
                    } else {
                        $viauser = new stdClass();
                        $viauser = $this->via_user_get($muser->viauserid);

                        // Ajouter des validations pour éviter les doubles appels.
                        if ($this->via_user_changed($viauser, $muser)) {
                            if ($ishtml5) {
                                $response = $this->via_user_create_html5($muser, true, $info, null, $moodleviauser->branchid);
                            } else {
                                $response = $this->via_user_create($muser, true, $info);
                            }
                        }
                    }
                    if (!$ishtml5) {
                        if (isset($response)) {
                            $DB->set_field('via_users', 'setupstatus', $response['SetupState'],
                                array('userid' => $muser->id, 'viauserid' => $muser->viauserid));
                        } else if (isset($viauser)) {
                            $DB->set_field('via_users', 'setupstatus', $viauser['SetupState'],
                                array('userid' => $muser->id, 'viauserid' => $muser->viauserid));
                        }
                    }
                } else {
                    // Deleted - we go throught the whole process of creating a user
                    // we try to reassociate him or create a new user and update his viauserid
                    // if there are no other accounts, we create a new via user.
                    $muser->viausername = null;
                    try {
                        $uservalidated = $this->validate_via_user($muser, $ishtml5);
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
     * @param string $data the data of the request
     * @param string $url the url (function) to send to the Via server
     * @param string $apiurl the url of the api
     * @return object via server response
     */
    public function send_saop_enveloppe($data, $url, $apiurl=null) {
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

    /**
     *  Get a list of activity depending on some criteria.
     *
     * @param timestamp ($lastcron)
     * @return list of notifications
     */
    public function get_activity_search($fromdate, $endate = null) {
        global $CFG;

        static $resultdata;

        $url = 'ActivitySearch';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiActivitySearch>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<DateFrom>".$this->change_date_format($endate)."</DateFrom>";
        $data .= "<DateTo>".$this->change_date_format(time())."</DateTo>";
        $data .= "<ActivityType></ ActivityType >";
        $data .= "<UserID></ UserID >";

        $data .= "</cApiActivitySearch>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe($data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivitySearch"]) {
            throw new Exception("Problem getting activity between these date");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        if ($resultdata['Result']["ResultDetail"]) {
            return  $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivitySearch"]["ActivitySearch"];
        } else {
            return $resultdata;
        }
    }
    /**
     * Get a list of activity depending on some criteria.
     *
     * @param timestamp ($lastcron)
     * @return list of notifications
     */
    public function get_activity_nbconnectedusers($activityid, $userid) {
        global $CFG;

        static $resultdata;

        $url = 'ActivityGet';

        $data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $data .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
        $data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $data .= '<soap:Body>';
        $data .= "<cApiActivityGet>";
        $data .= "<CieID>".get_config('via', 'via_cleid')."</CieID>";
        $data .= "<ApiID>".get_config('via', 'via_apiid')."</ApiID>";
        $data .= "<ActivityID>".$activityid."</ActivityID>";
        $data .= "<UserID>".$userid."</UserID>";

        $data .= "</cApiActivityGet>";
        $data .= "</soap:Body>";
        $data .= "</soap:Envelope>";
        $response = $this->send_saop_enveloppe($data, $url);

        if (!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivity"]) {
            throw new Exception("Problem getting activity between these date");
        }

        if ($resultdata['Result']['ResultState'] == "ERROR") {
            throw new Exception($resultdata['Result']['ResultDetail']);
        }

        if ($resultdata['Result']['ResultState'] == "SUCCESS") {
            return  $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivity"]["NbConnectedUsers"];
        } else {
            return 0;
        }
    }

    /**
     * Check if Moodle User is the same as the via user
     *
     * @param $viauser (user from Via)
     * @param $muser (user from Moodle)
     * @return boolean: are the users exactly the same
     */
    protected function via_user_changed ($viauser, $muser) {
        $alike = true;

        $alike = $alike && $muser->lastname == $viauser["LastName"];
        $alike = $alike && $muser->firstname == $viauser["FirstName"];
        $alike = $alike && $muser->email == $viauser["Email"];
        $alike = $alike && $muser->usertype = $viauser["UserType"];

        return !$alike;
    }

    // Section Via HTML5.
    /**
     * Sends JSON request to VIA server (new API) and treat it's response
     *
     * @param string $data the data of the request
     * @param string $url the url (function) to send to the Via server
     * @param string $apiurl the url of the api
     * @param string $apiurl the url of the api
     * @return object via server response
     */
    public function send_soap_enveloppe_json($data, $url, $apiurl=null, $apiid=null) {
        global $CFG;

        if (!$apiurl) {
            $apiurl = get_config('via', 'via_apiurlhtml5');
        }
        if (!$apiid) {
            $apiid = get_config('via', 'via_apiidhtml5');
        }

        $apiurl .= (substr($apiurl, -1, 1) != "/") ? "/" : "";

        $apiurl .= $url;

        $headers = array("Content-type: text/json;charset=\"utf-8\"",
            "Accept: text/json",
            "Content-length: ".strlen($data),
            "ApiID :".$apiid
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
        if ($fp == "[]") {
            $response['datajson'] = "[]";
            return $response;
        }

        if (!$datajson = json_decode($fp, true)) {
            throw new Exception("Problem reading result json");
        }

        $response['datajson'] = $datajson;

        return $response;
    }


    /**
     * Tests the given informations for connection to Via server
     *
     * @param string $apiurl the url
     * @param string $apiid th api id
     * @return Array containing response from Via
     */
    public function testconnectionhtml5($apiurl, $apiid) {

        $url = 'client/test';

        $response = $this->send_soap_enveloppe_json("", $url, $apiurl, $apiid);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Failed to connect to server");
        }

        $this->check_api_error($resultdata);

        return $resultdata;
    }

    /**
     * Creates a new activity on Via
     *
     * @param object $via the via object
     * @param string $presenterid the activity presenter ID
     * @param integer $language language of the title (French by default)
     * @return Array containing response from Via
     */
    public function activity_create_html5($via, $presenterid, $language = 1) {
        $url = "activity/create";

        $data = "{'title': {
                    'texts': [
                        {
                            'text': '". $this->convert_text_json($via->name) ."',
                            'languageId': ".$language."
                        }
                    ]
                },
                'presenterID' : '".$presenterid."',
                'isPublicAccess': false,";
        $data .= " 'playbackAccessType' : ".$via->isreplayallowed.",";
        $data .= " 'recordingModeBehavior' : ".$via->recordmodebehavior;

        if ($via->activitytype == 1) {
            $data .= ", 'startDate': '".$this->change_date_format($via->datebegin)."',
                        'duration': ".$via->duration;
        }

        if (get_config('via', 'lara_branch') != null && get_config('via', 'lara_branch') != '') {
            $data .= ", 'branches': ['".get_config('via', 'lara_branch')."']";
            $via->branchid = get_config('via', 'lara_branch');
        }

        $data .= "}";

        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem creating VIA HTML5 activity");
        }

        $this->check_api_error($resultdata);

        return $resultdata["id"];
    }

    // State 1 = Active, 2 = Delete, 3 = Deactivate.
    public function activity_edit_html5($via, $activitystate=1, $language = 1) {
        $editmode = $activitystate != 2;

        $data = "{'id' : '" . $via->viaactivityid . "'";
        if (!$editmode) {
            $url = "activity/delete";
        } else {
            $url = "activity/edit";
            $data .= ", 'title': {
                    'texts': [
                        {
                            'text': '". $this->convert_text_json($via->name) ."',
                            'languageId': ".$language."
                        }
                    ]
                },
                'isPublicAccess': false,
                'state' : ". $activitystate.",";
            $data .= " 'playbackAccessType' : ".$via->isreplayallowed.",";
            $data .= " 'recordingModeBehavior' : ".$via->recordmodebehavior;

            if ($via->activitytype == 1) {
                $data .= ", 'startDate': '".$this->change_date_format($via->datebegin)."',
                        'duration': ".$via->duration;
            }
        }
        $data .= "}";

        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            if (!$editmode) {
                throw new Exception("Problem deleting VIA HTML5 activity");
            } else {
                throw new Exception("Problem editing VIA HTML5 activity");
            }
        }

        $this->check_api_error($resultdata);

        return $resultdata["id"];
    }

    /**
     * Create a user on Via with html5 API
     *
     * @param object $muser an object user
     * @param bool $edit if true, edit an existing user, else create a new one
     * @param Array $infoplus additional info when creating/editing user
     * @return Array containing response from Via
     *
     */
    public function via_user_create_html5($muser, $edit=false, $infoplus=null, $viauser=null, $userbranchid = null) {
        global $CFG;

        require_once($CFG->dirroot . '/mod/via/lib.php');

        if ($edit) {
            // We are editing user.
            $url = 'user/edit';
        } else {
            // We are creating a new user.
            $url = 'user/create';
        }
        if (!isset($muser->viausername)) {
            $muser->viausername = strtolower($muser->email);
        }

        $data = "{";

        if ($edit) {
            $data .= "'id' : '".$muser->viauserid."',";
        }

        // Possibilités de renforcer la synchronisation.
        if (!$edit || get_config('via', 'via_participantsynchronization')) {
            if ($muser->lastname) {
                $data .= "'lastName' : '".$this->convert_text_json($muser->lastname)."',";
            } else {
                $data .= "'lastName' : 'Utilisateur',";
            }

            if ($muser->firstname) {
                $data .= "'firstName' : '".$this->convert_text_json($muser->firstname)."',";
            } else {
                $data .= "'firstName' : 'Temporaire',";
            }
        } else if (isset($viauser)) {
            $data .= "'lastName' : '". $this->convert_text_json($viauser["LastName"]) ."',";
            $data .= "'firstName' : '". $this->convert_text_json($viauser["FirstName"]) ."',";
        }

        $data .= "'login' : '".$muser->viausername."',";
        if (!$edit) {
            $data .= "'password' : '".via_create_user_password()."',";
        }
        $data .= "'email' : '".strtolower($muser->email)."',";
        if ($edit) {
            if (get_config('via', 'lara_branch') != null && get_config('via', 'lara_branch') != '' && get_config('via', 'lara_branch') != $userbranchid) {
                $this->user_add_branch($muser->viauserid, get_config('via', 'lara_branch'));
            }

        } else {
            // En mode création.
            if (get_config('via', 'lara_branch') != null && get_config('via', 'lara_branch') != '') {
                $data .= "'branchId': '".get_config('via', 'lara_branch')."',";
                $muser->branchid = get_config('via', 'lara_branch');
            }

        }

        if (isset($infoplus) && get_config('via', 'via_participantsynchronization')) {
            $data .= "'phoneHome' : '". $this->convert_text_json($infoplus["PhoneHome"]) ."',";
            $data .= "'companyName' : '". $this->convert_text_json($infoplus["CompanyName"]) ."',";
        }

        $data .= "'language' : '". get_via_language($muser->lang) ."',";
        $data .= "'enableNotifications' : 0";
        $data .= "}";

        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem creating ViaHTML5 User");
        }

        if (isset($resultdata["errors"])) {
            // Login already exists.
            if ($resultdata["errors"]["code"] == 108) {
                $resultdata = "LOGIN_USED";
                return $resultdata;
            } else {
                $this->check_api_error($resultdata);
            }
        }

        $resultdata["UserID"] = $resultdata["id"];

        return $resultdata;
    }

    /**
     * Deletes a Via HTML5 user, used in html5 connection test.
     * @param mixed $viauserid
     */
    public function via_user_delete_html5($viauserid) {
        global $CFG;

        $url = 'user/delete';

        $data = "{'id': '".$viauserid."'}";
        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem deleting ViaHTML5 User");
        }

        $this->check_api_error($resultdata);

        return $resultdata;
    }

    /**
     * get an activity on Via
     *
     * @param integer $viaid
     * @return Array containing response from Via
     */
    public function via_activity_get_html5($viaid) {
        global $CFG;

        $url = 'activity/get';

        $data = "{'id' : '".$viaid."'}";
        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem getting ViaHTML5 User");
        }

        if (isset($resultdata["errors"])) {
            // InvalidID : activity should have been deleted.
            if ($resultdata["errors"]["code"] == 1701) {
                $resultdata = "ACTIVITY_DOES_NOT_EXIST";
                return $resultdata;
            } else {
                $this->check_api_error($resultdata);
            }
        }

        $this->check_api_error($resultdata);

        $resultdata["Duration"] = isset($resultdata["duration"]) ? $resultdata["duration"] : 0;
        return $resultdata;
    }

    /**
     * gets a user on Via
     *
     * @param integer $viauserid
     * @return Array containing response from Via
     */
    public function via_user_get_html5($viauserid) {
        global $CFG;

        $url = 'user/get';

        $data = "{'id':".$viauserid."}";
        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem getting ViaHTML5 User");
        }

        $this->check_api_error($resultdata);

        return $resultdata;
    }

    /**
     * Searches a user on VIA
     *
     * @param Array $search search items
     * @param Array $searchterm the info beeing searched (login, email, ect.)
     * @return Array containing search results or FALSE if nothing was found
     */
    public function viahtml5_user_search($search, $searchterm) {
        global $CFG;

        $url = 'user/search';

        $data = "{'".$searchterm."':'". $this->convert_text_json($search) ."'}";

        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem searching user in Via");
        }

        $this->check_api_error($resultdata);

        if ($resultdata == "[]") {
            // The username is already in use. We need to create the user with a new username.
            return false;
        }

        $searchedusers = new stdClass();
        $searchedusers = $response['datajson'];
        if (count($searchedusers) == 1) {

            if ($searchedusers[0]["email"] == $search) {
                return $searchedusers[0];
            } else {
                return false;
            }
        } else {
            if ($searchedusers) {
                return $searchedusers[0];
            }
        }

        return false;
    }

    /**
     * Add users to an activity
     *
     * @param object $usersdata users to subscribe
     * @param object $via  via object
     * @param boolean $addonly add only these users without removing others.
     * @return Array containing response from Via
     */
    public function set_users_activity_html5($usersdatalist, $via, $addonly = false) {
        global $CFG, $DB;

        if ($addonly) {
            $url = 'activity/addparticipants';
        } else {
            $url = 'activity/setparticipants';
        }

        $data = "{'id': '".$via->viaactivityid."', 'participantList': [";
        $useridlist = [];
        foreach ($usersdatalist as $userdata) {
            $userid = $this->get_user_via_id($userdata[0], false, false, true);
            if (in_array($userid, $useridlist)) {
                // Each user should be unique.
                continue;
            } else {
                array_push($useridlist, $userid);
            }
            $data .= "{'userid':'".$userid."', 'participantType' :";

            if ($userdata[1] == 2) {
                // Hôte.
                $data .= "'2'";
            } else if ($userdata[1] == 3) {
                // Animateur.
                $data .= "'1'";
            } else {
                // Participants.
                $data .= "'0'";
            }
            $data .= "},";
        }
        $data .= "]}";
        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem setting ViaHTML5 User");
        }

        $this->check_api_error($resultdata);

        foreach ($usersdatalist as $userdata) {
            $sub = $DB->get_record("via_participants", array('userid' => $userdata[0], 'activityid' => $via->id));
            if (isset($sub) && (!isset($sub->timesynched) || $sub->timesynched == 0 )) {
                $sub->synchvia = 1;
                $sub->timesynched = time();

                $DB->update_record("via_participants", $sub);
            }
        }

        return $resultdata;
    }

    /**
     * remove users from an activity
     *
     * @param object $usersdata users to unsubscribe
     * @param array $viaid  via Id
     * @return Array containing response from Via
     */
    public function remove_users_activity_html5($usersdatalist, $viaid) {
        global $CFG, $DB;

        $url = 'activity/removeparticipants';

        $data = "{'id': '".$viaid."', 'participantList': [";
        foreach ($usersdatalist as $userdata) {
            $data .= "{'userid':'".$this->get_user_via_id($userdata[0], false, false, true)."'";
            $data .= "},";
        }
        $data .= "]}";
        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem setting ViaHTML5 User");
        }

        $this->check_api_error($resultdata);

        return $resultdata;
    }

    /**
     * throw exception if error occurs
     *
     * @param Array $resultdata data return from API
     */

    public function check_api_error($resultdata) {
        if (isset($resultdata["errors"])) {
            throw new Exception($resultdata["errors"]["message"]  . " (code : ".$resultdata["errors"]["code"] .")", $resultdata["errors"]["code"]);
        }
        return;
    }

    /**
     * Duplicate activity
     *
     * @param object $via activity to duplicate
     * @param array $language language
     * @return Array containing response from Via
     */
    public function activity_duplicate_html5($via, $language = 1) {
        $url = "activity/duplicate";

        $data = "{'id' : '" . $via->viaactivityid . "'";
        $data .= ", 'title': {
                'texts': [
                    {
                        'text': '". $this->convert_text_json($via->name) ."',
                        'languageId': ".$language."
                    }
                ]
            },
            'includeUsers' : ". $via->include_userInfo;

        if ($via->activitytype == 1) {
            $data .= ", 'startDate': '".$this->change_date_format($via->datebegin)."',
                    'duration': ".$via->duration;
        }
        $data .= "}";

        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem duplicating VIA HTML5 activity");
        }

        $this->check_api_error($resultdata);

        return $resultdata->id;
    }

    /**
     * Get list of playbacks
     *
     * @param object $via activity which contains the playbacks
     * @return Array containing list of playbacks
     */
    public function playback_getlist_html5($via) {
        $url = "playback/getlist";

        $data = "{'FilterID' : '" . $via->viaactivityid . "'";
        if ($via->playbacksync) {
            // Minus 5 minutes just in case!
            $data .= ", 'filterStartDate': '".$this->change_date_format($via->playbacksync - 300) ."'";
        }
        $data .= "}";

        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem getting playback list");
        }

        $this->check_api_error($resultdata);

        return $resultdata;
    }


    /**
     * Gets a token to redirect a user to VIA for HTML5 activities
     *
     * @param object $via the via html 5 object
     * @param integer $redirect where to redirect
     * @param string $playbackid id of the playback to redirect to
     * @param boolean $forceaccess permits those with editing rights in moodle to view recording taht are not public
     * @return string URL for redirect
     */
    public function userget_ssotoken_html5($via=null, $redirect=null, $playbackid=null, $forceaccess=null) {
        global $CFG, $USER;

        $muser = $USER->id;

        if (!$userid = $this->get_user_via_id($muser, true, null, true)) {
            return false;
        }

        $url = "user/getsso";

        $data = "{'id' : '" . $userid . "'";
        $data .= ", 'refId' : '". $via->viaactivityid . "'";

        $data .= ", 'subRefId': '".$playbackid."'
                , 'redirectType': ".$redirect . "
                , 'forceAccess': ".$forceaccess;
        $data .= "}";

        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem getting token sso for VIA HTML5 activity");
        }

        $this->check_api_error($resultdata);

        return $resultdata['urlSSO'];
    }


    /**
     * Edit a given playback for a given HTML5 activity
     * @param string $playbackid the id of the playback
     * @param object $playback the playback object
     * @return Array containing response from Via
     */
    public function edit_playback_html5($playbackid, $playback, $language = 1) {
        global $CFG;

        $title = str_replace("'", '&#39;', $playback->title);

        $url = "playback/edit";

        $data = "{'id' : '" . $playbackid . "'";
        $data .= ", 'title': {
                'texts': [
                    {
                        'text': '". $this->convert_text_json($title) ."',
                        'languageId': ".$language."
                    }
                ]
            },
            'accessType' : ". $playback->accesstype . "}";

        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem editing playback for VIA HTML5 activity");
        }

        $this->check_api_error($resultdata);

        return $resultdata["id"];
    }

    /**
     * Deletes a Via HTML5 playback.
     * @param mixed $playbackid Playback to delete.
     */
    public function delete_playback_html5($playbackid) {
        global $CFG;

        $url = 'playback/delete';

        $data = "{'id': '".$playbackid."'}";

        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem deleting ViaHTML5 playback");
        }

        $this->check_api_error($resultdata);

        return $resultdata["id"];
    }


    /**
     * get activity's logs for all users.
     *
     * @param string $viaactivityid the VRoom via id
     * @return Array containing connection data for the via activity
     */
    public function via_get_user_logs_html5($viaactivityid) {
        global $CFG;

        $url = 'activity/getparticipantslogs';

        $data = "{'id': '".$viaactivityid."'}";

        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem fetching ViaHTML5 user logs");
        }

        $this->check_api_error($resultdata);

        return $resultdata["participantList"];
    }

    /**
     * Convert text for json
     *
     * @param string $data text to convert
     * @return string data converted
     */
    protected function convert_text_json($data) {

        return html_entity_decode(str_replace("'", "\'", $data));
    }

    /**
     * Add branch from parameter to specified activity
     * @param string $activityid the id of the activity
     * @param string $branchid the id of the branch
     * @param boolean $ishtml5 if the activity is an html5 activity
     * @return Array containing response from Via
     */
    public function add_activity_branch($activityid, $branchid = null, $ishtml5 = true) {
        global $CFG;

        if ($branchid == null) {
            $branchid = get_config('via', 'lara_branch');
        }

        $url = "activity/addbranches";

        $data = "{'id' : '" . $activityid . "'";
        $data .= ", 'branches':['".$branchid."']";
        $data .= ", 'context': '".($ishtml5 ? "10" : "9")."'}";
        // Context 10 = Vroom.
        // Context 9 = Via.

        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem adding branch for Vroom activity");
        }

        $this->check_api_error($resultdata);

        return $resultdata["id"];
    }
    /**
     * Add branch from parameter to specified activity
     * @param string $branchid the id of the branch
     * @return string containing response from Via
     */
    public function get_branch($branchid) {
        global $CFG;

        $url = "branch/get";

        $data = "{'id' : '" . $branchid . "'}";

        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem Getting branch");
        }

        $this->check_api_error($resultdata);

        return $resultdata["id"];
    }

    /**
     * Add branch to a user
     * @param string $userid the id of the user
     * @param string $branchid the id of the branch
     * @return  array containing response from Via
     */
    public function user_add_branch($userid, $branchid) {
        global $CFG;

        $url = "user/addtobranch";

        $data = "{'id' : '" . $userid . "', 'branchid':'".$branchid."'}";

        $response = $this->send_soap_enveloppe_json($data, $url);

        if (!$resultdata = $response['datajson']) {
            throw new Exception("Problem adding branch for VIA activity");
        }

        $this->check_api_error($resultdata);

        return $resultdata["id"];
    }
}