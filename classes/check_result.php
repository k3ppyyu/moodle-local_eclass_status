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

namespace local_eclass_status;

/**
 * Result of a single health check.
 *
 * @package   local_eclass_status
 * @copyright 2026 York University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_result {
    /**
     * Unique identifier for this check.
     * @var string
     */
    public $id;

    /**
     * Human-readable check name.
     * @var string
     */
    public $name;

    /**
     * Check category: 'tasks', 'external', 'core', 'mail', etc.
     * @var string
     */
    public $category;

    /**
     * Severity: 'info', 'warning', 'critical', 'unknown'.
     * @var string
     */
    public $severity;

    /**
     * Status/state: 'ok', 'down', 'slow', 'running', etc.
     * @var string
     */
    public $status;

    /**
     * Human-readable message/issue description.
     * @var string
     */
    public $message;

    /**
     * The value that was observed (e.g., "running 1h 30m", "2.8s response time").
     * @var string
     */
    public $observed_value;

    /**
     * The threshold or expectation (e.g., "must be under 2s", "must succeed").
     * @var string
     */
    public $threshold;

    /**
     * Unix timestamp when this check was performed.
     * @var int
     */
    public $timestamp;

    /**
     * Optional: additional structured data for this result.
     * @var array
     */
    public $data = [];

    /**
     * Constructor.
     *
     * @param string $id Unique check ID
     * @param string $name Human-readable name
     * @param string $category Category (tasks, external, core, mail, etc.)
     * @param string $severity Severity (info, warning, critical, unknown)
     * @param string $status Status string
     * @param string $message Issue description
     */
    public function __construct($id, $name, $category, $severity, $status, $message) {
        $this->id = $id;
        $this->name = $name;
        $this->category = $category;
        $this->severity = $severity;
        $this->status = $status;
        $this->message = $message;
        $this->timestamp = time();
    }

    /**
     * Normalize severity to a canonical value.
     *
     * @return string
     */
    public function get_severity() {
        $valid = ['info', 'warning', 'critical', 'unknown'];
        $sev = strtolower($this->severity);
        return in_array($sev, $valid) ? $sev : 'unknown';
    }

    /**
     * Compare two checks for equality (used in deduplication).
     *
     * @param check_result $other
     * @return bool
     */
    public function equals_state(check_result $other) {
        return $this->id === $other->id
            && $this->severity === $other->severity
            && $this->status === $other->status;
    }

    /**
     * Get fingerprint for deduplication.
     *
     * @return string
     */
    public function fingerprint() {
        return md5("{$this->id}|{$this->severity}|{$this->status}");
    }
}

