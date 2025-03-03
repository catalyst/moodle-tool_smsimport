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
 * tool_smsimport settings.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $PAGE;

if ($hassiteconfig) {

    // Plugin category inside Admin tools navigation.
    $ADMIN->add('tools', new admin_category('tool_smsimport',
    get_string('pluginname', 'tool_smsimport', null, true)));

    // Plugin setting page.
    $page = new admin_settingpage('tool_smsimport_managesms',
    get_string('managesms', 'tool_smsimport', null, true));
    $ADMIN->add('tool_smsimport', $page);

    // Plugin listing page.
    $ADMIN->add('tool_smsimport', new admin_externalpage('tool_smsimport_sms',
    get_string('addsms', 'tool_smsimport'),
    new moodle_url('/admin/tool/smsimport/addsms.php')));

    // Plugin listing page.
    $ADMIN->add('tool_smsimport', new admin_externalpage('tool_smsimport_index',
    get_string('managesmsschools', 'tool_smsimport'),
    new moodle_url('/admin/tool/smsimport/index.php')));

    // Define plugin settings page.
    $options = [];
    foreach (get_courses() as $course) {
        if ($course->visible == 1 && $course->category) {
            $options[$course->id] = ucwords($course->shortname);
        }
    }
    $page->add(new admin_setting_configselect('tool_smsimport/smscourse',
    new lang_string('smscourse', 'tool_smsimport'), new lang_string('smscourse_help', 'tool_smsimport'), 1, $options));

    $customfields = [];
    $fields = $DB->get_records('user_info_field', null, 'sortorder ASC');
    foreach ($fields as $field) {
        $customfields[$field->shortname] = $field->name;
    }
    $page->add(new admin_setting_configmultiselect('tool_smsimport/smsuserfields',
    new lang_string('smsuserfields', 'tool_smsimport'), new lang_string('smsuserfields_help', 'tool_smsimport'),
    '', $customfields));

    $page->add(new admin_setting_configtext('tool_smsimport/safeguard',
    new lang_string('safeguard', 'tool_smsimport'), new lang_string('safeguard_help', 'tool_smsimport'),
    1, PARAM_INT));

    // Plugin upload page.
    $ADMIN->add('tool_smsimport', new admin_externalpage('tool_smsimport_upload',
    get_string('uploadusers', 'tool_smsimport'),
    new moodle_url('/admin/tool/smsimport/upload.php')));
}
