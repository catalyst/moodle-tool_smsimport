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
 * tool_smsimport SMS log entity class implementation
 *
 * @package     tool_smsimport
 * @copyright   2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_smsimport\local\entities;

use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\autocomplete;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\format;
use lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use tool_smsimport\local\helper;

/**
 * tool_smsimport SMS log entity class implementation
 *
 * @package     tool_smsimport
 * @copyright   2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sms_log extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['sms_log' => 'sl'];
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entitysmslog', 'tool_smsimport');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }
        // All the filters defined by the entity can also be used as conditions.
        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }
        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $tablealias = $this->get_table_alias('sms_log');

        // School Number column.
        $columns[] = (new column(
            'schoolno',
            new lang_string('schoolno', 'tool_smsimport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("$tablealias.schoolno")
            ->set_is_sortable(true);

        // Target column.
        $columns[] = (new column(
            'target',
            new lang_string('target', 'tool_smsimport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("$tablealias.target")
            ->set_is_sortable(true);

        // Action column.
        $columns[] = (new column(
            'action',
            new lang_string('action', 'tool_smsimport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("$tablealias.action")
            ->set_is_sortable(true);

        // Error column.
        $columns[] = (new column(
            'error',
            new lang_string('error', 'tool_smsimport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("$tablealias.error")
            ->set_is_sortable(true)
            ->add_callback(static function(?string $value): string {
                $result = helper::extract_strings($value);
                return $result;
            });

        // Other column.
        $columns[] = (new column(
            'other',
            new lang_string('other', 'tool_smsimport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("$tablealias.other")
            ->set_is_sortable(true)
            ->add_callback(static function(?string $value): string {
                $result = helper::extract_strings($value);
                return $result;
            });

        // Info column.
        $columns[] = (new column(
            'info',
            new lang_string('info', 'tool_smsimport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("$tablealias.info")
            ->add_callback(static function(?string $value): string {
                $value = json_decode($value);
                $result = helper::extract_strings($value);
                return $result;
            });

        // Origin column.
        $columns[] = (new column(
            'origin',
            new lang_string('origin', 'tool_smsimport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("$tablealias.origin")
            ->set_is_sortable(true);

        // IP Address column.
        $columns[] = (new column(
            'ip',
            new lang_string('ip', 'tool_smsimport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("$tablealias.ip")
            ->set_is_sortable(true);

        // Time created column.
        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'tool_smsimport'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("$tablealias.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate'], get_string('strftimedatetimeshortaccurate', 'core_langconfig'));

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $tablealias = $this->get_table_alias('sms_log');

        // School Number filter.
        $filters[] = (new filter(
            number::class,
            'schoolno',
            new lang_string('schoolno', 'tool_smsimport'),
            $this->get_entity_name(),
            "{$tablealias}.schoolno"
        ))
        ->add_joins($this->get_joins());

        // Target filter.
        $filters[] = (new filter(
            text::class,
            'target',
            new lang_string('target', 'tool_smsimport'),
            $this->get_entity_name(),
            "{$tablealias}.target"
        ))
        ->add_joins($this->get_joins());

        // Action filter.
        $filters[] = (new filter(
            text::class,
            'action',
            new lang_string('action', 'tool_smsimport'),
            $this->get_entity_name(),
            "{$tablealias}.action"
        ))
        ->add_joins($this->get_joins());

        // Error filter.
        $filters[] = (new filter(
            autocomplete::class,
            'error',
            new lang_string('error', 'tool_smsimport'),
            $this->get_entity_name(),
            "{$tablealias}.error"
        ))
        ->add_joins($this->get_joins())
        ->set_options([
            'lognoregister' => new lang_string('lognoregister', 'tool_smsimport'),
            'logduplicate' => new lang_string('logduplicate', 'tool_smsimport'),
            'lognodata' => new lang_string('lognodata', 'tool_smsimport'),
            'lognogroups' => new lang_string('lognogroups', 'tool_smsimport'),
            'logmapping' => new lang_string('logmapping', 'tool_smsimport'),
            'lognsndouble' => new lang_string('lognsndouble', 'tool_smsimport'),
            'logerrorsync' => new lang_string('logerrorsync', 'tool_smsimport'),
        ]);

        // Info filter.
        $filters[] = (new filter(
            text::class,
            'info',
            new lang_string('info', 'tool_smsimport'),
            $this->get_entity_name(),
            "{$tablealias}.info"
        ))
        ->add_joins($this->get_joins());

        // Time created filter.
        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'tool_smsimport'),
            $this->get_entity_name(),
            "{$tablealias}.timecreated"
        ))
        ->add_joins($this->get_joins())
        ->set_limited_operators([
            date::DATE_ANY,
            date::DATE_RANGE,
            date::DATE_PREVIOUS,
            date::DATE_CURRENT,
        ]);

        // Origin filter.
        $filters[] = (new filter(
            text::class,
            'origin',
            new lang_string('origin', 'tool_smsimport'),
            $this->get_entity_name(),
            "{$tablealias}.origin"
        ))
        ->add_joins($this->get_joins());

        // IP address filter.
        $filters[] = (new filter(
            text::class,
            'ip',
            new lang_string('ip', 'tool_smsimport'),
            $this->get_entity_name(),
            "{$tablealias}.ip"
        ))
        ->add_joins($this->get_joins());

        return $filters;
    }
}
