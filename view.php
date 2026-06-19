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
 * Health Status Dashboard - view live monitoring results.
 *
 * @package   local_eclass_status
 * @copyright 2026 York University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

// Require admin access.
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/local/eclass_status/view.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('dashboard:title', 'local_eclass_status'));
$PAGE->set_heading(get_string('dashboard:title', 'local_eclass_status'));
$PAGE->set_pagelayout('admin');

// Add navigation breadcrumb.
$PAGE->navbar->add(get_string('pluginname', 'local_eclass_status'), new moodle_url('/admin/settings.php?section=local_eclass_status'));
$PAGE->navbar->add(get_string('dashboard:title', 'local_eclass_status'));

// Get latest results.
$latest_run = get_latest_check_results();

$templatecontext = [
    'hasresults' => false,
    'lastcheck' => get_string('dashboard:nodata', 'local_eclass_status'),
    'criticalcount' => 0,
    'warningcount' => 0,
    'infocount' => 0,
    'totalcount' => 0,
    'criticaltitle' => get_string('dashboard:critical_items', 'local_eclass_status'),
    'warningtitle' => get_string('dashboard:warning_items', 'local_eclass_status'),
    'infotitle' => get_string('dashboard:info_items', 'local_eclass_status'),
    'criticaltable' => [],
    'warningtable' => [],
    'infotable' => [],
];

if ($latest_run) {
    $results = json_decode($latest_run->results);
    if (!is_array($results)) {
        $results = [];
    }

    $critical = [];
    $warnings = [];
    $info = [];

    foreach ($results as $result) {
        $row = format_row_for_template($result);
        switch ($result->severity ?? 'unknown') {
            case 'critical':
                $critical[] = $row;
                break;
            case 'warning':
                $warnings[] = $row;
                break;
            case 'info':
                $info[] = $row;
                break;
            default:
                $info[] = $row;
                break;
        }
    }

    $templatecontext = [
        'hasresults' => true,
        'lastcheck' => get_string('dashboard:lastcheck', 'local_eclass_status', userdate($latest_run->timerun)),
        'criticalcount' => count($critical),
        'warningcount' => count($warnings),
        'infocount' => count($info),
        'totalcount' => count($results),
        'criticaltitle' => get_string('dashboard:critical_items', 'local_eclass_status'),
        'warningtitle' => get_string('dashboard:warning_items', 'local_eclass_status'),
        'infotitle' => get_string('dashboard:info_items', 'local_eclass_status'),
        'hascritical' => !empty($critical),
        'haswarning' => !empty($warnings),
        'hasinfo' => !empty($info),
        'criticaltable' => [
            'sectiontitle' => get_string('dashboard:critical_items', 'local_eclass_status'),
            'rows' => $critical,
        ],
        'warningtable' => [
            'sectiontitle' => get_string('dashboard:warning_items', 'local_eclass_status'),
            'rows' => $warnings,
        ],
        'infotable' => [
            'sectiontitle' => get_string('dashboard:info_items', 'local_eclass_status'),
            'rows' => $info,
        ],
    ];
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_eclass_status/dashboard', $templatecontext);

echo $OUTPUT->footer();

/**
 * Convert one result object to a template-ready row.
 *
 * @param stdClass $result
 * @return array
 */
function format_row_for_template($result) {
    $severity = $result->severity ?? 'unknown';
    $badgeclass = 'bg-secondary';
    if ($severity === 'critical') {
        $badgeclass = 'bg-danger';
    } else if ($severity === 'warning') {
        $badgeclass = 'bg-warning text-dark';
    } else if ($severity === 'info') {
        $badgeclass = 'bg-info text-dark';
    }

    return [
        'name' => $result->name ?? '',
        'category' => $result->category ?? '',
        'severity' => strtoupper($severity),
        'severitybadgeclass' => $badgeclass,
        'observedvalue' => $result->observed_value ?? '-',
        'threshold' => $result->threshold ?? '-',
        'message' => $result->message ?? '-',
    ];
}

/**
 * Get the latest check results from database.
 *
 * @return stdClass|null Latest result record or null
 */
function get_latest_check_results() {
    global $DB;
    return $DB->get_record_sql(
        "SELECT * FROM {local_eclass_status_results} ORDER BY timerun DESC",
        [],
        IGNORE_MULTIPLE
    );
}

