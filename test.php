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
 * TODO describe file test
 *
 * @package    tool_smsimport
 * @copyright  2024 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();
use tool_smsimport\helper;
require_login();

if (!$context = context_system::instance()) {
    throw new moodle_exception('wrongcontext', 'error');
}

require_capability('moodle/site:config', $context);

$url = new moodle_url('/admin/tool/smsimport/test.php', []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

$PAGE->set_heading($SITE->fullname);
echo $OUTPUT->header();
/*
echo "<h3>ETAP get request token</h3>";
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://nogo.etap.co.nz:9502?id=iDealapi&p=YwQofCNwgGIRyQjcb2GBo9',
  CURLOPT_USERPWD => 'ignore:me',
));
$key = curl_exec($curl);
curl_close($curl);

$key = rtrim($key, "1");
echo $key;
echo "<h3>ETAP get groups data</h3>";
$curl = curl_init();
// THE KEY NEEEDS TO BE ADDED AS TEXT IN THE URL BELOW. Need to figure out how to go about it.
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://api5.etap.co.nz:9502?k=edpFXXC6nrPacrMuSwdJ9srF8fhsr&m=2573&SendRooms=1',
  CURLOPT_USERPWD => 'ignore:me',
));
$response = curl_exec($curl);
curl_close($curl);
echo gettype($response);
echo "<h3>response</h3>";
echo $response;
*/
/*
$params = array('suspend' => 0, 'schoolno' => 3703);
$records = helper::get_sms_schools($params);
foreach ($records as $record) {
    $smsgroups = helper::get_sms_school_data($record);
}
print "<pre>"; print_r($smsgroups); print "</pre>";
*/
/*
$courseid = 19;
$userid = 53491;
$usergroups = groups_get_user_groups($courseid, $userid);
$displayusergroups = json_encode($usergroups);
print $displayusergroups;
print "<pre>"; print_r($usergroups); print "</pre>";
*/