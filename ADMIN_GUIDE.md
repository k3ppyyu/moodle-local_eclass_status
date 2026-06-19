# Admin Quick Start: Alert Severity Configuration

## Where to Configure

**Moodle Dashboard → Administration → Site Administration → Plugins → Local plugins → EClass Status**

## What You'll See

```
Alert Severity Levels    [checkbox group]

☑ Critical    - Data/availability risk, immediate action required
☐ Warning     - Degraded performance, review needed
☐ Info        - Healthy status, informational only
```

## Recommended Configurations

### **Option 1: Silent/Minimal (Default)**
- ☑️ Critical only
- **When to use:** Large production environments with on-call support
- **Effect:** Only urgent issues email; warnings/info reviewed proactively in dashboard

### **Option 2: Balanced**
- ☑️ Critical
- ☑️ Warning
- **When to use:** Mid-sized deployments needing early warning signals
- **Effect:** Emails for urgent issues + performance degradation; info stays in dashboard

### **Option 3: Verbose**
- ☑️ Critical
- ☑️ Warning
- ☑️ Info
- **When to use:** Testing environments or paranoid admins
- **Effect:** All status items email; no need to check dashboard, but inbox gets busy

## Impact on Email Volume

| Configuration | Typical Emails/Day | When They Fire |
|---|---|---|
| Critical only | 0–2 | Only outages/major failures |
| Critical + Warning | 2–10 | Add slow services, queue buildup |
| All three | 10–50+ | Add routine task runs (very noisy) |

## Dashboard

**All results are always stored in the dashboard**, regardless of email configuration:

- View full history of all checks (critical, warning, info)
- Export/download status reports
- Trend analysis over time
- No alerts configured? Errors and warnings still visible in dashboard.

## Example

You selected: **Critical + Warning**

Check result from task:
- Backup task: running 5h 30m → **CRITICAL** (>4hr threshold) → **EMAILS**
- LDAP: 2.8s response time → **WARNING** (>2s threshold) → **EMAILS**
- MySQL: 120ms response time → **INFO** (healthy) → **NOT emailed** (but in dashboard)

## Common Scenarios

### "I only want urgent alerts"
→ Keep default: Critical only

### "I want to know when things are degrading"
→ Enable: Critical + Warning

### "I want ALL status information"
→ Enable all three (not recommended for production)

### "I don't trust email — I'll just check the dashboard"
→ Disable all; run checks silently; review dashboard weekly

## Testing Your Configuration

After setting the checkboxes, you can test by:

1. **SSH to Moodle container**
2. **Run the check task manually:**
   ```bash
   php admin/cli/scheduled_task.php --execute='\local_eclass_status\task\run_checks'
   ```
3. **Check mail logs** to see if emails were sent
4. **Visit dashboard** to verify all results are stored

## Troubleshooting

### No emails being sent?
- Check **Alert Recipients** is not empty
- Check at least one severity level is ☑️ checked
- Verify cron is running (scheduled task logs)
- Check Moodle mail settings (Settings > Mail configuration)

### Too many emails?
- Uncheck **Warning** and **Info** (keep **Critical** only)
- Increase **Check Interval** (e.g., 10+ minutes instead of 5)

### I need different rules for different times
- Moodle doesn't support time-of-day filtering; use external email filters
- Or maintain separate recipients (e.g., on-call vs. day shift lists)

## Next Steps

1. ✅ Install and upgrade plugin
2. ✅ Set **Alert Recipients** (required)
3. ✅ Choose your **Alert Severity Levels**
4. ✅ Test with manual task run
5. ✅ Cron will run automatically every 5 minutes

---

**Questions?** See `README.md` for full documentation.

