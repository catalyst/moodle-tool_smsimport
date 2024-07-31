# moodle-tool_smsimport

Student management system import

This plugin allows Moodle LMS to be integrated with multiple student management systems.

# Installation

Add the plugin to /admin/tool/

Run the Moodle upgrade.

# Configuration

The plugin can be configured from Admin -> Plugins -> Admin tools -> Student management system import -> Manage SMS

# Setup

The SMS details are to be entered manually in the plugin table directly

For e.g.
insert into mdl_tool_sms values (1, 'example_key', 'example_secret', 'example_name', '1718295277', '1718295277', 'https://myexample.com/gettoken', 'https://myexample.com/getStudentsData', 'https://myexample.com/getGroupsData');

