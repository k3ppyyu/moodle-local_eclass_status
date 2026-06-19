# Monitored Items & Thresholds

This document lists **all health checks** performed by the plugin, their default severity thresholds, and what triggers each alert.

## 1. Scheduled Tasks (Tier 1: Task Execution)

### 1.1 Task Runtime Monitoring

Each **scheduled task** is monitored individually for execution time:

| Condition | Severity | Threshold | Message |
|-----------|----------|-----------|---------|
| Task running ≤ 30 min | INFO | Normal range | Task is healthy |
| Task running 30 min – 2 hours | WARNING | Elevated time | Task is running longer than usual |
| Task running > 4 hours | CRITICAL | Hung threshold | Task may be hung, requires investigation |

**Examples:**
- `enrol_sync` task running 1h 45m → WARNING
- `backup` task running 4h 51m → CRITICAL
- `gradebook_recalc` task running 12m → INFO

### 1.2 Overdue Task Detection

If a task hasn't run within 2× its expected interval:

| Condition | Severity |
|-----------|----------|
| Last run > 2× expected interval | WARNING |
| Last run > 4× expected interval | CRITICAL |

**Example:** Cron task expected every 5 min, last ran 12 min ago → WARNING

### 1.3 Failed Tasks

If a task has failed and is in retry mode:

| Condition | Severity | Message |
|-----------|----------|---------|
| Task failed, retry scheduled | WARNING | Task failed and is scheduled for retry |

---

## 2. Adhoc Task Queue (Tier 1: Backlog)

### 2.1 Queue Buildup

Monitors the count of pending adhoc (on-demand) tasks:

| Queue Size | Severity | Meaning |
|-----------|----------|---------|
| < 100 tasks | INFO | Healthy queue |
| 100 – 500 tasks | WARNING | Queue starting to build |
| > 500 tasks | CRITICAL | Queue stalled or massive backlog |

**Examples:**
- 45 adhoc tasks pending → INFO
- 156 adhoc tasks pending → WARNING
- 892 adhoc tasks pending → CRITICAL (processing may be hung)

---

## 3. External Connectivity: LDAP (Optional)

If configured in settings, each LDAP server is tested:

### 3.1 LDAP Connection & Bind

| Test | Severity | Threshold |
|------|----------|-----------|
| Connection fails | CRITICAL | Cannot reach LDAP server at all |
| Response time > 2 seconds | WARNING | Server is slow but responding |
| Response time > 5 seconds | CRITICAL | Server is severely slow or nearly hung |
| Success, < 2s | INFO | LDAP is healthy |

**Examples:**
- `ldap.yorku.ca:389` → 186ms response → INFO
- `ldap.yorku.ca:389` → 2.8s response → WARNING
- `ldap.yorku.ca:389` → connection refused → CRITICAL

---

## 4. External Connectivity: MySQL (Optional)

If configured in settings, each MySQL server is tested:

### 4.1 MySQL Connection & Query

Each server tested with:
1. **Connection test** — TCP connect to host:port
2. **Query test** — Execute `SELECT 1` on test database

| Test | Severity | Details |
|------|----------|---------|
| Connection fails | CRITICAL | Cannot reach MySQL server |
| Query fails | CRITICAL | Connected but query execution failed |
| Total latency > 2s | WARNING | Overall response is slow |
| Total latency > 5s | CRITICAL | Server is severely slow |
| Success, < 2s | INFO | MySQL is healthy |

**Examples:**
- `reporting-db.yorku.ca:3306` → 187ms total (45ms connect + 142ms query) → INFO
- `local:3306` → 23ms total → INFO
- `replica-db.yorku.ca:3306` → 2.8s total → WARNING
- `reporting-db.yorku.ca:3306` → connection refused → CRITICAL

---

## 5. External Connectivity: Oracle (Optional)

**Status:** Placeholder (future implementation)

When implemented, will monitor:
- Connection to Oracle SID/service name
- Test query execution (e.g., `SELECT 1 FROM DUAL`)
- Response time thresholds (similar to MySQL)

---

## What is NOT Currently Monitored

The plugin **does not** read the Moodle system status page. Instead, it performs **independent tests**:

- ❌ Site maintenance mode state (not currently monitored)
- ❌ Plugin/extension health (detected via system status page)
- ❌ Cache store availability (not currently monitored)
- ❌ Backup state/completion (not currently monitored)
- ❌ Search indexing state (not currently monitored)
- ❌ Mail queue (not currently monitored)
- ❌ Disk space (not currently monitored)

These could be added as future checkers by implementing additional `checker` classes.

---

## How to Add New Checks

To monitor additional systems:

1. **Create a new checker class** implementing the `checker` interface:
   ```php
   namespace local_eclass_status\checker;
   
   class my_custom_checker implements checker {
       public function check() {
           // Return array of check_result objects
       }
       
       public function is_enabled() {
           // Control if this checker runs
       }
       
       public function get_name() {
           // Human-readable name
       }
   }
   ```

2. **Register it** in `local_eclass_status/task/run_checks.php`:
   ```php
   $checkers = [
       new scheduled_tasks_checker(),
       new ldap_checker(),
       new mysql_checker(),
       new my_custom_checker(),  // ← Add here
   ];
   ```

3. **Add settings** to configure the external service (host, port, credentials)

4. **Test** and deploy

---

## Severity Classification Logic

### INFO
- ✅ Service/system is functioning normally
- No action needed
- Baseline healthy state

### WARNING
- ⚠️ Degradation detected but still functioning
- Examples: slow response, queue growth, task overdue
- Should be reviewed but not immediate threat
- Escalates to CRITICAL if sustained over multiple checks

### CRITICAL
- 🔴 Service unavailable or data/availability at risk
- Examples: connection down, repeated failures, hung process
- Requires immediate remediation
- Triggers immediate email alert (if severity enabled)

---

## Configuration

### Default Checks (Always Enabled)
- Scheduled tasks
- Adhoc task queue

### Optional Checks (Enable via Settings)
- LDAP servers (list in settings)
- MySQL servers (list in settings)
- Oracle servers (placeholder, via settings)

### Alert Routing
- Configured severity levels determine which results email
- **All results always stored** in database for dashboard review

---

## Example Monitoring Report

```
SEVERITY BREAKDOWN FROM LAST CHECK RUN:
=========================================

CRITICAL (1):
  - Backup task running 4h 47m

WARNING (3):
  - LDAP response time 2.8s
  - Course sync running 1h 12m
  - Adhoc queue at 156 tasks

INFO (7):
  - Badges sync completed, healthy
  - Calendar cleanup completed, healthy
  - MySQL local responding 23ms
  - MySQL replica responding 187ms
  - [... 3 more healthy items ...]

TOTAL: 11 checks run
```

---

## View All Results

After the plugin runs (every 5 minutes by default), results are stored in:
- **Database**: `mdl_local_eclass_status_results` table
- **Dashboard**: Health Status Dashboard (future view page)
- **CLI**: `php admin/cli/scheduled_task.php --execute='\local_eclass_status\task\run_checks'`

