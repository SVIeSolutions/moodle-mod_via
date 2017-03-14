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
 * Authenticates user in Moodle coming from the Via mobile app.
 *
 * @package    mod
 * @subpackage via
 * @copyright  SVIeSolutions <support@sviesolutions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
defined('MOODLE_INTERNAL') || die();
global $CFG, $DB;

require_once('../../../config.php');
require_once($CFG->dirroot. '/lib/moodlelib.php');
require_once($CFG->dirroot.'/mod/via/lib.php');

header('content-type: text/xml');

$str = file_get_contents("php://input");

$urlwhole = get_config('via', 'via_apiurl');
$url = explode('/', $urlwhole);

/* default values */
$status = 'ERROR';
$token = '';

if ($str) {

    $xmlstr = <<<XML
$str
XML;

    $xml = new SimpleXMLElement($xmlstr);

    $login = (string)$xml->Login;
    $password = (string)$xml->Password;
    $password25 = (string)$xml->Password25;// The password slating changed with moodle version 2.5!
    $validated = false;

    $muser = $DB->get_record('user', array('username' => $login));
    if ($muser) {
        $moodlepassword = base64_encode(hash('sha256', utf8_encode($muser->password), true));
        if ($moodlepassword == $password) {
            $validated = true;
        } else {
            $password25 = base64_decode($password25);
            $validated = validate_internal_user_password($muser, $password25);
        }

        if ($validated) {
            $api = new mod_via_api();
            $response = $api->userget_ssotoken(null, null, null, null, null, $muser->id);

            $response = explode('?', $response);
            $explose = explode('&', $response[1]);
            $token = explode('=', $explose[0]);
            $token = $token[1];

            $status = 'SUCCESS';
            $message = '<Message/>';

        } else {// If the user does not exist.
            $message = '<Message>AUTH_FAILED_BAD_PASSWORD</Message>';
        }
    } else {  // If the user does not exist.
        $message = '<Message>UTH_FAILED_BAD_USERNAME</Message>';
    }

} else {  // If no xml is posted.
    $message = '<Message>INVALID_XML_FORMAT</Message>';
}

echo '<?xml version="1.0" encoding="UTF-8"?>
        <Authentication>
        <Result>
        <Status>'.$status.'</Status>
            '.$message.'
        </Result>
        <SSOToken>'.$token.'</SSOToken>
        <URLVia>'.$url[0].'//'.$url[2].'</URLVia>
    </Authentication>';
