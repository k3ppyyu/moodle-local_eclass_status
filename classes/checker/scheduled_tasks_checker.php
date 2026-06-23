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

namespace local_eclass_status\checker;

use local_eclass_status\check_result;
use core\task\manager;

/**
 * Checker for scheduled task health.
 *
 * Monitors:
 * - Long-running tasks (warn at 30min, critical at 4hr)
 * - Failed tasks in retry backoff
 * - Adhoc task queue backlog
 *
 * Uses only valid Moodle 5.x task_base API:
 *   get_timestarted(), get_fail_delay(), get_last_run_time(), get_next_run_time()
 *
 * @package   local_eclass_status
 * @copyright 2026 York University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scheduled_tasks_checker implements checker {

    public function check() {
        $results = [];

        $tasks = manager::get_all_scheduled_tasks();

        foreach ($tasks as $task) {
            $id   = 'task_' . str_replace('\\', '_', get_class($task));
            $name = $task->get_name();

            // A task is "running" when timestarted is set in the DB record.
            $timestarted = $task->get_timestarted();
            $faildelay   = $task->get_fail_delay();

            if ($timestarted !== null && $timestarted > 0) {
                // Task is actively running.
                $duration_sec = time() - $timestarted;
                $duration_min = (int)($duration_sec / 60);
                $duration_hr  = (int)($duration_min / 60);

                // Severity thresholds: warn at 30min, critical at 4hr.
                $severity  = 'info';
                $threshold = 'Normal (under 30 minutes)';
                if ($duration_min >= 240) {
                    $severity  = 'critical';
                    $threshold = 'Critical threshold: > 4 hours';
                } else if ($duration_min >= 30) {
                    $severity  = 'warning';
                    $threshold = 'Warning threshold: 30 minutes – 4 hours';
                }

                $time_str = ($duration_hr >= 1)
                    ? sprintf('%d hour%s %d minute%s',
                        $duration_hr, $duration_hr === 1 ? '' : 's',
                        $duration_min % 60, ($duration_min % 60) === 1 ? '' : 's')
                    : sprintf('%d minute%s', $duration_min, $duration_min === 1 ? '' : 's');

                $result = new check_result(
                    $id,
                    'Task: ' . $name,
                    'tasks',
                    $severity,
                    'running',
                    'Scheduled task is currently executing'
                );
                $result->observed_value = $time_str . ' elapsed';
                $result->threshold      = $threshold;
                $result->data           = ['duration_seconds' => $duration_sec, 'running' => true];
                $results[]              = $result;

            } else if ($faildelay > 0) {
                // Task has failed and is in exponential retry backoff.
                $result = new check_result(
                    $id,
                    'Task: ' . $name,
                    'tasks',
                    'warning',
                    'failed_retry',
                    'Task failed and is scheduled for retry'
                );
                $next_retry = $task->get_next_run_time();
                $result->observed_value = 'next retry: ' . ($next_retry > 0 ? userdate($next_retry) : 'unknown');
                $result->threshold      = 'should not have failed';
                $results[]              = $result;

            } else {
                // Task is idle. Check whether it is overdue.
                $lastrun  = $task->get_last_run_time();
                $nextrun  = $task->get_next_run_time();
                $now      = time();

                if ($lastrun > 0 && $nextrun > 0 && $now > $nextrun) {
                    $overdue_sec = $now - $nextrun;
                    $overdue_min = (int)($overdue_sec / 60);

                    $result = new check_result(
                        $id,
                        'Task: ' . $name,
                        'tasks',
                        'warning',
                        'overdue',
                        'Scheduled task is overdue'
                    );
                    $result->observed_value = 'overdue by ' . $overdue_min . ' minute' . ($overdue_min === 1 ? '' : 's');
                    $result->threshold      = 'should run by ' . userdate($nextrun);
                    $results[]              = $result;
                } else {
                    $result = new check_result(
                        $id,
                        'Task: ' . $name,
                        'tasks',
                        'info',
                        'ok',
                        'Task is healthy'
                    );
                    $result->observed_value = $lastrun > 0 ? 'last ran ' . userdate($lastrun) : 'never run';
                    $result->threshold      = 'within normal interval';
                    $results[]              = $result;
                }
            }
        }

        // Check adhoc task queue.
        $adhoc_count = $this->get_adhoc_task_count();
        $adhoc_severity = 'info';
        $adhoc_status   = 'ok';
        $adhoc_message  = 'Adhoc task queue is healthy';
        if ($adhoc_count > 500) {
            $adhoc_severity = 'critical';
            $adhoc_status   = 'backlog';
            $adhoc_message  = 'Adhoc task queue has a large backlog';
        } else if ($adhoc_count > 100) {
            $adhoc_severity = 'warning';
            $adhoc_status   = 'building';
            $adhoc_message  = 'Adhoc task queue is growing';
        }

        $adhoc_result = new check_result(
            'adhoc_queue',
            'Adhoc Task Queue',
            'tasks',
            $adhoc_severity,
            $adhoc_status,
            $adhoc_message
        );
        $adhoc_result->observed_value = "{$adhoc_count} task" . ($adhoc_count === 1 ? '' : 's') . ' queued';
        $adhoc_result->threshold      = 'warn > 100, critical > 500';
        $results[] = $adhoc_result;

        return $results;
    }

    public function is_enabled() {
        return true;
    }

    public function get_name() {
        return 'Scheduled Tasks Health';
    }

    /**
     * Count pending adhoc tasks.
     *
     * @return int
     */
    private function get_adhoc_task_count() {
        global $DB;
        return $DB->count_records('task_adhoc');
    }
}
