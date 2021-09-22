#!/bin/bash

 # Exit on error.
 set -e

 # Login sudo.
 sudo true

 # Navigate to directory.
 SCRIPTPATH="$( cd -- "$(dirname "$0")" || exit >/dev/null 2>&1  ; pwd -P )"
 cd "$SCRIPTPATH"

 # Stop docker containers.
 cd ..
 echo "Stopping docker containers ..."
 docker-compose down > /dev/null 2>&1

 # Set permissions.
 cd web/sites
 chmod 0755 default

 # Remove settings file.
 cd default
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
 docker-compose up -d > /dev/null 2>&1

 # Wait for containers to be accessible.
 sleep 5

 # Reinstall drupal.
 echo "Installing Drupal ..."
 make drush "si -y youvo_development \
  --locale=en \
  --db-url=mysql://drupal:drupal@mariadb:3306/youvo_test \
  --site-name=youvo.org \
  --site-mail=admin@youvo.org \
  --account-name=admin@youvo.org \
  --account-mail=admin@youvo.org \
  --account-pass=admin"

 # Rebuild Cache.
 echo "Rebuilding Cache ..."
 make drush cr > /dev/null 2>&1

 # Bye bye.
 echo "Exit in 3 seconds!"
 sleep 3


