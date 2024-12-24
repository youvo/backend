#!/bin/bash

 # Exit on error.
 set -e

 # Get variables.
 set -a; source config/.env.ci; set +a
 echo "Variables loaded ..."

 # Reset settings.
 cd web/sites || exit
 chmod -R 0755 default
 cd default || exit
 if test -f "settings.php"; then
  	sudo chmod 0777 settings.php
  	rm settings.php
 fi
 if test -f "services.yml"; then
   	sudo chmod 0777 services.yml
   	rm services.yml
  fi
 cp install.settings.php settings.php
 cp install.services.yml services.yml
 echo "Settings reset ..."

 # Install Drupal.
 cd ../../..
 vendor/bin/drush si --yes --existing-config \
  --locale=en \
  --db-url="${DB_DRIVER}"://"${DB_USER}":"${DB_PASSWORD}"@"${DB_HOST}":"${DB_PORT}"/"${DB_NAME}" \
  --site-name="${SITE_NAME}" \
  --site-mail="${SITE_MAIL}" \
  --account-name="${ACCOUNT_NAME}" \
  --account-mail="${ACCOUNT_MAIL}" \
  --account-pass="${ACCOUNT_PASS}"

 # Rebuild Cache.
 echo "Rebuild cache ..."
 vendor/bin/drush cr > /dev/null 2>&1
