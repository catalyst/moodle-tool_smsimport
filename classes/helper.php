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
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once("{$CFG->dirroot}/local/organisations/locallib.php");
require_once("{$CFG->dirroot}/cohort/lib.php");
require_once("{$CFG->dirroot}/user/lib.php");
require_once("{$CFG->dirroot}/user/profile/lib.php");
require_once("{$CFG->dirroot}/group/lib.php");
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/csvlib.class.php');

 /**
  * tool_smsimport entity class for helper functions
  *
  * @package   tool_smsimport
  * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
  * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  */
class helper {

    /**
     * @var array Store the groups retrived by the API.
     */
    private static $smsgroups = [];

    /**
     * Get SMS details.
     *
     * @param string $value field value
     * @param string $key field name
     * @return object
     */
    public static function get_sms($value, $key) {
        global $DB;
        $record = $DB->get_record('tool_sms', [$key => $value]);
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
     * @param string $value field value
     * @param string $key field name     *
     * @return mixed | boolean
     */
    public static function get_sms_school_groups($value, $key) {
        global $DB;
        $groups = [];
        $sql = "select groupid, idnumber, g.name from {tool_sms_school_groups} sg JOIN {groups} g on sg.groupid = g.id
        WHERE {$key} = :value";
        $params = ['value' => $value];
        if ($linkedgroups = $DB->get_records_sql($sql, $params)) {
            foreach ($linkedgroups as $key => $value) {
                if ($value->idnumber) {
                    $groups[$value->idnumber] = $value->name;
                }
            }
            return $groups;
        } else {
            return false;
        }
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
    public static function check_local_organisations() {
        $pluginman = \core_plugin_manager::instance();
        $plugins = $pluginman->get_installed_plugins('local');
        if (array_key_exists('organisations', $plugins)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Edit SMS school details.
     *
     * @param object $data School details
     * @return object
     */
    public static function save_sms_school($data) {
        global $DB;
        $data->timemodified = time();
        if (!isset($data->transferin)) {
            $data->transferin = 0;
        }
        if (!isset($data->transferout)) {
            $data->transferout = 0;
        }
        if (!isset($data->suspend)) {
            $data->suspend = 0;
        }
        $data->name = trim($data->name);
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
        $records = $DB->get_records('cohort_members', ['cohortid' => $cohortid]);
        foreach ($records as $record) {
            if ($DB->get_field('user', 'auth', ['id' => $record->userid]) == 'webservice') {
                $sql = "UPDATE {user} SET auth = :nologin WHERE id = :id";
                $params = ['nologin' => 'nologin', 'id' => $record->userid];
                $DB->execute($sql, $params);
            }
        }
    }

    /**
     * Remove diacritics from string.
     *
     * @param string $data data
     * @return string
     */
    public static function remove_accent($data) {
        $normalized = \Normalizer::normalize($data, \Normalizer::NFD);
        $data = preg_replace('/[\x{0300}-\x{036F}]/u', '', $normalized);
        return $data;
    }

    /**
     * Add and Edit groups and course to school.
     *
     * @param array  $data School data
     * @param string $action The state of change the school is in; select or edit.
     * @param array  $groups School's groups existing before the change.
     * @return object
     */
    public static function save_sms_school_details($data, $action = null, $groups = null) {
        global $DB;
        $data = (object)$data;
        $data->timemodified = time();
        $result = new stdClass();
        $courseid = get_config('tool_smsimport', 'smscourse');
        $localorg = self::check_local_organisations();
        // Log record.
        $logrecord = new stdClass();
        $logrecord->schoolno = $data->schoolno;
        $logrecord->target = get_string('logschool', 'tool_smsimport');
        $logsource = 'web';
        if (isset($data->cohortid)) {
            $logaction = get_string('logcreate', 'tool_smsimport');
            $cohortid = $data->cohortid;
            $schoolname = $data->name;
            // Plugin local_organisations school update.
            $data->organisationname = $schoolname;
            $data->organisationcode = $data->schoolno;
            $data->organisation_school_type = school::STANDARD;
            if ($cohortid == 0) {
                // Create new school and cohort.
                $result->message[] = "Create a new school: {$schoolname} ";
                if ($action == 'edit') {
                    if ($localorg) {
                        $cohortid = local_organisations_create_organisation($data, 'tool_smsimport');
                    } else {
                        $cohortid = cohort_add_cohort($data);
                    }
                    $data->cohortid = $cohortid;
                    $result->message[] = "Created school with cohort ".$cohortid;
                }
            } else {
                $schoolname = $DB->get_field('cohort', 'name', ['id' => $cohortid]);
                $logaction = get_string('logupdate', 'tool_smsimport');
            }
            // Unlink a school.
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
                    // Link course to cohort.
                    if (!self::save_cohort_course($cohortid, $schoolname, $courseid)) {
                        throw new \moodle_exception('coursecoutnotbelinked', 'tool_smsimport');
                    }
                }
                // Link cohort to SMS school.
                self::save_sms_school($data);
                // Log event.
                $logrecord->action = $logaction;
                $info = ['cohortid' => $cohortid];
                $logrecord->info = $info;
                self::add_sms_log($logrecord, $logsource);
            }

            if (isset($data->groupsselect)) {
                // Delete groups from existing groups.
                if ($groups) {
                    $groupsexist = array_keys($groups);
                    $deletegroups = array_diff($groupsexist, $data->groupsselect);
                    if ($deletegroups) {
                        foreach ($deletegroups as $deletegroup) {
                            $deletegroupdata = groups_get_group_by_idnumber($courseid, $deletegroup);
                            $dgroupnamedisplay = str_replace($schoolname, '', $deletegroupdata->name);
                            $result->message[] = "Unlink SMS school {$schoolname} from group:
                                {$dgroupnamedisplay} ({$deletegroup})";
                            if ($action == 'edit') {
                                self::delete_sms_school_groups($data->id, $deletegroupdata->id);
                                $info['groupremove'] = $deletegroup;
                                $logrecord->action = get_string('logdelete', 'tool_smsimport');
                                $logrecord->info = $info;
                                self::add_sms_log($logrecord, $logsource);
                            }
                        }
                    }
                }

                foreach ($data->groupsselect as $gidnumber) {
                    $logaction = '';
                    if (!$gidnumber) {
                        break;
                    }
                    $groupname = self::find_groupname($gidnumber, $data);
                    $groupnamedisplay = str_ireplace($schoolname, '', $groupname);
                    $groupdata = groups_get_group_by_idnumber($courseid, $gidnumber);
                    // Check if groups exists in core.
                    if (!empty($groupdata)) {
                        $groupid = $groupdata->id;
                        // Update group name.
                        $schoolname = $DB->get_field('cohort', 'name', ['id' => $cohortid]);
                        $groupdata->name = $schoolname.$groupname;
                        $logaction = get_string('logupdate', 'tool_smsimport');
                    } else {
                        // If the SMS school is linked to existing school.
                        if ($localorg && $records = local_organisations_get_organisation_groups($cohortid, $courseid)) {
                            $ngroupname = str_replace(' ', '', $groupname);
                            // Check if the existing school's group match the SMS school group.
                            $logaction = get_string('logcreate', 'tool_smsimport');
                            foreach ($records as $record) {
                                $norggroupname = str_replace(' ', '', $record->orggroupname);
                                if ($ngroupname == $norggroupname && $gidnumber != $record->idnumber) {
                                    $logaction = get_string('logupdate', 'tool_smsimport');
                                    $groupid = $record->id;
                                    $groupdata = groups_get_group($groupid);
                                }
                            }
                        } else {
                            // Create new group and add to tool_smsimport groups table.
                            $logaction = get_string('logcreate', 'tool_smsimport');
                        }
                    }
                    // Unlink a school group.
                    // Only remove idnumbers from the groups.
                    if ($action == 'edit' && isset($data->unlink) && $data->unlink) {
                        self::delete_sms_school_groups($data->id, $groupdata->id);
                        $info['groupremove'] = $gidnumber;
                        $logrecord->action = get_string('logdelete', 'tool_smsimport');
                        $logrecord->info = $info;
                        self::add_sms_log($logrecord, $logsource);
                    }
                    // Add/Update groups.
                    if (empty($data->unlink) && empty($deletegroups)) {
                        $schoolname = $DB->get_field('cohort', 'name', ['id' => $cohortid]);
                        $groupname = $schoolname."".$groupname;
                        if ($logaction == get_string('logcreate', 'tool_smsimport') ) {
                            $result->message[] = "Link SMS school {$schoolname} to new group: {$groupnamedisplay} ({$gidnumber})";
                            $newgroupdata = new stdClass();
                            $newgroupdata->courseid = $courseid;
                            $newgroupdata->name = $groupname;
                            $newgroupdata->idnumber = $gidnumber;
                            if ($action == 'edit') {
                                $info['groupadd'] = $gidnumber;
                                $groupid = groups_create_group($newgroupdata);
                            }
                        }
                        if ($logaction == get_string('logupdate', 'tool_smsimport')) {
                            $result->message[] = "Link SMS school {$schoolname} to group: {$groupnamedisplay} ({$gidnumber})";
                            $groupdata->idnumber = $gidnumber;
                            $groupdata->name = $groupname;
                            if ($action == 'edit') {
                                $info['groupupdate'] = $gidnumber;
                                groups_update_group($groupdata);
                            }
                        }
                        if ($action == 'edit') {
                            // Link groups to local organisation schools.
                            self::save_sms_school_groups($data->id, $groupid);
                            if ($localorg) {
                                local_organisations_store_organisation_group($cohortid, $groupid);
                            }
                            $data->groupid = $groupid;
                            // Log event.
                            if ($logaction) {
                                $logrecord->action = $logaction;
                                $logrecord->info = $info;
                                self::add_sms_log($logrecord, $logsource);
                            }
                        }
                    }
                } // forloop for groupselect ends.
            }
        }
        $result->status = 1;
        return $result;
    }

    /**
     * Store new course to cohort enrol linking table. Returns success or failure.
     *
     * Cannot use plugin->add_instance as it is leading to timeout due to the massive cohorts for the assessments course
     *       $plugin = enrol_get_plugin($type);
     *       $plugin->add_instance($course, $data);
     * @param int $cohortid
     * @param string $cohortname
     * @param int $courseid
     * @return bool
     */
    public static function save_cohort_course($cohortid, $cohortname, $courseid) {
        global $DB;
        $type = 'cohort';
        $instance = new stdClass();
        $record = $DB->get_records('enrol', ['courseid' => $courseid, 'enrol' => $type, 'customint1' => $cohortid], '', '*', 0, 1);
        if ($record) {
            foreach (reset($record) as $key => $value) {
                $instance->$key = $value;
            }
        }
        $fields = [
            'customint2' => -1,
            'customint1' => $cohortid,
            'roleid' => 5,
            'status' => 0,
            'courseid' => $courseid,
            'enrol' => $type,
            'name' => $cohortname,
        ];
        foreach ($fields as $field => $value) {
            $instance->$field = $value;
        }
        if (empty($record)) {
            $instance->status         = ENROL_INSTANCE_ENABLED;
            $instance->enrolstartdate = 0;
            $instance->enrolenddate   = 0;
            $instance->timemodified   = time();
            $instance->timecreated    = $instance->timemodified;
            $instance->sortorder      = $DB->get_field('enrol', 'COALESCE(MAX(sortorder), -1) + 1', ['courseid' => $courseid]);
            $instance->id = $DB->insert_record('enrol', $instance);
            \core\event\enrol_instance_created::create_from_record($instance)->trigger();
        } else {
            $instance->timemodified = time();
            $update = $DB->update_record('enrol', $instance);
            if ($update) {
                \core\event\enrol_instance_updated::create_from_record($instance)->trigger();
            }
        }
        return true;
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
        $params = ['schoolid' => $schoolid, 'groupid' => $groupid];
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
        // Remove SMS group idnumber from core group.
        $groupdata = groups_get_group($groupid);
        $groupdata->idnumber = '';
        groups_update_group($groupdata);
        // Delete group from sms groups.
        $params = ['schoolid' => $schoolid, 'groupid' => $groupid];
        $id = $DB->get_field('tool_sms_school_groups', 'id', $params);
        if ($id) {
            $DB->delete_records('tool_sms_school_groups', ['id' => $id]);
        }
    }

    /**
     * Checks if a user has teacher's role.
     *
     * @param integer $userid User ID.
     * @param integer $courseid Course ID.
     * @return boolean
     */
    public static function is_teacher($userid, $courseid) {
        global $DB;
        if (self::check_local_organisations()) {
            $isteacher = false;
            $context = \context_course::instance($courseid);
            // Get roles for the course.
            $roles = $DB->get_records_sql("SELECT DISTINCT(ra.id), r.id AS role, r.shortname
                FROM {role_assignments} ra, {role} r
                WHERE userid = ?
                AND contextid = ?
                AND r.id = ra.roleid", [$userid, $context->id]);
            foreach ($roles as $role) {
                if ($role->shortname == 'teacher') {
                    $isteacher = true;
                    break;
                }
            }
        } else {
            $isteacher = true;
        }
        return $isteacher;
    }

    /**
     * Cleanup groups and other stuff from students in a SMS school.     *
     *
     * @param object $school School school details
     * @param string $logsource The source that executes this; cron or web.
     * @return mixed
     */
    public static function cleanup_sms_school_users($school, $logsource = 'cron') {
        global $DB;
        $nsn = 'national student number';
        $smsusersnsn = [];
        $linebreak = ($logsource == 'cron') ? "\n" : "<br>";
        $logrecord = new stdClass();
        $logrecord->schoolno = $school->schoolno;
        $logrecord->target  = get_string('loguser', 'tool_smsimport');

        mtrace("Checking current users groups for school {$school->schoolno} ", $linebreak);
        // Get users from the external API.
        $smsusers = self::get_sms_school_data($school);
        foreach ($smsusers as $smsuser) {
            if ($smsuser->$nsn) {
                $smsuser->$nsn = ltrim($smsuser->$nsn, 0);
                $smsuser->lastname = $smsuser->surname;
                $smsusersnsn[] = $smsuser->$nsn;
                $userid = $DB->get_field('user', 'id', ['idnumber' => $smsuser->$nsn, 'auth' => 'webservice']);
                if (!empty($userid)) {
                    $smsuser->userid = $userid;
                    $gidnumber = self::find_groupidnumber($smsuser->profile_field_room, $school);
                    $smsgroupid = $DB->get_field('groups',  'id', ['idnumber' => $gidnumber]);
                    mtrace("SMS user group gidnumber {$gidnumber} groupid {$smsgroupid}", $linebreak);
                    self::remove_users_groups($school, $smsuser, $logrecord, $logsource, $linebreak, $smsgroupid, false);
                }
            }
        }

        mtrace("Checking missing users groups for school {$school->schoolno} ", $linebreak);
        $sql = "select idnumber from {user} u LEFT JOIN {cohort_members} cm ON u.id = cm.userid
                 WHERE cm.cohortid = :cohortid AND u.deleted = 0 AND u.suspended = 0
                 AND u.auth = :authtype AND u.idnumber IS NOT null";
        $params = ['cohortid' => $school->cohortid, 'authtype' => 'webservice'];
        $dbusersnsn = $DB->get_fieldset_sql($sql, $params);
        $missingusers = array_diff($dbusersnsn, $smsusersnsn);
        foreach ($missingusers as $missinguser) {
            $dbuser = $DB->get_record('user', ['idnumber' => $missinguser]);
            $dbuser->$nsn = $missinguser;
            $dbuser->userid = $dbuser->id;
            self::remove_users_groups($school, $dbuser, $logrecord, $logsource, $linebreak, '', true);
        }
    }

    /**
     * Remove a group from a user if it is removed from the external source remove
     * Or all the groups for a user no longer available in the external source
     *
     * @param object $school school details
     * @param object $user user details
     * @param object $logrecord Log record
     * @param string $logsource The source that executes this; cron or web
     * @param string $linebreak line break
     * @param string $smsgroupid User groupid according to the API.
     * @param boolean $missingusers User no longer available in SMS feed.
     *
     * @return void
     */
    public static function remove_users_groups($school, $user, $logrecord, $logsource, $linebreak,
            $smsgroupid = null, $missingusers = false) {
        global $DB;
        $nsn = 'national student number';
        $info = [];
        $logrecord->info = $info;
        $courseid = get_config('tool_smsimport', 'smscourse');
        $cohortid = $school->cohortid;
        $userid = $user->userid;
        mtrace("Checking groups for {$user->firstname} {$user->lastname} {$user->$nsn}", $linebreak);
        if (self::is_teacher($userid, $courseid)) {
            mtrace("User has teacher role, skip groups cleanup", $linebreak);
        } else {
            // User group according to the API.
            // User groups in the site.
            $usergroups = groups_get_user_groups($courseid, $userid);
            $displayusergroups = json_encode($usergroups);
            mtrace("Current user groups {$displayusergroups}", $linebreak);
            $logrecord->action  = get_string('logupdate', 'tool_smsimport');
            foreach ($usergroups[0] as $usergroup) {
                /* If the user is not in the group the API says it should be then remove the user from the group.
                    Or if these are users no longer in the SMS school remove the groups */
                if ((isset($smsgroupid) && $smsgroupid != $usergroup) || $missingusers == true) {
                    // Adding an additional to exclude users transferred to another school.
                    if (cohort_is_member($cohortid, $userid) &&
                        $DB->record_exists('tool_sms_school_groups', ['groupid' => $usergroup])
                    ) {
                        groups_remove_member($usergroup, $userid);
                        mtrace("User {$user->$nsn} removed from groupid: {$usergroup}", $linebreak);
                        $groupidnumber = $DB->get_field('groups',  'idnumber', ['id' => $usergroup]);
                        $info['nsn'] = $user->$nsn;
                        $info['groupremove'] = $groupidnumber;
                        $logrecord->info = $info;
                        $logrecord->userid = $userid;
                        self::add_sms_log($logrecord, $logsource);
                    }
                }
            }
        }
    }

    /**
     * Save user details / profile fields.
     *
     * @param object $user user details modified
     * @param object $smsuser user details from SMS feed
     * @param object $logrecord Log record
     * @param string $logsource The source that executes this; cron or web
     * @param array  $info Log record additional info
     *
     * @return string profile save error
     */
    public static function save_user_details($user, $smsuser, $logrecord, $logsource, $info) {
        $profilefields = get_config('tool_smsimport', 'smsuserfields');
        $profilefields = explode(',', $profilefields);
        // Prepare user custom profile fields.
        foreach ($profilefields as $profilefield) {
            $profileerror = '';
            $logrecord->error = '';
            $logrecord->other = '';
            $fieldname = 'profile_field_'.$profilefield;
            if (isset($smsuser->$fieldname)) {
                $smsprofilefield = $smsuser->$fieldname;
            } else {
                $smsprofilefield = '';
            }
            $result = self::sms_data_mapping($profilefield, $smsprofilefield);
            $user->$fieldname = $result['data'];
            profile_save_data($user);
            if (!empty($result['error'])) {
                $logrecord->error = $result['error'];
                $logrecord->other = $logrecord->error.'help';
                $info['profilefield'] = $profilefield. " ". $smsprofilefield;
                $profileerror = 'lognodataprofilefield';
            }
        }
        if (!empty($profileerror)) {
            $logrecord->info = $info;
            self::add_sms_log($logrecord, $logsource);
        }
        return $profileerror;
    }

    /**
     * Transfer-in/transfer-out users from one school to another.
     *
     * @param object $school School details
     * @param int    $groupid Group ID
     * @param string $usernsn student NSN number
     * @param string $linebreak line break
     * @param object $logrecord Log record
     * @param string $logsource The source that executes this; cron or web.
     * @param array  $info Log record additional info
     *
     * @return string transfer error
     */
    public static function transfer_user_school($school, $groupid, $usernsn,
            $linebreak, $logrecord, $logsource, $info) {
        global $DB;
        $transfererror = '';
        $logrecord->error = '';
        $logrecord->other = '';
        $cohortid = $info['cohortid'];
        $userid = $info['userid'];
        $localorg = self::check_local_organisations();
        // If student exists in other schools then deal with transfer-in/transfer-out.
        $otherrecords = $DB->get_records_select('cohort_members', 'cohortid != :cohortid AND userid = :userid',
        ['cohortid' => $cohortid, 'userid' => $userid], '',  '*');
        $otherschools = json_encode($otherrecords);
        if (!empty($otherrecords)) {
            mtrace("User exists in other schools {$otherschools}.", $linebreak);
            mtrace("Start user transfer-in/transfer-out process.", $linebreak);
            foreach ($otherrecords as $otherrecord) {
                $oldcohortid = $otherrecord->cohortid;
                $oldschool = self::get_sms_school(['cohortid' => $oldcohortid]);
                $info['transferin'] = $school->schoolno;
                $info['transferout'] = $oldschool->schoolno;
                if (empty($oldschool)) {
                    if ($localorg) {
                        $orgschool = school::from_cohort_id($oldcohortid);
                        $oldschool->transferout = $orgschool->get('transferout');
                        $oldschool->cohortid = $oldcohortid;
                    }
                    $oldschool->schoolno = 0;
                    $info['transferout'] = $oldschool->schoolno." ({$oldcohortid})";
                }
                if (isset($oldschool->transferout) && $oldschool->transferout) {
                    // Transfer user out.
                    if (cohort_is_member($oldschool->cohortid, $userid)) {
                        mtrace("Successful transfer-out user from old school", $linebreak);
                        cohort_remove_member($oldschool->cohortid, $userid);
                        // Transfer user in.
                        if (isset($school->transferin) && $school->transferin) {
                            if (!cohort_is_member($cohortid, $userid) && $cohortid > 0) {
                                mtrace("Successful transfer-in user to new school", $linebreak);
                                mtrace("Add {$usernsn} to cohort: {$cohortid}", $linebreak);
                                cohort_add_member($cohortid, $userid);
                            }
                            if (!groups_is_member($groupid, $userid)) {
                                $info['groupadd'] = $groupid;
                                groups_add_member($groupid, $userid);
                                mtrace("Add {$usernsn} to group: {$groupid}", $linebreak);
                            }
                        } else {
                            mtrace("Cannot transfer-in user to new school", $linebreak);
                            $transfererror = 'lognoregister';
                            $logrecord->error = $transfererror;
                            $logrecord->other = $logrecord->error.'help';
                        }
                    }
                } else {
                    mtrace("Cannot transfer-out user from old school", $linebreak);
                    $transfererror = 'logduplicate';
                    $logrecord->error = $transfererror;
                    $logrecord->other = $logrecord->error.'help';
                }
            }
        } else {
            if (!cohort_is_member($cohortid, $userid) && $cohortid > 0) {
                // We do not worry about transferin and consider this a fresh student.
                mtrace("Add user {$usernsn} to cohort: {$cohortid}", $linebreak);
                cohort_add_member($cohortid, $userid);
            }
            if (!groups_is_member($groupid, $userid)) {
                mtrace("Checking .. if user {$usernsn} {$userid} is a member of group: {$groupid}", $linebreak);
                $info['groupadd'] = $groupid;
                mtrace("Add {$usernsn} to group: {$groupid}", $linebreak);
                groups_add_member($groupid, $userid);
            }
        }
        if (!empty($transfererror)) {
            $logrecord->info = $info;
            self::add_sms_log($logrecord, $logsource);
        }
        return $transfererror;
    }

    /**
     * Import students to a school.
     * The users can be imported via the API endpoint to a SMS school or CSV file to a SMS or non SMS school.
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
     * @param object $school School details
     * @param mixed  $smsusers School users
     * @param string $logsource The source that executes this; cron or web.
     * @return mixed
     */
    public static function import_school_users($school, $smsusers, $logsource = 'cron') {
        global $DB, $CFG, $SITE, $USER;
        $courseid = get_config('tool_smsimport', 'smscourse');
        $nsn = 'national student number';
        $total = 0;
        $newusers = 0;
        $updateusers = 0;
        $updateuser = 0;
        $syncerror = '';
        $usersparsed = new stdClass();
        $localorg = self::check_local_organisations();
        // Get SMS school config.
        $cohortid = $school->cohortid;
        if ($logsource == 'cron') {
            // The users are coming from an external API.
            $authtype = 'webservice';
            $linebreak = "\n";
            $groups = self::get_sms_school_groups($school->id, 'schoolid');
        } else {
            if ($localorg) {
                $authtype = 'nologin';
                $linebreak = "<br>";
                $orggroups = local_organisations_get_organisation_groups($cohortid, $courseid);
                foreach ($orggroups as $orggroup) {
                    $orggroupname = str_replace($school->name, '', $orggroup->orggroupname);
                    $groups[$orggroup->id] = $orggroupname;
                }
            }
        }
        // Log record.
        $logrecord = new stdClass();
        $logrecord->schoolno = $school->schoolno;
        $logrecord->target  = get_string('loguser', 'tool_smsimport');
        $info = [];
        $logrecord->info = $info;
        $info['cohortid'] = $cohortid;
        if (empty($groups) || $groups == false) {
            $logrecord->error = 'lognogroups';
            $logrecord->other = 'lognogroupshelp';
        } else {
            $groupsoutput = json_encode($groups);
            mtrace ("Import begins for school {$school->name}", $linebreak);
            mtrace ("-----------------------------------------------------", $linebreak);
            mtrace ("Groups to be imported {$groupsoutput}", $linebreak);
            if (empty($smsusers)) {
                mtrace ("No users found.", $linebreak);
                return false;
            }
            foreach ($smsusers as $smsuser) {
                $logrecord->error = '';
                $logrecord->other = '';
                $transfererror = '';
                $profileerror = '';
                $total++;
                $usernsn = ltrim($smsuser->$nsn, 0);
                $user = new stdClass();
                $user->firstname = ucwords(strtolower($smsuser->firstname));
                $user->lastname = ucwords(strtolower($smsuser->surname));
                $user->idnumber = $usernsn;
                $user->profile_field_school = $school->name;
                $user->mnethostid = $CFG->mnet_localhost_id;
                $firstname = clean_param($smsuser->firstname, PARAM_ALPHANUM);
                $lastname = clean_param($smsuser->surname, PARAM_ALPHANUM);
                $user->username = strtolower($firstname."_".$lastname);
                $user->email = $user->username."@invalid";
                $user->auth = $authtype;
                $user->deleted = 0;
                $user->confirmed = 1;
                if (isset($smsuser->suspended) && $smsuser->suspended == 1) {
                    $user->suspended = $smsuser->suspended;
                } else {
                    $user->suspended = 0;
                }
                $other = $DB->count_records_sql("SELECT count(idnumber) from {user}
                    WHERE username LIKE :username AND idnumber NOT LIKE :idnumber",
                    ['username' => $user->username.'%', 'idnumber' => $user->idnumber]
                );
                if ($other) {
                    $user->username = $user->username.rand(1, 1000);
                }
                // Check if user is in the group to be imported.
                if ($logsource == 'cron') {
                    /*  For the API import cycle.
                        We check against the group idnumber which is the groupID from the endpoint
                        We rely on the idnumber and not the name of the group.
                    */
                    $gidnumber = self::find_groupidnumber($smsuser->profile_field_room, $school);
                    $groupid = $DB->get_field('groups',  'id', ['idnumber' => $gidnumber]);
                } else {
                    /* We have to rely on the group name for this.*/
                    $groupid = array_search($smsuser->profile_field_room, $groups);
                }
                if ($groupid) {
                    mtrace ("Group found ID {$groupid} for" .  " smsuser->nsn: " .$usernsn . " ".
                        $smsuser->firstname . " ". $smsuser->surname, $linebreak);
                } else {
                    mtrace ("Group not found for" .  " smsuser->nsn: " .$usernsn . " ".
                        $smsuser->firstname . " ". $smsuser->surname, $linebreak);
                }
                if ($groupid && $usernsn) {
                    $sql = "select * from {user}
                        WHERE idnumber = :idnumber OR idnumber = :wzeroidnumber AND deleted = 0 AND suspended = 0";
                    $params = ['idnumber' => $usernsn, 'wzeroidnumber' => '0'.$usernsn];
                    $nsnvalue = $usernsn;
                    $info['nsn'] = $nsnvalue;
                    // If the NSN is not a duplicate in this feed.
                    if (empty($usersparsed->$nsnvalue)) {
                        $usersparsed->$nsnvalue = 1;
                        // Create/update user.
                        if ($records = $DB->get_records_sql($sql, $params)) {
                            count($records);
                            $counter = 1;
                            // If there are more than one record found then update the latest record and delete the others.
                            foreach ($records as $record) {
                                $updateuser = 0;
                                if ($counter != count($records)) {
                                    $logrecord->action  = get_string('logdelete', 'tool_smsimport');
                                    user_delete_user($record);
                                } else {
                                    $userid = $record->id;
                                    $logrecord->action  = get_string('logupdate', 'tool_smsimport');
                                    $user = (object) array_merge((array) $record, (array) $user);
                                    $updateuser = 1;
                                }
                                $counter++;
                            }
                            $updateusers++;
                        } else {
                            $userid = user_create_user($user, false, false);
                            $user->id = $userid;
                            $logrecord->action  = get_string('logcreate', 'tool_smsimport');
                            $newusers++;
                            mtrace("User with idnumber {$usernsn} created", $linebreak);
                        }
                        // The userid of the user who is being updated.
                        $logrecord->userid = $userid;
                        $info['userid'] = $userid;
                        // School transfer-in/transfer-out.
                        $transfererror = self::transfer_user_school($school, $groupid, $usernsn,
                            $linebreak, $logrecord, $logsource, $info);
                        if (empty($transfererror)) {
                            if ($updateuser) {
                                user_update_user($user, false, false);
                                mtrace("User with idnumber {$usernsn} updated", $linebreak);
                            }
                            // Save user details.
                            $profileerror = self::save_user_details($user, $smsuser, $logrecord, $logsource, $info);
                        }
                        if (!empty($transfererror) || !empty($profileerror)) {
                            $syncerror = 'logerrorsync';
                        }
                    } else {
                        $logrecord->error = 'lognsndouble';
                        $logrecord->other = $logrecord->error.'help';
                        // As user details are not be updated unset these values.
                        $logrecord->action = '';
                        $logrecord->userid = 0;
                        unset($info['userid']);
                    }
                    // Log user create/update event.
                    if (empty($transfererror) && empty($profileerror)) {
                        $logrecord->info = $info;
                        self::add_sms_log($logrecord, $logsource);
                    }
                }
            }
        }
        // Log sync event.
        $result = "Total users in source: {$total}
        Total users created in {$SITE->fullname}: {$newusers}
        Total users updated in {$SITE->fullname}: {$updateusers} ";
        mtrace($result, $linebreak);
        $logrecord->action  = get_string('logsync', 'tool_smsimport');
        $summary['total'] = $total;
        $summary['newusers'] = $newusers;
        $summary['updateusers'] = $updateusers;
        $logrecord->error = '';
        $logrecord->other = '';
        if (!empty($syncerror)) {
            $logrecord->error = $syncerror;
            $logrecord->other = $logrecord->error.'help';
        }
        $logrecord->target  = get_string('logschool', 'tool_smsimport');
        $logrecord->info = $summary;
        $logrecord->userid = $USER->id; // The userid of the logged user who is running the script.
        self::add_sms_log($logrecord, $logsource, true);
        return true;
    }


    /**
     * Get SMS groups name from API.
     *
     * @param int $gidnumber Group IDnumber
     * @param object $school SMS School data
     * @return mixed
     */
    public static function find_groupname($gidnumber, $school) {
        $smsgroups = self::get_sms_group($school);
        foreach ($smsgroups as $key => $value) {
            if ($key == $gidnumber) {
                return $value;
            }
        }
        return 0;
    }


    /**
     * Get SMS group ID number from API.
     *
     * @param string $groupname Group name.
     * @param string $school SMS School object
     * @return mixed
     */
    public static function find_groupidnumber($groupname, $school) {
        $smsgroups = self::get_sms_group($school);
        $groupname = str_replace(' ', '', $groupname);
        foreach ($smsgroups as $key => $value) {
            $value = str_replace(' ', '', $value);
            if (self::remove_accent($value) == self::remove_accent($groupname)) {
                return $key;
            }
        }
        return 0;
    }

    /**
     * Tranlate user data from the SMS into the supported
     * format of the various user custom fields.
     *
     * @param string $name name of the user custom field
     * @param string $value value of the user custom field
     * @return string translated data value
     */
    public static function sms_data_mapping($name, $value) {
        $localorg = self::check_local_organisations();
        $finalvalue = '';
        $error = '';
        $customfield = profile_get_custom_field_data_by_shortname($name);
        if (empty($customfield)) {
            $customfield = profile_get_custom_field_data_by_shortname(ucwords($name));
        }
        $name = strtolower($name);
        switch ($name) {
            // Format e.g. : Australian.
            case "ethnicity":
                if ($localorg) {
                    if (empty($value)) {
                        $finalvalue = 'Unknown';
                    } else {
                        $sitevalue = explode("\n", $customfield->param1);
                        $multiet = explode(",", $value);
                        /* If there are multiple ethnicities, take the first one and ignore the rest.
                           as this profile field currently only supports taking a single value.
                           */
                        $value = $multiet[0];
                        if (!$finalvalue = local_organisations_convert_ethnicity_to_category($value)) {
                            $et = explode("/", $value);
                            foreach ($et as $ethnicity) {
                                if (!$finalvalue = local_organisations_convert_ethnicity_to_category($ethnicity)) {
                                    $finalvalue = 'Unknown';
                                    $error = 1;
                                }
                            }
                        }
                    }
                }
            break;

            case "gender":
                // Formats supported: M/F, Male/Female, Tane/Wahine.
                // Format: F/M.
                if (empty($value)) {
                    $finalvalue = 'Not Specified';
                }
                if ($finalvalue == 'Not Specified') {
                    $error = 1;
                } else {
                    $value = ucwords($value);
                    $value = str_replace('Tane', 'Male', $value);
                    $value = str_replace('Wahine', 'Female', $value);
                    $value = str_replace('Tāne', 'Male', $value);
                    $value = str_replace('Wāhine', 'Female', $value);
                    $sitevalue = explode("\n", $customfield->param1);
                    if (strlen($value) == 1) {
                        foreach ($sitevalue as $gender) {
                            if (strpos($gender, $value) !== false) {
                                $finalvalue = $gender;
                            }
                        }
                    } else {
                        // Format: Female or Male / Tāne.
                        $value = explode(" / ", $value);
                        $common = array_intersect($sitevalue, $value);
                        if ($common) {
                            // Get first value common in the two arrays.
                            $finalvalue = reset($common);
                        } else {
                            // Get last value set in the profile field.
                            $finalvalue = end($sitevalue);
                        }
                    }
                }

            break;

            case "year":
                // Formats supported: Year8, Y8,8,eight.
                $yearinwords = [
                    1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
                    5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
                    10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen',
                ];
                $value = strtolower($value);
                if ((int)($value)) {
                    $finalvalue = $value;
                } else {
                    if (strpos($value, 'year') !== false) {
                        $finalvalue = str_replace('year', '', $value);
                    } else if (strpos($value, 'y') !== false) {
                        $finalvalue = str_replace('y', '', $value);
                    } else if (($key = array_search($value, $yearinwords)) != 0) {
                        $finalvalue = $key;
                    }
                }
                $finalvalue = (int)($finalvalue);
            break;

            case "dob":
                /* Formats supported: 2019-03-25 | 2019/03/25 | 25-03-2019 | 25/03/2019 | 25.03.2019 | 25.03.2019
                 * 25 Nov 2019 | 25 November 2015.  */
                if (strpos($value, ".") !== false) {
                    $value = str_replace('.', '-', $value);
                }
                if (strpos($value, "/") !== false) {
                    $value = str_replace('/', '-', $value);
                }
                if (strpos($value, "-") !== false) {
                    $val = explode("-", $value);
                    if (strlen($val[0]) <= 2 && strlen($val[2]) == 2) {
                        $value = $val[0].'-'.$val[1].'-20'.$val[2];
                    }
                }
                $tdate = strtotime($value);
                if ($tdate) {
                    $finalvalue = date("Y-m-d", $tdate);
                } else {
                    $finalvalue = 0;
                    $error = 1;
                }
            break;

            case "room":
                $finalvalue = $value;
            break;

            default:
                $finalvalue = $value;
        }

        if ($error == 1) {
            $error = 'logmapping';
        }

        $result = [
            'data' => $finalvalue,
            'error' => $error,
        ];

        return $result;
    }

    /**
     * Get SMS groups data from API for the current year.
     *
     * @param object $school School details
     * @return mixed
     */
    public static function get_sms_group($school) {
        $schoolno = $school->schoolno;
        $safeguard = get_config('tool_smsimport', 'safeguard');
        if (!empty(self::$smsgroups[$schoolno])) {
            $smsgroups = self::$smsgroups[$schoolno];
        } else {
            $response = self::get_sms_token($school);
            if (isset($response->access_token)) {
                switch ($response->smsname) {
                    case 'edge':
                        $curl = new \curl();
                        $appid = 'appId: '. $response->key;
                        $authorization = "Authorization: ". $response->token_type. " " .$response->access_token;
                        $post = [
                            'CURLOPT_HTTPHEADER' => [
                                $authorization,
                                $appid,
                            ],
                        ];
                        $year = date('Y');
                        $result = json_decode($curl->get($response->getgroups."/".$year, null, $post));
                        if ($result && count($result) >= $safeguard) {
                            foreach ($result as $key => $value) {
                                $smsgroups[$value->GroupNo] = $value->GroupName;
                            }
                        }
                        break;
                    case 'etap':
                        $smsgroups = self::get_etap_data($response->access_token, $school->schoolno,
                            $response->getgroups, 'groups');
                        break;
                }
                self::$smsgroups[$schoolno] = $smsgroups;
            }
        }
        if (!empty($smsgroups)) {
            return $smsgroups;
        } else {
            // Log error.
            $logrecord = new stdClass();
            $logrecord->schoolno = $schoolno;
            $logrecord->target = get_string('logschool', 'tool_smsimport');
            $logrecord->action = get_string('logsync', 'tool_smsimport');
            $logrecord->error = 'lognodata';
            $logrecord->other = 'lognodatahelp';
            $info = [];
            $info['logendpoint'] = $response->getgroups;
            $logrecord->info = $info;
            self::add_sms_log($logrecord, '', true);
            // Throwing an exception in the task will mean that it isn't removed from the queue and is tried again.
            throw new \moodle_exception($logrecord->other);
        }
    }

    /**
     * Get SMS token from API.
     *
     * @param object $school School details
     * @return object
     */
    public static function get_sms_token($school) {
        global $DB;
        $response = new stdClass();
        // Get SMS API details.
        $param = ['id' => $school->smsid];
        $record = $DB->get_record('tool_sms', $param);
        $getusers = $record->url2;
        $getgroups = $record->url3;
        $curl = new \curl();
        switch ($record->name) {
            case 'edge':
                // Get token.
                $params = "grant_type=school&appId={$record->key}&appSecret={$record->secret}&schoolNo={$school->schoolno}";
                $response = $curl->put($record->url1, $params);
                $response = json_decode($response);
                break;
            case 'etap':
                $params = "id={$record->key}&p={$record->secret}";
                $url = "{$record->url1}?{$params}";
                $options = [
                    'CURLOPT_USERPWD' => 'ignore:me',
                ];
                // Get token.
                $accesstoken = $curl->get($url, null, $options);
                /* ETAP does not validate against a school in step 1 hence
                * we need to retrieve data early for validation purpose.
                */
                $response = self::get_etap_data($accesstoken, $school->schoolno, $getusers, 'users');
                $response->access_token = $accesstoken;
                break;
        }
        $response->smsname = $record->name;
        $response->key = $record->key;
        $response->getusers = $getusers;
        $response->getgroups = $getgroups;
        if (!empty($response)) {
            return $response;
        }
    }

    /**
     * Get user data from SMS ETAP.

     * Data from ETAP is in text format.
     * ETAP uses the same URL to retrieve users and groups depending on parameters sent.
     *
     * @param string $accesstoken The access token.
     * @param int $schoolno The SMS school no.
     * @param string $url The endpoint to get data.
     * @param string $datatype users or groups
     * @return object Get data or error from the endpoint
     */
    public static function get_etap_data($accesstoken, $schoolno, $url, $datatype) {
        $curl = new \curl();
        if ($datatype == 'users') {
            $response = new stdClass();
            $params = "k={$accesstoken}&m={$schoolno}";
            $url = "{$url}?{$params}";
            $options = [
                'CURLOPT_USERPWD' => 'ignore:me',
            ];
            $text  = $curl->get($url, null, $options);
            // The endpoint returns string with 'mlep' in the headers.
            if (strpos($text, 'mlep') !== false) {
                $response->error = null;
                $response->data = $text;
            } else {
                $response->error = $text;
                $response->data = null;
            }
            return $response;
        } else if ($datatype == 'groups') {
            $params = "k={$accesstoken}&m={$schoolno}&SendRooms=1";
            $url = "{$url}?{$params}";
            $options = [
                'CURLOPT_USERPWD' => 'ignore:me',
            ];
            $text  = $curl->get($url, null, $options);
            // The endpoint returns string with 'Room' in the headers.
            if (strpos($text, 'Room') !== false) {
                $groups = $text;
                $text = preg_replace('!\r\n?!', "\n", $groups);
                $importid = \csv_import_reader::get_new_iid('groupsimport');
                $csvimport = new \csv_import_reader($importid, 'groupsimport');
                $csvimport->load_csv_content($text, 'UTF-8', ',');
                $csvimport->init();
                $groups = [];
                while ($line = $csvimport->next()) {
                    $groupidnumber = $schoolno.''.$line[0];
                    $groupname = $line[1];
                    $groups[$groupidnumber] = $groupname;
                }
                $csvimport->close();
                return $groups;
            }
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
    public static function add_sms_log($data, $origin = 'cron', $email = null) {
        global $DB, $USER;
        $data->ip = '';
        $data->timecreated = time();
        $data->origin = $origin;
        if ($origin == 'web') {
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
            $error = self::extract_strings($data->error);
            $other = self::extract_strings($data->other);
            $info = self::extract_strings($data->info);
            $subject = get_string('logemailsubject', 'tool_smsimport');
            $sender = \core_user::get_noreply_user();
            $recipient = \core_user::get_support_user();
            $a = [
                'subject' => get_string('logemailsubject', 'tool_smsimport'),
                'schoolno' => $data->schoolno,
                'error' => $error,
                'other' => $other,
                'info' => $info,
            ];
            $subject = get_string('logemailsubject', 'tool_smsimport');
            $sender = \core_user::get_noreply_user();
            $recipient = \core_user::get_support_user();
            $message = get_string('logemailmessage', 'tool_smsimport', $a);
            mtrace("Email notification sent to {$recipient->email}");
            email_to_user($recipient, $sender, $subject, strip_tags($message), $message);
        }
        return $result;
    }

    /**
     * Extract localized strings from a given string.
     *
     * @param string $value
     * @return array
     */
    public static function extract_strings($value) {
        $result = '';
        if ($value == null || $value == '' || empty($value)) {
            return $result;
        }
        if (is_string($value)) {
            $value = trim($value);
            if (clean_param($value, PARAM_STRINGID) !== '') {
                $result = get_string($value, 'tool_smsimport');
            }
        } else {
            foreach ($value as $key => $val) {
                if (clean_param($key, PARAM_STRINGID) !== '') {
                    $label = get_string($key, 'tool_smsimport');
                    $result .= "<br>".$label. ": ". $val;
                }

            }
        }
        return $result;
    }

    /**
     * Get SMS school TEST data from pretend API endpoint.
     *
     * @return array
     */
    public static function test_date() {
        $nsn = 'national student number';
        $student = [];
        $student[1]->firstname = 'Ryder';
        $student[1]->surname = 'Smith';
        $student[1]->profile_field_DOB = '10/10/2013';
        $student[1]->profile_field_year = '1';
        $student[1]->profile_field_room = 'Room 1';
        $student[1]->profile_field_gender = 'Male / Tāne';
        $student[1]->profile_field_ethnicity = 'Asian, Maori';
        $student[1]->$nsn = '0149291995';
        return $student;
    }

    /**
     * Map labels with the data stored in iDeal.
     *
     * @param string $h text string of the label.
     * @return string modified label
     */
    public static function fix_labels($h) {
        $h = trim($h);
        $h = strtolower($h);
        // Edge.
        $h = str_replace('profile_field_', '', $h);
        // Etap.
        $h = str_replace('mlep', '', $h);
        if ($h == 'lastname') {
            $h = 'surname';
        }
        if ($h == 'date of birth' || $h == 'dateofbirth') {
            $h = 'dob';
        }
        if ($h == 'suspend') {
            $h = 'suspended';
        }
        if ($h == 'nsn' || $h == 'studentnsn') {
            $h = 'national student number';
        }
        if ($h == 'dob') {
            $h = 'profile_field_'.strtoupper($h);
        }
        if ($h == 'ethnicity') {
            $h = 'profile_field_'.ucwords($h);
        }
        if ($h == 'year' || $h == 'groupmembership') {
            $h = 'year';
            $h = 'profile_field_'.$h;
        }
        if ($h == 'room' || $h == 'homegroup') {
            $h = 'room';
            $h = 'profile_field_'.$h;
        }
        if ($h == 'gender') {
            $h = 'profile_field_'.$h;
        }
        return $h;
    }

    /**
     * Parse data coming from endpoint or CSV source.
     *
     * @param mixed $data object or string.
     * @param array $options the attributes of the data.
     * @param object $school school details.
     * @return array $users A list of users.
     * @throws moodle_exception
     */
    public static function parse_data($data, $options, $school) {
        global $DB;
        $required = [
            "firstname" => 1,
            "surname" => 1,
            "national student number" => 1,
        ];
        $optional = [
            "suspended" => 1,
        ];
        $profilefields = get_config('tool_smsimport', 'smsuserfields');
        $profilefields = explode(',', $profilefields);
        foreach ($profilefields as $profilefield) {
            $optional['profile_field_'.$profilefield] = 1;
        }

        if ($options['format'] == 'text') {
            $text = preg_replace('!\r\n?!', "\n", $data);
            $importid = \csv_import_reader::get_new_iid('userimport');
            $csvimport = new \csv_import_reader($importid, 'userimport');
            $readcount = $csvimport->load_csv_content($text, $options['encoding'], $options['delimiter']);
            if ($options['source'] == 'web') {
                if ($readcount === false) {
                    throw new \moodle_exception('csvfileerror', 'error', '', $csvimport->get_error());
                } else if ($readcount == 0) {
                    throw new \moodle_exception('csvemptyfile', 'error', '', $csvimport->get_error());
                } else if ($readcount == 1) {
                    throw new \moodle_exception('csvnodata', 'error', '');
                }
            }
            $csvimport->init();
            unset($text);
            $header = $csvimport->get_columns();
            // Check for valid field names.
            foreach ($header as $i => $h) {
                $h = strtolower($h);
                $h = trim($h);
                $h = self::fix_labels($h);
                $header[$i] = $h;
                if (isset($required[$h])) {
                    $required[$h] = 2;
                }
            }
            // Check for required fields.
            foreach ($required as $key => $value) {
                if ($value < 2) {
                    throw new \moodle_exception('fieldrequired', 'error', '', $key);
                }
            }
            // Prepare the data to import users.
            $users = [];
            $counter = 0;
            while ($line = $csvimport->next()) {
                $user = new stdClass();
                foreach ($line as $key => $value) {
                    if ((isset($required[$header[$key]]) || isset($optional[$header[$key]]))) {
                        $label = $header[$key];
                        // Remove any trailing space.
                        $value = trim($value);
                        // Remove double whitespace.
                        $value = preg_replace('/\s+/', ' ', $value);
                        // Remove whitespace.
                        $value = trim($value);
                        /* SMS 'ETAP' has profile_field_year field saved with room. We need to extract the value from it.
                        E.g. Room1#Y6
                        */
                        if ($label == 'profile_field_year') {
                            $value = strtolower($value);
                            $value = explode('#', $value);
                            foreach ($value as $val) {
                                if ((strpos($val, 'y') !== false || strpos($val, 'year') !== false)
                                    && strpos($val, ' ') === false) {
                                    $value = $val;
                                }
                            }
                            if (gettype($value) != 'string') {
                                $value = '';
                            }
                        }
                        if ($options['source'] == 'web' && $label == 'profile_field_room') {
                            // Create group if it does not exist.
                            $courseid = get_config('tool_smsimport', 'smscourse');
                            $schoolname = $DB->get_field('cohort', 'name', ['id' => $school->cohortid]);
                            $groupname = $schoolname.$value;
                            $groupid = groups_get_group_by_name($courseid, $groupname);
                            if (empty($groupid)) {
                                $newgroupdata = new stdClass();
                                $newgroupdata->courseid = $courseid;
                                $newgroupdata->name = $groupname;
                                $groupid = groups_create_group($newgroupdata);
                                if (self::check_local_organisations()) {
                                    local_organisations_store_organisation_group($school->cohortid, $groupid);
                                }
                            }
                        }
                        $user->$label = trim($value);
                    }
                }
                $users[$counter] = $user;
                $counter++;
                unset ($user);
            }
            $csvimport->close();
        }

        if ($options['format'] == 'json') {
            $users = [];
            foreach ($data as $count => $record) {
                $user = new stdClass();
                foreach ($record as $h => $value) {
                    $h = self::fix_labels($h);
                    $user->$h = $value;
                }
                $users[] = $user;
                unset ($user);
            }
        }

        return $users;
    }

    /**
     * Get SMS school data from API.
     *
     * @param object $school School details
     * @return mixed array or throw exception
     * @throws moodle_exception
     */
    public static function get_sms_school_data($school) {
        $response = self::get_sms_token($school);
        $safeguard = get_config('tool_smsimport', 'safeguard');
        if (isset($response->access_token)) {
            // Get school data from the API endpoint.
            $curl = new \curl();
            switch ($response->smsname) {
                case 'edge':
                    $appid = 'appId: '. $response->key;
                    $authorization = "Authorization: ". $response->token_type. " " .$response->access_token;
                    $options = [
                        'CURLOPT_HTTPHEADER' => [
                            $authorization,
                            $appid,
                        ],
                    ];
                    $data = json_decode($curl->get($response->getusers, null, $options));
                    if (!empty($data)) {
                        $options = [
                            'format' => 'json',
                            'source' => 'cron',
                        ];
                        $records = self::parse_data($data, $options, $school);
                    } else {
                        $error = $data->error;
                    }
                    break;
                case 'etap':
                    // Data already retrieved in step 1.
                    if (!empty($response->data)) {
                        $options = [
                            'format' => 'text',
                            'delimiter' => 'comma',
                            'encoding' => 'UTF-8',
                            'source' => 'cron',
                        ];
                        $records = self::parse_data($response->data, $options, $school);
                    } else {
                        $error = $response->error;
                    }
                    break;
            }
            if (!empty($records) && count($records) > $safeguard && empty($error)) {
                return $records;
            } else {
                // Log error.
                $logrecord = new stdClass();
                $logrecord->schoolno = $school->schoolno;
                $logrecord->target = get_string('logschool', 'tool_smsimport');
                $logrecord->action = get_string('logsync', 'tool_smsimport');
                $logrecord->error = 'lognodata';
                $logrecord->other = 'lognodatahelp';
                $info = [];
                $info['logendpoint'] = $response->getusers;
                $info['logerrorsync'] = $error;
                $logrecord->info = $info;
                self::add_sms_log($logrecord, '', true);
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
        // Check API for school details.
        $response = self::get_sms_token($data);
        $data->name = trim($data->name);
        if (empty($response->error)) {
            return $DB->insert_record('tool_sms_school', $data, true, false);
        } else {
            throw new \moodle_exception('errorschoolnotfound', 'tool_smsimport');
        }
    }

    /**
     * Delete SMS school details.
     *
     * @param int $id School ID
     * @return boolean
     */
    public static function delete_sms_school($id) {
        global $DB;
        $smsschool = self::get_sms_school(['id' => $id]);
        $cohortid = $smsschool->cohortid;
        $result = false;
        try {
            $cohortmembers = $DB->get_records_sql("SELECT u.id FROM {user} u, {cohort_members} cm
            WHERE u.id = cm.userid AND cm.cohortid = ? AND u.auth = 'webservice'
            ORDER BY lastname ASC, firstname ASC", [$cohortid]);
            $userids = array_keys($cohortmembers);
            foreach ($userids as $userid) {
                $user = $DB->get_record('user', ['id' => $userid]);
                user_delete_user($user);
            }
            $groups = $DB->get_records('tool_sms_school_groups', ['schoolid' => $id]);
            foreach ($groups as $group) {
                groups_delete_group($group->groupid);
            }
            $DB->delete_records('tool_sms_school_log', ['schoolno' => $smsschool->schoolno]);
            $DB->delete_records('tool_sms_school_groups', ['schoolid' => $id]);
            $DB->delete_records('tool_sms_school', ['id' => $id]);
            $result = true;
        } catch (\Exception $e) {
            throw new \moodle_exception('errorschoolnotdeleted', 'tool_smsimport', $e);
        }
        // Log record.
        $logrecord = new stdClass();
        $logrecord->schoolno = $smsschool->schoolno;
        $logrecord->target  = get_string('logschool', 'tool_smsimport');
        $logrecord->action  = get_string('logdelete', 'tool_smsimport');
        self::add_sms_log($logrecord);
        return $result;
    }
}
