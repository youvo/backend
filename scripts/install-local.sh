#!/bin/bash

 # Exit on error.
 set -e

 # Login sudo.
 sudo true

 # Display time.
 START_TIME=$SECONDS
 NOW=$(date +"%T")
 echo "Installation started at: $NOW"

 # Navigate to directory.
 SCRIPT_PATH="$( cd -- "$(dirname "$0")" || exit >/dev/null 2>&1  ; pwd -P )"
 cd "$SCRIPT_PATH" || exit

 # Get variables and stop docker containers.
 cd ..
 set -a; source config/.env.local; set +a
 echo "Stopping docker containers ..."
 ddev stop > /dev/null 2>&1

 # Set permissions.
 cd web/sites || exit
 chmod -R 0755 default

 # Remove settings file.
 cd default || exit
 if test -f "settings.php"; then
 	sudo chmod 0777 settings.php
 	rm settings.php
 fi
 cp install.settings.php settings.php
 chmod 0777 settings.php
 echo "Settings reset ..."

 # Reset files folder.
 if test -d "files"; then
 	sudo chmod 0777 files
 	rm -rf files
 fi
 mkdir files
 chmod 0777 files
 cd files || exit
 mkdir academy creatives organizations projects
 chmod 0777 academy creatives organizations projects
 cd ..
 echo "Files folder reset ..."

 # Start Docker containers.
 cd ../../..
 echo "Starting Docker containers ..."
 ddev start > /dev/null 2>&1

 # Wait for containers to be accessible.
 sleep 5

 # Enable auth.
 ddev auth ssh

 # Disable xdebug for installation.
 ddev xdebug disable

 # Reinstall drupal.
 echo "Installing Drupal ..."
 ddev drush si --yes --existing-config \
  --locale=en \
  --db-url="${DB_DRIVER}"://"${DB_USER}":"${DB_PASSWORD}"@"${DB_HOST}":"${DB_PORT}"/"${DB_NAME}" \
  --site-name="${SITE_NAME}" \
  --site-mail="${SITE_MAIL}" \
  --account-name="${ACCOUNT_NAME}" \
  --account-mail="${ACCOUNT_MAIL}" \
  --account-pass="${ACCOUNT_PASS}" > /dev/null 2>&1

 # Rebuild Cache.
 echo "Rebuilding Cache ..."
 ddev drush cr > /dev/null 2>&1

 # Set permissions for settings file.
 cd web/sites/default || exit
 chmod 0444 settings.php
 chmod 0444 default.settings.php
 chmod 0444 install.settings.php
 echo "Settings permissions updated ..."
 cd ../../..

 # Wait for containers to be accessible.
 sleep 5

 # Enable xdebug.
 ddev xdebug enable

 # Bye bye.
 DURATION=$((SECONDS - START_TIME))
 echo "Installation duration: $DURATION seconds."
 echo "Exit in 3 seconds!"
 sleep 3
