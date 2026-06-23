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

/**
 * Resolves server entry strings into connection parameters.
 *
 * Supports two line formats in the admin textarea:
 *
 * 1. DIRECT entry (existing):
 *    hostname:port
 *    hostname:port:sid_or_service   (Oracle only)
 *
 * 2. PLUGIN REFERENCE entry (new):
 *    Reads connection settings from any other installed Moodle plugin's config.
 *    Format: plugin:component:host_field[:port_field[:user_field[:pass_field[:sid_field]]]]
 *
 *    Examples:
 *      plugin:local_sisup:dbhost
 *      plugin:local_sisup:dbhost:dbport:dbuser:dbpass
 *      plugin:local_winprism:oracle_host:oracle_port:oracle_user:oracle_pass:oracle_sid
 *
 *    Fields after host_field are optional.
 *    Blank lines and lines starting with # are ignored.
 *
 * @package   local_eclass_status
 * @copyright 2026 York University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connection_resolver {

    /**
     * Parse a single line from a servers textarea into connection parameters.
     *
     * @param  string   $entry       Raw config line.
     * @param  int      $defaultport Default port for this protocol (e.g. 3306, 1521, 389).
     * @return array|null  Assoc array with keys: hostname, port, username, password, sid, label
     *                     Returns null if line should be skipped (blank / comment / invalid).
     */
    public static function resolve(string $entry, int $defaultport): ?array {
        $entry = trim($entry);

        // Skip blank lines and comments.
        if ($entry === '' || $entry[0] === '#') {
            return null;
        }

        if (strncmp($entry, 'plugin:', 7) === 0) {
            return self::resolve_plugin_reference($entry, $defaultport);
        }

        return self::resolve_direct($entry, $defaultport);
    }

    /**
     * Resolve a direct host:port[:sid] entry.
     *
     * @param  string $entry
     * @param  int    $defaultport
     * @return array
     */
    private static function resolve_direct(string $entry, int $defaultport): array {
        $parts    = explode(':', $entry);
        $hostname = $parts[0];
        $port     = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : $defaultport;
        $sid      = isset($parts[2]) ? $parts[2] : '';

        return [
            'hostname' => $hostname,
            'port'     => $port,
            'username' => '',
            'password' => '',
            'sid'      => $sid,
            'label'    => null,
            'source'   => 'direct',
        ];
    }

    /**
     * Resolve a plugin reference entry.
     *
     * Format: plugin:component:host_field[:port_field[:user_field[:pass_field[:sid_field]]]]
     *
     * @param  string $entry
     * @param  int    $defaultport
     * @return array|null
     */
    private static function resolve_plugin_reference(string $entry, int $defaultport): ?array {
        // plugin:component:host_field[:port_field[:user_field[:pass_field[:sid_field]]]]
        $parts = explode(':', $entry);

        // Minimum required: plugin, component, host_field (3 parts).
        if (count($parts) < 3 || $parts[0] !== 'plugin' || trim($parts[1]) === '' || trim($parts[2]) === '') {
            debugging(
                'local_eclass_status: invalid plugin reference entry: ' . $entry,
                DEBUG_DEVELOPER
            );
            return null;
        }

        $component  = trim($parts[1]);
        $host_field = trim($parts[2]);
        $port_field = isset($parts[3]) ? trim($parts[3]) : '';
        $user_field = isset($parts[4]) ? trim($parts[4]) : '';
        $pass_field = isset($parts[5]) ? trim($parts[5]) : '';
        $sid_field  = isset($parts[6]) ? trim($parts[6]) : '';

        $hostname = get_config($component, $host_field);
        if (empty($hostname)) {
            // Plugin not installed or field not set — skip silently.
            return null;
        }

        $port = $defaultport;
        if ($port_field !== '') {
            $portval = get_config($component, $port_field);
            if (!empty($portval) && is_numeric($portval)) {
                $port = (int)$portval;
            }
        }

        $username = $user_field !== '' ? (string)(get_config($component, $user_field) ?: '') : '';
        $password = $pass_field !== '' ? (string)(get_config($component, $pass_field) ?: '') : '';
        $sid      = $sid_field  !== '' ? (string)(get_config($component, $sid_field)  ?: '') : '';

        return [
            'hostname' => $hostname,
            'port'     => $port,
            'username' => $username,
            'password' => $password,
            'sid'      => $sid,
            'label'    => $component . ' (' . $hostname . ':' . $port . ')',
            'source'   => 'plugin:' . $component,
        ];
    }
}

