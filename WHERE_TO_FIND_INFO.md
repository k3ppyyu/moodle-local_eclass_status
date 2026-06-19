# Where to Find Monitoring Information

## Quick Navigation

| I Want To... | See File | Notes |
|---|---|---|
| **Get started quickly** | `QUICK_REFERENCE.md` | 1-page overview of all checks & thresholds |
| **List what's monitored** | `MONITORED_ITEMS.md` | Complete documentation of each check type |
| **Configure alerts** | `ADMIN_GUIDE.md` | Step-by-step admin configuration |
| **Full technical docs** | `README.md` | Installation, architecture, database schema |
| **Understand alert features** | `ALERT_CONFIGURATION_FEATURE.md` | How severity filtering works |
| **View live status** | Web UI: `/local/eclass_status/view.php` | Live dashboard with all results |
| **Test the plugin** | Terminal: Run scheduled task manually | See "Testing" section below |

---

## What's Being Monitored? (TL;DR)

### ✅ **Always Checked (Built-In)**

1. **Scheduled Tasks**
   - Runtime duration (warn if >30m, critical if >4h)
   - Failure status (warning if failed, critical if repeated)
   - Last run time (warning if overdue)

2. **Adhoc Task Queue**
   - Queue depth (info if <100, warning if 100-500, critical if >500)
   - Detects backlog growth

### 🔧 **Optional (Configure in Settings)**

3. **LDAP Servers** (if configured)
   - Connection success/failure
   - Response time (warn >2s, critical >5s)
   - One per line in settings: `hostname:389`

4. **MySQL Servers** (if configured)
   - Connection success
   - Query execution (`SELECT 1`)
   - Response time (warn >2s, critical >5s)
   - One per line: `host:3306`

5. **Oracle Servers** (placeholder, future)
   - Not yet implemented but framework ready

---

## Where to View Results

### 1. **Live Dashboard (Best Option)**

**Path:** `/local/eclass_status/view.php`

**How to access:**
1. Log in as admin
2. Site Administration > Plugins > Local plugins
3. Look for "EClass Status" entry
4. (Future link to "View Dashboard")

**What you'll see:**
- Severity summary badges (Critical: 2, Warning: 1, Info: 7)
- Table of all checks with:
  - Check name
  - Category
  - Severity badge
  - Observed value (timing, latency, etc.)
  - Threshold
  - Message

### 2. **Database directly**

**Table:** `mdl_local_eclass_status_results`

**Columns:**
- `id` — primary key
- `timerun` — when check was run (unix timestamp)
- `results` — JSON array of check_result objects

**Query:**
```sql
SELECT * FROM mdl_local_eclass_status_results 
ORDER BY timerun DESC 
LIMIT 1;
```

### 3. **Email (if configured)**

**When:** Immediately when critical items occur (or per configured severity)

**Example subject:** `CRITICAL: EClass Status Alert`

**Content:**
- Only severity levels admin configured
- All items grouped by severity
- Link to dashboard for full context

### 4. **Cron/Task Log**

**Command:**
```bash
php admin/cli/scheduled_task.php --execute='\local_eclass_status\task\run_checks'
```

**Output shows:**
```
Starting health checks...
  Running Scheduled Tasks Health...
    Found 12 checks
  Running LDAP Connectivity...
    Found 2 checks
  ...
Severity breakdown: 0 critical, 1 warning, 13 info
Sending alert for: critical, warning
Health checks completed.
```

---

## Things NOT Currently Monitored

The plugin does **not** read the Moodle system status page. It performs independent checks:

- ❌ Plugin/extension updates available
- ❌ Cache store health
- ❌ Search indexing state
- ❌ Mail queue depth
- ❌ Disk space
- ❌ Backup state
- ❌ PHP/environment errors
- ❌ Database schema issues

**These could be added by creating custom checker classes.** See `MONITORED_ITEMS.md` > "How to Add New Checks".

---

## Severity Labels & What They Mean

### 🔴 CRITICAL
- **Meaning:** Service unavailable, data at risk, immediate action needed
- **Examples:**
  - LDAP connection fails (login broken)
  - Database unavailable (queries fail)
  - Backup task hung >4 hours
  - Task failed repeatedly
- **Emails:** Yes (by default, configurable)

### ⚠️ WARNING
- **Meaning:** Degraded performance, approaching problem
- **Examples:**
  - LDAP responding in 2.8s (slow but working)
  - MySQL latency at 1.9s (elevated but OK)
  - Adhoc queue at 156 items (growing)
  - Task running 1h 30m (longer than usual)
- **Emails:** No by default (configurable in settings)

### ℹ️ INFO
- **Meaning:** Healthy, everything normal
- **Examples:**
  - Task completed successfully
  - LDAP responding in 186ms
  - Adhoc queue has 2 items
  - MySQL responding in 23ms
- **Emails:** No by default (configurable in settings)

---

## Configuration Quick Links

**All settings at:** Site Administration > Plugins > Local plugins > EClass Status

### Basic Settings
- **Alert Recipients** — comma-separated email list (required)
- **Check Interval** — minutes between checks (default: 5)

### Alert Severity Configuration
- **Alert Severity Levels** — checkboxes to choose which severities email
  - ☑️ Critical (enabled by default)
  - ☐ Warning (disabled by default)
  - ☐ Info (disabled by default)

### External Services (Optional)
- **LDAP Servers to Monitor** — one per line (hostname:port)
- **MySQL Servers to Monitor** — one per line (host:port)
- **Oracle Servers to Monitor** — one per line (future)

---

## How the Plugin Works (Architecture)

```
┌─────────────────────────────────────────┐
│   Scheduled Task (every 5 minutes)      │
│   run_checks.php                        │
└──────────────┬──────────────────────────┘
               │
     ┌─────────┴──────────┐
     │                    │
     v                    v
┌──────────────┐   ┌─────────────┐
│  Checkers    │   │  Notifier   │
│              │   │             │
│ - tasks      │   │ - Email     │
│ - ldap       │   │   filter by │
│ - mysql      │   │   severity  │
│ - oracle     │   │ - Send to   │
│              │   │   recipients│
└──────┬───────┘   └────┬────────┘
       │                │
       └────────┬───────┘
                │
                v
        ┌──────────────────┐
        │  Database Store  │
        │                  │
        │ results table    │
        │ (all items kept) │
        └────────┬─────────┘
                 │
                 v
        ┌──────────────────┐
        │  Dashboard View  │
        │  /view.php       │
        │  (browse live)   │
        └──────────────────┘
```

---

## Testing Your Monitoring

### 1. **View current settings**
```bash
# SSH to container
docker exec -it moodle /bin/bash

# Check what's configured
grep -r "alert_severity" /moodle/config.php
```

### 2. **Run checks manually**
```bash
php /moodle/admin/cli/scheduled_task.php \
  --execute='\local_eclass_status\task\run_checks'

# Output will show what was checked and if alerts were sent
```

### 3. **View latest results in database**
```bash
mysql -u root -p moodle -e \
  "SELECT timerun, results FROM mdl_local_eclass_status_results ORDER BY timerun DESC LIMIT 1\G"
```

### 4. **Check mail queue**
```bash
# If alerts were sent, verify mail was queued
grep "local_eclass_status" /moodle/data/mail/
```

### 5. **Visit the dashboard**
1. Log in as admin
2. Navigate to: `/local/eclass_status/view.php`
3. Look at latest results summary

---

## Support & Next Steps

### If you need to...

**Add custom health checks:**
- See `MONITORED_ITEMS.md` > "How to Add New Checks"
- Implement checker interface
- Register in run_checks.php

**Change alert rules:**
- Edit severity thresholds in checker classes
- E.g., `scheduled_tasks_checker.php` line ~XX for task runtimes

**Add external service monitoring:**
- Create new checker (e.g., `redis_checker.php`)
- Add settings for configuration
- Follow same pattern as `mysql_checker.php`

**Debug why no alerts sent:**
- File: `ADMIN_GUIDE.md` > "Troubleshooting"
- Check: Recipients configured, severity levels enabled, cron running

---

## File Structure Reference

```
public/local/eclass_status/
├── README.md                           ← Full technical docs
├── QUICK_REFERENCE.md                  ← 1-page cheat sheet
├── MONITORED_ITEMS.md                  ← Complete monitoring list
├── ADMIN_GUIDE.md                      ← Admin configuration
├── ALERT_CONFIGURATION_FEATURE.md      ← Severity feature docs
│
├── view.php                            ← Dashboard page
├── settings.php                        ← Admin settings form
├── version.php                         ← Plugin metadata
│
├── classes/
│   ├── check_result.php                ← Result value object
│   ├── checker/
│   │   ├── checker.php                 ← Interface
│   │   ├── scheduled_tasks_checker.php ← Task checks
│   │   ├── ldap_checker.php            ← LDAP tests
│   │   ├── mysql_checker.php           ← MySQL tests
│   │   └── oracle_checker.php          ← Oracle stub
│   ├── notifier/
│   │   └── email_notifier.php          ← Email logic
│   └── task/
│       └── run_checks.php              ← Main task
│
├── db/
│   ├── install.xml                     ← DB schema
│   └── tasks.php                       ← Task registration
│
└── lang/en/
    └── local_eclass_status.php          ← Strings
```

---

**Last updated:** 2026-06-19  
**Plugin version:** 1.0.0

