#!/bin/bash

set -x
exec > >(tee /var/log/user-data.log | logger -t user-data) 2>&1
echo BEGIN
date '+%Y-%m-%d %H:%M:%S'

echo 127.0.0.1 $(hostname) >> /etc/hosts

if ! grep -q LC_ALL /etc/environment; then
  echo "LC_ALL=en_US.UTF-8" >> /etc/environment
  echo "LANG=en_US.UTF-8" >> /etc/environment
fi

export DEBIAN_FRONTEND=noninteractive

apt-get update -y
apt-get install -y unattended-upgrades vim curl unzip rsync software-properties-common awscli

cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF

add-apt-repository -y ppa:ondrej/php
apt-get update -y

### Apache ###

apt-get install -y apache2 apache2-utils

a2enmod rewrite proxy proxy_fcgi actions deflate expires headers setenvif cgid

cat > /etc/apache2/conf-available/fqdn.conf <<'EOF'
ServerName localhost
EOF
a2enconf fqdn

cat > /etc/apache2/conf-available/security.conf <<'EOF'
ServerTokens Prod
ServerSignature Off
TraceEnable Off
<DirectoryMatch "/\.git">
   Require all denied
</DirectoryMatch>
Header set X-Frame-Options: "sameorigin"
EOF
a2enconf security

cat > /etc/apache2/conf-available/pci.conf <<'EOF'
FileETag None
EOF
a2enconf pci

cat > /etc/apache2/conf-available/ec2.conf <<'EOF'
# See: https://aws.amazon.com/premiumsupport/knowledge-center/apache-backend-elb/
Timeout 120
KeepAlive On
KeepAliveTimeout 120
MaxKeepAliveRequests 100
AcceptFilter http none
AcceptFilter https none
LogFormat "%%{X-Forwarded-For}i %h %l %u %t \"%r\" %>s %b %D \"%%{Referer}i\" \"%%{User-Agent}i\"" combined

<IfModule mod_setenvif.c>
  SetEnvIf X-Forwarded-Proto "^https$" HTTPS
</IfModule>
EOF
a2enconf ec2

### PHP 8.4 ###

apt-get install -y \
  php8.4-fpm php8.4-cli php8.4-curl php8.4-mysql php8.4-sqlite3 php8.4-gd \
  php8.4-dev php8.4-mbstring php8.4-dom php8.4-memcached php8.4-igbinary \
  php8.4-opcache php8.4-bcmath php8.4-xml php8.4-zip \
  php-pear build-essential checkinstall

sed -i "s/^memory_limit = .*/memory_limit = ${php_memory_limit}/" /etc/php/8.4/fpm/php.ini
sed -i "s/^upload_max_filesize = .*/upload_max_filesize = ${php_upload_max_filesize}/" /etc/php/8.4/fpm/php.ini
sed -i "s/^post_max_size = .*/post_max_size = ${php_post_max_size}/" /etc/php/8.4/fpm/php.ini
sed -i "s/^;\?opcache.enable=.*/opcache.enable=1/" /etc/php/8.4/fpm/php.ini
sed -i "s/^;\?opcache.memory_consumption=.*/opcache.memory_consumption=128/" /etc/php/8.4/fpm/php.ini
sed -i "s/^;\?opcache.interned_strings_buffer=.*/opcache.interned_strings_buffer=16/" /etc/php/8.4/fpm/php.ini
sed -i "s/^;\?opcache.max_accelerated_files=.*/opcache.max_accelerated_files=16228/" /etc/php/8.4/fpm/php.ini
sed -i "s/^;\?opcache.validate_timestamps=.*/opcache.validate_timestamps=0/" /etc/php/8.4/fpm/php.ini

echo "${php_fpm_www_conf_b64}" | base64 -d > /etc/php/8.4/fpm/pool.d/www.conf

cat > /etc/php/8.4/mods-available/memcached.ini <<'EOF'
; priority=25
extension=memcached.so
memcached.serializer=igbinary
EOF
ln -sf /etc/php/8.4/mods-available/memcached.ini /etc/php/8.4/fpm/conf.d/25-memcached.ini
ln -sf /etc/php/8.4/mods-available/memcached.ini /etc/php/8.4/cli/conf.d/25-memcached.ini

systemctl restart php8.4-fpm
systemctl enable php8.4-fpm

### Memcached ###

apt-get install -y memcached libmemcached-tools

sed -i 's/^-m 64/-m 128/' /etc/memcached.conf
systemctl restart memcached
systemctl enable memcached

### MySQL (local to this instance, matching the legacy LAMP setup) ###

apt-get install -y mysql-server

systemctl start mysql
systemctl enable mysql

mysql -e "CREATE DATABASE IF NOT EXISTS \`${league_frontend_db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${league_frontend_db_user}'@'localhost' IDENTIFIED BY '${league_frontend_db_password}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${league_frontend_db_name}\`.* TO '${league_frontend_db_user}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

### Composer ###

curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod 755 /usr/local/bin/composer

### league-frontend app scaffolding ###

usermod -aG www-data ubuntu

mkdir -p ${league_frontend_docroot}
chown ubuntu:ubuntu ${league_frontend_docroot}

mkdir -p /home/ubuntu/logs
chown ubuntu:www-data /home/ubuntu/logs
chmod 0775 /home/ubuntu/logs

echo "${vhost_conf_b64}" | base64 -d > /etc/apache2/sites-available/010-league-frontend.conf
a2ensite 010-league-frontend
a2dissite 000-default || true

systemctl restart apache2
systemctl enable apache2

### Backup automation ###

echo "${my_cnf_b64}" | base64 -d > /home/ubuntu/.league-frontend.my.cnf
chown ubuntu:ubuntu /home/ubuntu/.league-frontend.my.cnf
chmod 0644 /home/ubuntu/.league-frontend.my.cnf

echo "${league_frontend_backup_sh_b64}" | base64 -d > /home/ubuntu/league_frontend_backup.sh
chown ubuntu:ubuntu /home/ubuntu/league_frontend_backup.sh
chmod 0755 /home/ubuntu/league_frontend_backup.sh

echo "${league_frontend_logrotate_sh_b64}" | base64 -d > /home/ubuntu/league_frontend_logrotate.sh
chown ubuntu:ubuntu /home/ubuntu/league_frontend_logrotate.sh
chmod 0755 /home/ubuntu/league_frontend_logrotate.sh

echo "${backup_sh_b64}" | base64 -d > /home/ubuntu/backup.sh
chown ubuntu:ubuntu /home/ubuntu/backup.sh
chmod 0755 /home/ubuntu/backup.sh

crontab -u ubuntu -l 2>/dev/null > /tmp/ubuntu_cron || true
cat >> /tmp/ubuntu_cron <<'EOF'
3 20 * * * /home/ubuntu/league_frontend_logrotate.sh
14 20 * * * /home/ubuntu/league_frontend_backup.sh >> /home/ubuntu/logs/league_frontend_backup.sh.log 2>&1
19 12 * * * /home/ubuntu/backup.sh >> /home/ubuntu/logs/backup.sh.log 2>&1
19 13 * * * /usr/bin/savelog -n -c 30 /home/ubuntu/logs/backup.sh.log > /dev/null 2>&1
EOF
crontab -u ubuntu /tmp/ubuntu_cron
rm -f /tmp/ubuntu_cron

echo "done" >> /home/ubuntu/userdata

echo END
date '+%Y-%m-%d %H:%M:%S'
exit 0
