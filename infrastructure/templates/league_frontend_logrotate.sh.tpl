#!/bin/bash

set -e

HOME_DIR=/home/ubuntu

LOG_DIR=$${HOME_DIR}/logs
LOG_ROTATE_DAYS=30

LOG_FILENAME=league_frontend_backup.sh.log
LOG_FILE=$${LOG_DIR}/$${LOG_FILENAME}

[ -f "$${LOG_FILE}" ] && savelog -n -c $${LOG_ROTATE_DAYS} $${LOG_FILE}

exit 0
