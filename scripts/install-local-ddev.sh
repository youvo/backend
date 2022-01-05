#!/bin/bash

 # Exit on error.
 # set -e

 # Login sudo.
 sudo true

 # Navigate to directory.
 SCRIPT_PATH="$( cd -- "$(dirname "$0")" || exit >/dev/null 2>&1  ; pwd -P )"
 cd "$SCRIPT_PATH" || exit

 # Get variables and stop docker containers.
 cd ..
 set -a; source config/.env.local-ddev; set +a
 echo "Stopping docker containers ..."
 ddev stop > /dev/null 2>&1

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
 ddev start

 # Wait for containers to be accessible.
 sleep 5

 # Reinstall drupal.
 echo "Installing Drupal ..."
 ddev drush "si -y youvo_development \
  --locale=en \
  --db-url=${DB_DRIVER}://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_NAME} \
  --site-name=${SITE_NAME} \
  --site-mail=${SITE_MAIL} \
  --account-name=${ACCOUNT_NAME} \
  --account-mail=${ACCOUNT_MAIL} \
  --account-pass=${ACCOUNT_PASS}"

 # Rebuild Cache.
 echo "Rebuilding Cache ..."
 ddev drush cr > /dev/null 2>&1

 # Set permissions for settings file.
 cd web/sites/default || exit
 chmod 0444 settings.php
 chmod 0444 default.settings.php

 # Bye bye.
 echo "Exit in 3 seconds!"
 sleep 3


