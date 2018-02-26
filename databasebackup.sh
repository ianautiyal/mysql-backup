#!/bin/bash

# MySQL credentials
BACKUP_DIR=""
MYSQL_HOST="localhost"
MYSQL_USER="root"
MYSQL_PASS=""

# Get MySQL databases
DATABASES=`echo 'show databases' | mysql --host=${MYSQL_HOST} --user=${MYSQL_USER} --password=${MYSQL_PASS} -B | sed /^Database$/d`

# Backup and compress each database
for DATABASE in $DATABASES; do
  if [ "${DATABASE}" == "information_schema" ] || [ "${DATABASE}" == "performance_schema" ]; then
    EXTRA_PARAM="--skip-lock-tables"
  else
    EXTRA_PARAM=""
  fi

  mysqldump ${EXTRA_PARAM} --host=${MYSQL_HOST} --user=${MYSQL_USER} --password=${MYSQL_PASS} ${DATABASE} | gzip > "${BACKUP_DIR}/${DATABASE}.sql.gz"
  chmod 600 "${BACKUP_DIR}/${DATABASE}.sql.gz"
done

# run php script to move data to s3 and update google sheet
php index.php
