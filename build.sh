#!/bin/bash

set -e

USAGE="build.sh [config]"

if [ "$#" -ne 1 ]; then
  echo "$USAGE"
  exit 1
fi

CONFIG=$1

parse_yaml() {
   local prefix=$2
   local s='[[:space:]]*' w='[a-zA-Z0-9_]*' fs=$(echo @|tr @ '\034')
   sed -ne "s|^\($s\)\($w\)$s:$s\"\(.*\)\"$s\$|\1$fs\2$fs\3|p" \
        -e "s|^\($s\)\($w\)$s:$s\(.*\)$s\$|\1$fs\2$fs\3|p"  $1 |
   awk -F$fs '{
      indent = length($1)/2;
      vname[indent] = $2;
      for (i in vname) {if (i > indent) {delete vname[i]}}
      if (length($3) > 0) {
         vn=""; for (i=0; i<indent; i++) {vn=(vn)(vname[i])("_")}
         printf("%s%s%s=\"%s\"\n", "'$prefix'",vn, $2, $3);
      }
   }'
}

CONFIG_FILE="./config/config.${CONFIG}.yml"

eval $(parse_yaml ${CONFIG_FILE} "config_")

if [ -f "/usr/local/bin/php7" ]; then
  php="/usr/local/bin/php7"
else
  php="php"
fi

if [ ! -f "composer.phar" ]; then
  wget -O composer-setup.php https://getcomposer.org/installer
  ${php} -r "if (hash_file('SHA384', 'composer-setup.php') === '48e3236262b34d30969dca3c37281b3b4bbe3221bda826ac6a9a62d6444cdb0dcd0615698a5cbe587c3f0fe57a54d8f5') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
  ${php} -d allow_url_fopen=On composer-setup.php
  ${php} -r "unlink('composer-setup.php');"
fi

if [ "$config_league_frontend_runmode" = "production" ]; then
  echo -e "Installing PRODUCTION dependencies"
  ${php} -d allow_url_fopen=On composer.phar install --no-dev --optimize-autoloader
else
  echo -e "Installing DEVELOPMENT dependencies"
  ${php} -d allow_url_fopen=On composer.phar install
fi

exit 0
