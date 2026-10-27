#!/bin/bash

 # Copies users, academy content and files from the old production database
 # into a fresh installation. Both databases must be on the same server and
 # the Drupal database user needs read access to the source database.
 #
 # Usage: scripts/copy-content.sh <source database>

 # Exit on error.
 set -e

 SOURCE_DB=$1
 if [[ -z "$SOURCE_DB" ]]; then
  echo "Usage: $0 <source database>"
  exit 1
 fi

 # Navigate to project root.
 SCRIPT_PATH="$( cd -- "$(dirname "$0")" || exit >/dev/null 2>&1 ; pwd -P )"
 cd "$SCRIPT_PATH/.." || exit
 PATH=$PATH:$(pwd)/vendor/bin

 # Refuse to overwrite a site with content.
 USERS=$(drush sqlq "SELECT COUNT(*) FROM users")
 if [[ "$USERS" -gt 2 ]]; then
  echo "The target site already has $USERS users. Run this on a fresh installation only."
  exit 1
 fi

 # Tables to copy. Taxonomy terms are created identically by the installation.
 TABLES="
  users users_field_data users_data user__roles
  course course_field_data course__tags course_progress
  lectures lectures_field_data lecture_progress
  paragraphs paragraphs_field_data
  questions questions_field_data question__answers question__options question_submissions
  file_managed file_usage
 "
 TABLES="$TABLES $(drush sqlq "SELECT table_name FROM information_schema.tables WHERE table_schema = '$SOURCE_DB' AND (table_name LIKE 'user\_\_field\_%' OR table_name LIKE 'paragraph\_\_%')")"

 # Copy each table by the columns both databases share.
 for TABLE in $TABLES; do
  COLUMNS=$(drush sqlq "SELECT GROUP_CONCAT(CONCAT('\`', s.column_name, '\`') ORDER BY s.ordinal_position) FROM information_schema.columns s JOIN information_schema.columns t ON t.table_name = s.table_name AND t.column_name = s.column_name AND t.table_schema = DATABASE() WHERE s.table_schema = '$SOURCE_DB' AND s.table_name = '$TABLE'")
  if [[ -z "$COLUMNS" || "$COLUMNS" == "NULL" ]]; then
   echo "Skipping $TABLE: not found in both databases."
   continue
  fi
  drush sqlq "DELETE FROM \`$TABLE\`; INSERT INTO \`$TABLE\` ($COLUMNS) SELECT $COLUMNS FROM \`$SOURCE_DB\`.\`$TABLE\`"  > /dev/null
  echo "Copied $TABLE: $(drush sqlq "SELECT COUNT(*) FROM \`$TABLE\`") rows."
 done

 # Rebuild Cache.
 echo "Rebuilding Cache ..."
 drush cr > /dev/null 2>&1

 echo "Done. Copy the academy files next, e.g. from web/sites/default/files/academy of the old site to web/files/academy."
