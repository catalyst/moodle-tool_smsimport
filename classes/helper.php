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
 * tool_smsimport entity class for helper functions.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_smsimport;

use local_organisations\persistent\school;

require_once($CFG->libdir . '/filelib.php');
require_once("{$CFG->dirroot}/local/organisations/locallib.php");
require_once("{$CFG->dirroot}/cohort/lib.php");
require_once("{$CFG->dirroot}/user/lib.php");
require_once("{$CFG->dirroot}/user/profile/lib.php");
require_once("{$CFG->dirroot}/group/lib.php");
require_once($CFG->libdir.'/enrollib.php');

 /**
 * tool_smsimport entity class for helper functions
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper  {

    /**
     * @var array Store the groups retrived by the API.
     */
    private static $smsgroups = array();

    /**
     * Get SMS details.
     *
     * @param string $schoolid School ID
     * @return object
     */
    public static function get_sms($value, $key) {
        global $DB;
        $record = $DB->get_record('tool_sms', array($key => $value));
        return $record;
    }

   /**
     * Get SMS school details.
     *
     * @param array $params key value pair to search
     * @return object of schools
     */
    public static function get_sms_school($params) {
        global $DB;
        $record = $DB->get_record('tool_sms_school', $params);
        return $record;
    }

    /**
     * Get SMS groups details saved in the database.
     *
     * @param string $schoolid School schoolno
     * @return mixed | boolean
     */
    public static function get_sms_school_groups($value, $key) {
        global $DB;
        $groups = array();
        $sql = "select idnumber, g.name from {tool_sms_school_groups} sg JOIN {groups} g on sg.groupid = g.id
        WHERE {$key} = :value ";
        $params = array('value' => $value);
        if ($linkedgroups = $DB->get_records_sql($sql, $params)) {
            foreach($linkedgroups as $key => $value) {
                    $groups[$value->idnumber] = $value->name;
            }
            return $groups;
        } else return false;
    }

    /**
     * Get all SMS schools details.
     *
     * @param array $params key value pair to search
     * @return object of schools
     */
    public static function get_sms_schools($params) {
        global $DB;
        $record = $DB->get_records('tool_sms_school', $params);
        return $record;
    }

    /**
     * Check if local_organisations is installed.
     *
     * @return boolean
     */
    public static function check_local_organisations(){
        $pluginman = \core_plugin_manager::instance();
        $plugins= $pluginman->get_installed_plugins('local');
        if (array_key_exists('organisations', $plugins)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Edit SMS school details.
     *
     * @param string $schoolid School schoolno
     * @return object
     */
    public static function save_sms_school($data) {
        global $DB;
        $data->timemodified = time();
        if (!isset($data->transferin)) $data->transferin = 0;
        if (!isset($data->transferout)) $data->transferout = 0;
        if (!isset($data->suspend)) $data->suspend = 0;
        if (!($result = $DB->update_record('tool_sms_school', $data, false))) {
            throw new \moodle_exception('errorschoolnoteditted', 'tool_smsimport');
        }
        return $result;
    }

    /**
     * Unlink user from SMS school by updating user auth type.
     *
     * @param int $cohortid School cohortid
     * @return void
     */
    public static function unlink_sms_users($cohortid) {
        global $DB;
        $records = $DB->get_records('cohort_members', array('cohortid' => $cohortid));
        foreach ($records as $record) {
            if($DB->get_field('user', 'auth', array('id' => $record->userid)) == 'webservice') {
                $sql = "UPDATE {user} SET auth = :nologin WHERE id = :id";
                $params = array('nologin' => 'nologin', 'id' => $record->userid);
                $DB->execute($sql, $params);
            }
        }
    }

    /**
     * Add and Edit groups and course to school.
     *
     * @param string $schoolid School schoolno
     * @param string $action The state of change the school is in; select or edit.
     * @param array $groups School's groups existing before the change.
     * @return object
     */
    public static function save_sms_school_details($data, $action = NULL, $groups = NULL) {
        global $DB;
        $data = (object)$data;
        $data->timemodified = time();
        $result =  new \stdClass();
        $courseid = get_config('tool_smsimport', 'smscourse');
        // Log record.
        $logrecord = new \stdClass();
        $logrecord->schoolno = $data->schoolno;
        $logrecord->target = get_string('logschool', 'tool_smsimport');
        $logsource = 'web';
        if (isset($data->cohortid)) {
            $logaction = get_string('logcreate', 'tool_smsimport');
            $cohortid = $data->cohortid;
            $schoolname = $data->name;
            //local_organisations school update.
            $data->organisationname = $schoolname;
            $data->organisationcode = $data->schoolno;
            $data->organisation_school_type = school::STANDARD;
            if ($cohortid == 0) {
                // Create new school and cohort.
                $result->message[] = "Create a new school: {$schoolname} ";
                if ($action == 'edit') {
                    $cohortid = local_organisations_create_organisation($data, 'tool_smsimport');
                    $data->cohortid = $cohortid;
                    $result->message[] = "Created school with cohort ".$cohortid;
                }
            } else {
                $schoolname = $DB->get_field('cohort', 'name', array('id' => $cohortid));
                $logaction = get_string('logupdate', 'tool_smsimport');
            }

            if (isset($data->unlink) && $data->unlink) {
                $logaction = get_string('logdelete', 'tool_smsimport');
                $result->message[] = "Unlink school from organisation {$schoolname}";
            }

            if ($action == 'edit') {
                if (isset($data->unlink) && $data->unlink) {
                    // Resets the cohortid for the school.
                    $schoolname = $data->name;
                    $data->cohortid = 0;
                    self::unlink_sms_users($cohortid);
                } else {
                    // Link course to local organisation school.
                    if(!local_organisations_store_organisation_course($cohortid, $schoolname, $courseid)) {
                        throw new \moodle_exception('coursecoutnotbelinked', 'tool_smsimport');
                    }
                }
                // Link cohort to SMS school.
                self::save_sms_school($data);
                // Log event.
                $logrecord->action = $logaction;
                $info = array('cohortid' => $cohortid);
                $logrecord->info = $info;
                self::add_sms_log($logrecord, $logsource);
            }

            if (isset($data->groupsselect)) {
                // Check against the existing groups of a SMS school.
                if ($groups) {
                    // Check if there are groups that were removed
                    $groupsexist = array_keys($groups);
                    $deletegroups = array_diff($groupsexist, $data->groupsselect);
                    $addgroups = array_diff($data->groupsselect, $groupsexist);
                    if ($addgroups) {
                        foreach($addgroups as $addgroup) {
                            if ($addgroupdata = groups_get_group_by_idnumber($courseid, $addgroup)) {
                                $agroupnamedisplay = str_replace($schoolname, '', $addgroupdata->name);
                                $result->message[] =  "Link SMS school {$schoolname} to group : {$agroupnamedisplay} ({$addgroupdata->id})";
                                if ($action == 'edit') {
                                    self::save_sms_school_groups($data->id, $addgroupdata->id);
                                    // Log event.
                                    if ($logaction) {
                                        $logrecord->action = get_string('logcreate', 'tool_smsimport');
                                        $info['groupadd'] = $addgroup;
                                        $logrecord->info = $info;
                                        self::add_sms_log($logrecord, $logsource);
                                    }
                                }
                            }
                        }
                    }

                    if ($deletegroups) {
                        foreach($deletegroups as $deletegroup) {
                            $deletegroupdata = groups_get_group_by_idnumber($courseid, $deletegroup);
                            $dgroupnamedisplay = str_replace($schoolname, '', $deletegroupdata->name);
                            $result->message[] =  "Unlink SMS school {$schoolname} from group: {$dgroupnamedisplay} ({$deletegroupdata->id})";
                            if ($action == 'edit') {
                                self::delete_sms_school_groups_idnumber($data->id, $deletegroupdata->id);
                                self::delete_sms_school_groups($data->id, $deletegroupdata->id);
                                $info['groupremove'] = $deletegroup;
                                $logrecord->action = get_string('logdelete', 'tool_smsimport');
                                $logrecord->info = $info;
                                self::add_sms_log($logrecord, $logsource);
                            }
                        }
                    }
                }

                foreach($data->groupsselect as $gidnumber) {
                    $logaction = '';
                    if (!$gidnumber) {
                        break;
                    }
                    $groupname = self::find_groupname($gidnumber, $data->schoolno);
                    $groupnamedisplay = str_replace($schoolname, '', $groupname);
                    $groupdata = groups_get_group_by_idnumber($courseid, $gidnumber);
                    // Check if groups exists in core.
                    if(!empty($groupdata)) {
                        $groupid = $groupdata->id;
                        // Update group name.
                        $groupdata->name = $schoolname.$groupname;
                        $logaction = get_string('logupdate', 'tool_smsimport');
                    } else {
                        // If the new SMS school is linked to existing school.
                        if ($cohortid != 0  &&($records = local_organisations_get_organisation_groups($cohortid, $courseid))) {
                            $ngroupname = str_replace(' ', '', $groupname);
                            // Check if the existing school's group match the SMS school group
                           $logaction = get_string('logcreate', 'tool_smsimport');
                            foreach($records as $record) {
                                $norggroupname = str_replace(' ', '', $record->orggroupname);
                                if ($ngroupname == $norggroupname && $gidnumber != $record->idnumber) {
                                    $logaction = get_string('logupdate', 'tool_smsimport');
                                    $groupid = $record->id;
                                    $groupdata = groups_get_group($groupid);
                                    break;
                                }
                            }
                        } else {
                            // Create new group and add to tool_smsimport groups table
                            $logaction = get_string('logcreate', 'tool_smsimport');
                        }
                    }

                    // Only remove idnumbers from the groups
                    if ($action == 'edit' && isset($data->unlink) && $data->unlink) {
                        self::delete_sms_school_groups($data->id, $groupdata->id);
                    }

                    if($logaction == get_string('logcreate', 'tool_smsimport')) {
                        $result->message[] =  "Link SMS school {$schoolname} to new group: {$groupnamedisplay} ({$gidnumber})";
                        $newgroupdata = new \stdClass();
                        $newgroupdata->courseid = $courseid;
                        $newgroupdata->name = $schoolname."".$groupname;
                        $newgroupdata->idnumber = $gidnumber;
                        if($action == 'edit') {
                            $info['groupadd'] = $gidnumber;
                            $groupid = groups_create_group($newgroupdata);
                        }
                    }

                    if($logaction == get_string('logupdate', 'tool_smsimport')) {
                        $result->message[] =  "Link SMS school {$schoolname} to group: {$groupnamedisplay} ({$gidnumber})";
                        $groupdata->idnumber = $gidnumber;
                        if($action == 'edit' && !isset($deletegroups)) {
                            /* Updating the name of the group is not covered assuming that it should manually be controlled by local_organisations.
                            Or done via user import and added as a exception report (Pending check if needs to be done? )
                            $groupdata->name = $groupname;
                            */
                            $info['groupupdate'] = $gidnumber;
                            groups_update_group($groupdata);
                        }
                    }

                    if ($action == 'edit') {
                        // Link groups to local organisation schools.
                        self::save_sms_school_groups($data->id, $groupid);
                        local_organisations_store_organisation_group($cohortid, $groupid);
                        $data->groupid = $groupid;
                        // Log event.
                        if ($logaction) {
                            $logrecord->action = $logaction;
                            $logrecord->info = $info;
                            self::add_sms_log($logrecord, $logsource);
                        }
                    }
                } // forloop for groupselect ends.
            }
        }
        $result->status = 1;
        return $result;
    }


    /**
     * Link and unlink a group to a SMS school.
     *
     * @param int $schoolid The ID of the school
     * @param int $groupid the ID of the group
     * @return void
     */
    public static function save_sms_school_groups($schoolid, $groupid) {
        global $DB;
        $params = array('schoolid' => $schoolid, 'groupid' => $groupid);
        $id = $DB->get_field('tool_sms_school_groups', 'id', $params);
        if (!$id) {
            $DB->insert_record('tool_sms_school_groups',  $params);
        }
    }

    /**
     * Unlink a group to a SMS school.
     *
     * @param int $schoolid The ID of the school
     * @param int $groupid the ID of the group
     * @return void
     */
    public static function delete_sms_school_groups($schoolid, $groupid) {
        global $DB;
        $params = array('schoolid' => $schoolid, 'groupid' => $groupid);
        $id = $DB->get_field('tool_sms_school_groups', 'id', $params);
        if ($id) {
            $DB->delete_records('tool_sms_school_groups', array('id' => $id));
        }
    }

    /**
     * Unlink a group to a SMS school.
     *
     * @param int $schoolid The ID of the school
     * @param int $groupid the ID of the group
     * @return void
     */
    public static function delete_sms_school_groups_idnumber($schoolid, $groupid) {
        global $DB;
        $params = array('schoolid' => $schoolid, 'groupid' => $groupid);
        $id = $DB->get_field('tool_sms_school_groups', 'id', $params);
        if ($id) {
            // Remove SMS group idnumber from core group.
            $groupdata = groups_get_group($groupid);
            $groupdata->idnumber = '';
            groups_update_group($groupdata);
        }
    }

    /**
     * Cleanup groups and other stuff from students in a SMS school.
     *
     * @param string $schoolid School schoolno
     * @param string $logsource The source that executes this; cron or web.
     * @return mixed
     */
    public static function cleanup_sms_school_users($schoolno, $logsource = 'cron') {
        global $DB;
        $nsn='national student number';
        $user = new \stdClass();
        $courseid = get_config('tool_smsimport', 'smscourse');
        // Get SMS school config.
        $school = self::get_sms_school(array('schoolno' => $schoolno));
        $cohortid = $school->cohortid;
        // Get users from the external API.
         $smsusers = self::get_sms_school_data($schoolno);
        //$smsusers = self::get_sms_school_data_test($schoolno);
        // Log record.
        $logrecord = new \stdClass();
        $logrecord->schoolno = $schoolno;
        $logrecord->target  = get_string('loguser', 'tool_smsimport');
        $info = array();
        $logrecord->info = $info;

        foreach ($smsusers as $smsuser) {
            if ($smsuser->$nsn) {
                $userid = $DB->get_field('user', 'id', array('idnumber' => $smsuser->$nsn));
                $user->id = $userid;
                $info['nsn'] = $smsuser->$nsn;
                // User group according to the API.
                $gidnumber = helper::find_groupidnumber($smsuser->profile_field_room, $school->schoolno);
                $smsgroupid = $DB->get_field('groups',  'id', array('idnumber' => $gidnumber));
                // User groups in the site.
                $usergroups = groups_get_user_groups($courseid, $userid);
                mtrace("gidnumber {$gidnumber} smsgroupid {$smsgroupid}");

                // If the user is not in the group the API says it should then remove the user from the group.
                mtrace("Checking groups for user {$smsuser->$nsn} {$smsuser->firstname} {$smsuser->surname}");
                $logrecord->action  = get_string('logupdate', 'tool_smsimport');
                foreach($usergroups[0] as $usergroup) {
                    mtrace("User {$smsuser->$nsn} groups found {$usergroup}");
                    if ($smsgroupid != $usergroup) {
                        mtrace("Matching groups for {$smsuser->$nsn} ");
                        $records = $DB->get_records('tool_sms_school_groups', array('groupid' => $usergroup));
                        foreach ($records as $record) {
                            if ($record->schoolid == $school->id) {
                                // If the group belongs to the current school then the user has moved groups so remove old groups.
                                mtrace("User {$smsuser->$nsn} removed from group: {$usergroup}  cohort: {$cohortid}");
                                groups_remove_member($usergroup, $userid);
                                // Log event for user changes.
                                $info['groupremove'] = $gidnumber;
                                $logrecord->info = $info;
                                self::add_sms_log($logrecord, $logsource);
                            } else {
                                // If the group belongs to the other school that the user does not belong to then remove the orphan groups.
                                $oldcohortid = $DB->get_field('tool_sms_school', 'cohortid', array('id' => $record->schoolid));
                                if (!cohort_is_member($oldcohortid, $userid)) {
                                    mtrace("User {$smsuser->$nsn} removed from group: {$usergroup} cohort: {$oldcohortid}");
                                    groups_remove_member($usergroup, $userid);
                                    // Log event for user changes.
                                    $info['groupremove'] = $gidnumber;
                                    $logrecord->info = $info;
                                    $logrecord->userid = $userid;
                                    self::add_sms_log($logrecord, $logsource);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Import students from a SMS school.
     *
     * Sample data from Edge
     * [firstname] => Ariel
     * [surname] => Abbott
     * [profile_field_DOB] => 09/11/2009
     * [profile_field_year] => 6
     * [profile_field_room] => Room 1
     * [profile_field_gender] => Not Stated / Kaore e ki
     * [profile_field_ethnicity] => African
     * [national student number] => 9661002980
     *
     * @param string $schoolid School schoolno
     * @param string $logsource The source that executes this; cron or web.
     * @return mixed
     */
    public static function import_sms_school_users($schoolno, $logsource = 'cron') {
        // Pending: Add support for CSV file import.
        global $DB, $CFG, $SITE, $USER;
        $nsn='national student number';
        // Get SMS school config.
        $school = self::get_sms_school(array('schoolno' => $schoolno));
        $cohortid = $school->cohortid;
        // Get users from the external API.
        $smsusers = self::get_sms_school_data($schoolno);
        //$smsusers = self::get_sms_school_data_test($schoolno);
        // Log record.
        $logrecord = new \stdClass();
        $logrecord->schoolno = $schoolno;
        $logrecord->target  = get_string('loguser', 'tool_smsimport');
        $info = array();
        $logrecord->info = $info;
        $total = 0;
        $newusers = 0;
        $updateusers = 0;
        $profilefields = get_config('tool_smsimport', 'smsuserfields');
        $profilefields = explode(',', $profilefields);
        $groups = helper::get_sms_school_groups($school->id, 'schoolid');
        if (empty($groups) || $groups == false) {
            $logrecord->error = 'lognogroups';
            $logrecord->other = 'lognogroupshelp';
        } else {
            $groupsoutput = json_encode($groups);
            mtrace ("Import begins for school {$school->name}");
            mtrace ("Groups to be imported {$groupsoutput}");
            foreach($smsusers as $smsuser) {
                $total++;
                $user = new \stdClass();
                $user->firstname = $smsuser->firstname;
                $user->lastname = $smsuser->surname;
                $user->idnumber = $smsuser->$nsn;
                $user->profile_field_school = $school->name;
                $user->mnethostid = $CFG->mnet_localhost_id;
                $user->username = $smsuser->$nsn;
                $user->email = $user->firstname."_".$user->lastname."@invalid.com";
                $user->auth = 'webservice';
                $user->deleted = 0;
                $user->suspended = 0;
                $gidnumber = helper::find_groupidnumber($smsuser->profile_field_room, $school->schoolno);

                $groupid = $DB->get_field('groups',  'id', array('idnumber' => $gidnumber));
                /* Check if user is in the group to be imported.
                    We check against the group idnumber which is the groupID from the endpoint
                    We rely on the idnumber and not the name of the group.
                */
                $found = array_search($gidnumber, array_keys($groups));
                mtrace ("Found: " . $found . " gidnumber: ".$gidnumber . " groupid: ".$groupid . " smsuser->nsn: " .$smsuser->$nsn . " ". $smsuser->firstname . " ". $smsuser->surname);
                if (isset($found) && is_int($found) && $groupid && $smsuser->$nsn) {
                    $sql = "select * from {user} WHERE idnumber = :idnumber OR idnumber = :nozeroidnumber OR username = :username";
                    $params = array('idnumber' => $smsuser->$nsn, 'nozeroidnumber' => ltrim($smsuser->$nsn, '0'), 'username' => $smsuser->$nsn);
                    // Create/update user.
                    if ($records = $DB->get_records_sql($sql, $params)) {
                        $counter = 1;
                        // If there are more than one record found then update the first and delete the others.
                        foreach ($records as $record) {
                            if ($counter == 1) {
                                $userid = $record->id;
                                //$user = array_replace($record, $user);
                                $user = (object) array_merge((array) $record, (array) $user);
                                user_update_user($user, false, false);
                                $logrecord->action  = get_string('logupdate', 'tool_smsimport');
                                $updateusers++;
                                mtrace("User with idnumber {$smsuser->$nsn} updated ");
                            } else {
                                $logrecord->action  = get_string('logdelete', 'tool_smsimport');
                                user_delete_user($user);
                            }
                            $counter++;
                        }
                    } else {
                        $userid = user_create_user($user, false, false);
                        $user->id = $userid;
                        $logrecord->action  = get_string('logcreate', 'tool_smsimport');
                        $newusers++;
                        mtrace("User with idnumber {$smsuser->$nsn} created");
                    }
                    // The userid of the user who is being updated
                    $logrecord->userid = $userid;
                    $info['nsn'] = $smsuser->$nsn;
                    // Prepare user custom profile fields.
                    foreach ($profilefields as $profilefield) {
                        $fieldname = 'profile_field_'.$profilefield;
                        if (isset($smsuser->$fieldname)) {
                            $smsprofilefield = $smsuser->$fieldname;
                        }  else {
                            $newfieldname = strtolower($fieldname);
                            $smsprofilefield = $smsuser->$newfieldname;
                        }
                        $fieldvalue = self::sms_data_mapping($profilefield, $smsprofilefield);
                        $user->$fieldname = $fieldvalue;
                    }
                    // Save user custom profile fields.
                    profile_save_data($user);
                    // Reset values.
                    $logrecord->error = '';
                    $logrecord->other = '';
                    $transfererror = '';
                    // If student exists in other schools then deal with transfer-in/transfer-out
                    $records = $DB->get_records_select('cohort_members', 'cohortid != :cohortid AND userid = :userid',
                    ['cohortid' => $cohortid, 'userid' => $userid], '',  '*');
                    if ($records && !empty($records)) {
                        print "<pre>";print_r($records);print"</pre>";
                        foreach($records as $record) {
                            $oldcohortid = $record->cohortid;
                            $oldschool = self::get_sms_school(array('cohortid' => $oldcohortid));
                            if (isset($school->transferin) && $school->transferin) {
                                if (!groups_is_member($groupid, $userid)) {
                                    $info['groupadd'] = $gidnumber;
                                    groups_add_member($groupid, $userid);
                                    mtrace("Add {$smsuser->$nsn} to group: {$groupid}");
                                }
                                if (!cohort_is_member($cohortid, $userid)) {
                                    mtrace("Add {$smsuser->$nsn} to cohort: {$cohortid}");
                                    cohort_add_member($cohortid, $userid);
                                    $info['transferin'] = $school->schoolno;
                                }
                            } else {
                                $transfererror = 'lognoregister';
                                $logrecord->error = $transfererror;
                                $logrecord->other = $transfererror.'help';
                            }

                            if (isset($oldschool->transferout) && $oldschool->transferout) {
                                mtrace("Remove {$smsuser->$nsn} from cohort: {$oldschool->cohortid}");
                                if (cohort_is_member($oldschool->cohortid, $userid)) {
                                    cohort_remove_member($oldschool->cohortid, $userid);
                                    $info['transferout'] = $oldschool->schoolno;
                                }
                            } else {
                                $transfererror = 'logduplicate';
                                $logrecord->error = $transfererror;
                                $logrecord->other = $transfererror.'help';
                            }
                        }
                    } else {
                        if (!cohort_is_member($cohortid, $userid)) {
                            // We do not worry about transferin and consider this a fresh student.
                            mtrace("Add {$smsuser->$nsn} to cohort: {$cohortid}");
                            cohort_add_member($cohortid, $userid);
                        }
                        if (!groups_is_member($groupid, $userid)) {
                            mtrace("Checking .. if {$smsuser->$nsn} is a member of group: {$groupid}");
                            $info['groupadd'] = $gidnumber;
                            mtrace("Add {$smsuser->$nsn} to group: {$groupid}");
                            groups_add_member($groupid, $userid);
                        }
                    }
                    // Log event for user changes.
                    $logrecord->info = $info;
                    self::add_sms_log($logrecord, $logsource);
                }
            }
        }
        // Log event for sync.
        $result = "Total users in external source: {$total}
        Total users created in {$SITE->fullname}: {$newusers}
        Total users updated in {$SITE->fullname}: {$updateusers} ";
        mtrace($result);
        $logrecord->action  = get_string('logsync', 'tool_smsimport');
        $summary['total'] = $total;
        $summary['newusers'] = $newusers;
        $summary['updateusers'] = $updateusers;
        if (!empty($transfererror))  {
            $logrecord->error = 'logerrorsync';
            $logrecord->other = 'logerrorsynchelp';
        }
        $logrecord->info = $summary;
        $logrecord->userid = $USER->id; // The userid of the logged user who is running the script.
        self::add_sms_log($logrecord, $logsource, true);
        return true;
    }


    /**
     * Get SMS groups name from API.
     *
     * @param int $gidnumber Group IDnumber
     * @param string $schoolno SMS School number
     * @return mixed
     */
    public static function find_groupname($gidnumber, $schoolno) {
        $smsgroups = self::get_sms_group($schoolno);
        foreach($smsgroups as $key => $value) {
            if($key == $gidnumber) {
                return $value;
            }
        }
        return 0;
    }


    /**
     * Get SMS groups name from API.
     *
     * @param string $groupname Group name.
     * @param string $schoolno SMS School number
     * @return mixed
     */
    public static function find_groupidnumber($groupname, $schoolno) {
        $smsgroups = self::get_sms_group($schoolno);
        $groupname = str_replace(' ', '', $groupname);
        foreach($smsgroups as $key => $value) {
            $value = str_replace(' ', '', $value);
            if($value == $groupname) {
                return $key;
            }
        }
        return 0;
    }

    /**
     * Tranlate user data from the SMS into the supported
     * format of the various user custom fields.
     *
     * @param $name name of the user custom field
     * @param $value value of the user custom field
     * @return string translated data value
     */
    public static function sms_data_mapping($name, $value) {
        $finalvalue = '';
        $customfield = profile_get_custom_field_data_by_shortname($name);
        if (empty($customfield)){
            $customfield = profile_get_custom_field_data_by_shortname(ucwords($name));
        }
        $name = strtolower($name);
        switch ($name) {
            // Format e.g. : Australian
            case "ethnicity":
            $sitevalue = explode("\n", $customfield->param1);
            $ehtnicities = explode("/", $value);
            foreach($ehtnicities as $ethnicity) {
                if($finalvalue = local_organisations_convert_ethnicity_to_category($ethnicity)) {
                    return $finalvalue;
                }
            }
            break;

            case "gender":
            // Format e.g. : Female
            $sitevalue = explode("\n", $customfield->param1);
            $value = explode(" / ", $value);
            $common = array_intersect($sitevalue, $value);
            if ($common) {
                // Get first value common in the two arrays.
                $finalvalue = reset($common);
            } else {
                // Get last value set in the profile field.
                $finalvalue = end($sitevalue);
            }
            break;

            case "year":
            // Format e.g. : 7
            $yearinwords = array(
                1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
                5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
                10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen'
            );
            $value = strtolower($value);
            if((int)($value)){
                $finalvalue = $value;
            } else {
                if(strpos($value, 'year') !== false) {
                $finalvalue = str_replace('year', '', $value) ;
                }
                elseif(strpos($value, 'y') !== false) {
                $finalvalue = str_replace('y', '', $value) ;
                }
                elseif(($key = array_search($value, $yearinwords)) != 0) {
                $finalvalue = $key;
                }
            }
            $finalvalue = (int)($finalvalue);
            break;

            case "dob":
            // Format e.g. : 2019-03-25
            $value = explode("/", $value);
            $year = $value[2];
            $month = $value[1];
            $day = $value[0];
            $finalvalue = $year.'-'.$month.'-'.$day;
            break;

            default:
            $finalvalue = $value;
            }

            return $finalvalue;
        }

    /**
     * Get SMS groups data from API for the current year.
     *
     * @param string $schoolid School schoolno
     * @return mixed
     */
    public static function get_sms_group($schoolno) {
        if (!empty(self::$smsgroups[$schoolno])) {
            $smsgroups = self::$smsgroups[$schoolno];
        } else {
            $response = self::get_sms_token($schoolno);
            if ($response) {
                // Get school data from the API endpoint.
                $curl = new \curl();
                $url = $response->urlgroup;
                $appid = 'appId: '. $response->key;
                $authorization = "Authorization: ". $response->token_type. " " .$response->access_token;
                $post = array(
                    'CURLOPT_HTTPHEADER' => array(
                        $authorization,
                        $appid
                    )
                );
                $year = date('Y');
                $safeguard = get_config('tool_smsimport', 'safeguard');
                $result = json_decode($curl->get($url."/".$year, NULL, $post));
                if ($result && count($result) >= $safeguard) {

                    foreach($result as $key => $value) {
                        $smsgroups[$value->GroupNo] = $value->GroupName;
                    }
                self::$smsgroups[$schoolno] = $smsgroups;
                } else {
                    // Log error.
                    $logrecord = new \stdClass();
                    $logrecord->schoolno = $schoolno;
                    $logrecord->target = get_string('logschool', 'tool_smsimport');
                    $logrecord->action = get_string('logsync', 'tool_smsimport');
                    $logrecord->error = 'lognodata';
                    $logrecord->other = 'lognodatahelp';
                    $info = array();
                    $info['logendpoint'] = $response->urlgroup;
                    $logrecord->info = $info;
                    self::add_sms_log($logrecord, '', true);
                    // Throwing an exception in the task will mean that it isn't removed from the queue and is tried again.
                    throw new \moodle_exception($logrecord->other);
                }
            }
        }
        return $smsgroups;
    }

    /**
     * Get SMS token from API.
     *
     * @param string $schoolid School schoolno
     * @return object
     */
    public static function get_sms_token($schoolno) {
        global $DB;
        // Get SMS API details.
        $param = array('id' => 1); // Pending.
        $record = $DB->get_record('tool_sms', $param);
        // Get Token to access the API endpoint.
        $curl = new \curl();
        $url = $record->url1;
        $appid = $record->key;
        $appSecret = $record->secret;
        $schoolNo = $schoolno;
        $grant_type = 'school';
        //$post_string = 'grant_type=school&appId=5E9E386B-0E8C-42C6-A8E0-99308E6F8CFE&appSecret=vmpeVtGdLS7Eq4cAXy610Tq67wWWM8&schoolNo=999';
        $post_string = 'grant_type='.$grant_type.'&appId='.$appid.'&appSecret='.$appSecret.'&schoolNo='.$schoolNo;
        $response = $curl->put($url, $post_string);
        $response = json_decode($response);
        $response->key = $record->key;
        $response->urlschool = $record->url2;
        $response->urlgroup = $record->url3;
        if (!empty($response->error)) {
            throw new \moodle_exception($response->error);
        } else {
            return ($response);
        }
    }

    /**
     * Add log entries to the SMS log table.
     *
     * @param object $data
     * @param string $origin cron or web
     * @param object $email boolean to determine if admin is to be notified by email or not.
     * @return boolean
     */
    public static function add_sms_log($data, $origin = 'cron', $email = NULL) {
        global $DB, $USER;
        $data->ip = '';
        $data->timecreated = time();
        $data->origin = $origin;
        if ($origin  == 'web') {
            $data->ip = $_SERVER['REMOTE_ADDR'];
        }
        if (!(isset($data->userid))) {
            $data->userid = $USER->id;
        }
        if (isset($data->info)) {
            $data->info = json_encode($data->info);
        }
        $result = $DB->insert_record('tool_sms_school_log', $data);

        if ($email && !empty($data->error)) {
            $error = helper::extract_strings($data->error);
            $other = helper::extract_strings($data->other);
            $info = helper::extract_strings($data->info);
            $subject = get_string('logemailsubject', 'tool_smsimport');
            $sender = \core_user::get_noreply_user();
            $recipient = \core_user::get_support_user();
            $a = array(
                'subject' => get_string('logemailsubject', 'tool_smsimport'),
                'schoolno' => $data->schoolno,
                'error' => $error,
                'other' => $other,
                'info' => $info,
            );
            $subject = get_string('logemailsubject', 'tool_smsimport');
            $sender = \core_user::get_noreply_user();
            $recipient = \core_user::get_support_user();
            $message = get_string('logemailmessage', 'tool_smsimport', $a);
            mtrace("Email notification sent to {$recipient->email}");
            email_to_user($recipient, $sender, $subject, strip_tags($message), $message);
        }
        return $result;
    }

    public static function extract_strings($value) {
        $result = '';
        if ($value == null || $value == '' || empty($value)) {
            return $result;
        }
        if (is_string($value)){
            $value = trim($value);
            if (clean_param($value, PARAM_STRINGID) !== '') {
                $result = get_string($value, 'tool_smsimport');
            }
        } else {
            foreach ($value as $key => $val) {

                if (clean_param($key, PARAM_STRINGID) !== '') {
                    $label = get_string($key, 'tool_smsimport');
                    $result .=  "\n".$label. ": ". $val;
                }

            }
        }
        return $result;
    }

    /**
     * Get SMS school TEST data from pretend API endpoint.
     *
     * @param string $schoolid School schoolno
     * @return array
     */
    public static function get_sms_school_data_test($schoolno) {
        $nsn = 'national student number';
        $response = self::get_sms_token($schoolno);
        $student = array();
        /*
        $student[0]->firstname = 'Clementine';
        $student[0]->surname = 'Borrie';
        $student[0]->profile_field_DOB = '28/01/2019';
        $student[0]->profile_field_year = '1';
        $student[0]->profile_field_room = 'Room 1';
        $student[0]->profile_field_gender = 'Female / Wahine';
        $student[0]->profile_field_ethnicity = 'NZ European/Pākehā';
        $student[0]->$nsn = '0160654556';

        $student[1]->firstname = 'Romae';
        $student[1]->surname = 'Brown';
        $student[1]->profile_field_DOB = '28/03/2019';
        $student[1]->profile_field_year = '1';
        $student[1]->profile_field_room = 'Room 1';
        $student[1]->profile_field_gender = 'Female / Wahine';
        $student[1]->profile_field_ethnicity = 'Chinese';
        $student[1]->$nsn = '0160894858';

        $student[1]->firstname = 'George';
        $student[1]->surname = 'Dobson';
        $student[1]->profile_field_DOB = '05/02/2019';
        $student[1]->profile_field_year = '1';
        $student[1]->profile_field_room = 'Room 1';
        $student[1]->profile_field_gender = 'Male / Tāne';
        $student[1]->profile_field_ethnicity = 'NZ European/Pākehā';
        $student[1]->$nsn = '0161977113';

        $student[1]->firstname = 'Harper';
        $student[1]->surname = 'Thomson';
        $student[1]->profile_field_DOB = '25/02/2019';
        $student[1]->profile_field_year = '1';
        $student[1]->profile_field_room = 'Room 1';
        $student[1]->profile_field_gender = 'Female / Wahine';
        $student[1]->profile_field_ethnicity = 'Māori';
        $student[1]->$nsn = '0163115307';

        $student[1]->firstname = 'Roa';
        $student[1]->surname = 'Ciccoricco';
        $student[1]->profile_field_DOB = '03/12/2018';
        $student[1]->profile_field_year = '1';
        $student[1]->profile_field_room = 'Room 9';
        $student[1]->profile_field_gender = 'Female / Wahine';
        $student[1]->profile_field_ethnicity = 'NZ European/Pākehā';
        $student[1]->$nsn = '0161833937';
        */

        $student[1]->firstname = 'Ryder';
        $student[1]->surname = 'Smith';
        $student[1]->profile_field_DOB = '10/10/2013';
        $student[1]->profile_field_year = '6';
        $student[1]->profile_field_room = 'Ngata';
        $student[1]->profile_field_gender = 'Male / Tāne';
        $student[1]->profile_field_ethnicity = 'Other Southeast Asian';
        $student[1]->$nsn = '0149291995';

        $safeguard = get_config('tool_smsimport', 'safeguard');
        $result = $student;
        if ($response  && count($result) >= $safeguard) {
            return $result;
        } else {
            // Log error.
            $logrecord = new \stdClass();
            $logrecord->schoolno = $schoolno;
            $logrecord->target = get_string('logschool', 'tool_smsimport');
            $logrecord->action = get_string('logsync', 'tool_smsimport');
            $logrecord->error = 'lognodata';
            $logrecord->other = 'lognodatahelp';
            $info = array();
            $info['logendpoint'] = $response->urlschool;
            $logrecord->info = $info;
            self::add_sms_log($logrecord, '', true);
            // Throwing an exception in the task will mean that it isn't removed from the queue and is tried again.
            throw new \moodle_exception($logrecord->other);
        }
    }

    /**
     * Get SMS school data from API.
     *
     * @param string $schoolid School schoolno
     * @return mixed array or throw exception
     * @throws moodle_exception
     */
    public static function get_sms_school_data($schoolno) {
        $response = self::get_sms_token($schoolno);
        if ($response && isset($response->token_type)) {
            // Get school data from the API endpoint.
            $curl = new \curl();
            $url = $response->urlschool;
            $appid = 'appId: '. $response->key;
            $authorization = "Authorization: ". $response->token_type. " " .$response->access_token;
            $options = array(
                'CURLOPT_HTTPHEADER' => array(
                    $authorization,
                    $appid
                )
            );
            $safeguard = get_config('tool_smsimport', 'safeguard');
            $result = json_decode($curl->get($url, NULL, $options));
            if ($result && count($result) >= $safeguard) {
                return $result;
            } else {
                // Log error.
                $logrecord = new \stdClass();
                $logrecord->schoolno = $schoolno;
                $logrecord->target = get_string('logschool', 'tool_smsimport');
                $logrecord->action = get_string('logsync', 'tool_smsimport');
                $logrecord->error = 'lognodata';
                $logrecord->other = 'lognodatahelp';
                $info = array();
                $info['logendpoint'] = $response->urlschool;
                $logrecord->info = $info;
                self::add_sms_log($logrecord, '', true);
                // Throwing an exception in the task will mean that it isn't removed from the queue and is tried again.
                throw new \moodle_exception($logrecord->other);
            }
        }
    }

    /**
     * Add SMS school details.
     *
     * @param object $data School data
     * @return int The ID of the school added
     */
    public static function add_sms_school($data) {
        global $DB;
        $data->timecreated = time();
        $data->timemodified = time();
        // Check Edge API for school details using schoolno
        if (self::get_sms_token($data->schoolno)) {
            $data->smsid = 1;// Pending.
            return $DB->insert_record('tool_sms_school', $data, true, false);
        } else {
            throw new \moodle_exception('errorschoolnotfound', 'tool_smsimport');
        }
    }

    /**
     * Delete SMS school details.
     *
     * @param object $data School data
     * @return int The ID of the school added
     */
    public static function delete_sms_school($id) {
        global $DB;
        $smsschool = self::get_sms_school(array('id' => $id));
        $cohortid = $smsschool->cohortid;
        try {
            $cohortmembers = $DB->get_records_sql("SELECT u.id FROM {user} u, {cohort_members} cm
            WHERE u.id = cm.userid AND cm.cohortid = ? AND u.auth = 'webservice'
            ORDER BY lastname ASC, firstname ASC", array($cohortid));
            $userids = array_keys($cohortmembers);

            foreach ($userids as $userid) {
                $user = $DB->get_record('user', array ('id' => $userid));
                user_delete_user($user, false, false);
            }
            $DB->delete_records('tool_sms_school_log', array('schoolno' => $smsschool->schoolno));
            $DB->delete_records('tool_sms_school_groups', array('schoolid' => $id));
            $DB->delete_records('tool_sms_school', ['id' => $id]);
            $result = true;
        }
        catch (\Exception $e) {
            throw new \moodle_exception('errorschoolnotdeleted', 'tool_smsimport', $e);
        }
        // Log record.
        $logrecord = new \stdClass();
        $logrecord->schoolno = $smsschool->schoolno;
        $logrecord->target  = get_string('logschool', 'tool_smsimport');
        $logrecord->action  = get_string('logdelete', 'tool_smsimport');
        self::add_sms_log($logrecord);
        return $result;
    }
}
