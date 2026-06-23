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
 * Each line in "MySQL Servers to Monitor" can be:
 *   Direct:           127.0.0.1:3306
 *   Plugin reference: plugin:local_sisup:dbhost:dbport:dbuser:dbpass
 *
 * Phase 1: TCP socket (always — works without credentials).
 * Phase 2: MySQLi connect + SELECT 1 (when credentials available).
 *
 * Severity thresholds:
 *   TCP/connection failure   → CRITICAL
 *   Total latency > 5 000 ms → CRITICAL
 *   Total latency > 2 000 ms → WARNING
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

        $lines = array_filter(array_map('trim', explode("\n", $mysql_hosts)));

        foreach ($lines as $line) {
            $conn = connection_resolver::resolve($line, 3306);
            if ($conn === null) {
                continue;
            }

            $hostname = $conn['hostname'];
            $port     = $conn['port'];
            $username = $conn['username'];
            $password = $conn['password'];
            $label    = $conn['label'] ?? null;

            $id   = 'mysql_' . \clean_filename($hostname);
            $name = 'MySQL: ' . ($label ?? "$hostname:$port");

            // --- Phase 1: TCP socket ------------------------------------------------
            $errno  = 0;
            $errstr = '';
            $start  = microtime(true);
            $socket = @stream_socket_client(
                'tcp://' . $hostname . ':' . $port,
                $errno,
                $errstr,
                5,
                STREAM_CLIENT_CONNECT
            );
            $elapsed = (microtime(true) - $start) * 1000;

            if ($socket === false) {
                $result = new check_result($id, $name, 'external', 'critical', 'down',
                    'Cannot connect to MySQL server');
                $result->observed_value = 'TCP connection failed' . (!empty($errstr) ? ': ' . $errstr : '');
                $result->threshold      = 'Connection should succeed';
                $result->data           = ['errno' => $errno, 'error' => $errstr, 'source' => $conn['source']];
                $results[]              = $result;
                continue;
            }
            fclose($socket);

            // --- Phase 2 (optional): full auth + query --------------------------------
            if (!empty($username) && !empty($password)) {
                $db_start   = microtime(true);
                $dbconn     = @mysqli_connect($hostname, $username, $password, null, $port);
                $db_elapsed = (microtime(true) - $db_start) * 1000;

                if (!$dbconn) {
                    $result = new check_result($id, $name, 'external', 'critical', 'down',
                        'TCP reachable but MySQL auth/connection failed');
                    $result->observed_value = 'Auth failed: ' . @mysqli_connect_error();
                    $result->threshold      = 'Connection with credentials should succeed';
                    $result->data           = ['source' => $conn['source']];
                    $results[]              = $result;
                    continue;
                }

                $q_start   = microtime(true);
                $qr        = @mysqli_query($dbconn, 'SELECT 1');
                $q_elapsed = (microtime(true) - $q_start) * 1000;
                @mysqli_close($dbconn);

                if (!$qr) {
                    $result = new check_result($id, $name, 'external', 'critical', 'query_failed',
                        'Connected but query failed');
                    $result->observed_value = 'SELECT 1 failed';
                    $result->threshold      = 'Query should execute successfully';
                    $results[]              = $result;
                    continue;
                }

                $total    = $db_elapsed + $q_elapsed;
                $severity = $total > 5000 ? 'critical' : ($total > 2000 ? 'warning' : 'info');

                $result = new check_result($id, $name, 'external', $severity, 'ok',
                    'MySQL server connectivity check (with auth)');
                $result->observed_value = round($total, 0) . 'ms (connect: ' . round($db_elapsed, 0) . 'ms, query: ' . round($q_elapsed, 0) . 'ms)';
                $result->threshold      = '< 2000ms warning, > 5000ms critical';
                $result->data           = ['response_ms' => round($total, 0), 'source' => $conn['source']];
                $results[]              = $result;

            } else {
                // TCP-only result.
                $severity = $elapsed > 5000 ? 'critical' : ($elapsed > 2000 ? 'warning' : 'info');

                $result = new check_result($id, $name, 'external', $severity, 'ok',
                    'MySQL TCP connectivity check (no credentials configured)');
                $result->observed_value = round($elapsed, 0) . 'ms TCP response';
                $result->threshold      = '< 2000ms warning, > 5000ms critical — configure credentials for deeper check';
                $result->data           = ['response_ms' => round($elapsed, 0), 'source' => $conn['source']];
                $results[]              = $result;
            }
        }

        return $results;
    }

    public function is_enabled() {
        return !empty(get_config('local_eclass_status', 'mysql_servers'));
    }

    public function get_name() {
        return 'MySQL Database Connectivity';
    }
}
