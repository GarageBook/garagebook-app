#!/bin/bash

BACKUP_DIR="/home/willem/backups"

LATEST_DB=$(ls -t $BACKUP_DIR/database-*.sqlite | head -n 1)
LATEST_STORAGE=$(ls -t $BACKUP_DIR/storage-*.tar.gz | head -n 1)
LATEST_ENV=$(ls -t $BACKUP_DIR/env-*.backup | head -n 1)

cp "$LATEST_DB" /home/willem/garagebook/database/database.sqlite
cp "$LATEST_ENV" /home/willem/garagebook/.env
tar -xzf "$LATEST_STORAGE" -C /

sudo chown -R willem:www-data /home/willem/garagebook
sudo chmod -R 775 /home/willem/garagebook/storage
sudo chmod -R 775 /home/willem/garagebook/bootstrap/cache
sudo chmod 664 /home/willem/garagebook/database/database.sqlite

php /home/willem/garagebook/artisan optimize:clear

echo "Latest backup restored successfully."
