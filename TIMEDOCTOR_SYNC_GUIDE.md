sudo crontab -u www-data -l
# TimeDoctor v1 Worklog Sync Guide

This guide provides detailed instructions for running the TimeDoctor v1 worklog sync command on your server using Putty and setting up automated daily sync via cron jobs.

## Overview

The `timedoctor:sync-worklogs` Artisan command allows you to sync worklog data from TimeDoctor v1 API for all active IVA users within a specified date range. The command includes error handling, progress tracking, and activity logging.

## Prerequisites

1. **Server Access**: SSH access to your server via Putty or similar terminal
2. **TimeDoctor Integration**: Ensure TimeDoctor v1 is properly connected and configured
3. **Active IVA Users**: Ensure you have active IVA users with TimeDoctor user mappings
4. **PHP & Laravel**: Laravel application must be properly deployed on the server

## Command Usage

### Basic Command Structure

```bash
php artisan timedoctor:sync-worklogs [options]
```

### Available Options

- `--start-date=YYYY-MM-DD` : Start date for sync (required if specifying date range)
- `--end-date=YYYY-MM-DD` : End date for sync (required if specifying date range)  
- `--dry-run` : Run without making changes (preview mode)

### Command Examples

#### 1. Sync Yesterday's Data (Default Behavior)
```bash
php artisan timedoctor:sync-worklogs
```
*This will sync worklog data for yesterday's date automatically*

#### 2. Sync Specific Date Range
```bash
php artisan timedoctor:sync-worklogs --start-date=2025-01-15 --end-date=2025-01-20
```

#### 3. Dry Run (Preview Changes)
```bash
php artisan timedoctor:sync-worklogs --start-date=2025-01-15 --end-date=2025-01-20 --dry-run
```

#### 4. Sync Single Specific Date
```bash
php artisan timedoctor:sync-worklogs --start-date=2025-01-15 --end-date=2025-01-15
```

## Step-by-Step Server Setup Instructions

### Step 1: Connect to Server via Putty

1. **Open Putty** on your local machine
2. **Enter Server Details**:
   - Host Name (or IP address): `your-server-ip-or-domain`
   - Port: `22` (default SSH port)
   - Connection type: `SSH`
3. **Click "Open"** to establish connection
4. **Login** with your server credentials:
   ```
   login as: your-username
   password: your-password
   ```

### Step 2: Navigate to Project Directory

```bash
# Navigate to your Laravel project directory
cd /path/to/your/timetrack/project

# Example paths (adjust according to your setup):
# cd /var/www/html/timetrack
# cd /home/user/timetrack
# cd /opt/timetrack
```

### Step 3: Verify Laravel Environment

```bash
# Check if you're in the correct directory
ls -la

# You should see Laravel files like:
# - artisan (Laravel command file)
# - composer.json
# - .env
# - app/
# - config/

# Test artisan command works
php artisan --version
```

### Step 4: Test TimeDoctor Connection

```bash
# Test the sync command with dry-run first
php artisan timedoctor:sync-worklogs --dry-run

# This will show you what would be synced without making changes
```

### Step 5: Run Actual Sync

```bash
# Sync yesterday's data (recommended for daily use)
php artisan timedoctor:sync-worklogs

# Or sync specific date range
php artisan timedoctor:sync-worklogs --start-date=2025-01-15 --end-date=2025-01-20
```

## Setting Up Daily Cron Job (1 PM SGT)

### Step 1: Access Cron Configuration

```bash
# Open crontab for editing
crontab -e

# If prompted to choose an editor, select your preferred one (usually nano or vim)
```

### Step 2: Add Cron Job Entry

Add this line to run daily at 1:00 PM Singapore Time (SGT):

```bash
# Daily TimeDoctor worklog sync at 1:00 PM SGT
0 13 * * * cd /path/to/your/timetrack/project && /usr/bin/php artisan timedoctor:sync-worklogs >> /var/log/timedoctor-sync.log 2>&1
```

**Important**: Replace `/path/to/your/timetrack/project` with your actual project path!

### Step 3: Alternative Cron Entry with Error Handling

For better error handling and logging:

```bash
# Daily TimeDoctor worklog sync with enhanced logging
0 13 * * * cd /path/to/your/timetrack/project && /usr/bin/php artisan timedoctor:sync-worklogs > /var/log/timedoctor-sync-$(date +\%Y\%m\%d).log 2>&1 || echo "TimeDoctor sync failed on $(date)" >> /var/log/timedoctor-sync-errors.log
```

0 5 * * * cd /var/www/iva && /usr/bin/php artisan timedoctor:sync-worklogs >> /var/log/timedoctor-sync.log 2>&1
### Step 4: Save and Verify Cron Job

```bash
# Save the crontab (in nano: Ctrl+X, then Y, then Enter)
# Verify the cron job was added
crontab -l
sudo crontab -u www-data -l
# You should see your new cron job listed
```

### Step 5: Test Cron Job (Optional)

```bash
# Test the cron job command manually
cd /path/to/your/timetrack/project && /usr/bin/php artisan timedoctor:sync-worklogs

# Check log file
tail -f /var/log/timedoctor-sync.log
```

## Understanding Time Zones

- **SGT (Singapore Time)**: UTC+8
- **Server Time**: Verify your server's timezone with `date` command
- **Cron Time**: Cron jobs run in the server's local timezone

If your server is not in SGT timezone, you may need to adjust the cron time:

```bash
# Check server timezone
date
timedatectl

# For UTC server to run at 1 PM SGT (which is 5 AM UTC):
0 5 * * * cd /path/to/your/timetrack/project && /usr/bin/php artisan timedoctor:sync-worklogs >> /var/log/timedoctor-sync.log 2>&1

0 5 * * * cd /var/www/iva && /usr/bin/php artisan timedoctor:sync-worklogs >> /var/log/timedoctor-sync.log 2>&1
```

## Monitoring and Troubleshooting

### Check Sync Logs

```bash
# View recent sync logs
tail -50 /var/log/timedoctor-sync.log

# View logs in real-time
tail -f /var/log/timedoctor-sync.log

# View Laravel application logs
tail -50 storage/logs/laravel.log
```

### Check Cron Job Status

```bash
# Check if cron service is running
systemctl status cron

# or on some systems:
systemctl status crond

# View cron job logs
grep CRON /var/log/syslog | tail -10
```

### Common Issues and Solutions

#### 1. Command Not Found
```bash
# Error: command not found
# Solution: Use full path to PHP binary
which php
# Use the full path in cron job, e.g., /usr/bin/php
```

#### 2. Permission Denied
```bash
# Error: Permission denied
# Solution: Check file permissions
ls -la artisan
chmod +x artisan

# Or ensure correct user is running the cron job
```

#### 3. TimeDoctor Connection Issues
```bash
# Test TimeDoctor connection
php artisan timedoctor:sync-worklogs --dry-run

# Check if TimeDoctor token is valid in the web interface
```

#### 4. Memory or Timeout Issues
```bash
# Increase PHP memory limit and execution time
php -d memory_limit=2G -d max_execution_time=0 artisan timedoctor:sync-worklogs
```

### Manual Sync for Missed Days

If the cron job fails or you need to sync historical data:

```bash
# Sync specific date range for missed days
php artisan timedoctor:sync-worklogs --start-date=2025-01-10 --end-date=2025-01-15

# Sync last week's data
php artisan timedoctor:sync-worklogs --start-date=2025-01-01 --end-date=2025-01-07
```

 Cron Entry for Debian:
     # Weekly sync every Tuesday 3AM GMT+8 (Monday 7PM UTC)
     0 19 * * 1 cd /var/www/iva && /usr/bin/php artisan timedoctor:sync-worklogs
     --synclastweek >> /var/log/laravel/timedoctor-weekly-sync.log 2>&1

## Security Considerations

1. **Log File Permissions**: Ensure log files are not publicly accessible
2. **Cron Job User**: Run cron jobs with appropriate user permissions
3. **TimeDoctor Credentials**: Keep API credentials secure in `.env` file
4. **Server Access**: Limit SSH access to authorized personnel only

## Support and Maintenance

### Regular Maintenance Tasks

1. **Monitor Log Files**: Check logs weekly for errors or issues
2. **Verify Data Integrity**: Periodically check that synced data is accurate
3. **Update Documentation**: Keep this guide updated with any system changes
4. **Backup Strategy**: Ensure regular backups include worklog data

### Getting Help

If you encounter issues:

1. Check the application logs in `storage/logs/laravel.log`
2. Review the sync-specific logs in `/var/log/timedoctor-sync.log`
3. Test the command manually with `--dry-run` flag
4. Verify TimeDoctor API connectivity through the web interface

## Command Output Examples

### Successful Sync Output
```
=== TimeDoctor Worklog Sync Command ===
No date range specified, defaulting to yesterday: 2025-01-15
Sync Date Range: 2025-01-15 to 2025-01-15
Total Days: 1
Connecting to TimeDoctor API...
Using TimeDoctor Company ID: 12345
Found 50 active IVA users with TimeDoctor mapping
 1/1 [████████████████████████████] 100% -- Processing: 2025-01-15
✓ Day 2025-01-15: Added 125, Updated 45, Errors 0

=== SYNC RESULTS ===
Date Range: 2025-01-15 to 2025-01-15
Days Processed: 1
Records Inserted: 125
Records Updated: 45
Total Records Synced: 170
Errors: 0

✅ Sync completed successfully!
```

### Dry Run Output
```
=== TimeDoctor Worklog Sync Command ===
DRY RUN MODE - No data will be modified
Sync Date Range: 2025-01-15 to 2025-01-20
...
DRY RUN RESULTS (No actual changes made):
...
```

This guide should help you successfully set up and manage TimeDoctor v1 worklog synchronization on your server.
------------

Sure! Here's a clean, copy-ready **Markdown (`.md`) version** of your plan:

````md
# 🛠️ TimeDoctor Worklogs Sync – Implementation Summary

## 1. Modify `SyncTimeDoctorWorklogs` Command

**File:** `app/Console/Commands/SyncTimeDoctorWorklogs.php`

### 🔧 Changes Required:
- Add new `--days-ago` parameter to command signature:
    ```php
    protected $signature = 'timedoctor:sync-worklogs
                            {--start-date= : Start date for sync (Y-m-d format)}
                            {--end-date= : End date for sync (Y-m-d format)}
                            {--days-ago= : Number of days ago to sync (default: 2)}
                            {--dry-run : Run without making changes}';
    ```
- Update command description to mention the new `--days-ago` parameter.
- Modify the `parseDateRange()` method:
  - Accept the `--days-ago` parameter.
  - Default to **2 days ago** when no parameters are provided.
  - Retain support for existing `--start-date` and `--end-date` parameters.

### 🧠 Logic Overview:
- If `--days-ago` is provided → sync data from N days ago.
- If `--start-date` and `--end-date` are provided → sync the given date range (existing behavior).
- If no parameters are provided → default to syncing **2 days ago**.

---

## 2. Update Cronjob Time

### 🕔 Current Crontab:
```cron
0 5 * * * cd /var/www/iva && /usr/bin/php artisan timedoctor:sync-worklogs >> /var/log/laravel/timedoctor-sync.log 2>&1
````

**Problem:** Runs at 5 AM UTC → 1 PM GMT+8 (too late)

### 🕓 New Crontab (4 AM GMT+8 = 8 PM UTC):

```cron
0 20 * * * cd /var/www/iva && /usr/bin/php artisan timedoctor:sync-worklogs >> /var/log/laravel/timedoctor-sync.log 2>&1
```

### ⏱ Timezone Conversion:

* 4 AM GMT+8 = 4 AM - 8 hours = **8 PM UTC (previous day)**
* So `0 20 * * *` runs at **8 PM UTC = 4 AM GMT+8**

---

## 3. Command Usage Examples

| Command                                                                              | Description                                         |
| ------------------------------------------------------------------------------------ | --------------------------------------------------- |
| `php artisan timedoctor:sync-worklogs`                                               | Syncs data from **2 days ago** (default)            |
| `php artisan timedoctor:sync-worklogs --days-ago=3`                                  | Syncs data from 3 days ago                          |
| `php artisan timedoctor:sync-worklogs --start-date=2025-10-06 --end-date=2025-10-06` | Syncs data for a specific date                      |
| `php artisan timedoctor:sync-worklogs --dry-run`                                     | Runs logic without changes (defaults to 2 days ago) |

---

## 4. Testing Checklist

* ✅ Test the command with:

  * No parameters
  * `--days-ago`
  * Date range (`--start-date`, `--end-date`)
  * `--dry-run`
* ✅ Verify cron timing: `0 20 * * *` = 8 PM UTC = 4 AM GMT+8
* ✅ Check log output at: `/var/log/laravel/timedoctor-sync.log`
* ✅ Confirm data is synced for the correct "N days ago"
* ✅ Validate fallback to **2-days-ago** logic

---

## ✅ Final Result

At **4:00 AM GMT+8 every day**,
the cron job (running at **8:00 PM UTC**) automatically syncs
**TimeDoctor worklogs from 2 days ago**,
allowing proper buffer time for complete data from the TimeDoctor system.

```

You can now **copy and paste** this entire block into any `.md` file (like `timedoctor-sync.md`) for documentation or team sharing.

Let me know if you'd like a sample `parseDateRange()` update as well!
```
