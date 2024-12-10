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
 * Index page for list all the schools in the tool_smsimport plugin.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();
admin_externalpage_setup('tool_smsimport_index');

global $PAGE, $DB;

require_login();

if (!$context = context_system::instance()) {
    throw new moodle_exception('wrongcontext', 'error');
}

require_capability('moodle/site:config', $context);

$url = new moodle_url('/admin/tool/smsimport/index.php');
$PAGE->set_url($url);
// Breadcrumbs.
$PAGE->navbar->add(get_string('pluginname', 'tool_smsimport'), $url);
$PAGE->navbar->add(get_string('schools', 'tool_smsimport'));
// Page layout.
$PAGE->set_pagelayout('admin');
$PAGE->set_context($context);
$PAGE->set_primary_active_tab('siteadminnode');

$PAGE->set_title(get_string('pluginname', 'tool_smsimport'));
$PAGE->set_heading(get_string('pluginname', 'tool_smsimport'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('schools', 'tool_smsimport'));

// Links.
$urlparams = [];
$urlparams['sesskey'] = sesskey();
// Add new school.
$urlparams['action'] = 'add';
$urladd = new moodle_url('add.php', $urlparams);
// Edit existing school.
$urlparams['action'] = 'edit';
$urledit = new moodle_url('add.php', $urlparams);
// Add/edit groups to a school.
$urlparams['action'] = 'select';
$urlgroup = new moodle_url('edit.php', $urlparams);
// Delete a school.
$urlparams['action'] = 'delete';
$urlparams['confirm'] = 1;
$urldelete = new moodle_url('add.php', $urlparams);

$table = new \html_table();
$table->head = [
    get_string('schoolname', 'tool_smsimport'),
    get_string('actions', 'tool_smsimport'),
];
$table->colclasses = [
    'schoolname',
    'actions',
];
$table->data = [];

$records = $DB->get_records('tool_smsimport_school', null, 'name');
foreach ($records as $record) {
    $urldelete->params(['id' => $record->id]);
    $urledit->params(['id' => $record->id]);
    $urlgroup->params(['id' => $record->id]);
    $school = new \html_table_cell($record->schoolno ." ". $record->name);
    $edit = \html_writer::link($urledit, get_string('edit'),  [
        'class' => 'btn btn-primary',
    ]);
    $group = \html_writer::link($urlgroup, get_string('groups'),  [
        'class' => 'btn btn-primary',
    ]);
    $delete = \html_writer::link($urldelete, get_string('delete'),  [
        'class' => 'btn btn-primary',
        'data-confirmation' => 'modal',
        'data-confirmation-title-str' => json_encode(['delete', 'core']),
        'data-confirmation-content-str' => json_encode(['areyousure']),
        'data-confirmation-yes-button-str' => json_encode(['delete', 'core']),
        'data-confirmation-destination' => $urldelete,
    ]);

    $buttons = new \html_table_cell($edit. " ". $group . " ". $delete);
    $row = new \html_table_row([$school, $buttons]);
    $table->data[] = $row;
}

echo \html_writer::table($table);

// Add button to add a new school.
echo '<a href="' . $urladd . '" class="btn btn-secondary">' . get_string('addschool', 'tool_smsimport') . '</a>';

echo html_writer::start_tag('p');
echo get_string('oryoucan', 'tool_smsimport');
echo html_writer::link(new moodle_url('upload.php') , get_string('uploadusers', 'tool_smsimport'));
echo html_writer::end_tag('p');

echo $OUTPUT->footer();
