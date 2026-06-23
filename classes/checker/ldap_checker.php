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
 * Checker for LDAP connectivity.
 *
 * Tests configured LDAP servers for:
 * - Bind/connection success
 * - Response time (warn > 2s, critical > 5s)
 *
 * @package   local_eclass_status
 * @copyright 2026 York University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ldap_checker implements checker {

    public function check() {
        $results = [];

        // Get list of LDAP servers to check from settings.
        $ldap_hosts = get_config('local_eclass_status', 'ldap_servers');
        if (empty($ldap_hosts)) {
            return $results;
        }

        $hosts = array_filter(array_map('trim', explode("\n", $ldap_hosts)));

        foreach ($hosts as $host) {
            $parts = explode(':', $host);
            $hostname = $parts[0];
            $port = isset($parts[1]) ? (int)$parts[1] : 389;

            $id = 'ldap_' . \clean_filename($hostname);
            $name = "LDAP: $hostname:$port";

            $start = microtime(true);
            $errno = 0;
            $errstr = '';
            $socket = @stream_socket_client(
                'tcp://' . $hostname . ':' . $port,
                $errno,
                $errstr,
                5,
                STREAM_CLIENT_CONNECT
            );
            $elapsed = (microtime(true) - $start) * 1000; // ms

            if ($socket === false) {
                $result = new check_result(
                    $id,
                    $name,
                    'external',
                    'critical',
                    'down',
                    'Cannot connect to LDAP server'
                );
                $result->observed_value = 'Connection failed' . (!empty($errstr) ? ': ' . $errstr : '');
                $result->threshold = 'Connection should succeed';
                $result->data = ['errno' => $errno, 'error' => $errstr];
                $results[] = $result;
                continue;
            }

            fclose($socket);

            // Check response time.
            $severity = 'info';
            $status = 'ok';

            if ($elapsed > 5000) {
                $severity = 'critical';
                $status = 'slow';
            } else if ($elapsed > 2000) {
                $severity = 'warning';
                $status = 'slow';
            }

            $result = new check_result(
                $id,
                $name,
                'external',
                $severity,
                $status,
                "LDAP server connectivity check"
            );
            $result->observed_value = round($elapsed, 0) . "ms response time";
            $result->threshold = "< 2000ms warning, > 5000ms critical";
            $result->data = ['response_ms' => round($elapsed, 0)];
            $results[] = $result;
        }

        return $results;
    }

    public function is_enabled() {
        // Only enabled if LDAP is being used and servers are configured.
        $ldap_hosts = get_config('local_eclass_status', 'ldap_servers');
        return !empty($ldap_hosts);
    }

    public function get_name() {
        return 'LDAP Connectivity';
    }
}

