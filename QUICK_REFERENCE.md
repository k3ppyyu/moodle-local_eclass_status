# Quick Reference: What's Being Monitored

## At a Glance

| Category | Check Type | Status | Frequency | Configurable |
|----------|-----------|--------|-----------|--------------|
| **Tasks** | Scheduled task runtime | Running/Healthy/Overdue | Every check | Via settings (list) |
| **Tasks** | Task failures | Failed tasks in retry | Every check | Auto-detected |
| **Tasks** | Adhoc queue | Queue depth/backlog | Every check | Via settings |
| **External** | LDAP connectivity | Connected/Slow/Down | Every check | Via settings (list) |
| **External** | MySQL connectivity | Connected/Slow/Down | Every check | Via settings (list) |
| **External** | Oracle connectivity | Connected/Slow/Down | Every check | Future (placeholder) |

---

## Severity Thresholds

### Task Runtime
```
✅ INFO:     Running < 30 minutes
⚠️ WARNING:  Running 30 min - 2 hours  
🔴 CRITICAL: Running > 4 hours
```

### Task Overdue (not run recently)
```
⚠️ WARNING:  Not run in 2× expected interval
🔴 CRITICAL: Not run in 4× expected interval
```

### Adhoc Task Queue
```
✅ INFO:     < 100 queued tasks
⚠️ WARNING:  100-500 queued tasks
🔴 CRITICAL: > 500 queued tasks
```

### LDAP Response Time
```
✅ INFO:     < 2 seconds
⚠️ WARNING:  2-5 seconds
🔴 CRITICAL: > 5 seconds OR connection failed
```

### MySQL Response Time
```
✅ INFO:     < 2 seconds (connect + query)
⚠️ WARNING:  2-5 seconds
🔴 CRITICAL: > 5 seconds OR connection/query failed
```

---

## Default Alert Configuration

| Severity | Email by Default | In Dashboard |
|----------|------------------|--------------|
| CRITICAL | ✅ Yes | Always |
| WARNING  | ❌ No | Always |
| INFO     | ❌ No | Always |

**To change:** Site Administration > Plugins > Local plugins > EClass Status > Alert Severity Levels

---

## Enable External Monitoring

### To Monitor LDAP Servers:
1. Go to **Settings > Alert Configuration**
2. Scroll to **LDAP Servers to Monitor**
3. Enter one per line: `hostname:port` or just `hostname`
   ```
   ldap.yorku.ca:389
   ldap-replica.example.com:389
   ```

### To Monitor MySQL Servers:
1. Go to **Settings > Alert Configuration**
2. Scroll to **MySQL Servers to Monitor**
3. Enter one per line: `host:port` (port defaults to 3306)
   ```
   reporting-db.yorku.ca:3306
   localhost
   ```
4. *(Future: configure credentials separately)*

### To Monitor Oracle Servers:
- Placeholder for future implementation
- Requires OCI8 PHP extension

---

## Email Examples

### You'll Get This Email If...

**Critical Email (immediate):**
- Task running > 4 hours
- LDAP connection fails
- MySQL query timeouts
- Repeated task failures

**Warning Email (if configured):**
- LDAP responding slow (>2s)
- MySQL latency high (>2s)
- Task queue > 100 items
- Task running 1.5-2 hours

**Info Email (if configured):**
- Task completed successfully
- All connectivity normal
- Adhoc queue < 100 items

---

## On the Dashboard

Visit: **Site Administration > Plugins > Local plugins > EClass Status > View Dashboard**

You'll see:
```
┌─────────────────────────────────────────┐
│ Critical: 3   Warning: 2   Info: 7     │
│           Total Checks: 12              │
└─────────────────────────────────────────┘

Last check: 2026-06-19 14:32

[Table of all checks]
- Name
- Category
- Status badge (CRITICAL/WARNING/INFO)
- Observed value (timing, latency, etc.)
- Threshold
- Message
```

---

## Severity Decision Matrix

**When does each item trigger an alert?**

### CRITICAL (Always Alerts if Configured)
- Service unavailable (connection fails)
- Repeated failures (task fails 3 times)
- Data at risk (backup hung >4hrs)
- User impact (LDAP auth down)

### WARNING (Alerts if Configured)
- Degraded service (latency 2-5s)
- Approaching problem (queue > 100)
- Overdue but not critical (task late but still running)
- Sustained minor issue (slow response for 2+ checks)

### INFO (Alerts if Configured — very verbose)
- All systems healthy
- Task completed successfully
- Connections responding normally
- Within expected thresholds

---

## Manual Test Run

```bash
# SSH into container
docker exec -it moodle /bin/bash

# Run checks manually
php /moodle/admin/cli/scheduled_task.php --execute='\local_eclass_status\task\run_checks'

# Output will show:
# - Which checkers ran
# - Count of: critical, warning, info items
# - Whether email was sent
# - All results stored for dashboard
```

---

## Common Questions

### "How often does this run?"
- Every 5 minutes (configurable in settings)
- Via Moodle's cron/task scheduler
- All results stored in database

### "Why didn't I get an email?"
1. Check **Alert Recipients** is configured
2. Check at least one severity is enabled (Settings > Alert Severity Levels)
3. Check cron is running (`php admin/cron.php` or task scheduler)
4. No critical/warning/info items triggered
5. Check Moodle mail configuration works

### "I want to add more checks"
- See `MONITORED_ITEMS.md` for "How to Add New Checks"
- Implement a custom `checker` class
- Register in `run_checks.php` task

### "Can I use this without the email feature?"
- Yes! Disable email recipients, use dashboard only for monitoring
- All results stored and viewable regardless

---

## File References

| File | Purpose |
|------|---------|
| `MONITORED_ITEMS.md` | Complete list of all checks and thresholds |
| `ADMIN_GUIDE.md` | Admin configuration guide |
| `README.md` | Full technical documentation |
| `view.php` | Dashboard page (rendered HTML) |
| `settings.php` | Admin settings form |

---

Last updated: 2026-06-19

