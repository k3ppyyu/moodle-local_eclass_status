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

namespace local_eclass_status\task;

use core\task\scheduled_task;
use local_eclass_status\checker\scheduled_tasks_checker;
use local_eclass_status\checker\ldap_checker;
use local_eclass_status\checker\mysql_checker;
use local_eclass_status\checker\oracle_checker;
use local_eclass_status\notifier\email_notifier;

/**
 * Scheduled task to run health checks and send alerts.
 *
 * @package   local_eclass_status
 * @copyright 2026 York University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_checks extends scheduled_task {

    public function get_name() {
        return get_string('task:run_checks', 'local_eclass_status');
    }

    public function execute() {
        $recipients = get_config('local_eclass_status', 'recipients');
        if (empty($recipients)) {
            mtrace('local_eclass_status: No recipients configured. Skipping health checks.');
            return;
        }

        mtrace('local_eclass_status: Starting health checks...');

        $all_results = [];

        // Run each checker.
        $checkers = [
            new scheduled_tasks_checker(),
            new ldap_checker(),
            new mysql_checker(),
            new oracle_checker(),
        ];

        foreach ($checkers as $checker) {
            if (!$checker->is_enabled()) {
                mtrace("  Skipping {$checker->get_name()} (not enabled)");
                continue;
            }

            mtrace("  Running {$checker->get_name()}...");
            try {
                $results = $checker->check();
                $all_results = array_merge($all_results, $results);
                mtrace("    Found " . count($results) . " checks");
            } catch (\Exception $e) {
                mtrace("    ERROR: " . $e->getMessage());
            }
        }

        mtrace("Total checks run: " . count($all_results));

        // Group by severity for logging.
        $critical = array_filter($all_results, function($r) { return $r->get_severity() === 'critical'; });
        $warnings = array_filter($all_results, function($r) { return $r->get_severity() === 'warning'; });
        $info = array_filter($all_results, function($r) { return $r->get_severity() === 'info'; });

        mtrace("  Severity breakdown: " . count($critical) . " critical, " . count($warnings) . " warning, " . count($info) . " info");

        if (email_notifier::send_alert($all_results)) {
            mtrace('  Sent alert email for configured severities.');
        } else {
            mtrace('  No email sent (no matching severities or no recipients configured).');
        }

        // Store all results for dashboard/plugin viewing.
        $this->store_results($all_results);

        mtrace('local_eclass_status: Health checks completed.');
    }

    /**
     * Store results for deduplication and digest.
     *
     * @param array $results
     */
    private function store_results($results) {
        global $DB;

        $data = [
            'timerun' => time(),
            'results' => json_encode($results),
        ];

        // Keep last 100 result sets.
        $DB->delete_records_select('local_eclass_status_results',
            'timerun < ?',
            [time() - (7 * 86400)] // Keep 7 days
        );

        $DB->insert_record('local_eclass_status_results', (object)$data);
    }
}

