<?php

/**
 * Data access for the via module.
 *
 * @package   mod-via
 * @copyright 2011 - 2013 SVIeSolutions
 */


/** Data access class for the via module. */
class mod_via_api {
	
		 /**
		 * Creates a user on VIA
		 *
		 * @param object $user an object user
		 * @param bool $edit if true, edit an existing user, else create a new one
		 * @param Array $infoplus additional info when creating/editing user
		 * @return Array containing response from Via
		 */
		function userCreate($user, $edit=false, $infoplus=NULL){
				global $CFG;
				
				require_once($CFG->dirroot . '/mod/via/lib.php');
				
				if($edit){
					// we are editing user
					$url = 'UserEdit';
				}else{
					// we are creating a new user
					$url = 'UserCreate';
				}
				if(!isset($user->viausername)){
					$user->viausername = $user->email;
				}
				
				$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
				$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
				$data .= '<soap:Body>';
				$data .= "<cApiUsers>";
				$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
				$data .= "<CieID>".$CFG->via_cleid."</CieID>";
				if($edit){
					$data .= "<ID>".$user->viauserid."</ID>";
					if(isset($user->usertype)){
						$data .= "<UserType>".$user->usertype."</UserType>";
					}
					if(isset($user->status)){
						$data .= "<Status>".$user->status."</Status>";
					}
				}
				$data .= "<LastName>".$user->lastname."</LastName>";
				$data .= "<FirstName>".$user->firstname."</FirstName>";
				$data .= "<Login>".$user->viausername."</Login>";
				if(!$edit){
					$data .= "<Password>".via_create_user_password()."</Password>";
				}
				$data .= "<UniqueID>".$user->username."</UniqueID>";
				$data .= "<Email>".$user->email."</Email>";
				
				if($infoplus){
					foreach($infoplus as $name=>$info){
						$data .= "<".$name.">".$info."</".$name.">";	
					}
				}
				if($user->lang == "en" || $user->lang == "en_utf8"){
					$data .= "<Language>2</Language>";
				}else{
					$data .= "<Language>1</Language>";
				}
				$data .= "</cApiUsers>";
				$data .= "</soap:Body>";
				$data .= "</soap:Envelope>";
					
				$response = $this->send_saop_enveloppe($user, $data, $url);
				
				if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"]){
					throw new Exception("Problem adding new user to Via");
				}
				
				if(!$edit){
					if($response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"]["Result"]["ResultState"] == "ERROR"){
						if($response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"]["Result"]["ResultDetail"] == "LOGIN_USED"){
							$resultdata = 'LOGIN_USED';
							return $resultdata;
						}else{
							throw new Exception('userCreate : ["ResultState"] == "ERROR")');	
							return false;
						}
					}
				}
				
				$user->viauserid = $resultdata['ID'];
				
				$resultdata['UserID'] = $resultdata['ID'];

				return $resultdata;
		}
		
		/**
		 * gets a user on Via
		 *
		 * @param object $user the user
		 * @return Array containing response from Via
		 */
		function userGet($user){
			global $CFG;
		
			$url = 'UserGet';	
			
			$muser = $user;
							 
			$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
			$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$data .= '<soap:Body>';
			$data .= "<cApiUsersGet>";
			$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
			$data .= "<CieID>".$CFG->via_cleid."</CieID>";
			$data .= "<ID>".$user->viauserid."</ID>";
			$data .= "</cApiUsersGet>";
			$data .= "</soap:Body>";
			$data .= "</soap:Envelope>";			
			$response = $this->send_saop_enveloppe($user, $data, $url);
			
			if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"]){
				throw new Exception("Problem getting user on VIA");
			}
		
			if ($resultdata['Result']['ResultState'] == "ERROR") {
				throw new Exception($resultdata['Result']['ResultDetail']);
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
	function userSearch($search, $searchterm=null){
			global $CFG;
			
			$url = 'UserSearch';
			
			$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
			$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$data .= '<soap:Body>';
			$data .= "<cApiUserSearch>";
			$data .= "<CieID>".$CFG->via_cleid."</CieID>";
			$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
			$data .= "<".$searchterm.">".$search."</".$searchterm.">";
			$data .= "</cApiUserSearch>";
			$data .= "</soap:Body>";
			$data .= "</soap:Envelope>";
			
			$response = $this->send_saop_enveloppe(NULL, $data, $url);	

			if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserSearch"]){
				throw new Exception("Problem searching user in Via");
			}
			
			if ($resultdata['Result']['ResultState'] == "ERROR") {
				throw new Exception($resultdata['Result']['ResultDetail']);
			}

			if($resultdata["nbrResults"] == 0){
				// the username is already in use. We need to create the user with a new username
				return false;
				//throw new Exception("Problem searching user in Via");
			}
			
				
		if(isset($response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserSearch"]["Search"]["Match attr"])){
			$searchedusers = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserSearch"]["Search"]["Match attr"];
		
			return $searchedusers;
			
		}else{
			$searchedusers = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserSearch"]["Search"]["Match"];	
			if($searchedusers){		
				$l = count($searchedusers);
				$i = '';
				$lastusertype = '';	
				foreach($searchedusers as $searcheduser){
					if(!empty($searcheduser["UserID"])){
						$viauser = new stdClass();
						$viauser->viauserid = $searcheduser["UserID"];
						$viauserdata = $this->userGet($viauser);
						$usertype = $viauserdata["UserType"];
						if($usertype > $lastusertype){
							$i = $searcheduser;
							$lastusertype = $usertype;
						}
					}
					$l -= 1;
					if($l == 1){
						//return the user with the highest user rights
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
		function activityCreate($via){
			global $CFG;
			
			$url = 'ActivityCreate';
					 
			$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
			$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$data .= '<soap:Body>';
			$data .= "<cApiActivity>";
			$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
			$data .= "<CieID>".$CFG->via_cleid."</CieID>";
			$data .= "<UserID>".$CFG->via_adminid."</UserID>";
			$data .= "<Title>".$via->name."</Title>";
			$data .= "<ProfilID>".$via->profilid."</ProfilID>";
			if($via->category != '0'){
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
			if($CFG->via_moodleemailnotification){
				$data .= "<ReminderTime>".'0'."</ReminderTime>"; // the reminder email will be sent by Moodle, not VIA
			}else{
				$data .= "<ReminderTime>".via_get_remindertime_svi($via->remindertime)."</ReminderTime>";	
			}
			if($via->activitytype != 2){
				$data .= "<DateBegin>".$this->change_date_format($via->datebegin)."</DateBegin>";
				$data .= "<Duration>".$via->duration."</Duration>";
			}else{
				$data .= "<DateBegin>".'0'."</DateBegin>";
				$data .= "<Duration>".'0'."</Duration>";
			}
			$data .= "</cApiActivity>";
			$data .= "</soap:Body>";
			$data .= "</soap:Envelope>";
					
			$response = $this->send_saop_enveloppe($via, $data, $url);
			
			
			if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivity"]){
				throw new Exception("Problem reading getting VIA activity id");
			}
			
			if ($resultdata['Result']['ResultState'] == "ERROR") {
				throw new Exception($resultdata['Result']['ResultDetail']);
			}

			$via->viaactivityid = $resultdata['ActivityID'];
			
			return $response['viaresponse'];
	
	}
	
		/**
		 * Edits an acitivity on Via
		 *
		 * @param object $via the via object
		 * @param bool $delete if true, activity needs to be deleted on Via
		 * @return Array containing response from Via
		 */
		function activityEdit($via, $delete=1){
			global $CFG;
			
			$url = 'ActivityEdit';
								 
			$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
			$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$data .= '<soap:Body>';
			$data .= "<cApiActivity>";
			$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
			$data .= "<CieID>".$CFG->via_cleid."</CieID>";
			$data .= "<UserID>".$CFG->via_adminid."</UserID>";
			$data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
			$data .= "<Title>".$via->name."</Title>";
			$data .= "<ProfilID>".$via->profilid."</ProfilID>";
			if($via->category != '0'){
				$data .= "<CategoryID>".$via->category."</CategoryID>";
			}
			$data .= "<IsReplayAllowed>".$via->isreplayallowed."</IsReplayAllowed>";
			$data .= "<ActivityState>".$delete."</ActivityState>";
			$data .= "<RoomType>".$via->roomtype."</RoomType>";
			$data .= "<AudioType>".$via->audiotype."</AudioType>";
			$data .= "<ActivityType>".$via->activitytype."</ActivityType>";
			$data .= "<NeedConfirmation>".$via->needconfirmation."</NeedConfirmation>";
			$data .= "<RecordingMode>".$via->recordingmode."</RecordingMode>";
			$data .= "<RecordModeBehavior>".$via->recordmodebehavior."</RecordModeBehavior>";
			$data .= "<WaitingRoomAccessMode>".$via->waitingroomaccessmode."</WaitingRoomAccessMode>";
			if($CFG->via_moodleemailnotification){
				$data .= "<ReminderTime>0</ReminderTime>"; // the reminder email will be sent by Moodle
			}else{
			$data .= "<ReminderTime>".via_get_remindertime_svi($via->remindertime)."</ReminderTime>";	// the reminder email will be sent by VIA
			}
			if($via->activitytype != 2){ // if the activity isn't permanent
				$data .= "<DateBegin>".$this->change_date_format($via->datebegin)."</DateBegin>";
				$data .= "<Duration>".$via->duration."</Duration>";
			}
			$data .= "</cApiActivity>";
			$data .= "</soap:Body>";
			$data .= "</soap:Envelope>";
			
			
			$response = $this->send_saop_enveloppe($via, $data, $url);
			
			
			if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivity"]){
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
		function activityGet($via){
			global $CFG;
			
			$url = 'ActivityGet';	
								 
			$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
			$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$data .= '<soap:Body>';
			$data .= "<cApiActivityGet>";
			$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
			$data .= "<CieID>".$CFG->via_cleid."</CieID>";
			$data .= "<UserID>".$CFG->via_adminid."</UserID>";
			$data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
			$data .= "</cApiActivityGet>";
			$data .= "</soap:Body>";
			$data .= "</soap:Envelope>";			
			$response = $this->send_saop_enveloppe($via, $data, $url);
	
		
			if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivity"]){
				throw new Exception("Problem reading getting VIA activity id");
			}
			
			if ($resultdata['Result']['ResultState'] == "ERROR") {
				throw new Exception($resultdata['Result']['ResultDetail']);
			}
			
			return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiActivity"];
	
		}
	
		/**
		* gets all categories for this company from Via
		*
		* @return Array containing response from Via
		*/
	
		function getCategories(){
			global $CFG;
			
			$url = 'GetCategories';	
		
			$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
			$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$data .= '<soap:Body>';
			$data .= "<cApiCategoryGet>";
			$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
			$data .= "<CieID>".$CFG->via_cleid."</CieID>";
			$data .= "</cApiCategoryGet>";
			$data .= "</soap:Body>";
			$data .= "</soap:Envelope>";			
			$response = $this->send_saop_enveloppe(NULL, $data, $url);
		
			if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiCategoryGet"]){
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
		function getUsersListActivity($viaid){
			global $CFG;
			
			$url = 'GetUsersListActivity';	
								 
			$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
			$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$data .= '<soap:Body>';
			$data .= "<cApiUsersListActivityGet>";
			$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
			$data .= "<CieID>".$CFG->via_cleid."</CieID>";
			$data .= "<ActivityID>".$viaid."</ActivityID>";
			$data .= "</cApiUsersListActivityGet>";
			$data .= "</soap:Body>";
			$data .= "</soap:Envelope>";			
			$response = $this->send_saop_enveloppe($viaid, $data, $url);
	
		
			if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsersListActivityGet"]){
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
		function listProfils(){
			global $CFG;
			
			$url = 'ListProfils';
								 
			$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
			$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$data .= '<soap:Body>';
			$data .= "<cApiListProfils>";
			$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
			$data .= "<CieID>".$CFG->via_cleid."</CieID>";
			$data .= "</cApiListProfils>";
			$data .= "</soap:Body>";
			$data .= "</soap:Envelope>";			
			$response = $this->send_saop_enveloppe(NULL, $data, $url);
			
			if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiListProfils"]){
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
	function UserGetSSOtoken($via=NULL, $redirect=3, $playback=NULL, $mobile=NULL){
			global $CFG, $USER;
			
			if(!$mobile){
				$muser = $USER->id;
			}else{
				$muser = $mobile;
			}
			$url = 'UserGetSSOToken';		
			
			$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
			$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$data .= '<soap:Body>';
			$data .= "<cApiGetUserToken>";
			$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
			$data .= "<CieID>".$CFG->via_cleid."</CieID>";
			$data .= "<ID>".$this->get_user_via_id($muser, true)."</ID>";
			if($via){
				$data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
			}			
			if($playback){
				$data .= "<PlaybackID>".$playback."</PlaybackID>";
			}
			$data .= "<RedirectType>".$redirect."</RedirectType>";
			$data .= "</cApiGetUserToken>";
			$data .= "</soap:Body>";
			$data .= "</soap:Envelope>";
				
			$response = $this->send_saop_enveloppe($via, $data, $url);
			
			if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsersSSO"]){
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
	function addUserActivity($via){
			global $CFG;
			
			$url = 'AddUserActivity';		
								 
			$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
			$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$data .= '<soap:Body>';
			$data .= "<cApiUserActivity_AddUser>";
			$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
			$data .= "<CieID>".$CFG->via_cleid."</CieID>";
			$data .= "<UserID>".$this->get_user_via_id($via->userid)."</UserID>";				
			$data .= "<ActivityID>".$via->viaactivityid."</ActivityID>"; 
			$data .= "<ParticipantType>".$via->participanttype."</ParticipantType>";
			$data .= "<ConfirmationStatus>".$via->confirmationstatus."</ConfirmationStatus>";
			$data .= "</cApiUserActivity_AddUser>";
			$data .= "</soap:Body>";
			$data .= "</soap:Envelope>";
			
			$response = $this->send_saop_enveloppe($via, $data, $url);
			
			if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivity_AddUser"]){
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
		function editUserActivity($via){
			global $CFG;
			
			$url = 'EditUserActivity';		
								 
			$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
			$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$data .= '<soap:Body>';
			$data .= "<cApiUserActivity_AddUser>";
			$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
			$data .= "<CieID>".$CFG->via_cleid."</CieID>";
			$data .= "<UserID>".$this->get_user_via_id($via->userid)."</UserID>";				
			$data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
			$data .= "<ConfirmationStatus>".$via->confirmationstatus."</ConfirmationStatus>";
			$data .= "</cApiUserActivity_AddUser>";
			$data .= "</soap:Body>";
			$data .= "</soap:Envelope>";
			

			$response = $this->send_saop_enveloppe($via, $data, $url);
			
			if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivity_AddUser"]){
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
	function getUserActivity($via){
			global $CFG;
			
			$url = 'GetUserActivity';		
								 
			$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
			$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$data .= '<soap:Body>';
			$data .= "<cApiUserActivityGet>";
			$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
			$data .= "<CieID>".$CFG->via_cleid."</CieID>";
			$data .= "<UserID>".$this->get_user_via_id($via->userid)."</UserID>";			
			$data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
			$data .= "</cApiUserActivityGet>";
			$data .= "</soap:Body>";
			$data .= "</soap:Envelope>";
			
			$response = $this->send_saop_enveloppe($via, $data, $url);
			
			if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivityGet"]){
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
	function removeUserActivity($viaid, $userid, $moodleid = true){
		global $CFG;
		
		$url = 'RemoveUser';	
							 
		$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
		$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
		$data .= '<soap:Body>';
		$data .= "<cApiUserActivity_RemoveUser>";
		$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
		$data .= "<CieID>".$CFG->via_cleid."</CieID>";
		if ($moodleid == false){
			$data .= "<UserID>".$userid."</UserID>";
		}else{
			$data .= "<UserID>".$this->get_user_via_id($userid)."</UserID>";
		}
		$data .= "<ActivityID>".$viaid."</ActivityID>";
		
		$data .= "</cApiUserActivity_RemoveUser>";
		$data .= "</soap:Body>";
		$data .= "</soap:Envelope>";
		
		$response = $this->send_saop_enveloppe($viaid, $data, $url);
		
		if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUserActivity_RemoveUser"]){
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
	function listPlayback($via){
			global $CFG;
			
			$url = 'ListPlayback';
								 
			$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
			$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
			$data .= '<soap:Body>';
			$data .= "<cApiListPlayback>";
			$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
			$data .= "<CieID>".$CFG->via_cleid."</CieID>";
			$data .= "<ActivityID>".$via->viaactivityid."</ActivityID>";
			$data .= "</cApiListPlayback>";
			$data .= "</soap:Body>";
			$data .= "</soap:Envelope>";			
			$response = $this->send_saop_enveloppe(NULL, $data, $url);

			if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiListPlayback"]){
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
	function editPlayback($via, $playbackid, $playback){
		global $CFG;
		
		$url = 'EditPlayback';
		
		$title = str_replace("'",'&#39;',$playback->title);
							 
		$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
		$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
		$data .= '<soap:Body>';
		$data .= "<cApiListPlayback>";
		$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
		$data .= "<CieID>".$CFG->via_cleid."</CieID>";
		$data .= "<PlaybackList>";
		$data .= "<Playback PlaybackID='".$playbackid."' Title='".$title."' IsPublic='".$playback->ispublic."'/>";
		$data .= "</PlaybackList>";
		$data .= "</cApiListPlayback>";
		$data .= "</soap:Body>";
		$data .= "</soap:Envelope>";			
		$response = $this->send_saop_enveloppe(NULL, $data, $url);

		if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiListPlayback"]){
			throw new Exception("Problem reading getting VIA activity id");
		}
		
		if ($resultdata['Result']['ResultState'] == "ERROR") {
				throw new Exception($resultdata['Result']['ResultDetail']);
		}
		
		return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiListPlayback"]["PlaybackList"];
	}
		
	/**
	 * Sends invitation to a user for an activity
	 *
	 * @param integer $userid the id of the user to invite
	 * @param string $activityid the VIA id of the activity 
	 * @param string $msg the message to write in the invitation
	 * @return Array containing response from Via
	 */	
	function sendinvitation($userid, $activityid, $msg=NULL){
		global $CFG;
		
		$url = 'SendInvitation';
							 
		$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
		$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
		$data .= '<soap:Body>';
		$data .= "<cApiSendInvitation>";
		$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
		$data .= "<CieID>".$CFG->via_cleid."</CieID>";
		$data .= "<UserID>".$CFG->via_adminid."</UserID>";
		$data .= "<ActivityID>".$activityid."</ActivityID>";
		if($msg && !empty($msg)){
			$data .= "<Msg>".$msg."</Msg>";
		}
		$data .= "</cApiSendInvitation>";
		$data .= "</soap:Body>";
		$data .= "</soap:Envelope>";			
		$response = $this->send_saop_enveloppe(NULL, $data, $url);
		
		if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiSendInvitation"]){
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
	function change_date_format($date){
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
	function testconnection($apiurl, $cleid, $apiid){
		global $CFG;
		
		$url = 'Test';
							 
		$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
		$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
		$data .= '<soap:Body>';
		$data .= "<cApiTest>";
		$data .= "<ApiID>".$apiid."</ApiID>";		 
		$data .= "<CieID>".$cleid."</CieID>";
		$data .= "</cApiTest>";
		$data .= "</soap:Body>";
		$data .= "</soap:Envelope>";		
		
		$response = $this->send_saop_enveloppe(NULL, $data, $url, $apiurl);
		
		if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiTest"]){
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
	function testAdminId($apiurl, $cleid, $apiid, $adminid){
		global $CFG;
		
		$url = 'UserGet';		
							 
		$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
		$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
		$data .= '<soap:Body>';
		$data .= "<cApiUsersGet>";
		$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
		$data .= "<CieID>".$CFG->via_cleid."</CieID>";
		$data .= "<ID>".$adminid."</ID>";
		$data .= "</cApiUsersGet>";
		$data .= "</soap:Body>";
		$data .= "</soap:Envelope>";			
		$response = $this->send_saop_enveloppe(NULL, $data, $url);
			
		if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"]){
			throw new Exception("Problem getting user on VIA");
		}
		
		if ($resultdata['Result']['ResultState'] == "ERROR") {
			throw new Exception($resultdata['Result']['ResultDetail']);
	}
	return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiUsers"];
		
	}
	
	/**
	 * Gets the Via id of a user. If not found, we create a new user
	 *
	 * @param integer $u the moodle id of the user
	 * @param bool $teacher true if the user is a teacher
	 * @return string via user id
	 */	
	function get_user_via_id($u, $connection=false){
		global $CFG, $DB;
		$info = NULL;
			
		$user = $DB->get_record('user', array('id'=>$u));
		$viauser = $DB->get_record('via_users', array('userid'=>$u));
		if(!$viauser){
			// the user doesn't exists yet. We need to create it.
			try {		
			
				$info["UserType"] = 2; 		// usertype is always 2		

				// we validate if the user already exisits on via with the email as email OR as login
				$viauserdata = $this->userSearch($user->email, "Email");
				if(!$viauserdata){
					$viauserdata = $this->userSearch($user->email, "Login");
					if(!$viauserdata){
						// false = create new user
						$i = 1;
						$viauserdata = $this->userCreate($user, false, $info);
						
						if($viauserdata == 'LOGIN_USED'){
							$user->viausername = $user->viausername. '_'. $i++;
							$viauserdata = $this->userCreate($user, false, $info);
							if(!$viauserdata){
								return false;	
							}
						}
					}
				}
				
				$user->viauserid = $viauserdata['UserID'];
				$validatestatus = $this->userGet($user);
				if($validatestatus["Status"] == 1){
					$user->status = 0; //change status back to active...
					$response = $this->userCreate($user, true);
				}	
				
				$participant = new stdClass();
				$participant->userid = $user->id;
				$participant->timecreated = time();
				$participant->timemodified = time();
				$participant->viauserid = $viauserdata['UserID'];
				$participant->username = $viauserdata['Login'];
				$participant->usertype = 2; // We only create participants
							
				if(!$added = $DB->insert_record("via_users", $participant)){
					$DB->insert_record('via_log', array('userid'=>$user->id, 'viauserid'=>$viauserdata['UserID'], 'activityid'=>NULL, 'action'=>'get_user_via_id', 'result'=>'could not add new user to via_users', 'time'=>time()));
					throw new Exception("could not add new user");
				}	
				
				return $user->viauserid;				
			}
			catch (Exception $e){
				$result = false;
			}
		}else{

			$user->viauserid = $viauser->viauserid;	
				
			if($connection == true){ // we only synchronize if/when the participant is trying to connect to an activity
				
				$validatestatus = $this->userGet($user);
				
				if($validatestatus["Status"] == 0){ // the status might have been changed since he was added and validated 
					if($CFG->via_participantsynchronization){
						// synchronizing info but we not not change the user type
						$viauserdata = $this->userGet($user);	
						//we do not update usertypes, we only send back the usertype, because if it is left empty they will  be changed to participant
						$user->viausername = $viauserdata["Login"];
						$user->usertype = $viauserdata["UserType"];
						$response = $this->userCreate($user, true);		
					}
					return $user->viauserid;	
				}else{
					
					throw new Exception("STATUS_INVALID");
					$result = false;
				}
					
			}else{
				return $user->viauserid;
			}
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
	function send_saop_enveloppe($via, $data, $url, $apiurl=NULL){
			global $CFG;
					
			if(!$apiurl){
				$apiurl = $CFG->via_apiurl;
			}
			
			$apiurl .= (substr($apiurl, -1, 1) == "/")? "" : "/";
			
			$apiurl .= $url;
			
			$params = array('http' => array( 'method' => 'POST',
											 'header' => 'Content-Type: text/xml; charset=utf-8',
											 'content' => $data ));
			
			if(isset($CFG->proxyhost[0])){
				if($CFG->proxyport === '0'){
					$params['http']['proxy'] = $CFG->proxyhost;
				}else{
					$params['http']['proxy'] = $CFG->proxyhost.':'.$CFG->proxyport;
				}
				$params['http']['request_fulluri'] = TRUE;
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
			
			if(!$dataxml = XML_unserialize($response['viaresponse'])){
				throw new Exception("Problem reading result xml");
			}
			
			$response['dataxml'] = $dataxml;
			
			
			return $response;
	}
	
	/**
	 * Get the number of seats taken at the moment and how many we have total
	 *
	 * @return Array containing response from Via
	 */	
	/*function getseats(){
		global $CFG;
		
		$url = 'GetSeats';
							 
		$data = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
		$data .= 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
		$data .= '<soap:Body>';
		$data .= "<cApiNbSeats>";
		$data .= "<ApiID>".$CFG->via_apiid."</ApiID>";		 
		$data .= "<CieID>".$CFG->via_cleid."</CieID>";		
		$data .= "</cApiNbSeats>";
		$data .= "</soap:Body>";
		$data .= "</soap:Envelope>";			
		$response = $this->send_saop_enveloppe(NULL, $data, $url);
			
		if(!$resultdata = $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiNbSeats"]){
			throw new Exception("Problem getting seats");
		}
		
		if ($resultdata['Result']['ResultState'] == "ERROR") {
			throw new Exception($resultdata['Result']['ResultDetail']);
		}
		
		return $response['dataxml']["soap:Envelope"]["soap:Body"]["cApiNbSeats"];
	}*/
	

}
