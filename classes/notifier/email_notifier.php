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

namespace local_eclass_status\notifier;

use local_eclass_status\check_result;

/**
 * Email notifier for health checks.
 *
 * Handles sending alerts for configured severity levels (info, warning, critical).
 * Defaults to critical only. Items not configured to be emailed are still stored in the plugin for dashboard review.
 *
 * @package   local_eclass_status
 * @copyright 2026 York University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_notifier {

    /**
     * Send alert for configured severity levels.
     *
     * Respects the admin-configured alert_severity setting.
     * Items not configured to be emailed are still stored in the plugin for dashboard review.
     *
     * @param check_result[] $results Array of check results
     * @return bool True if email sent
     */
    public static function send_alert($results) {
        global $PAGE, $SITE;

        if (empty($results)) {
            return false;
        }

        $recipients = get_config('local_eclass_status', 'recipients');
        if (empty($recipients)) {
            return false;
        }

        $emails = array_filter(array_map('trim', explode(',', $recipients)));
        if (empty($emails)) {
            return false;
        }

        // Get configured severity levels to alert on.
        $alert_severity_config = get_config('local_eclass_status', 'alert_severity');
        if (empty($alert_severity_config)) {
            // Default to critical if not configured.
            $alert_severity_config = 'critical';
        }

        $alert_severities = self::parse_alert_severities($alert_severity_config);

        if (empty($alert_severities)) {
            return false;
        }

        // Filter results by configured severity levels.
        $to_alert = array_filter($results, function($r) use ($alert_severities) {
            return in_array($r->get_severity(), $alert_severities, true);
        });

        if (empty($to_alert)) {
            return false;
        }

        $emailinterval = (int)get_config('local_eclass_status', 'email_interval');
        $lastsent = (int)get_config('local_eclass_status', 'last_email_sent');
        if ($emailinterval > 0 && $lastsent > 0) {
            $minnextsend = $lastsent + ($emailinterval * MINSECS);
            if (time() < $minnextsend) {
                return false;
            }
        }

        $fromuser = \core_user::get_support_user();

        // Determine subject based on highest severity being alerted.
        $has_critical = count(array_filter($to_alert, fn($r) => $r->get_severity() === 'critical')) > 0;
        $subject = $has_critical
            ? get_string('email:subject:critical', 'local_eclass_status')
            : get_string('email:subject:warning', 'local_eclass_status');

        $dashboardurl = (new \moodle_url('/local/eclass_status/view.php'))->out(false);
        $renderer = $PAGE->get_renderer('local_eclass_status');
        $body = $renderer->render_from_template('local_eclass_status/email_alert', self::build_email_context($to_alert, $dashboardurl, $SITE->fullname));
        $plaintext = html_to_text($body);

        foreach ($emails as $email) {
            $touser = (object)[
                'id' => -1,
                'email' => $email,
                'firstname' => '',
                'lastname' => $email,
                'maildisplay' => true,
            ];

            email_to_user($touser, $fromuser, $subject, $plaintext, $body);
        }

        set_config('last_email_sent', time(), 'local_eclass_status');

        return true;
    }

    /**
     * Build formatted email body for configured alert severity items.
     *
     * @param check_result[] $results All results (filtered by send_alert)
     * @return string HTML email body
     */
    private static function build_email_context($results, $dashboardurl, $sitename) {
        $criticalrows = [];
        $warningrows = [];
        $inforows = [];

        foreach ($results as $result) {
            $row = self::format_row_for_template($result);
            if ($result->get_severity() === 'critical') {
                $criticalrows[] = $row;
            } else if ($result->get_severity() === 'warning') {
                $warningrows[] = $row;
            } else if ($result->get_severity() === 'info') {
                $inforows[] = $row;
            }
        }

        return [
            'sitename' => $sitename,
            'generated' => userdate(time()),
            'dashboardurl' => $dashboardurl,
            'footer' => get_string('email:body:footer', 'local_eclass_status', (object)['sitename' => $sitename]),
            'hascritical' => !empty($criticalrows),
            'haswarning' => !empty($warningrows),
            'hasinfo' => !empty($inforows),
            'criticalrows' => $criticalrows,
            'warningrows' => $warningrows,
            'inforows' => $inforows,
        ];
    }

    /**
     * Build HTML table of check results.
     *
     * @param check_result[] $results
     * @return string HTML table
     */
    private static function parse_alert_severities($configvalue) {
        if (is_array($configvalue)) {
            $severities = [];
            foreach ($configvalue as $key => $value) {
                if (!empty($value)) {
                    $severities[] = $key;
                }
            }
            return $severities;
        }

        if (!is_string($configvalue) || $configvalue === '') {
            return [];
        }

        $raw = preg_split('/[,\s]+/', $configvalue);
        return array_values(array_filter(array_map('trim', $raw)));
    }

    /**
     * Format a check result for template output.
     *
     * @param check_result $result
     * @return array
     */
    private static function format_row_for_template($result) {
        return [
            'name' => $result->name,
            'observedvalue' => $result->observed_value,
            'message' => $result->message,
            'threshold' => $result->threshold,
        ];
    }
}

