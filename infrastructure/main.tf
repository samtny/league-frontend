locals {
  common_tags = {
    Project     = "web"
    Service     = "lamp"
    Environment = "prod"
  }

  my_cnf = templatefile("${path.module}/templates/league-frontend.my.cnf.tpl", {
    db_host     = var.league_frontend_db_host
    db_user     = var.league_frontend_db_user
    db_password = var.league_frontend_db_password
  })

  league_frontend_backup_sh = templatefile("${path.module}/templates/league_frontend_backup.sh.tpl", {
    db_name    = var.league_frontend_db_name
    backup_dir = var.backup_dir
  })

  league_frontend_logrotate_sh = file("${path.module}/templates/league_frontend_logrotate.sh.tpl")

  backup_sh = templatefile("${path.module}/templates/backup.sh.tpl", {
    log_group_name    = var.log_group_name
    log_stream_prefix = var.log_stream_prefix
    aws_region        = var.aws_region
    backup_dir        = var.backup_dir
    s3_bucket         = var.s3_backup_bucket
    s3_prefix         = var.s3_backup_prefix
  })

  vhost_conf = templatefile("${path.module}/templates/010-league-frontend.conf.tpl", {
    league_frontend_url     = var.league_frontend_url
    league_frontend_docroot = var.league_frontend_docroot
  })

  php_fpm_www_conf = templatefile("${path.module}/templates/www.conf.tpl", {
    php_pm_max_children      = var.php_pm_max_children
    php_pm_start_servers     = var.php_pm_start_servers
    php_pm_min_spare_servers = var.php_pm_min_spare_servers
    php_pm_max_spare_servers = var.php_pm_max_spare_servers
  })

  user_data = templatefile("${path.module}/templates/user_data.sh.tpl", {
    league_frontend_docroot     = var.league_frontend_docroot
    league_frontend_db_name     = var.league_frontend_db_name
    league_frontend_db_user     = var.league_frontend_db_user
    league_frontend_db_password = var.league_frontend_db_password
    php_memory_limit            = var.php_memory_limit
    php_upload_max_filesize     = var.php_upload_max_filesize
    php_post_max_size           = var.php_post_max_size

    my_cnf_b64                       = base64encode(local.my_cnf)
    league_frontend_backup_sh_b64    = base64encode(local.league_frontend_backup_sh)
    league_frontend_logrotate_sh_b64 = base64encode(local.league_frontend_logrotate_sh)
    backup_sh_b64                    = base64encode(local.backup_sh)
    vhost_conf_b64                   = base64encode(local.vhost_conf)
    php_fpm_www_conf_b64             = base64encode(local.php_fpm_www_conf)
  })
}

resource "aws_instance" "web_prod_lamp2" {
  ami                    = data.aws_ssm_parameter.ubuntu_ami.value
  instance_type          = var.instance_type
  subnet_id              = data.aws_subnet.web_private.id
  vpc_security_group_ids = [data.aws_security_group.web_lamp.id]
  iam_instance_profile   = data.aws_iam_instance_profile.web_prod_lamp.name
  key_name               = var.key_name

  associate_public_ip_address = false

  root_block_device {
    volume_type           = "gp3"
    volume_size           = var.root_volume_size_gb
    delete_on_termination = true
    encrypted             = true
  }

  metadata_options {
    http_tokens   = "required"
    http_endpoint = "enabled"
  }

  user_data                   = local.user_data
  user_data_replace_on_change = true

  tags = merge(local.common_tags, {
    Name = var.instance_name
  })
}
