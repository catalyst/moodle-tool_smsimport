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
 * Privacy Subsystem implementation for tool_smsimport.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_smsimport\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;


/**
 * Class provider
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider,
    \core_privacy\local\request\data_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table('tool_smsimport_school_log', [
            'schoolno' => 'privacy:metadata:schoolno',
            'target' => 'privacy:metadata:target',
            'action' => 'privacy:metadata:action',
            'error' => 'privacy:metadata:error',
            'info' => 'privacy:metadata:info',
            'other' => 'privacy:metadata:other',
            'timecreated' => 'privacy:metadata:timecreated',
            'userid' => 'privacy:metadata:userid',
            'origin' => 'privacy:metadata:origin',
            'ip' => 'privacy:metadata:ip',
        ], 'privacy:metadata:tool_sms_school_log');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('tool_smsimport_school_log', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $sql = "SELECT userid FROM {tool_smsimport_school_log}";
        $userlist->add_from_sql('userid', $sql, []);
    }


    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $list = [];
                // Overrides with a matching userid.
                $rows = $DB->get_records('tool_smsimport_school_log', ['userid' => $userid]);
                foreach ($rows as $row) {
                    $list[] = [
                        'userid' => $userid,
                        'schoolno' => $row->schoolno,
                        'info' => $row->info,
                        'other' => $row->other,
                        'origin' => $row->origin,
                        'ip' => $row->ip,
                        'timecreated' => $row->timecreated,
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:metadata:tool_sms_school_log', 'tool_smsimport')],
                    (object) $list
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        // Delete issue records.
        $DB->delete_records('tool_smsimport_school_log');
    }
}
