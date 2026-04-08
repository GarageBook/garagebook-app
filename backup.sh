#!/bin/bash

BACKUP_DIR="/home/willem/backups"
DATE=$(date +%F-%H%M)

mkdir -p "$BACKUP_DIR"

cp /home/willem/garagebook/database/database.sqlite "$BACKUP_DIR/database-$DATE.sqlite"
cp /home/willem/garagebook/.env "$BACKUP_DIR/env-$DATE.backup"

tar -czf "$BACKUP_DIR/storage-$DATE.tar.gz" /home/willem/garagebook/storage

find "$BACKUP_DIR" -type f -mtime +14 -delete
