#!/bin/bash

# QuickShare Database Backup Script
# This script backs up the MySQL database and stores it in the backup directory

BACKUP_DIR="/backup"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DB_NAME="quickshare"
DB_HOST="mysql"
DB_USER="root"
DB_PASSWORD="${DB_ROOT_PASSWORD}"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Perform backup
echo "Starting backup at $(date)"
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" | gzip > "$BACKUP_DIR/quickshare_$TIMESTAMP.sql.gz"

# Check if backup was successful
if [ $? -eq 0 ]; then
    echo "Backup completed successfully: quickshare_$TIMESTAMP.sql.gz"
else
    echo "Backup failed!"
    exit 1
fi

# Remove backups older than 7 days
find "$BACKUP_DIR" -type f -name "quickshare_*.sql.gz" -mtime +7 -delete

echo "Old backups removed"
echo "Backup process completed at $(date)"
