output "instance_id" {
  value = aws_instance.web_prod_lamp2.id
}

output "private_ip" {
  value = aws_instance.web_prod_lamp2.private_ip
}

output "availability_zone" {
  value = aws_instance.web_prod_lamp2.availability_zone
}

output "ami_id" {
  value     = data.aws_ssm_parameter.ubuntu_ami.value
  sensitive = true
}
