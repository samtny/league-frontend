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

if [ -f "/usr/bin/php8.0" ]; then
  php="/usr/bin/php8.0"
else
  php="php"
fi

if [ ! -f "composer.phar" ]; then
  ${php} -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  ${php} -r "if (hash_file('sha384', 'composer-setup.php') === '55ce33d7678c5a611085589f1f3ddf8b3c52d662cd01d4ba75c0ee0459970c2200a51f492d557530c71c15d8dba01eae') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
  ${php} composer-setup.php
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
