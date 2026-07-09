data "aws_vpc" "web" {
  filter {
    name   = "tag:Name"
    values = [var.vpc_name_tag]
  }
}

data "aws_subnet" "web_private" {
  vpc_id = data.aws_vpc.web.id

  filter {
    name   = "tag:Name"
    values = [var.subnet_name_tag]
  }
}

data "aws_security_group" "web_lamp" {
  vpc_id = data.aws_vpc.web.id

  filter {
    name   = "group-name"
    values = [var.security_group_name]
  }
}

data "aws_iam_instance_profile" "web_prod_lamp" {
  name = var.iam_instance_profile_name
}

data "aws_ssm_parameter" "ubuntu_ami" {
  name = var.ubuntu_ssm_ami_parameter
}
