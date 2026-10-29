#!/bin/bash

 # One-time upgrade of the old v0.2.3 production site to this codebase.
 #
 # Backs up the old database, files and settings, exports users and academy
 # content, empties the database, reinstalls from existing configuration,
 # imports the content and copies the academy files to the new public files
 # path. Files are never deleted; the old files folder stays in place.
 #
 # With --content, an existing export from scripts/export-content.sh is
 # imported instead, e.g. after an interrupted run. The database then does not
 # need to hold the old content.
 #
 # Run after checking out this code, switching to PHP 8.3 and running
 # composer install. Nothing is changed before all checks pass.
 #
 # Usage: scripts/upgrade-production.sh [--content <export file>] [backup directory]

 # Get arguments.
 CONTENT=
 if [[ "$1" == "--content" ]]; then
  CONTENT=$(cd "$(dirname "$2")" && pwd -P)/$(basename "$2")
  shift 2
 fi

 # Exit on error.
 set -e

 # Navigate to project root and get variables.
 SCRIPT_PATH="$( cd -- "$(dirname "$0")" || exit >/dev/null 2>&1 ; pwd -P )"
 cd "$SCRIPT_PATH/.." || exit
 PATH=$PATH:$(pwd)/vendor/bin
 BACKUP=${1:-$HOME/upgrade-$(date +%Y%m%d-%H%M)}
 set -a; source config/.env.production; set +a
 # Pass credentials through an option file, which overrides ~/.my.cnf.
 DEFAULTS=$(mktemp)
 trap 'rm -f "$DEFAULTS"' EXIT
 printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASSWORD" > "$DEFAULTS"
 CONNECTION=(--defaults-extra-file="$DEFAULTS")
 SITE=web/sites/default
 OLD_FILES=$SITE/files
 NEW_FILES=web/files

 # Check everything before changing anything.
 fail() { echo "Error: $1"; exit 1; }
 php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' || fail "PHP 8.3 or newer is required."
 [[ -x vendor/bin/drush ]] || fail "Run composer install first."
 [[ -r config/.env.consumers.production ]] || fail "config/.env.consumers.production is missing."
 [[ -r config/.env.oauth_remote.production ]] || fail "config/.env.oauth_remote.production is missing."
 [[ -r config/certs/public.key && -r config/certs/private.key ]] || fail "OAuth keys are missing in config/certs."
 [[ -n "$TRUSTED_HOST_PATTERN" ]] || fail "Set TRUSTED_HOST_PATTERN in config/.env.production."
 [[ -v JSONAPI_OBSCURITY_PREFIX ]] || fail "Set JSONAPI_OBSCURITY_PREFIX in config/.env.production (may be empty)."
 mysql "${CONNECTION[@]}" -e "SELECT 1" "$DB_NAME" > /dev/null || fail "Cannot connect to the database $DB_NAME."
 if [[ -n "$CONTENT" ]]; then
  [[ -r "$CONTENT" ]] || fail "$CONTENT is not readable."
  gzip -cdf "$CONTENT" | grep -q "^INSERT INTO \`users_field_data\`" || fail "$CONTENT is not a content export."
 else
  USERS=$(mysql "${CONNECTION[@]}" -N -e "SELECT COUNT(*) FROM users" "$DB_NAME") || fail "Cannot read the old content in $DB_NAME. Use --content with an existing export."
  [[ "$USERS" -gt 2 ]] || fail "The database has no content to migrate. Was it upgraded already? Use --content with an existing export."
 fi
 [[ ! -e "$NEW_FILES/academy" ]] || fail "$NEW_FILES/academy exists already."
 [[ ! -e "$BACKUP" ]] || fail "$BACKUP exists already."
 echo "Checks passed. Database $DB_NAME will be emptied ..."

 # Back up database, files and settings.
 mkdir -p "$BACKUP"
 chmod 700 "$BACKUP"
 mysqldump "${CONNECTION[@]}" --single-transaction --no-tablespaces "$DB_NAME" | gzip > "$BACKUP/database.sql.gz"
 [[ -d "$OLD_FILES" ]] && tar czf "$BACKUP/files.tgz" -C web sites/default/files
 cp -a $SITE/settings.php "$BACKUP/" 2>/dev/null || true
 cp -a $SITE/services.yml "$BACKUP/" 2>/dev/null || true
 echo "Backup written to $BACKUP ..."

 # Export content, or keep a copy of the given export.
 if [[ -n "$CONTENT" ]]; then
  cp "$CONTENT" "$BACKUP/content.sql.gz"
  echo "Using content from $CONTENT ..."
 else
  scripts/export-content.sh "$BACKUP/content.sql.gz"
 fi

 # Empty the database. The database itself is kept.
 TABLES=$(mysql "${CONNECTION[@]}" -N -e "SET SESSION group_concat_max_len = 1000000; SELECT GROUP_CONCAT(CONCAT('\`', table_name, '\`')) FROM information_schema.tables WHERE table_schema = '$DB_NAME'")
 if [[ -n "$TABLES" && "$TABLES" != "NULL" ]]; then
  mysql "${CONNECTION[@]}" -e "SET FOREIGN_KEY_CHECKS = 0; DROP TABLE $TABLES" "$DB_NAME"
 fi
 echo "Database emptied ..."

 # Reset settings. Keep an existing services.yml and set the obscurity prefix.
 chmod u+w $SITE $SITE/settings.php 2>/dev/null || true
 rm -f $SITE/settings.php
 cp $SITE/install.settings.php $SITE/settings.php
 printf "\$settings['trusted_host_patterns'] = ['%s'];\n" "$TRUSTED_HOST_PATTERN" >> $SITE/settings.php
 [[ -f $SITE/services.yml ]] || cp $SITE/install.services.yml $SITE/services.yml
 chmod u+w $SITE/services.yml
 sed -i.bak -E "s|^( *jsonapi_obscurity\.prefix:).*|\1 '$JSONAPI_OBSCURITY_PREFIX'|" $SITE/services.yml && rm $SITE/services.yml.bak
 grep -q "jsonapi_obscurity.prefix: '$JSONAPI_OBSCURITY_PREFIX'" $SITE/services.yml || fail "Could not set jsonapi_obscurity.prefix in $SITE/services.yml."
 echo "Settings reset ..."

 # Install Drupal with the production consumers and OAuth remote settings.
 # The installation drops all tables of the existing database; dropping and
 # creating the database itself needs privileges the database user may lack.
 echo "Installing Drupal ..."
 CONFIG_ENV=production drush site:install -y --existing-config \
  --locale="en" \
  --db-url="${DB_DRIVER}://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_NAME}" \
  --site-name="${SITE_NAME}" \
  --site-mail="${SITE_MAIL}" \
  --account-name="${ACCOUNT_NAME}" \
  --account-mail="${ACCOUNT_MAIL}" \
  --account-pass="${ACCOUNT_PASS}" > /dev/null 2>&1

 # Import content.
 scripts/import-content.sh "$BACKUP/content.sql.gz"

 # Enable blocker mode.
 drush state:set system.blocker_mode 1 --input-format=integer
 echo "Blocker mode enabled ..."

 # Copy academy files to the new public files path.
 mkdir -p $NEW_FILES
 if [[ -d "$OLD_FILES/academy" ]]; then
  cp -a "$OLD_FILES/academy" $NEW_FILES/
  echo "Copied $(find $NEW_FILES/academy -type f | wc -l | tr -d ' ') academy files ..."
 fi

 # Set OAuth settings. Config ignore keeps them on later deploys.
 drush cset -y simple_oauth.settings public_key "$(pwd)/config/certs/public.key" > /dev/null
 drush cset -y simple_oauth.settings private_key "$(pwd)/config/certs/private.key" > /dev/null
 (
  set -a; source config/.env.oauth_remote.production; set +a
  [[ -n "$JWT_KEY_PATH" ]] && drush cset -y oauth_grant_remote.settings jwt_key_path "$JWT_KEY_PATH" > /dev/null
  [[ -n "$AUTH_RELAY_URL" ]] && drush cset -y oauth_grant_remote.settings auth_relay_url "$AUTH_RELAY_URL" > /dev/null
  drush cset -y oauth_grant_remote.settings development "${DEVELOPMENT:-0}" > /dev/null
 )
 echo "OAuth settings updated ..."

 # Rebuild Cache.
 echo "Rebuilding Cache ..."
 drush cr > /dev/null 2>&1

 # Set permissions for settings files.
 chmod 0444 $SITE/settings.php $SITE/services.yml

 # Report.
 drush cst || true
 drush core:requirements --severity=2 || true
 echo "Done. Backup in $BACKUP. Verify the site, then remove $BACKUP/content.sql.gz in a few days."
