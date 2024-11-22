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
 * Save school form tool_smsimport plugin.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_smsimport\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Save school form tool_smsimport plugin.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_school_form extends moodleform {

    /**
     * School form definition.
     */
    public function definition() {

        $mform = $this->_form;
        $school = $this->_customdata['school'];
        $action = $this->_customdata['action'];

        if (!empty($school->message)) {
            $message = implode('<br>', $school->message);
            $mform->addElement('html', '<h5>'.get_string('changesmake', 'tool_smsimport').'</h5>
            <div class="alert alert-warning">'.$message.'</div>');
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $school->id);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ALPHANUMEXT);
        $mform->setDefault('action', $action);

        $mform->addElement('hidden', 'groupssave');
        $mform->setType('groupssave', PARAM_ALPHANUMEXT);
        if (isset($school->groupsselect)) {
            $groups = implode('-', $school->groupsselect);
            $mform->setDefault('groupssave', $groups);
        }

        $mform->addElement('hidden', 'unlink');
        $mform->setType('unlink', PARAM_ALPHANUMEXT);
        if (isset($school->unlink)) {
            $mform->setDefault('unlink', $school->unlink);
        }

        $mform->addElement('hidden', 'cohortid');
        $mform->setType('cohortid', PARAM_INT);
        $mform->setDefault('cohortid', $school->cohortid);

        if (!empty($school->message)) {
            $this->add_action_buttons(true, 'Continue');
        }
    }

    /**
     * Validate the school form data.
     * @param array $data Data to be validated
     * @param array $files unused
     * @return array|bool
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}
