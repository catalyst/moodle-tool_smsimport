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

namespace tool_smsimport\local\form;

use tool_smsimport\local\helper;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Add school form tool_smsimport plugin.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_school_form extends moodleform {

    /**
     * School form definition.
     */
    public function definition() {

        $mform = $this->_form;
        $school = $this->_customdata['school'];
        $action = $this->_customdata['action'];

        global $DB;
        $rows = $DB->get_records('tool_smsimport');
        $options = [];
        foreach ($rows as $row) {
            $options[$row->id] = ucwords($row->name);
        }
        $mform->addElement('select', 'smsid', get_string('sms', 'tool_smsimport'), $options);

        $mform->addElement('text', 'schoolno', get_string('schoolno', 'tool_smsimport'), 'size="20"');
        $mform->addRule('schoolno', null, 'required', null, 'client');

        $mform->setType('schoolno', PARAM_TEXT);
        $mform->addHelpButton('schoolno', 'schoolno', 'tool_smsimport');

        $mform->addElement('text', 'name', get_string('schoolname', 'tool_smsimport'), 'size="20"');
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('text', 'moeid', get_string('schoolmoeid', 'tool_smsimport'), 'size="20"');
        $mform->setType('moeid', PARAM_TEXT);

        $mform->addElement('checkbox', 'transferin', get_string('transferin', 'tool_smsimport'));
        $mform->addElement('checkbox', 'transferout', get_string('transferout', 'tool_smsimport'));

        $mform->addElement('checkbox', 'suspend', get_string('suspend', 'tool_smsimport'));

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ALPHANUMEXT);
        $mform->setDefault('action', $action);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $school->id);

        $this->add_action_buttons();
    }


    /**
     * Validate the school form data.
     * @param array $data Data to be validated
     * @param array $files unused
     * @return array|bool
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['action'] == 'add' && !empty($school = helper::get_sms_school(['schoolno' => $data['schoolno']]))) {
            $a = ['url' => 'admin/tool/smsimport/add.php?action=edit&id='.$school->id];
            $errors['schoolno']  = get_string('errorschoolexists', 'tool_smsimport', $a);
        }
        if ($errors) {
            return $errors;
        }
        return true;
    }
}
