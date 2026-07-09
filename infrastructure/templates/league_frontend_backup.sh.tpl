#!/bin/bash

set -e

DB_NAME=${db_name}

MYSQL_DEFAULTS_FILE=/home/ubuntu/.league-frontend.my.cnf

BACKUP_DIR=${backup_dir}
BACKUP_FILENAME=$${DB_NAME}.sql
BACKUP_FILE=$${BACKUP_DIR}/$${BACKUP_FILENAME}

BACKUP_DAYS=15

mkdir -p $${BACKUP_DIR}

SAVELOG=$(which savelog)

$${SAVELOG} -n -c $${BACKUP_DAYS} $${BACKUP_FILE}

MYSQLDUMP=$(which mysqldump)

$${MYSQLDUMP} --defaults-file=$${MYSQL_DEFAULTS_FILE} --no-tablespaces $${DB_NAME} > $${BACKUP_FILE}

exit 0
