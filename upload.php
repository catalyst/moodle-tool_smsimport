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
 * Upload users via csv tool_smsimport plugin.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_smsimport\form\upload_users_form;
use tool_smsimport\helper;
use local_organisations\persistent\school;
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

$returnurl = new moodle_url('/admin/tool/smsimport/upload.php');

$pageurl = '/admin/tool/smsimport/upload.php';
$PAGE->set_url($pageurl);

$PAGE->navbar->add(get_string('pluginname', 'tool_smsimport'), $returnurl);
$PAGE->navbar->add(get_string('addschool', 'tool_smsimport'));

$PAGE->set_pagelayout('admin');
$PAGE->set_context($context);
$PAGE->set_primary_active_tab('siteadminnode');

$PAGE->set_title(get_string('sms', 'tool_smsimport'));
$PAGE->set_heading(get_string('sms', 'tool_smsimport'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('uploadusers', 'tool_smsimport'));

// Setup the form.
$importform = new upload_users_form(null);
// Process the form.
if ($importform->is_cancelled()) {
    redirect($returnurl);
} else if ($formdata = $importform->get_data()) {
    // Get school details.
    $school = helper::get_sms_school(['cohortid' => $formdata->cohortid]);
    // If it is a non SMS school then use core cohort to retrieve details.
    if (empty($school)) {
        $school = new stdClass();
        $school->cohortid = $formdata->cohortid;
        if (helper::check_local_organisations()) {
            $orgschool = school::from_cohort_id($school->cohortid);
            $school->transferin = $orgschool->get('transferin');
        }
        $school->schoolno = 0;
        $school->name = $DB->get_field('cohort', 'name',
                ['id' => $school->cohortid]);
    }
    $text = $importform->get_file_content('userfile');
    $options = [
        'format' => 'text',
        'delimiter' => $formdata->delimiter_name,
        'encoding' => $formdata->encoding,
        'source' => 'web',
    ];
    $records = helper::parse_data($text, $options, $school);
    $result = helper::import_school_users($school, $records, 'web');
    if ($result) {
        echo html_writer::start_tag('p');
        echo html_writer::link($returnurl , get_string("continue"), ['class' => 'btn btn-primary']);
        echo html_writer::end_tag('p');
    }
    echo $OUTPUT->footer();
    die;
} else {
    // Display the form.
    $importform->display();
}
echo $OUTPUT->footer();
