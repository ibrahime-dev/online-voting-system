# 🔄 Automatic Database Backup Setup Instructions

## Overview
The Online Voting System now includes a professional database backup management system with automatic scheduling capabilities.

## Features
- ✅ **Manual Backup Creation** - Create backups on-demand
- ✅ **Automatic Scheduling** - Backups every 5 days
- ✅ **Backup History** - View and manage existing backups
- ✅ **Download & Delete** - Full backup file management
- ✅ **Database Statistics** - Real-time database information
- ✅ **Security Logging** - All backup activities are logged
- ✅ **Professional UI** - Modern and responsive design

## Access
- **URL**: `http://localhost/online-voting-system/src/admin/backup.php`
- **Required Role**: Admin or Super Admin
- **Navigation**: Admin Panel → Backup

## Manual Backup
1. Login as Admin or Super Admin
2. Navigate to Admin Panel → Backup
3. Click "Create Backup Now"
4. Backup will be automatically downloaded

## Automatic Backup Setup

### Option 1: Windows Task Scheduler (Recommended for XAMPP)

1. **Open Task Scheduler**
   - Press `Win + R`, type `taskschd.msc`, press Enter

2. **Create Basic Task**
   - Click "Create Basic Task" in the right panel
   - Name: "Voting System Auto Backup"
   - Description: "Automatic database backup every 5 days"

3. **Set Trigger**
   - Trigger: Daily
   - Start: Choose a time (e.g., 2:00 AM)
   - Recur every: 1 day

4. **Set Action**
   - Action: Start a program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\online-voting-system\src\admin\backup-scheduler.php`
   - Start in: `C:\xampp\htdocs\online-voting-system\src\admin\`

5. **Finish Setup**
   - Check "Open Properties dialog" before clicking Finish
   - In Properties → Conditions: Uncheck "Start the task only if the computer is on AC power"

### Option 2: Linux/Unix Cron Job

1. **Edit Crontab**
   ```bash
   crontab -e
   ```

2. **Add Cron Entry**
   ```bash
   # Run backup check daily at 2 AM
   0 2 * * * /usr/bin/php /path/to/your/project/src/admin/backup-scheduler.php
   ```

3. **Save and Exit**
   - The cron job will run daily and create backups every 5 days

### Option 3: Manual Scheduling Check

If automatic scheduling is not available, you can manually run the scheduler:

```bash
# Navigate to the admin directory
cd /path/to/your/project/src/admin/

# Run the backup scheduler
php backup-scheduler.php
```

## Backup File Locations

### Manual Backups
- **Location**: `src/backups/`
- **Format**: `voting_system_backup_YYYY-MM-DD_HH-MM-SS.sql`
- **Download**: Automatic download when created

### Automatic Backups
- **Location**: `src/backups/`
- **Format**: `auto_backup_YYYY-MM-DD_HH-MM-SS.sql`
- **Retention**: Keeps 10 most recent automatic backups

## Backup Schedule Status

The backup page shows:
- ✅ **Up to date**: Backup created today
- ✅ **Recent**: Last backup within 3 days
- ⚠️ **Due soon**: Last backup 3-4 days ago
- 🚨 **Overdue**: Last backup 5+ days ago

## Security Features

1. **Access Control**: Only admins can access backup functions
2. **Activity Logging**: All backup operations are logged in audit_log
3. **File Validation**: Only .sql files are processed
4. **Secure Downloads**: Files are served with proper headers
5. **Path Protection**: Prevents directory traversal attacks

## Backup Contents

Each backup includes:
- Complete database structure (all tables)
- All data (users, elections, votes, etc.)
- Foreign key constraints
- Indexes and triggers
- System settings

## Best Practices

1. **Regular Monitoring**: Check backup status weekly
2. **Multiple Locations**: Store backups in different locations
3. **Test Restoration**: Periodically test backup restoration
4. **Cleanup**: Remove old backups to save disk space
5. **Security**: Store backup files securely

## Troubleshooting

### Backup Creation Fails
- Check database connection
- Verify write permissions on `src/backups/` directory
- Check available disk space

### Automatic Backup Not Working
- Verify task scheduler/cron job is set up correctly
- Check PHP path in scheduler configuration
- Review error logs for issues

### Large Database Issues
- For very large databases, consider increasing PHP memory limit
- Use command-line backup tools for extremely large datasets

## File Permissions

Ensure proper permissions:
```bash
# Make backup directory writable
chmod 755 src/backups/

# Make scheduler executable
chmod +x src/admin/backup-scheduler.php
```

## Support

For issues with the backup system:
1. Check the audit log for error messages
2. Review PHP error logs
3. Verify database connectivity
4. Ensure proper file permissions

---

**Note**: The backup system is designed to work with the existing Online Voting System architecture and maintains all security and logging standards.