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
 * Base interface for health check providers.
 *
 * @package   local_eclass_status
 * @copyright 2026 York University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface checker {
    /**
     * Run the health check(s) and return array of results.
     *
     * @return check_result[]
     */
    public function check();

    /**
     * Check if this checker is enabled.
     *
     * @return bool
     */
    public function is_enabled();

    /**
     * Get a human-readable name for this checker.
     *
     * @return string
     */
    public function get_name();
}

