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
 * Admin settings for EClass Status plugin.
 *
 * @package   local_eclass_status
 * @copyright 2026 York University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('localplugins', new admin_category('local_eclass_status_settings', get_string('pluginname', 'local_eclass_status')));

// Alert Configuration.
$settings = new admin_settingpage('local_eclass_status', get_string('pluginname', 'local_eclass_status'));
$ADMIN->add('local_eclass_status_settings', $settings);

$settings->add(new admin_setting_configtext(
    'local_eclass_status/recipients',
    get_string('setting:recipients', 'local_eclass_status'),
    get_string('setting:recipients_help', 'local_eclass_status'),
    '',
    PARAM_TEXT
));

$settings->add(new admin_setting_configtext(
    'local_eclass_status/check_interval',
    get_string('setting:check_interval', 'local_eclass_status'),
    get_string('setting:check_interval_help', 'local_eclass_status'),
    '5',
    PARAM_INT
));

$settings->add(new admin_setting_configtext(
    'local_eclass_status/email_interval',
    get_string('setting:email_interval', 'local_eclass_status'),
    get_string('setting:email_interval_help', 'local_eclass_status'),
    '30',
    PARAM_INT
));

$severity_choices = [
    'info' => get_string('setting:alert_severity_info', 'local_eclass_status'),
    'warning' => get_string('setting:alert_severity_warning', 'local_eclass_status'),
    'critical' => get_string('setting:alert_severity_critical', 'local_eclass_status'),
];

$settings->add(new admin_setting_configmulticheckbox(
    'local_eclass_status/alert_severity',
    get_string('setting:alert_severity', 'local_eclass_status'),
    get_string('setting:alert_severity_help', 'local_eclass_status'),
    ['critical' => 1], // Default: critical only
    $severity_choices
));


// External Services Configuration.
$lookupurl = new moodle_url('/local/eclass_status/config_lookup.php');
$settings->add(new admin_setting_description(
    'local_eclass_status/config_lookup_link',
    get_string('config_lookup:settings_link', 'local_eclass_status'),
    get_string('config_lookup:settings_link_desc', 'local_eclass_status', $lookupurl->out(false))
));

$settings->add(new admin_setting_heading(
    'local_eclass_status/ldap_heading',
    get_string('check:category:external', 'local_eclass_status') . ': LDAP',
    ''
));

$settings->add(new admin_setting_configtextarea(
    'local_eclass_status/ldap_servers',
    get_string('config:ldap_servers', 'local_eclass_status'),
    get_string('config:ldap_servers_help', 'local_eclass_status'),
    '',
    PARAM_TEXT
));

// MySQL Configuration.
$settings->add(new admin_setting_heading(
    'local_eclass_status/mysql_heading',
    get_string('check:category:external', 'local_eclass_status') . ': MySQL',
    ''
));

$settings->add(new admin_setting_configtextarea(
    'local_eclass_status/mysql_servers',
    get_string('config:mysql_servers', 'local_eclass_status'),
    get_string('config:mysql_servers_help', 'local_eclass_status'),
    '',
    PARAM_TEXT
));

// Oracle Configuration.
$settings->add(new admin_setting_heading(
    'local_eclass_status/oracle_heading',
    get_string('check:category:external', 'local_eclass_status') . ': Oracle',
    ''
));

$settings->add(new admin_setting_configtextarea(
    'local_eclass_status/oracle_servers',
    get_string('config:oracle_servers', 'local_eclass_status'),
    get_string('config:oracle_servers_help', 'local_eclass_status'),
    '',
    PARAM_TEXT
));

