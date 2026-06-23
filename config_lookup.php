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

/**
 * Plugin config field browser.
 *
 * Lets admins search for config fields stored by other plugins so they can
 * build plugin:component:field reference strings for the MySQL/Oracle monitors.
 *
 * @package   local_eclass_status
 * @copyright 2026 York University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$query = optional_param('q', '', PARAM_RAW_TRIMMED);
// Restrict to safe alphanumeric + underscore + hyphen characters.
$query = preg_replace('/[^\w\-]/', '', $query);

$PAGE->set_url(new moodle_url('/local/eclass_status/config_lookup.php', $query !== '' ? ['q' => $query] : []));
$PAGE->set_context($context);
$PAGE->set_title(get_string('config_lookup:title', 'local_eclass_status'));
$PAGE->set_heading(get_string('config_lookup:title', 'local_eclass_status'));
$PAGE->set_pagelayout('admin');

$PAGE->navbar->add(
    get_string('pluginname', 'local_eclass_status'),
    new moodle_url('/admin/settings.php', ['section' => 'local_eclass_status'])
);
$PAGE->navbar->add(get_string('config_lookup:title', 'local_eclass_status'));

echo $OUTPUT->header();

echo '<p class="text-muted">' . get_string('config_lookup:help', 'local_eclass_status') . '</p>';

// Search form.
echo '<form method="get" action="" class="mb-4">';
echo '<div class="input-group">';
echo '<input type="text" name="q" value="' . htmlspecialchars($query, ENT_QUOTES) . '" '
    . 'class="form-control" placeholder="' . get_string('config_lookup:placeholder', 'local_eclass_status') . '" '
    . 'aria-label="' . get_string('config_lookup:placeholder', 'local_eclass_status') . '">';
echo '<button type="submit" class="btn btn-primary">'
    . get_string('config_lookup:search', 'local_eclass_status')
    . '</button>';
echo '</div>';
echo '</form>';

if ($query !== '') {
    $records = $DB->get_records_sql(
        'SELECT plugin, name, value
           FROM {config_plugins}
          WHERE ' . $DB->sql_like('plugin', ':q', false) . '
          ORDER BY plugin, name',
        ['q' => '%' . $DB->sql_like_escape($query) . '%']
    );

    if (empty($records)) {
        echo $OUTPUT->notification(
            get_string('config_lookup:noresults', 'local_eclass_status', htmlspecialchars($query)),
            'info'
        );
    } else {
        // Group by plugin component.
        $by_plugin = [];
        foreach ($records as $row) {
            $by_plugin[$row->plugin][] = $row;
        }

        echo '<p class="text-muted"><small>'
            . count($records) . ' field' . (count($records) === 1 ? '' : 's') . ' in '
            . count($by_plugin) . ' plugin' . (count($by_plugin) === 1 ? '' : 's')
            . '</small></p>';

        foreach ($by_plugin as $plugin => $rows) {
            echo '<div class="card mb-3">';
            echo '<div class="card-header d-flex justify-content-between align-items-center">';
            echo '<strong><code>' . htmlspecialchars($plugin) . '</code></strong>';
            echo '<span class="badge bg-secondary">' . count($rows) . ' field' . (count($rows) === 1 ? '' : 's') . '</span>';
            echo '</div>';
            echo '<div class="table-responsive">';
            echo '<table class="table table-sm table-hover mb-0">';
            echo '<thead class="table-light">';
            echo '<tr>';
            echo '<th>' . get_string('config_lookup:col_field', 'local_eclass_status') . '</th>';
            echo '<th>' . get_string('config_lookup:col_value', 'local_eclass_status') . '</th>';
            echo '<th>' . get_string('config_lookup:col_ref', 'local_eclass_status') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($rows as $row) {
                $is_sensitive = (bool)preg_match('/(pass|secret|key|token|credential|auth)/i', $row->name);
                $preview      = $is_sensitive
                    ? '<span class="text-muted fst-italic">hidden</span>'
                    : '<small class="text-muted">' . htmlspecialchars(
                        strlen($row->value) > 80 ? substr($row->value, 0, 80) . '…' : $row->value
                    ) . '</small>';

                $ref = 'plugin:' . $plugin . ':' . $row->name;

                echo '<tr>';
                echo '<td><code>' . htmlspecialchars($row->name) . '</code></td>';
                echo '<td>' . $preview . '</td>';
                echo '<td>';
                echo '<code class="user-select-all text-break small">' . htmlspecialchars($ref) . '</code>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';
            echo '</div>'; // card
        }

        echo '<div class="alert alert-light border mt-3">';
        echo '<p class="mb-2 fw-semibold">' . get_string('config_lookup:usage_title', 'local_eclass_status') . '</p>';
        echo '<p class="mb-1 small">' . get_string('config_lookup:usage_mysql', 'local_eclass_status') . '</p>';
        echo '<code class="small">plugin:<em>component</em>:<em>host_field</em>:<em>port_field</em>:<em>user_field</em>:<em>pass_field</em></code>';
        echo '<p class="mt-2 mb-1 small">' . get_string('config_lookup:usage_oracle', 'local_eclass_status') . '</p>';
        echo '<code class="small">plugin:<em>component</em>:<em>host_field</em>:<em>port_field</em>:<em>user_field</em>:<em>pass_field</em>:<em>sid_field</em></code>';
        echo '</div>';
    }
}

echo $OUTPUT->footer();

