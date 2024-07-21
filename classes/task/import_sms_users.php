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
 * tool_smsimport task class responsible for importing users from SMS schools.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_smsimport\task;
use tool_smsimport\helper;

/**
 * Simple task class responsible for importing users from SMS schools.
 */
class import_sms_users extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskimportsmsusers', 'tool_smsimport');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        // For testing
        //  helper::import_sms_school_users(3703);
        // Call your own api
        $params = array('suspend' => 0);
        $records = helper::get_sms_schools($params);
        foreach ($records as $record) {
            helper::import_sms_school_users($record->schoolno);
        }         
    }
}
