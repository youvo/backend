 #!/bin/bash 
 
 SITE_FOLDER=_test-backend
 PATH=$PATH:~/www/$SITE_FOLDER/vendor/bin

 cd ~
 cd www/$SITE_FOLDER
 set -a; source conf/.env; set +a
 
 # Delete database
 mysql -e "DROP DATABASE IF EXISTS ${DB_NAME}"
 mysql -e "CREATE DATABASE ${DB_NAME}"
 echo $'\033[1mDatabase reset!\033[0m'
 
 # Set permissions
 cd web/sites
 chmod 0755 default
 
 # Remove settings file
 cd default
 chmod 0777 settings.php
 rm settings.php
 cp default.settings.php settings.php
 chmod 0755 settings.php
 echo $'\033[1mSettings reset!\033[0m'
 
 # Reset files folder
 rm -rf files
 mkdir files
 chmod 0777 files
 echo $'\033[1mFiles folder reset!\033[0m'
  
 # Reinstall drupal
 cd ~
 cd www/$SITE_FOLDER/web
 drush site:install -y youvo_development \
  --locale="en" \
  --db-url="${DB_DRIVER}://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_NAME}" \
  --site-name="youvo.org" \
  --site-mail="hello@youvo.org" \
  --account-name="admin" \
  --account-mail="hello@youvo.org" \
  --account-pass="admin"
 
 drush cr
 echo $'\033[1mExit in 3 seconds!\033[0m'
 sleep 3
 
