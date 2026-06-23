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

/**
 * Checker for MySQL/MariaDB database connectivity.
 *
 * Tests configured MySQL servers for connection and query response time.
 * Warn > 2s, critical > 5s.
 *
 * @package   local_eclass_status
 * @copyright 2026 York University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mysql_checker implements checker {

    public function check() {
        $results = [];

        $mysql_hosts = get_config('local_eclass_status', 'mysql_servers');
        if (empty($mysql_hosts)) {
            return $results;
        }

        $hosts = array_filter(array_map('trim', explode("\n", $mysql_hosts)));

        foreach ($hosts as $host) {
            $parts = explode(':', $host);
            $hostname = $parts[0];
            $port = isset($parts[1]) ? (int)$parts[1] : 3306;
            $username = get_config('local_eclass_status', 'mysql_user_' . \clean_filename($hostname));
            $password = get_config('local_eclass_status', 'mysql_pass_' . \clean_filename($hostname));

            $id = 'mysql_' . \clean_filename($hostname);
            $name = "MySQL: $hostname:$port";

            // Skip if no credentials configured.
            if (empty($username) || empty($password)) {
                continue;
            }

            $start = microtime(true);
            $connection = @mysqli_connect($hostname, $username, $password, null, $port);
            $elapsed = (microtime(true) - $start) * 1000; // ms

            if (!$connection) {
                $result = new check_result(
                    $id,
                    $name,
                    'external',
                    'critical',
                    'down',
                    "Cannot connect to MySQL server"
                );
                $result->observed_value = "Connection failed";
                $result->threshold = "Connection should succeed";
                $results[] = $result;
                continue;
            }

            // Test query.
            $start = microtime(true);
            $query_result = @mysqli_query($connection, 'SELECT 1');
            $query_elapsed = (microtime(true) - $start) * 1000; // ms

            if (!$query_result) {
                @mysqli_close($connection);
                $result = new check_result(
                    $id,
                    $name,
                    'external',
                    'critical',
                    'down',
                    "Query execution failed"
                );
                $result->observed_value = "Query failed: " . @mysqli_error($connection);
                $result->threshold = "Query should execute successfully";
                $results[] = $result;
                continue;
            }

            @mysqli_close($connection);

            // Check response time of combined connect + query.
            $total_elapsed = $elapsed + $query_elapsed;
            $severity = 'info';
            $status = 'ok';

            if ($total_elapsed > 5000) {
                $severity = 'critical';
                $status = 'slow';
            } else if ($total_elapsed > 2000) {
                $severity = 'warning';
                $status = 'slow';
            }

            $result = new check_result(
                $id,
                $name,
                'external',
                $severity,
                $status,
                "MySQL server connectivity check"
            );
            $result->observed_value = round($total_elapsed, 0) . "ms (connect: " . round($elapsed, 0) . "ms, query: " . round($query_elapsed, 0) . "ms)";
            $result->threshold = "< 2000ms warning, > 5000ms critical";
            $result->data = ['response_ms' => round($total_elapsed, 0)];
            $results[] = $result;
        }

        return $results;
    }

    public function is_enabled() {
        $mysql_hosts = get_config('local_eclass_status', 'mysql_servers');
        return !empty($mysql_hosts);
    }

    public function get_name() {
        return 'MySQL Database Connectivity';
    }
}

