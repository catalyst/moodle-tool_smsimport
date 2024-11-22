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
 * Upgrade steps for Student management system import
 *
 * Documentation: {@link https://moodledev.io/docs/guides/upgrade}
 *
 * @package    tool_smsimport
 * @category   upgrade
 * @copyright  2024 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_smsimport_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024110000) {
        $table = new xmldb_table('tool_sms');
        $dbman->rename_table($table, 'tool_smsimport');

        $table = new xmldb_table('tool_sms_school');
        $dbman->rename_table($table, 'tool_smsimport_school');

        $table = new xmldb_table('tool_sms_school_groups');
        $dbman->rename_table($table, 'tool_smsimport_school_groups');

        $table = new xmldb_table('tool_sms_school_log');
        $dbman->rename_table($table, 'tool_smsimport_school_log');

        upgrade_plugin_savepoint(true, 2024110000, 'tool', 'smsimport');
    }

    return true;
}
