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
global $CFG;

require_once('../../config.php');
require_once($CFG->dirroot. '/lib/moodlelib.php');
require_once($CFG->dirroot.'/mod/via/lib.php');

header('content-type: text/xml');

$str = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Authentication>
<Login></Login>
<Password></Password>
</Authentication>
XML;


$xml = new SimpleXMLElement($str);

$login = (string)$xml->Login;
$password = (string)$xml->Password;

$muser = authenticate_user_login($login, $password);

if ($muser) {
    $api = new mod_via_api();
    $response = $api->userget_ssotoken(null, null, null, null, null, $muser->id);

    $response = explode('=', $response);
    $url = $response[0];
    $token = $response[1];

    echo '<?xml version="1.0" encoding="UTF-8"?>
            <Authentication>
            <Result>
                <Status>SUCCESS</Status>
                <Message/>
            </Result>
            <SSOToken>'.$token.'</SSOToken >
            <URLVia>http://via.sviesolutions.com/ </ URLVia>
            </Authentication>';

} else {
    echo 'is not a moodle user';
}
