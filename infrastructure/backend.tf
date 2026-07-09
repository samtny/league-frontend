terraform {
  backend "s3" {
    bucket         = "web-prod-lamp"
    key            = "terraform/web-prod-lamp2/terraform.tfstate"
    region         = "us-east-1"
    dynamodb_table = "prod_orchestration_terraform_lock"
    encrypt        = true
  }
}
