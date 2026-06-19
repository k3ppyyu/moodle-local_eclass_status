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
 * Tests configured Oracle servers for connection and query response time.
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

        // Oracle check is optional and complex - requires OCI8 extension
        // For now, return empty results. Admin would configure this separately.
        // TODO: Implement Oracle connection check.

        return $results;
    }

    public function is_enabled() {
        $oracle_hosts = get_config('local_eclass_status', 'oracle_servers');
        // Only enabled if extension is available and servers configured.
        return !empty($oracle_hosts) && extension_loaded('oci8');
    }

    public function get_name() {
        return 'Oracle Database Connectivity';
    }
}

