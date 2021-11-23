#!/bin/bash

 # Exit on error.
 set -e

 SITE_FOLDER=_dev/backend
 PATH=$PATH:~/www/$SITE_FOLDER/vendor/bin

 cd ~
 cd www/$SITE_FOLDER
 set -a; source conf/.env.development; set +a

 # Delete database.
 mysql -e "DROP DATABASE IF EXISTS ${DB_NAME}"
 mysql -e "CREATE DATABASE ${DB_NAME}"
 echo "Database reset ..."

 # Set permissions.
 cd web/sites
 chmod 0755 default

 # Remove settings file.
 cd default
 [[ -f settings.php ]] && chmod 0777 settings.php
 [[ -f settings.php ]] && rm settings.php
 cp default.settings.php settings.php
 chmod 0755 settings.php
 echo "Settings reset ..."

 # Reset files folder.
 rm -rf files
 mkdir files
 chmod 0755 files
 echo "Files folder reset ..."

 # Reinstall drupal.
 cd ~
 cd www/$SITE_FOLDER/web
 echo "Installing Drupal ..."
 drush site:install -y youvo_development \
  --locale="en" \
  --db-url="${DB_DRIVER}://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_NAME}" \
  --site-name="${SITE_NAME}" \
  --site-mail="${SITE_MAIL}" \
  --account-name="${ACCOUNT_NAME}" \
  --account-mail="${ACCOUNT_MAIL}" \
  --account-pass="${ACCOUNT_PASS}" > /dev/null 2>&1

 # Rebuild Cache.
 echo "Rebuilding Cache ..."
 drush cr > /dev/null 2>&1
	
 # Set permissions for settings file.
 cd ~
 cd www/$SITE_FOLDER/web/sites/default
 chmod 0444 settings.php
 chmod 0444 default.settings.php
 
 # Bye bye.
 echo "Exit in 3 seconds!"
 sleep 3

