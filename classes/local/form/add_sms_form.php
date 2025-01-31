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
 * Add SMS form tool_smsimport plugin.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_smsimport\local\form;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Add SMS form tool_smsimport plugin.
 *
 * @package   tool_smsimport
 * @copyright 2024, Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_sms_form extends moodleform {
    /**
     * SMS form definition.
     */
    public function definition() {

        $mform = $this->_form;

        $mform->addElement('textarea', 'smsconfig', get_string("smsconfig", "tool_smsimport"),
        'wrap="virtual" rows="20" cols="50"');
        $mform->addHelpButton('smsconfig', 'smsconfig', 'tool_smsimport');

        $this->add_action_buttons();
    }
}
