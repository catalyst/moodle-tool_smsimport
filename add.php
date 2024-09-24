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
 * Add school form tool_smsimport plugin.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_smsimport\form\add_school_form;
use tool_smsimport\helper;

require_once('../../../config.php');

require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();
admin_externalpage_setup('tool_smsimport_index');

global $PAGE;

require_login();

if (!$context = context_system::instance()) {
    throw new moodle_exception('wrongcontext', 'error');
}

require_capability('moodle/site:config', $context);

$action   = required_param('action', PARAM_ALPHA);
$id       = optional_param('id', 0, PARAM_INT);
$confirm  = optional_param('confirm', 0, PARAM_BOOL);

$returnurl = new moodle_url('/admin/tool/smsimport/index.php');

$school = new stdClass();
$school->id = null;
if ($id) {
    $urlparams['id'] = $id;
    $urlparams['sesskey'] = sesskey();
    // Load school if exists.
    if (!$school = helper::get_sms_school(array('id' => $id))) {
        throw new \moodle_exception('wrongschoolid', 'tool_smsimport');
    } else if(helper::check_local_organisations()) {
        if ($school->cohortid) {
            $school->groups = helper::get_sms_school_groups($school->id, 'schoolid');
        }
    }
}

$pageurl = '/admin/tool/smsimport/add.php';
$PAGE->set_url($pageurl, array('action' => $action, 'id' => $id, 'confirm' => $confirm));

$PAGE->navbar->add(get_string('pluginname', 'tool_smsimport'), $returnurl);
$PAGE->navbar->add(get_string('addschool', 'tool_smsimport'));

$PAGE->set_pagelayout('admin');
$PAGE->set_context($context);
$PAGE->set_primary_active_tab('siteadminnode');

$PAGE->set_title(get_string('sms', 'tool_smsimport'));
$PAGE->set_heading(get_string('sms', 'tool_smsimport'));

// If action is add, we ignore $id to avoid any further problems.
if (!empty($id) && $action == 'add') {
    $id = null;
}

if ($action === 'delete') {
    if (empty($id)) {
        throw new \moodle_exception('wrongschoolid', 'tool_smsimport');
    }
    if ($confirm && confirm_sesskey()) {
        if (empty(helper::delete_sms_school($id))) {
            throw new \moodle_exception('errorschoolnotdeleted', 'tool_smsimport');
        }
        redirect($returnurl);
    }
}

// Setup the form.
$mform = new add_school_form(null, compact('school','action'));
$school->action = $action;
$mform->set_data($school);

// Process the form.
if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
            if ($action == 'add') {
                // Add school.
                if (empty($id = helper::add_sms_school($data))) {
                    throw new \moodle_exception('errorschoolnotadded', 'tool_smsimport');
                }
                // Redirect to add school groups.
                $urlparams['id'] = $id;
                $urlparams['action'] = 'select';
                $urlselect = new moodle_url(new moodle_url('/admin/tool/smsimport/edit.php'), $urlparams);
                redirect($urlselect);
            } else if ($action == 'edit'){
                // Edit school.
                helper::save_sms_school($data);
                redirect($returnurl);
            }
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addschool', 'tool_smsimport'));

// Display the form.
$mform->display();

echo $OUTPUT->footer();
