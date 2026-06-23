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
 * Checker for Oracle database connectivity.
 *
 * Each line in "Oracle Servers to Monitor" can be:
 *   Direct:           oracle.yorku.ca:1521
 *   Direct with SID:  oracle.yorku.ca:1521:PROD
 *   Plugin reference: plugin:local_sisup:oraclehost:oracleport:oracleuser:oraclepass:oraclesid
 *
 * Phase 1: TCP socket (always — works without OCI8 or credentials).
 * Phase 2: OCI8 connect + SELECT 1 FROM DUAL (when OCI8 + credentials available).
 *
 * @package   local_eclass_status
 * @copyright 2026 York University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oracle_checker implements checker {

    public function check() {
        $results = [];

        $oracle_hosts = get_config('local_eclass_status', 'oracle_servers');
        if (empty($oracle_hosts)) {
            return $results;
        }

        $lines = array_filter(array_map('trim', explode("\n", $oracle_hosts)));

        foreach ($lines as $line) {
            $conn = connection_resolver::resolve($line, 1521);
            if ($conn === null) {
                continue;
            }

            $hostname = $conn['hostname'];
            $port     = $conn['port'];
            $username = $conn['username'];
            $password = $conn['password'];
            $sid      = $conn['sid'];
            $label    = $conn['label'] ?? null;

            $id   = 'oracle_' . \clean_filename($hostname);
            $name = 'Oracle: ' . ($label ?? "$hostname:$port" . ($sid !== '' ? ":$sid" : ''));

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
                    'Cannot connect to Oracle listener');
                $result->observed_value = 'TCP connection failed' . (!empty($errstr) ? ': ' . $errstr : '');
                $result->threshold      = 'Connection should succeed';
                $result->data           = ['errno' => $errno, 'error' => $errstr, 'source' => $conn['source']];
                $results[]              = $result;
                continue;
            }
            fclose($socket);

            // --- Phase 2 (optional): OCI8 deep check ---------------------------------
            if (!empty($username) && !empty($password) && extension_loaded('oci8')) {
                $dsn        = $sid !== '' ? "$hostname:$port/$sid" : "$hostname:$port";
                $conn_start = microtime(true);
                $dbconn     = @\oci_connect($username, $password, $dsn);
                $conn_el    = (microtime(true) - $conn_start) * 1000;

                if (!$dbconn) {
                    $e = \oci_error();
                    $result = new check_result($id, $name, 'external', 'critical', 'down',
                        'TCP reachable but OCI8 connection failed');
                    $result->observed_value = 'OCI8 connect failed: ' . (is_array($e) ? $e['message'] : 'unknown error');
                    $result->threshold      = 'OCI8 connection with credentials should succeed';
                    $result->data           = ['source' => $conn['source']];
                    $results[]              = $result;
                    continue;
                }

                $q_start  = microtime(true);
                $stmt     = @\oci_parse($dbconn, 'SELECT 1 FROM DUAL');
                $ok       = $stmt && @\oci_execute($stmt);
                $q_el     = (microtime(true) - $q_start) * 1000;
                @\oci_free_statement($stmt);
                @\oci_close($dbconn);

                if (!$ok) {
                    $result = new check_result($id, $name, 'external', 'critical', 'query_failed',
                        'Connected but SELECT 1 FROM DUAL failed');
                    $result->observed_value = 'SELECT 1 FROM DUAL failed';
                    $result->threshold      = 'Query should execute successfully';
                    $results[]              = $result;
                    continue;
                }

                $total    = $conn_el + $q_el;
                $severity = $total > 5000 ? 'critical' : ($total > 2000 ? 'warning' : 'info');

                $result = new check_result($id, $name, 'external', $severity, 'ok',
                    'Oracle connectivity check (with OCI8)');
                $result->observed_value = round($total, 0) . 'ms (connect: ' . round($conn_el, 0) . 'ms, query: ' . round($q_el, 0) . 'ms)';
                $result->threshold      = '< 2000ms warning, > 5000ms critical';
                $result->data           = ['response_ms' => round($total, 0), 'source' => $conn['source']];
                $results[]              = $result;

            } else {
                // TCP-only result.
                $severity = $elapsed > 5000 ? 'critical' : ($elapsed > 2000 ? 'warning' : 'info');
                $detail   = !extension_loaded('oci8')
                    ? 'OCI8 not loaded — configure credentials + OCI8 for deeper check'
                    : 'No credentials configured — configure oracle_user/pass for deeper check';

                $result = new check_result($id, $name, 'external', $severity, 'ok',
                    'Oracle TCP connectivity check (TCP only)');
                $result->observed_value = round($elapsed, 0) . 'ms TCP response';
                $result->threshold      = '< 2000ms warning, > 5000ms critical — ' . $detail;
                $result->data           = ['response_ms' => round($elapsed, 0), 'source' => $conn['source']];
                $results[]              = $result;
            }
        }

        return $results;
    }

    public function is_enabled() {
        return !empty(get_config('local_eclass_status', 'oracle_servers'));
    }

    public function get_name() {
        return 'Oracle Database Connectivity';
    }
}
