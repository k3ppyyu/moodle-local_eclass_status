# EClass Status Plugin

**Tier 2 Internal Health Monitoring & Alerting**

A Moodle local plugin that runs internal health checks every 5 minutes and sends alerts about Moodle internal state via email.

## Overview

This plugin is designed to work **alongside** external uptime monitoring (tier 1):

- **Tier 1** (vendor): Detects when Moodle is down (external HTTP monitoring)
- **Tier 2** (this plugin): Deep internal health when Moodle is running

### Alert Policy (Configurable)

- **CRITICAL alerts are enabled by default** — triggered immediately when critical issues occur
- **WARNING and INFO alerts disabled by default** — items stored in dashboard but not emailed
- **Admins can customize** which severity levels trigger email alerts (Settings > Alert Severity Levels)
- **All results always stored in dashboard** for review and historical analysis

This keeps the inbox quiet by default while allowing ops teams to opt-in to verbose alerting if desired.

### What It Checks

- **Scheduled Tasks**: Long-running tasks (warn at 30 min, critical at 4 hr), failed tasks, task backlog
- **LDAP**: Bind/search connectivity, response time (warn > 2s, critical > 5s)
- **MySQL**: Query execution, response time (warn > 2s, critical > 5s)
- **Oracle**: Connection and query success (placeholder for future implementation)

### Severity Levels

- **INFO**: Purely informational, no action needed — visible in dashboard
- **WARNING**: Degraded, growing queue, elevated latency, needs review — visible in dashboard
- **CRITICAL**: Dependency down, repeated failures, data risk — **immediately emailed**

## Installation

1. Place this plugin in `public/local/eclass_status/`
2. Run Moodle upgrade via web UI or CLI:
   ```bash
   php admin/cli/upgrade.php
   ```
3. Configure in **Site Administration > Plugins > Local plugins > EClass Status**

## Configuration

### Basic Settings

| Setting | Default | Purpose |
|---------|---------|---------|
| **Alert Recipients** | *(empty)* | Email addresses to receive alerts |
| **Check Interval** | 5 minutes | Reference value only; actual cadence is set in Scheduled tasks |
| **Minimum Email Interval** | 30 minutes | Minimum gap between alert emails |
| **Alert Severity Levels** | Critical only | Which severity levels trigger email alerts |

### Alert Severity Levels (Configurable)

In **Site Administration > Plugins > Local plugins > EClass Status**, select which severity levels should trigger email alerts:

- **☑️ Critical** (checked by default) — Data/availability risk, requires immediate action
- **☐ Warning** (unchecked by default) — Degraded performance, elevated latency, growing queues
- **☐ Info** (unchecked by default) — Healthy status, passed checks

**Recommendation:** Keep only **Critical** selected to minimize email volume. Use the dashboard to review warnings and info items proactively.

### Alert Frequency

Two separate timings matter:

1. **Check frequency**
   - The scheduled task is registered to run every 5 minutes by default.
   - In Moodle, the real cadence is managed at **Site administration > Server > Scheduled tasks**.
   - The plugin's `Check Interval` setting is informational and should match whatever you choose there.

2. **Email frequency**
   - The plugin now has a `Minimum Email Interval (minutes)` setting.
   - Example: if checks run every 5 minutes and `Minimum Email Interval` is `30`, matching alerts can send at most once every 30 minutes.
   - Set it to `0` only for aggressive testing, because that sends on every matching task run.

### External Services

#### LDAP
Enter hostnames (one per line) to monitor LDAP connectivity:
```
ldap.example.com:389
ldap2.example.com:389
ldap.another.org:636
```

#### MySQL
Enter hosts to monitor (one per line):
```
reporting-db.example.com:3306
replica-db.example.com:3306
localhost:3306
```

You must also configure MySQL credentials in settings. (In a production deployment, use Moodle's config-forced.php or environment variables to store secrets securely.)

#### Oracle
Enter service names or host:sid (one per line):
```
PROD_SID
DEV_SID
```

(Oracle checker requires OCI8 PHP extension to be enabled.)

## Email Alerts (Configurable by Severity)

The plugin respects the admin-configured **Alert Severity Levels** setting.

### Critical (Default)
When **Critical** is checked in settings and a critical issue occurs:
- Subject: `CRITICAL: EClass Status Alert` (if critical) or `WARNING: EClass Status Alert` (if warnings included)
- Sent immediately to all recipients
- Includes only the severity levels selected by admin
- Always includes link to Health Status Dashboard for full context

### Warning & Info (Optional)
When **Warning** or **Info** are checked in settings, those items will be included in alert emails.
Without these checked (the default), warning and info items are:
- Stored in the database
- **Not emailed**
- Accessible by logging into Moodle and visiting the Health Status Dashboard
- Admins can review trends and non-critical issues at their convenience

### Example Scenarios

**Recommended Settings: Critical only (default)**

**Emails sent for:**
- LDAP auth down (emails: critical)
- Database unavailable (emails: critical)
- Backup task hung for >4 hours (emails: critical)
- Repeated task failures (emails: critical)

**Dashboard only (no email):**
- LDAP response time elevated to 2.8s (severity: warning)
- MySQL query time at 1.9s (severity: warning)
- Adhoc task queue at 120 items (severity: warning)
- Course sync task running 1h 15m (severity: info)

**Alternative Settings: Critical + Warning (for more visibility)**

**Emails sent for:**
- All critical items (above)
- LDAP response time slow
- Database latency elevated
- Task queue growing
- Long-running tasks

**Dashboard only (no email):**
- Healthy task runs
- Successful connections
- Normal latencies

## Scheduled Task

The plugin registers a scheduled task: **Run health checks and send alerts**

- Default: Runs every 5 minutes
- Can be customized in Site Administration > Server > Scheduled tasks

## Database Schema

### `mdl_local_eclass_status_results`
Stores recent check results for deduplication and digest logic:

| Column | Type | Purpose |
|--------|------|---------|
| id | int | Primary key |
| timerun | int | Timestamp of check run |
| results | text | JSON-encoded check results array |

Results are kept for 7 days by default.

## Extending / Adding Custom Checks

To add a new health check:

1. Create a class implementing `\local_eclass_status\checker\checker`:
   ```php
   namespace my_plugin\checker;
   
   use local_eclass_status\check_result;
   use local_eclass_status\checker\checker;
   
   class my_checker implements checker {
       public function check() {
           // Return array of check_result objects
           return [$result1, $result2];
       }
       
       public function is_enabled() {
           return true;
       }
       
       public function get_name() {
           return 'My Custom Check';
       }
   }
   ```

2. Register it in your plugin's hooks or modify `local_eclass_status/task/run_checks.php` to instantiate your checker.

3. Each check must return a `check_result` with:
   - `id`: unique identifier
   - `name`: human-readable name
   - `category`: category (e.g., 'tasks', 'external', 'core')
   - `severity`: 'info', 'warning', 'critical', or 'unknown'
   - `status`: descriptive status string
   - `message`: the issue or observation
   - `observed_value`: what was measured (e.g., "running 1h 30m")
   - `threshold`: what was expected (e.g., "must be under 2 hours")

## Troubleshooting

### How do I test a lost connection alert?

#### LDAP
Use a host or port that is guaranteed to fail.

Examples for the LDAP servers setting:

```text
127.0.0.1:1
no-such-host.invalid:389
```

- `127.0.0.1:1` usually fails immediately because port 1 is closed.
- `no-such-host.invalid:389` usually fails DNS resolution.

Either one should produce a critical LDAP connectivity result on the next task run.

#### MySQL
Use a host/port that will refuse connections, or configure a valid host with an intentionally wrong port.

Examples:

```text
127.0.0.1:1
mysql.invalid:3306
```

Note: the MySQL checker only runs for hosts where credentials are available in plugin config. If no credentials are stored for that host, the checker skips it.

#### Long-running task alert
The current runtime thresholds are high enough that simulating a real 4-hour task is impractical for quick testing. For testing, the easier path is to validate LDAP/MySQL failure alerts first.

#### Manual test run

Run the plugin task manually inside the Moodle container/root:

```bash
php admin/cli/scheduled_task.php --execute='\local_eclass_status\task\run_checks'
```

For repeated email testing, temporarily set `Minimum Email Interval` to `0`, run the task, then restore it to a safer value such as `15` or `30`.

### No alerts being sent
1. Check that recipients are configured in settings
2. Verify Moodle's mail settings (Settings > Mail configuration)
3. Check cron is running: Site Administration > Server > Scheduled tasks (should show recent run times)
4. Enable debugging to see task execution logs

### Task not running
- Ensure cron is executing properly
- Check that the scheduled task is enabled in Site Administration > Server > Scheduled tasks
- Check logs in `data/` directory for cron errors

### Checker is not running
- Check `is_enabled()` returns true for your checker
- Verify configuration for that external service (e.g., LDAP servers list is not empty)

## Performance

By default, all checks are lightweight:
- Task runtime checks query `mdl_task_*` tables
- External checks use simple connection tests + single query
- Results stored for 7 days only (auto-cleanup)

If checks are taking too long:
1. Increase check interval (default 5 min is reasonable)
2. Disable external checks you don't need
3. Ensure external services are responsive

## For Developers

### Class Structure

```
classes/
├── check_result.php           # Result value object
├── checker/
│   ├── checker.php            # Interface
│   ├── scheduled_tasks_checker.php
│   ├── ldap_checker.php
│   ├── mysql_checker.php
│   └── oracle_checker.php
├── notifier/
│   └── email_notifier.php     # Email building/sending
├── task/
│   └── run_checks.php         # Main scheduled task
db/
├── install.xml                # DB schema
├── tasks.php                  # Task registration
settings.php                   # Admin settings
version.php                    # Plugin metadata
lang/
└── en/
    └── local_eclass_status.php # Language strings
```

### Extending Email Format

Edit `classes/notifier/email_notifier.php`:
- `build_email_body()` — controls HTML structure
- `build_result_table()` — controls how checks are displayed in email

Templates can be added to `templates/` for future mustache-based rendering.

## License

GNU GPL v3 or later. See COPYING or http://www.gnu.org/copyleft/gpl.html

## Copyright

2026 York University

---

## Quick Start Checklist

- [ ] Install plugin and run upgrade
- [ ] Configure alert recipients (required for any alerts)
- [ ] Test that cron is running
- [ ] In settings, add LDAP servers or MySQL servers to test
- [ ] Manually trigger task: `php admin/cli/scheduled_task.php --execute='\local_eclass_status\task\run_checks'`
- [ ] Visit Health Status Dashboard to view all check results
- [ ] Configure alert severity levels (which items should email)
- [ ] Create a critical scenario to test alerting
- [ ] Verify critical email is received

## Viewing Monitoring Results

### Dashboard (Recommended)
**Site Administration > Plugins > Local plugins > EClass Status > View Dashboard**

The dashboard shows:
- All historical check results
- Severity breakdown (critical/warning/info counts)
- All monitored items with current status
- response times and thresholds
- Last check timestamp

### What Gets Monitored?

See **`MONITORED_ITEMS.md`** for complete documentation:
- All health check types
- Severity thresholds for each
- Conditions that trigger alerts
- Examples of each alert type
- How to add custom checks

**Quick Reference:**
- ✅ **Scheduled tasks** — runtime, failures, backlog
- ✅ **LDAP servers** — connectivity, response time (optional)
- ✅ **MySQL servers** — connectivity, query performance (optional)
- ⏳ **Oracle servers** — placeholder for future implementation

