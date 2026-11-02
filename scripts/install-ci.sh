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
 # Point links and redirects to the frontends of this environment.
 printf "\$config['youvo.settings']['hub_url'] = '%s';\n" "${HUB_URL:?Set HUB_URL in config/.env.ci.}" >> settings.php
 printf "\$config['youvo.settings']['app_url'] = '%s';\n" "${APP_URL:?Set APP_URL in config/.env.ci.}" >> settings.php
 printf "\$config['youvo.settings']['website_url'] = '%s';\n" "${WEBSITE_URL:?Set WEBSITE_URL in config/.env.ci.}" >> settings.php
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

 # Import the development config split and generate dummy content.
 echo "Importing development configuration ..."
 vendor/bin/drush cim -y > /dev/null 2>&1
 echo "Generating dummy content ..."
 vendor/bin/drush php:eval "\Drupal::moduleHandler()->invoke('youvo_dummy', 'site_install_finished');"

 # Rebuild Cache.
 echo "Rebuild cache ..."
 vendor/bin/drush cr > /dev/null 2>&1
