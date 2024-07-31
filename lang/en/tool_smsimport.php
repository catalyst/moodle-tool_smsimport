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
 * Languages configuration for the tool_smsimport plugin.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Student management system import';
$string['sms'] = 'Student management system';
$string['schools'] = 'Schools';
$string['addschool'] = 'Add school';
$string['editschool'] = 'Edit school';
$string['addgroup'] = 'Add groups';
$string['schoolname'] = 'School Name';
$string['schoolno'] = 'School Number';
$string['schoolno_help'] = 'The school ID provided by the SMS';
$string['schoolmoeid'] = 'School MoE ID (Ministry of education)';
$string['transferin'] = 'School transfer-in';
$string['transferin_help'] = 'Approve student transfer in from another school';
$string['transferout'] = 'School transfer-out';
$string['transferout_help'] = 'Approve student transfer out to another school';
$string['cohortid'] = 'Linked school';
$string['cohortid_help'] = 'Linking the school will add new groups to an existing school.
It will also rename the organisation name, if a different name is used. ';
$string['unlink'] = 'Unlink school';
$string['unlink_help'] = 'This will unlink the SMS school and the associated groups.';
$string['changesmake'] = 'Would you like to make these changes?';
$string['changessuccess'] = 'Changes have been successfully made. ';
$string['goback'] = 'Go back to the listing page ';
$string['groups'] = 'School groups to sync';
$string['suspend'] = 'Suspend connection';
$string['delete'] = 'Delete connection';
$string['actions'] = 'Actions';
$string['uploadusers'] = 'Upload users via CSV';
$string['wrongschoolid'] = 'Wrong school ID';
$string['errorschoolnotadded'] = 'Something went wrong the school could not be added';
$string['errorschoolnotdeleted'] = 'School could not be deleted {a}';
$string['errorschoolnoteditted'] = 'Something went wrong the school could not be editted';
$string['errorschoolnotfound'] = 'School was not found';
$string['errorschoolexists'] = 'The school has alread been added. Visit {$a->url} to update it';
$string['errorschoollinked'] = 'The school has alread been linked to another school {$a->name}.';
$string['coursecoutnotbelinked'] = 'The course could not be linked to school';
$string['target'] = 'Target';
$string['action'] = 'Action';
$string['error'] = 'Error';
$string['info'] = 'Additional info';
$string['other'] = 'Error details';
$string['timecreated'] = 'Time created';
$string['origin'] = 'Origin';
$string['ip'] = 'IP address';
$string['logcreate'] = 'created';
$string['logupdate'] = 'updated';
$string['logdelete'] = 'deleted';
$string['logschool'] = 'school';
$string['logsync'] = 'sync';
$string['loggroup'] = 'group';
$string['loguser'] = 'user';
$string['logduplicate'] = 'Duplicate account';
$string['logduplicatehelp'] = 'A duplicate account deducted. A student is in more than one school. Please check transferin and transferout
settings to avoid this from happening again.';
$string['lognoregister'] = 'Cannot register';
$string['lognoregisterhelp'] = 'A student is not able to register in school. Please check school transfer-in setting.';
$string['lognodata'] = 'No data';
$string['lognodatahelp'] = 'The API endpoint is not sending any data';
$string['lognogroups'] = 'No groups';
$string['lognogroupshelp'] = 'No groups selected for SMS school in config.';
$string['logerrorsync'] = 'Error sync';
$string['logerrorsynchelp'] = 'Errors reported with the import sync';
$string['logemailsubject'] = 'SMS Import notification';
$string['logemailmessage'] = '<h3>SMS Import notification</h3><h5>School number {$a->schoolno}</h5><b>Error details</b><p>{$a->error}  {$a->other}  {$a->info}</p>';
$string['logendpoint'] = 'API endpoint';
$string['taskimportsmsusers'] = 'Import users from SMS';
$string['taskcleanupsmsusers'] = 'Cleanup users from incorrect groups from SMS';
$string['smslogs'] = 'SMS logs';
$string['entitysmslog'] = 'SMS log';
$string['total'] = 'Total';
$string['newusers'] = 'New users';
$string['updateusers'] = 'Updated users';
$string['userid'] = 'Student ID';
$string['groupadd'] = 'Group added';
$string['groupupdate'] = 'Group updated';
$string['groupremove'] = 'Group removed';
$string['nsn'] = 'NSN';
$string['smscourse'] = 'SMS course';
$string['smscourse_help'] = 'SMS data will be imported to this course';
$string['smsuserfields'] = 'SMS user fields';
$string['smsuserfields_help'] = 'SMS student data that will be imported to these user custom fields.';
$string['managesmsschools'] = 'Manage SMS schools';
$string['managesms'] = 'Manage SMS';
$string['safeguard'] = 'Safeguard';
$string['safeguard_help'] = 'The minimum number of records from the endpoint that are required to parse the data. This is placed to prevent widespread unexpected deletion of data.';
$string['privacy:metadata:schoolno'] = 'The school number of the user';
$string['privacy:metadata:target'] = 'The target of the action';
$string['privacy:metadata:action'] = 'The action taken on the user account';
$string['privacy:metadata:error'] = 'The error occured while the action was taking place';
$string['privacy:metadata:info'] = 'The additional details of the action';
$string['privacy:metadata:other'] = 'The addtional details of the error';
$string['privacy:metadata:timecreated'] = 'Time this record was created (unix timestamp)';
$string['privacy:metadata:userid'] = 'The user ID linked to the user account.';
$string['privacy:metadata:origin'] = 'The origin of the action';
$string['privacy:metadata:ip'] = 'The IP address if the origin is web';
$string['privacy:metadata:tool_sms_school_log'] = 'SMS import log record';
$string['invaliddobformat'] = 'Invalid date of birth format used. Please use YYYY-MM-DD.';
