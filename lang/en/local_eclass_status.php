<?php

$string['pluginname'] = 'EClass Status';
$string['plugindesc'] = 'Tier 2 internal health monitoring and alerting for Moodle 5. Sends severity-based alerts and stores all results for dashboard review.';

// Check categories
$string['check:category:tasks'] = 'Scheduled Tasks';
$string['check:category:external'] = 'External Dependencies';
$string['check:category:core'] = 'Core/Site Health';
$string['check:category:mail'] = 'Mail & Notifications';

// Severity levels
$string['severity:info'] = 'Info';
$string['severity:warning'] = 'Warning';
$string['severity:critical'] = 'Critical';
$string['severity:unknown'] = 'Unknown';

// Settings
$string['setting:recipients'] = 'Alert Recipients';
$string['setting:recipients_help'] = 'Comma-separated list of email addresses to receive status alerts. Leave empty to disable alerting.';
$string['setting:check_interval'] = 'Check Interval (minutes)';
$string['setting:check_interval_help'] = 'Reference value for your preferred check cadence. The actual task frequency is controlled in Site administration > Server > Scheduled tasks. The default scheduled task cadence is every 5 minutes.';
$string['setting:email_interval'] = 'Minimum Email Interval (minutes)';
$string['setting:email_interval_help'] = 'Minimum time between alert emails. Set to 0 to send on every task run when alerts match. Recommended: 15-30 minutes to avoid duplicate alerts.';
$string['setting:alert_severity'] = 'Alert Severity Levels';
$string['setting:alert_severity_help'] = 'Select which severity levels will trigger email alerts. CRITICAL is recommended for minimal alert noise. All results are always stored in the dashboard.';
$string['setting:alert_severity_info'] = 'Info';
$string['setting:alert_severity_warning'] = 'Warning';
$string['setting:alert_severity_critical'] = 'Critical';

// Email templates
$string['email:subject:critical'] = 'CRITICAL: EClass Status Alert';
$string['email:subject:warning'] = 'WARNING: EClass Status Alert';

// Email body parts
$string['email:body:header'] = 'EClass Status Report';
$string['email:body:footer'] = 'This is an automated status report from {$a->sitename}. Do not reply to this email. Log in to Moodle to view the full health dashboard.';
$string['email:body:critical_items'] = 'Critical items requiring immediate attention:';
$string['email:body:warning_items'] = 'Warning items to review:';
$string['email:body:info_items'] = 'Informational items:';
$string['email:body:dashboardhint'] = 'For full details, review the EClass Status dashboard in Moodle.';

// Task names
$string['task:run_checks'] = 'Run health checks and send status alerts';

// External connection config
$string['config:ldap_servers'] = 'LDAP Servers to Monitor';
$string['config:ldap_servers_help'] = 'One per line: hostname:port or hostname';
$string['config:oracle_servers'] = 'Oracle Servers to Monitor';
$string['config:oracle_servers_help'] = 'One per line: host:port:sid or service name';
$string['config:mysql_servers'] = 'MySQL Servers to Monitor';
$string['config:mysql_servers_help'] = 'One per line: host:port or host (port defaults to 3306)';

// Dashboard/View strings
$string['dashboard:title'] = 'Health Status Dashboard';
$string['dashboard:lastcheck'] = 'Last check: {$a}';
$string['dashboard:nodata'] = 'No check results yet. Scheduled task runs every 5 minutes.';
$string['dashboard:nocritical'] = 'No critical issues detected.';
$string['dashboard:view_all'] = 'View All Results';
$string['dashboard:critical_items'] = 'Critical Items';
$string['dashboard:warning_items'] = 'Warning Items';
$string['dashboard:info_items'] = 'Informational Items';
$string['dashboard:totalchecks'] = 'Total Checks';
$string['dashboard:col_check'] = 'Check';
$string['dashboard:col_category'] = 'Category';
$string['dashboard:col_status'] = 'Status';
$string['dashboard:col_observed'] = 'Observed Value';
$string['dashboard:col_threshold'] = 'Threshold';
$string['dashboard:col_message'] = 'Message';
$string['dashboard:monitoring_help_title'] = 'What is monitored';
$string['dashboard:monitoring_help_item1'] = 'Scheduled task runtime, overdue status, and failures';
$string['dashboard:monitoring_help_item2'] = 'Adhoc queue backlog';
$string['dashboard:monitoring_help_item3'] = 'Configured external connectivity checks (LDAP/MySQL/Oracle)';


