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
 * Edit school form tool_smsimport plugin.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_smsimport\form\edit_school_form;
use tool_smsimport\form\save_school_form;
use tool_smsimport\helper;

require_once('../../../config.php');

global $PAGE;

require_login();

if (!$context = context_system::instance()) {
    throw new moodle_exception('wrongcontext', 'error');
}

require_capability('moodle/site:config', $context);
$action   = required_param('action', PARAM_ALPHA);
$id       = required_param('id', PARAM_INT);

$returnurl = new moodle_url('/admin/tool/smsimport/index.php');

$school = new stdClass();
$school->id = null;
if ($id) {
    $urlparams['id'] = $id;
    $urlparams['sesskey'] = sesskey();
    if (!$school = helper::get_sms_school(array('id' => $id))) {
        throw new \moodle_exception('wrongschoolid', 'tool_smsimport');
    }
}

$pageurl = '/admin/tool/smsimport/edit.php';
$PAGE->set_url($pageurl, array('action' => $action,'id' => $id));

$PAGE->navbar->add(get_string('pluginname', 'tool_smsimport'), $returnurl);
$PAGE->navbar->add(get_string('editschool', 'tool_smsimport'));

$PAGE->set_pagelayout('admin');
$PAGE->set_context($context);
$PAGE->set_primary_active_tab('siteadminnode');

$PAGE->set_title(get_string('sms', 'tool_smsimport'));
$PAGE->set_heading(get_string('sms', 'tool_smsimport'));
echo $OUTPUT->header();

$pagetitle = ucwords($school->name). " -> ".get_string('addgroup', 'tool_smsimport');
echo $OUTPUT->heading($pagetitle);

if ($school->cohortid) {
    $school->groups = helper::get_sms_school_groups($school->id, 'schoolid');
}

if ($action == 'select') {
    $selectform = new edit_school_form(null, compact('school','action'));
    $selectform->set_data($school);
    if ($selectform->is_cancelled()) {
        redirect($returnurl);
    } else if ($selectformdata = $selectform->get_data()) {
        $groups = isset($school->groups) ? $school->groups : NULL;
        $result = helper::save_sms_school_details($selectformdata, $action, $groups);
        $school->cohortid = $selectformdata->cohortid;
        $school->groupsselect = $selectformdata->groupsselect;
        if (isset($selectformdata->unlink)) {
            $school->unlink = $selectformdata->unlink;
        }
        if (isset($result->message)) {
            $message = $result->message;
        } else {
            $message = "No changes selected";
        }
        $school->message = $message;
        $editformset = true;
        $action = 'edit';
        $editform = new save_school_form(null, compact('school','action'));
        $editform->set_data($school);
        echo $OUTPUT->box_start();
        $editform->display();
        echo $OUTPUT->box_end();
        echo $OUTPUT->footer();
        exit;
    }
    // Display the form.
    $selectform->display();
}

if ($action == 'edit') {
    $saveform = new save_school_form(null, compact('school','action'));
    if ($saveform->is_cancelled()) {
        redirect($returnurl);
    } else if ($saveformdata = $saveform->get_data()) {
        $school = helper::get_sms_school(array('id' => $saveformdata->id));
        $school->cohortid = $saveformdata->cohortid;
        $school->unlink = $saveformdata->unlink;
        $school->groupsselect = explode('-', $saveformdata->groupssave);
        $groups= helper::get_sms_school_groups($school->id, 'schoolid');
        $result = helper::save_sms_school_details($school, $action, $groups);

        if ($result) {
            echo '<div class="alert alert-info">'.get_string('changessuccess', 'tool_smsimport').'</div>';
        }
        // Add buttons.
        echo '<div class="">
            <a class="btn btn-secondary" href="'.new moodle_url('/admin/tool/smsimport/add.php?action=add').'">
                ' . get_string('addschool', 'tool_smsimport') . '
            </a>
            <a class="m-3 btn btn-secondary" href="'.$returnurl.'">
                ' . get_string('goback', 'tool_smsimport') . '
            </a>
        </div>';
    }
}

echo $OUTPUT->footer();
