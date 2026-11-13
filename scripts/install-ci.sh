#!/bin/bash

 # Exit on error.
 set -e

 # Get variables.
 set -a; source config/.env.ci; set +a
 echo "Variables loaded ..."

 # The simple_oauth keys are git-ignored, so CI generates throwaway ones.
 CERTS_PATH="$(pwd -P)/config/certs"
 if ! test -r "$CERTS_PATH/private.key"; then
 	openssl genrsa -out "$CERTS_PATH/private.key" 2048 2> /dev/null
 	openssl rsa -in "$CERTS_PATH/private.key" -pubout -out "$CERTS_PATH/public.key" 2> /dev/null
 	chmod 0600 "$CERTS_PATH/private.key"
 fi

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
 # The existing-config install imports the empty OAuth key paths from
 # config/sync over the values the install hook sets, so they are overridden
 # here. The auth relay stays off, as config/sync sets development to 1.
 printf "\$config['simple_oauth.settings']['public_key'] = '%s';\n" "$CERTS_PATH/public.key" >> settings.php
 printf "\$config['simple_oauth.settings']['private_key'] = '%s';\n" "$CERTS_PATH/private.key" >> settings.php
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
