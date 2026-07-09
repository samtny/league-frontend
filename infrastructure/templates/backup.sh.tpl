#!/bin/bash

set -e

CURRENT_TIMESTAMP=$(date +%s%3N)

LOG_GROUP_NAME="${log_group_name}"
LOG_STREAM_NAME="${log_stream_prefix}-$${CURRENT_TIMESTAMP}"

export AWS_DEFAULT_REGION="${aws_region}"

aws logs create-log-stream --log-group-name "$${LOG_GROUP_NAME}" --log-stream-name "$${LOG_STREAM_NAME}"

{
  sudo aws s3 sync --storage-class STANDARD_IA ${backup_dir} s3://${s3_bucket}${s3_prefix}/
} || {
  aws logs put-log-events --log-group-name "$${LOG_GROUP_NAME}" --log-stream-name "$${LOG_STREAM_NAME}" --log-events "timestamp=$${CURRENT_TIMESTAMP},message=ERROR\tFailed to backup ${backup_dir}."
  exit 1
}

aws logs put-log-events --log-group-name "$${LOG_GROUP_NAME}" --log-stream-name "$${LOG_STREAM_NAME}" --log-events "timestamp=$${CURRENT_TIMESTAMP},message=SUCCESS\tSuccessfully backed up ${backup_dir}."

exit 0
