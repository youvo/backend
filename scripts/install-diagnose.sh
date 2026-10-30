#!/bin/bash

# Diagnoses missing field storage definitions on the dev server.
#
# Runs the same steps as scripts/install-development.sh, but shows all output
# and checks the installed field storage definitions after every step. It also
# samples other PHP processes during the install to spot concurrent access.
#
# WARNING: This resets the dev installation, like "make install".
#
# With --check, it only runs the check on the current installation and lists
# running PHP processes, without resetting anything.

set -e

SITE_FOLDER=_dev/backend
SITE=~/www/$SITE_FOLDER
LOG=~/install-diagnose-$(date +%Y%m%d-%H%M%S).log
PATH=$PATH:$SITE/vendor/bin

# Compares the current field storage definitions with the installed ones, both
# in the database record (key_value) and in the cached copy (cache_discovery).
# During site:install Drupal only uses in-memory caches, so cache entries
# written by other processes in the meantime are never cleared.
check() {
  echo "--- check after: $1"
  (cd "$SITE/web" && drush php:eval '
$fm = \Drupal::service("entity_field.manager");
$kv = \Drupal::keyValue("entity.definitions.installed");
$db = \Drupal::database();
$ok = TRUE;
foreach (\Drupal::entityTypeManager()->getDefinitions() as $id => $type) {
  if (!$type->entityClassImplements(\Drupal\Core\Entity\FieldableEntityInterface::class)) continue;
  $current = $fm->getFieldStorageDefinitions($id);
  $record = $kv->get("$id.field_storage_definitions", []);
  if ($missing = array_diff_key($current, $record)) {
    $ok = FALSE;
    echo "MISSING in record   $id: ", implode(", ", array_keys($missing)), "\n";
  }
  $cid = "$id.field_storage_definitions.installed";
  $row = $db->query("SELECT data, created FROM {cache_discovery} WHERE cid = :cid", [":cid" => $cid])->fetchAssoc();
  if ($row && ($missing = array_diff_key($current, unserialize($row["data"])))) {
    $ok = FALSE;
    echo "MISSING in cache    $id (cached at ", date("H:i:s", (int) $row["created"]), "): ", implode(", ", array_keys($missing)), "\n";
  }
}
$d = $kv->get("project.field_storage_definitions", []);
echo "project fields in record: ", count($d), ", history: ", json_encode(isset($d["field_lifecycle_history"])), "\n";
echo "automated_cron enabled: ", json_encode(\Drupal::moduleHandler()->moduleExists("automated_cron")), "\n";
echo $ok ? "OK: no missing field storage definitions\n" : "";
') || echo "check failed"
}

# Samples PHP processes other than this install every second.
watch_processes() {
  while true; do
    ps -eo pid,ppid,lstart,args | grep -E "php|drush" | grep -vE "grep|ps -eo" \
      | sed "s/^/[$(date +%T)] /"
    sleep 1
  done > "$LOG.processes"
}

if [[ $1 == "--check" ]]; then
  check "nothing (check only)"
  echo "--- running PHP processes"
  ps -eo pid,ppid,lstart,args | grep -E "php|drush" | grep -vE "grep|ps -eo" || echo "(none)"
  exit 0
fi

read -r -p "This resets the installation in $SITE. Continue? [y/n] " REPLY
[[ $REPLY =~ ^[Yy]$ ]] || exit 1

# Log everything to the terminal and the log file.
exec > >(tee -a "$LOG") 2>&1

echo "Log: $LOG"
echo "Commit: $(cd "$SITE" && git log --oneline -1)"
php -v | head -1
echo "Crontab:"; crontab -l 2>/dev/null || echo "(none)"

cd "$SITE"
set -a; source config/.env.development; set +a

# Delete database.
mysql -e "DROP DATABASE IF EXISTS ${DB_NAME}"
mysql -e "CREATE DATABASE ${DB_NAME}"
echo "Database reset ..."

# Reset settings.
cd web/sites
chmod 0755 default
cd default
[[ -f settings.php ]] && chmod 0777 settings.php
[[ -f settings.php ]] && rm settings.php
cp install.settings.php settings.php
[[ -f services.yml ]] || cp install.services.yml services.yml
echo "\$config['config_split.config_split.development']['status'] = TRUE;" >> settings.php
chmod 0755 settings.php
echo "Settings reset ..."

# Reset files folder.
cd ../..
rm -rf files
mkdir files
chmod 0755 files
echo "Files folder reset ..."

# Install with the process sampler running.
watch_processes &
WATCHER=$!
trap 'kill $WATCHER 2>/dev/null' EXIT
echo "=== drush site:install ($(date +%T))"
drush site:install -y --existing-config \
  --locale="en" \
  --db-url="${DB_DRIVER}://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_NAME}" \
  --site-name="${SITE_NAME}" \
  --site-mail="${SITE_MAIL}" \
  --account-name="${ACCOUNT_NAME}" \
  --account-mail="${ACCOUNT_MAIL}" \
  --account-pass="${ACCOUNT_PASS}" -v || echo "site:install FAILED with $?"
echo "=== site:install done ($(date +%T))"
kill $WATCHER 2>/dev/null || true
check "site:install"

echo "=== drush cim"
drush cim -y || echo "cim FAILED with $?"
check "cim"

echo "=== drush cr"
drush cr || echo "cr FAILED with $?"
check "cr"

echo "=== dummy content"
drush php:eval "\Drupal::moduleHandler()->invoke('youvo_dummy', 'site_install_finished');" || echo "dummy content FAILED with $?"
check "dummy content"

drush cr

# Restore permissions for settings file.
chmod 0444 sites/default/settings.php
chmod 0444 sites/default/default.settings.php

echo "Done. Log: $LOG"
echo "PHP processes seen during site:install: $LOG.processes"
