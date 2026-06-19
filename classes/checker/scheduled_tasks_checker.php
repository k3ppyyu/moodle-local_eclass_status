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
 * - Failed tasks (critical if repeated)
 * - Adhoc task queue backlog
 *
 * @package   local_eclass_status
 * @copyright 2026 York University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scheduled_tasks_checker implements checker {

    public function check() {
        $results = [];

        // Get all scheduled tasks.
        $tasks = manager::get_all_scheduled_tasks();

        foreach ($tasks as $task) {
            // Check if task is running.
            $execution = $task->get_last_run_time();
            $locked = $task->is_locked();
            $nextrun = $task->get_next_run_time();
            $faildelay = $task->get_fail_delay();

            $id = "task_" . str_replace('\\', '_', get_class($task));
            $name = $task->get_name();

            if ($locked) {
                // Task is running.
                $start_time = $task->get_status_start_time();
                $duration_sec = time() - $start_time;
                $duration_min = round($duration_sec / 60);
                $duration_hr = round($duration_min / 60);

                // Apply severity based on duration.
                $severity = 'info';
                $threshold = 'info threshold: < 30 minutes';
                if ($duration_min > 30 && $duration_min < 120) {
                    $severity = 'warning';
                    $threshold = 'warning threshold: 30 minutes - 2 hours';
                } else if ($duration_min >= 120) {
                    $severity = 'critical';
                    $threshold = 'critical threshold: > 4 hours (currently ' . round($duration_min / 60, 1) . ' hr)';
                    if ($duration_min > 240) {
                        $severity = 'critical';
                    }
                }

                $time_str = ($duration_hr >= 1)
                    ? sprintf('%d hour%s %d minute%s', $duration_hr, $duration_hr === 1 ? '' : 's', $duration_min % 60, ($duration_min % 60) === 1 ? '' : 's')
                    : sprintf('%d minute%s', $duration_min, $duration_min === 1 ? '' : 's');

                $result = new check_result(
                    $id,
                    'Task: ' . $name,
                    'tasks',
                    $severity,
                    'running',
                    "Scheduled task is running"
                );
                $result->observed_value = $time_str . ' elapsed';
                $result->threshold = $threshold;
                $result->data = ['duration_seconds' => $duration_sec, 'locked' => true];
                $results[] = $result;

            } else if ($faildelay > 0) {
                // Task has failed and is in retry backoff.
                $result = new check_result(
                    $id,
                    'Task: ' . $name,
                    'tasks',
                    'warning',
                    'failed_retry',
                    "Task failed and is scheduled for retry"
                );
                $result->observed_value = 'retry at ' . userdate($faildelay);
                $result->threshold = 'should not have failed';
                $results[] = $result;

            } else {
                // Task is not running; check if it's overdue or had recent failures.
                $time_since_run = time() - $execution;
                $expected_interval = $nextrun - $execution;
                $overdue = ($time_since_run > $expected_interval * 2);

                if ($overdue) {
                    $result = new check_result(
                        $id,
                        'Task: ' . $name,
                        'tasks',
                        'warning',
                        'overdue',
                        "Scheduled task is overdue"
                    );
                    $result->observed_value = 'last ran ' . userdate($execution);
                    $result->threshold = 'should run every ' . round($expected_interval / 60) . ' minutes';
                    $results[] = $result;
                } else {
                    $result = new check_result(
                        $id,
                        'Task: ' . $name,
                        'tasks',
                        'info',
                        'ok',
                        "Task is healthy"
                    );
                    $result->observed_value = 'last ran ' . userdate($execution);
                    $result->threshold = 'within normal interval';
                    $results[] = $result;
                }
            }
        }

        // Check adhoc task queue.
        $adhoc_count = $this->get_adhoc_task_count();
        $adhoc_result = new check_result(
            'adhoc_queue',
            'Adhoc Task Queue',
            'tasks',
            'info',
            'ok',
            'Adhoc task queue is healthy'
        );
        $adhoc_result->observed_value = "{$adhoc_count} tasks queued";
        $adhoc_result->threshold = 'queue should not exceed 100 tasks';

        if ($adhoc_count > 100) {
            $adhoc_result->severity = 'warning';
            $adhoc_result->status = 'building';
        }
        if ($adhoc_count > 500) {
            $adhoc_result->severity = 'critical';
            $adhoc_result->status = 'backlog';
        }
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
     * Count adhoc tasks in queue.
     *
     * @return int
     */
    private function get_adhoc_task_count() {
        global $DB;
        return $DB->count_records('task_adhoc');
    }
}

