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
}
