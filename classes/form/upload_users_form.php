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
 * Upload users via csv form tool_smsimport plugin.
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
require_once($CFG->libdir . '/csvlib.class.php');

class upload_users_form extends moodleform {

    /**
     * School form definition.
     */
    public function definition() {

        $mform = $this->_form;
        $data  = $this->_customdata;

        $url = new \moodle_url('example.csv');
        $link = \html_writer::link($url, 'example.csv');

        // Get system level cohorts.
        global $DB;
        $records = $DB->get_records('cohort', array('visible' => 1, 'contextid' => 1), 'name');
        $cohorts[0] = 'None';
        foreach ($records as $record) {
            $cohorts[$record->id] = $record->name;
        }
        $mform->addElement('select', 'cohortid', get_string('cohortid', 'tool_smsimport'), $cohorts);
        $mform->addRule('cohortid', null, 'required');

        $mform->addElement('static', 'examplecsv', get_string('examplecsv', 'tool_uploaduser'), $link."<br>".get_string('examplecsv', 'tool_smsimport'));
        $mform->addHelpButton('examplecsv', 'examplecsv', 'tool_uploaduser');

        $mform->addElement('filepicker', 'userfile', get_string('file'));
        $mform->addRule('userfile', null, 'required');

        $choices = \csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'tool_uploaduser'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }

        $choices = \core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploaduser'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        $choices = array('10'=>10, '20'=>20, '100'=>100, '1000'=>1000, '100000'=>100000);
        $mform->addElement('select', 'previewrows', get_string('rowpreviewnum', 'tool_uploaduser'), $choices);
        $mform->setType('previewrows', PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, get_string('upload'));

        $this->set_data($data);

    }


    /**
     * Validate the school form data.
     * @param array $data Data to be validated
     * @param array $files unused
     * @return array|bool
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }

}