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
 * tool_smsimport SMS logs datasource
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace tool_smsimport\reportbuilder\datasource;

use tool_smsimport\local\entities\sms_log;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\user;

/**
 * tool_smsimport SMS logs datasource
 *
 * @package     tool_smsimport
 * @copyright   2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sms_logs extends datasource {

    /**
     * Return user friendly name of the report source
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('smslogs', 'tool_smsimport');
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $smslogentity = new sms_log();

        $smslogalias = $smslogentity->get_table_alias('sms_log');
        $this->set_main_table('tool_sms_school_log', $smslogalias);

        $this->add_entity($smslogentity);

        // Join the user entity to represent the associated user.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity->add_join("
            LEFT JOIN {user} {$useralias}
                   ON {$useralias}.id = {$smslogalias}.userid")
        );

        // Add report elements from each of the entities we added to the report.
        $this->add_all_from_entities();
    }

    /**
     * Return the columns that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'sms_log:schoolno',
            'sms_log:target',
            'sms_log:action',
            'sms_log:error',
            'sms_log:other',
            'sms_log:info',
            'sms_log:timecreated',
        ];

    }

    /**
     * Return the filters that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'sms_log:schoolno',
            'sms_log:target',
            'sms_log:action',
            'sms_log:timecreated',
        ];

    }

    /**
     * Return the conditions that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [];
    }

    /**
     * Return the condition values that will be set for the report upon creation
     *
     * @return array
     */
    public function get_default_condition_values(): array {
        return [];
    }
}
