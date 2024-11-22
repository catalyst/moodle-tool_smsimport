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
```
For e.g.
insert into mdl_tool_sms values (1, 'example_key', 'example_secret', 'example_name', '1718295277', '1718295277', 'https://myexample.com/gettoken', 'https://myexample.com/getStudentsData', 'https://myexample.com/getGroupsData');
```
# How to use?

* Manage SMS import -> admin/settings.php?section=tool_smsimport_managesms
* Manage SMS import schools -> admin/tool/smsimport/index.php
    You add / edit / delete schools
    You add groups or classes to a school
* Build a SMS import report
    Go to log report ->  Admin -> Reports -> Custom reports -> Source -> SMS logs
* Schedule task to import the users
    \tool_smsimport\task\import_sms_users
* Schedule task to clean-up users from incorrect group
    \tool_smsimport\task\cleanup_sms_users
* SMS import schedule tasks. Runs at 12am daily

# Screenshots

![image](pix/page-index.png)
![image](pix/page-groupsadd.png)
![image](pix/page-pluginconfig.png)
![image](pix/page-reportsmslogs.png)