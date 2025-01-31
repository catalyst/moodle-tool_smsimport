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

use tool_smsimport\local\form\add_sms_form;
use tool_smsimport\local\helper;

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

$returnurl = new moodle_url('/admin/tool/smsimport/index.php');

$pageurl = '/admin/tool/smsimport/addsms.php';
$PAGE->set_url($pageurl);

$PAGE->navbar->add(get_string('pluginname', 'tool_smsimport'), $returnurl);
$PAGE->navbar->add(get_string('addsms', 'tool_smsimport'));

$PAGE->set_pagelayout('admin');
$PAGE->set_context($context);
$PAGE->set_primary_active_tab('siteadminnode');

$PAGE->set_title(get_string('sms', 'tool_smsimport'));
$PAGE->set_heading(get_string('sms', 'tool_smsimport'));

// Setup the form.
$mform = new add_sms_form();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addsms', 'tool_smsimport'));
// Process the form.
if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    if ($data->smsconfig) {
        global $DB;
        $records = explode(PHP_EOL, $data->smsconfig);
        foreach($records as $record) {
            $record = str_getcsv($record);
            $data = [
                'key' => trim($record[0]),
                'secret' =>  trim($record[1]),
                'name' =>  trim($record[2]),
                'url1' =>  trim($record[3]),
                'url2' => trim($record[4]),
                'url3' =>  trim($record[5]),
                'timemodified' => time()
            ];
            if ($id = $DB->get_field('tool_smsimport', 'id', ['name' => $data['name']])) {
                $data['id'] = $id;
                $DB->update_record("tool_smsimport", $data);
            } else {
                $data['timecreated'] =  time();
                $DB->insert_record("tool_smsimport", $data);
            }
        }
        echo '<div class="alert alert-info">'.get_string('smssuccess', 'tool_smsimport').'</div>';
            // Add buttons.
            echo '<div class="">
            <a class="m-3 btn btn-secondary" href="'.$returnurl.'">
                ' . get_string('goback', 'tool_smsimport') . '
            </a>
        </div>';
    }
} else {
    // Display the form.
    $mform->display();
}


echo $OUTPUT->footer();
