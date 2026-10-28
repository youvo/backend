#!/bin/bash

 # Imports users, academy content and files exported by
 # scripts/export-content.sh into a fresh installation.
 #
 # Usage: scripts/import-content.sh <export file>

 # Exit on error.
 set -e

 INPUT=$1
 if [[ ! -f "$INPUT" ]]; then
  echo "Usage: $0 <export file>"
  exit 1
 fi
 INPUT=$(cd "$(dirname "$INPUT")" && pwd -P)/$(basename "$INPUT")

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

 # Import content.
 echo "Importing content ..."
 drush sqlq --file="$INPUT" > /dev/null
 echo "Imported $(drush sqlq "SELECT COUNT(*) FROM users") users and $(drush sqlq "SELECT COUNT(*) FROM course") courses."

 # Rebuild Cache.
 echo "Rebuilding Cache ..."
 drush cr > /dev/null 2>&1

 echo "Done. Copy the academy files next, e.g. from web/sites/default/files/academy of the old site to web/files/academy."
