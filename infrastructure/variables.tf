variable "aws_region" {
  description = "AWS region containing the web VPC"
  type        = string
  default     = "us-east-1"
}

variable "aws_profile" {
  description = "AWS CLI/SDK profile to authenticate with"
  type        = string
  default     = "personal"
}

variable "instance_name" {
  description = "Value for the instance's Name tag"
  type        = string
  default     = "web-prod-lamp2"
}

variable "instance_type" {
  description = "EC2 instance type"
  type        = string
  default     = "t4g.small"
}

variable "root_volume_size_gb" {
  description = "Size of the single consolidated gp3 root volume"
  type        = number
  default     = 40
}

variable "key_name" {
  description = "Existing EC2 key pair name for SSH access"
  type        = string
  default     = "web"
}

variable "vpc_name_tag" {
  description = "Name tag of the existing VPC to deploy into"
  type        = string
  default     = "web"
}

variable "subnet_name_tag" {
  description = "Name tag of the existing private subnet to deploy into"
  type        = string
  default     = "web-private"
}

variable "security_group_name" {
  description = "Name of the existing security group to reuse"
  type        = string
  default     = "web-lamp"
}

variable "iam_instance_profile_name" {
  description = "Name of the existing IAM instance profile to reuse"
  type        = string
  default     = "web-prod-lamp"
}

variable "ubuntu_ssm_ami_parameter" {
  description = "SSM parameter path for the current Ubuntu 26.04 LTS arm64 AMI"
  type        = string
  default     = "/aws/service/canonical/ubuntu/server/26.04/stable/current/arm64/hvm/ebs-gp3/ami-id"
}

# --- league-frontend application ---

variable "league_frontend_url" {
  description = "Primary domain served by this vhost"
  type        = string
  default     = "pinballleague.org"
}

variable "league_frontend_docroot" {
  description = "Laravel app root on the instance (public/ is the Apache DocumentRoot)"
  type        = string
  default     = "/var/www/league-frontend"
}

variable "league_frontend_db_name" {
  type    = string
  default = "league-frontend"
}

variable "league_frontend_db_user" {
  type    = string
  default = "league_frontend"
}

variable "league_frontend_db_password" {
  description = "Password for the league-frontend MySQL user. Supply via a gitignored *.auto.tfvars file or a TF_VAR_ environment variable -- do not commit a default."
  type        = string
  sensitive   = true
}

variable "league_frontend_db_host" {
  description = "MySQL runs locally on the instance"
  type        = string
  default     = "127.0.0.1"
}

# --- php-fpm pool tuning ---
# Legacy pf3infrastructure config.yml used pm.max_children=2 because one t2.small
# hosted five apps' worth of fpm pools. This instance runs league-frontend only,
# so the pool can use a larger share of the box's 2GB RAM.

variable "php_pm_max_children" {
  type    = number
  default = 4
}

variable "php_pm_start_servers" {
  type    = number
  default = 2
}

variable "php_pm_min_spare_servers" {
  type    = number
  default = 2
}

variable "php_pm_max_spare_servers" {
  type    = number
  default = 4
}

variable "php_memory_limit" {
  description = "Legacy value was 64M (shared 5-app box); Laravel wants more headroom"
  type        = string
  default     = "128M"
}

variable "php_upload_max_filesize" {
  type    = string
  default = "20M"
}

variable "php_post_max_size" {
  type    = string
  default = "32M"
}

# --- backups ---

variable "backup_dir" {
  type    = string
  default = "/home/ubuntu/backups"
}

variable "s3_backup_bucket" {
  description = "Fixed by the reused IAM policy's resource scope"
  type        = string
  default     = "web-prod-lamp"
}

variable "s3_backup_prefix" {
  type    = string
  default = "/home/ubuntu/backups"
}

variable "log_group_name" {
  description = "CloudWatch log group; fixed by the reused IAM policy's resource scope"
  type        = string
  default     = "web-prod-lamp"
}

variable "log_stream_prefix" {
  description = "Distinguishes this instance's log streams from web-prod-lamp's own"
  type        = string
  default     = "web-prod-lamp2"
}
