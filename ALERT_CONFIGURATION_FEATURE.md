# Configurable Alert Severity Feature

**local_eclass_status** now supports **admin-selectable alert severity levels**, allowing ops teams to control which types of status issues trigger email alerts.

## Overview

Instead of hard-coded "critical only" alerts, admins can now choose:

| Setting | Default | Purpose |
|---------|---------|---------|
| ☑️ **Critical** | Enabled | High-urgency issues requiring immediate action (data/availability risk) |
| ☐ **Warning** | Disabled | Degraded performance, elevated latency, growing queues |
| ☐ **Info** | Disabled | Healthy status and successful checks |

## How It Works

### Email Logic

- **Only selected severity levels trigger emails**
- All unselected levels are **still checked and stored in the dashboard**
- Subject line adapts: "CRITICAL:" if critical items present, "WARNING:" otherwise
- Items are grouped by severity in the email body

### Example Scenarios

#### Default Configuration (Critical only)
- Emails sent: LDAP down, DB unavailable, backup hung, repeated failures
- Dashboard only: slow LDAP (2.8s), elevated latency, task queue growing

#### Verbose Configuration (Critical + Warning)
- Emails sent: All above + LDAP slow, DB latency elevated, queue building
- Dashboard only: healthy tasks, normal operations info

## Configuration

**Site Administration > Plugins > Local plugins > EClass Status**

Use the **Alert Severity Levels** checkbox group to select which levels to email:

```
☑ Critical       - Send if any critical issues
☐ Warning        - Send if any warning issues (optional)
☐ Info           - Send if any info items (optional, verbose)
```

## Admin Settings

### `alert_severity` Configuration Setting

- Type: `admin_setting_configmulticheckbox`
- Default: `['critical' => 1]` (critical only)
- Stored as: Serialized array or comma-delimited string
- Accessible: `get_config('local_eclass_status', 'alert_severity')`

## Code Changes

### Email Notifier (`email_notifier.php`)
- `send_alert($results)` now filters by configured severity levels
- `build_email_body()` dynamically renders critical/warning/info sections based on what's present
- Subject determined by highest severity in alert (critical > warning > info)

### Scheduled Task (`run_checks.php`)
- Logs severity breakdown (critical/warning/info counts)
- Respects `alert_severity` configuration
- Still stores all results in DB regardless of email settings

### Settings (`settings.php`)
- Added `admin_setting_configmulticheckbox` for alert severity selection
- Removed `digest_time`, `critical_immediate`, `recovery_notify` settings
- Simplified to: recipients + check_interval + alert_severity

### Language Strings (`lang/en/local_eclass_status.php`)
- Added `setting:alert_severity`, `setting:alert_severity_help`
- Added severity option labels: `setting:alert_severity_info`, etc.
- Updated `email:subject:warning` string

## Usage Examples

### Get Current Alert Configuration

```php
$alert_severity_config = get_config('local_eclass_status', 'alert_severity');
// Returns: 'critical' or 'critical,warning' or similar
```

### Check If Warnings Should Be Alerted

```php
$config = get_config('local_eclass_status', 'alert_severity');
$should_alert_warnings = in_array('warning', (array)$config);
```

### Update Configuration Programmatically

```php
set_config('alert_severity', 'critical,warning', 'local_eclass_status');
```

## Testing the Feature

1. Install plugin and run upgrade
2. In Settings, keep default (Critical only selected)
3. Run a check that produces no critical items — no email sent
4. Create a critical condition, run task — critical email sent
5. In Settings, select Critical + Warning
6. Run a check with warning items — warning email sent
7. Verify both critical and warning sections appear in email body

## Backward Compatibility

- First-time installs default to critical-only (safe, quiet)
- Existing installations keep their settings (non-breaking)
- No changes to check results or severity classification

## Dashboard

All results regardless of alert configuration are stored in:
- Database table: `mdl_local_eclass_status_results`
- Viewable via: Health Status Dashboard (future feature)

This allows silent monitoring (email only critical), with admins proactively reviewing dashboard for trends.

---

**Benefit:** Reduces alert fatigue while maintaining full visibility. Ops teams choose their own alerting posture.

