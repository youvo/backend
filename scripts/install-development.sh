#!/bin/bash

 # Exit on error.
 set -e

 SITE_FOLDER=_dev/backend

 # Print the duration of each step.
 START_TIME=$SECONDS
 STEP_TIME=$SECONDS
 took() {
 	echo "  took $((SECONDS - STEP_TIME))s"
 	STEP_TIME=$SECONDS
 }
 PATH=$PATH:~/www/$SITE_FOLDER/vendor/bin

 cd ~
 cd www/$SITE_FOLDER
 set -a; source config/.env.development; set +a

 # The OAuth install hooks read config/.env.consumers.development and
 # config/.env.oauth_remote.development, see the README. The simple_oauth keys
 # are git-ignored and have to be generated once, see config/certs/README.md.
 for REQUIRED_FILE in config/.env.consumers.development config/.env.oauth_remote.development config/certs/public.key config/certs/private.key; do
 	if ! test -r "$REQUIRED_FILE"; then
 		echo "$REQUIRED_FILE is missing."
 		exit 1
 	fi
 done
 set -a; source config/.env.oauth_remote.development; set +a
 # The physical path, since PHP resolves the ~/www symlink.
 CERTS_PATH="$(pwd -P)/config/certs"

 # Reject web requests until the script ends, see settings.php below. Requests
 # during site:install cache outdated field storage definitions, which the
 # installer never clears, since it only uses in-memory caches.
 # site:install makes the folder read-only, so it is made writable to remove
 # the flag.
 SITES_DEFAULT=~/www/$SITE_FOLDER/web/sites/default
 chmod 0755 "$SITES_DEFAULT"
 touch "$SITES_DEFAULT/installing"
 trap 'chmod u+w "$SITES_DEFAULT"; rm -f "$SITES_DEFAULT/installing"; chmod a-w "$SITES_DEFAULT"' EXIT

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
 cp install.settings.php settings.php
 [[ -f services.yml ]] || cp install.services.yml services.yml
 echo "\$config['config_split.config_split.development']['status'] = TRUE;" >> settings.php
 # Point links and redirects to the frontends of this environment.
 printf "\$config['youvo.settings']['hub_url'] = '%s';\n" "${HUB_URL:?Set HUB_URL in config/.env.development.}" >> settings.php
 printf "\$config['youvo.settings']['app_url'] = '%s';\n" "${APP_URL:?Set APP_URL in config/.env.development.}" >> settings.php
 printf "\$config['youvo.settings']['website_url'] = '%s';\n" "${WEBSITE_URL:?Set WEBSITE_URL in config/.env.development.}" >> settings.php
 # The existing-config install imports the empty OAuth key paths and relay
 # settings from config/sync over the values the install hooks set, so they are
 # overridden here. The relay settings are optional with DEVELOPMENT=1, which
 # turns the relay off.
 printf "\$config['simple_oauth.settings']['public_key'] = '%s';\n" "$CERTS_PATH/public.key" >> settings.php
 printf "\$config['simple_oauth.settings']['private_key'] = '%s';\n" "$CERTS_PATH/private.key" >> settings.php
 if [[ "${DEVELOPMENT:-0}" == "1" || "${DEVELOPMENT:-0}" == "true" ]]; then
 	if [[ -n "${JWT_KEY_PATH:-}" ]]; then
 		printf "\$config['oauth_grant_remote.settings']['jwt_key_path'] = '%s';\n" "$JWT_KEY_PATH" >> settings.php
 	fi
 	if [[ -n "${AUTH_RELAY_URL:-}" ]]; then
 		printf "\$config['oauth_grant_remote.settings']['auth_relay_url'] = '%s';\n" "$AUTH_RELAY_URL" >> settings.php
 	fi
 else
 	printf "\$config['oauth_grant_remote.settings']['jwt_key_path'] = '%s';\n" "${JWT_KEY_PATH:?Set JWT_KEY_PATH in config/.env.oauth_remote.development.}" >> settings.php
 	printf "\$config['oauth_grant_remote.settings']['auth_relay_url'] = '%s';\n" "${AUTH_RELAY_URL:?Set AUTH_RELAY_URL in config/.env.oauth_remote.development.}" >> settings.php
 fi
 cat >> settings.php <<'EOF'
// Reject web requests while scripts/install-development.sh runs.
if (PHP_SAPI !== 'cli' && file_exists(__DIR__ . '/installing')) {
  http_response_code(503);
  exit('Installation in progress.');
}
EOF
 chmod 0755 settings.php
 echo "Settings reset ..."

 # Reset files folder. The public files path is web/files.
 cd ../..
 rm -rf files
 mkdir files
 chmod 0755 files
 echo "Files folder reset ..."

 # Reinstall drupal.
 cd ~
 cd www/$SITE_FOLDER/web
 echo "Installing Drupal ..."
 STEP_TIME=$SECONDS
 drush site:install -y --existing-config \
  --locale="en" \
  --db-url="${DB_DRIVER}://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_NAME}" \
  --site-name="${SITE_NAME}" \
  --site-mail="${SITE_MAIL}" \
  --account-name="${ACCOUNT_NAME}" \
  --account-mail="${ACCOUNT_MAIL}" \
  --account-pass="${ACCOUNT_PASS}" > /dev/null 2>&1
 took

 # Import the development config split and generate dummy content.
 echo "Importing development configuration ..."
 drush cim -y > /dev/null 2>&1
 drush cr > /dev/null 2>&1
 took
 echo "Generating dummy content ..."
 drush php:eval "\Drupal::moduleHandler()->invoke('youvo_dummy', 'site_install_finished');"
 took

 # Rebuild Cache.
 echo "Rebuilding Cache ..."
 drush cr > /dev/null 2>&1
 took

 # Generate the image styles of the dummy content in the background, since
 # ImageMagick takes minutes. There is no cron to process the queue.
 echo "Generating image styles in the background ..."
 nohup drush queue:run image_style_warmer_pregenerator > ~/image-style-warmer.log 2>&1 &

 # Set permissions for settings file.
 cd ~
 cd www/$SITE_FOLDER/web/sites/default
 chmod 0444 settings.php
 chmod 0444 default.settings.php

 # Bye bye.
 echo "Installation duration: $((SECONDS - START_TIME)) seconds."
 echo "Exit in 3 seconds!"
 sleep 3

