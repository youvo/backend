#!/bin/bash

 # Exports users, academy content and files from the old production database
 # into a gzipped SQL file for scripts/import-content.sh. Run this before the
 # reinstallation drops the database. It needs the mysql client only, not
 # Drupal.
 #
 # Usage: scripts/export-content.sh <output file> [env file]

 # Exit on error.
 set -e

 OUTPUT=$1
 ENV_FILE=${2:-config/.env.production}
 if [[ -z "$OUTPUT" ]]; then
  echo "Usage: $0 <output file> [env file]"
  exit 1
 fi

 # Navigate to project root and get variables.
 SCRIPT_PATH="$( cd -- "$(dirname "$0")" || exit >/dev/null 2>&1 ; pwd -P )"
 cd "$SCRIPT_PATH/.." || exit
 set -a; source "$ENV_FILE"; set +a
 # Pass credentials through an option file, which overrides ~/.my.cnf.
 DEFAULTS=$(mktemp)
 trap 'rm -f "$DEFAULTS"' EXIT
 printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASSWORD" > "$DEFAULTS"
 CONNECTION=(--defaults-extra-file="$DEFAULTS")

 # Tables to export. Taxonomy terms are created identically by the installation.
 TABLES="
  users users_field_data users_data user__roles
  course course_field_data course__tags course_progress
  lectures lectures_field_data lecture_progress
  paragraphs paragraphs_field_data
  questions questions_field_data question__answers question__options question_submissions
  file_managed file_usage
 "
 TABLES="$TABLES $(mysql "${CONNECTION[@]}" -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema = '$DB_NAME' AND (table_name LIKE 'user\_\_field\_%' OR table_name LIKE 'paragraph\_\_%')")"

 # Empty the target tables first, then insert with explicit column names, so
 # columns added since the old version keep their defaults.
 {
  for TABLE in $TABLES; do
   echo "DELETE FROM \`$TABLE\`;"
  done
  mysqldump "${CONNECTION[@]}" --no-create-info --complete-insert --skip-add-locks \
   --skip-disable-keys --skip-triggers --no-tablespaces --skip-comments \
   "$DB_NAME" $TABLES
 } | gzip > "$OUTPUT"

 echo "Exported $(echo $TABLES | wc -w | tr -d ' ') tables to $OUTPUT."
