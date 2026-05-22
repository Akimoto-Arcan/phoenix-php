# Backup and Restore

Regular backups protect your data from hardware failures, software problems, and accidental deletions. This guide explains how to back up your database, how often to do it, and how to restore from a backup if something goes wrong.

## Why Backups Are Important

Your system's database contains all of your production records, user accounts, schedules, maintenance history, and other critical information. If the database is lost or corrupted, this data cannot be recovered without a backup.

A good backup lets you get back up and running quickly after a problem.

## How to Back Up the Database

A database backup creates a copy of all your data in a single file. This is done using a command-line tool called **mysqldump**.

1. Open a terminal on the server.
2. Run the following command:
   ```
   /opt/lampp/bin/mysqldump -u appuser -p --all-databases > /path/to/backup/backup_YYYY-MM-DD.sql
   ```
3. When prompted, enter the database password.
4. The backup file is saved to the location you specified.

Replace `/path/to/backup/` with the folder where you want to store backups, and `YYYY-MM-DD` with today's date (for example, `backup_2026-05-22.sql`).

> Tip: Store backup files on a separate drive or network location, not on the same server as the database. If the server fails, backups stored on it will be lost too.

## How Often to Back Up

**Daily backups are recommended.** At minimum, back up before:

- Making any changes to the system or database.
- Installing updates or new modules.
- Any planned maintenance on the server.

Keep at least 7 days of backups so you can go back further if a problem is not discovered right away.

## How to Restore from a Backup

If you need to restore your database from a backup file:

1. Open a terminal on the server.
2. Run the following command:
   ```
   /opt/lampp/bin/mysql -u appuser -p < /path/to/backup/backup_YYYY-MM-DD.sql
   ```
3. When prompted, enter the database password.
4. Wait for the import to complete. This may take several minutes for large databases.

After the restore is finished, restart the web server to make sure all connections are refreshed:
```
sudo /opt/lampp/lampp restart
```

> Warning: Restoring a backup will overwrite all current data in the database. Any changes made after the backup was taken will be lost. Make sure you are restoring the correct backup file.

## What to Do if Something Goes Wrong

If you run into problems during a backup or restore:

1. **Do not panic.** Stop and assess the situation before taking further action.
2. **Do not run additional commands** that might make things worse.
3. **Contact your system administrator** or IT support for help.
4. If the database is corrupted and you have a backup, follow the restore steps above.
5. If you do not have a backup, contact a database recovery specialist. Some data may still be recoverable.

> Warning: Always test your backups periodically. A backup you haven't tested might not work when you need it.
