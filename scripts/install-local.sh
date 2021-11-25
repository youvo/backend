#!/bin/bash

 # Exit on error.
 # set -e

 # Login sudo.
 sudo true

 # Navigate to directory.
 SCRIPTPATH="$( cd -- "$(dirname "$0")" || exit >/dev/null 2>&1  ; pwd -P )"
 cd "$SCRIPTPATH" || exit

 # Get variables and stop docker containers.
 cd ..
 set -a; source conf/.env.local; set +a
 echo "Stopping docker containers ..."
 docker-compose down > /dev/null 2>&1

 # Set permissions.
 cd web/sites || exit
 chmod 0755 default

 # Remove settings file.
 cd default || exit
 if test -f "settings.php"; then
 	sudo chmod 0777 settings.php
 	rm settings.php
 fi
 cp default.settings.php settings.php
 chmod 0777 settings.php
 echo "Settings reset ..."

 # Reset files folder.
 if test -d "files"; then
 	sudo chmod 0777 files
 	rm -rf files
 fi
 mkdir files
 chmod 0777 files
 echo "Files folder reset ..."

 # Start docker containers.
 cd ../../..
 echo "Starting docker containers ..."
 docker-compose --env-file conf/.env.local up -d > /dev/null 2>&1

 # Wait for containers to be accessible.
 sleep 5

 # Reinstall drupal.
 echo "Installing Drupal ..."
 make drush "si -y youvo_development \
  --locale=en \
  --db-url=${DB_DRIVER}://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_NAME} \
  --site-name=${SITE_NAME} \
  --site-mail=${SITE_MAIL} \
  --account-name=${ACCOUNT_NAME} \
  --account-mail=${ACCOUNT_MAIL} \
  --account-pass=${ACCOUNT_PASS}" > /dev/null 2>&1

 # Rebuild Cache.
 echo "Rebuilding Cache ..."
 make drush cr > /dev/null 2>&1

 # Set permissions for settings file.
 cd web/sites/default || exit
 chmod 0444 settings.php
 chmod 0444 default.settings.php

 # Bye bye.
 echo "Exit in 3 seconds!"
 sleep 3


