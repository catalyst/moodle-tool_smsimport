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

namespace tool_smsimport\form;

use tool_smsimport\helper;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

class edit_school_form extends moodleform {

    /**
     * School form definition.
     */
    public function definition() {

        $mform = $this->_form;
        $school = $this->_customdata['school'];
        $action = $this->_customdata['action'];
        $schoolno = $school->schoolno;

        $message = '<h4>School '.$school->name. ": ". $schoolno."</h4>";
        $mform->addElement('html', '<div class="message">'.$message.'</div>');
        // Get groups from the API.
        $smsgroups = helper::get_sms_group($schoolno);
        $select = $mform->addElement('select', 'groupsselect', get_string('groups', 'tool_smsimport'), $smsgroups);
        $select->setMultiple(true);
        if (!empty($school->groups)) {
            foreach($school->groups as $key => $value) {
                $selected[] = $key;
            }
            $select->setSelected($selected);
        }
        if (helper::check_local_organisations()) {
            // Get system level cohorts.
            global $DB;
            $records = $DB->get_records('cohort', array('visible' => 1, 'contextid' => 1), 'name');
            $cohorts[0] = 'None';
            foreach ($records as $record) {
                $cohorts[$record->id] = $record->name;
            }
            $select = $mform->addElement('select', 'cohortid', get_string('cohortid', 'tool_smsimport'), $cohorts);
            if (!empty($school->cohortid)) {
                $mform->updateElementAttr('cohortid', array('disabled' => 'disabled'));
                $mform->addElement('checkbox', 'unlink', get_string('unlink', 'tool_smsimport'));
                $mform->addHelpButton('unlink', 'unlink', 'tool_smsimport');
            }
            $select->setSelected($school->cohortid);
            $mform->addHelpButton('cohortid', 'cohortid', 'tool_smsimport');
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $school->id);

        $mform->addElement('hidden', 'name');
        $mform->setType('name', PARAM_TEXT);
        $mform->setDefault('name', $school->name);

        $mform->addElement('hidden', 'schoolno');
        $mform->setType('schoolno', PARAM_ALPHANUMEXT);
        $mform->setDefault('schoolno', $schoolno);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ALPHANUMEXT);
        $mform->setDefault('action', $action);

        $this->add_action_buttons();

    }

    /**
     * Validate the school form data.
     * @param array $data Data to be validated
     * @param array $files unused
     * @return array|bool
     */
    function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        $sql = 'select name from {tool_sms_school} WHERE id != :schoolid AND cohortid != 0 AND cohortid = :cohortid';
        $params = array('schoolid' => $data['id'], 'cohortid' => $data['cohortid']);
        $record = $DB->get_record_sql($sql, $params);
        if (!empty($record)) {
            $a = array('name' => $record->name);
            $errors['cohortid']  = get_string('errorschoollinked', 'tool_smsimport', $a);
        }
        if ($errors) {
            return $errors;
        }
        return true;
    }

}