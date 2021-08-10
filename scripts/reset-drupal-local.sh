 #!/bin/bash 
 
 # Check if sudo user
 if [ "$UID" != "$ROOT_UID" ] 
 then 
   	echo "Please run using sudo!"
	exit
 fi
 
 # Navigate to directory
 SCRIPTPATH="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"
 cd $SCRIPTPATH
 
 # Stop docker containers
 cd ..
 echo "Stopping docker containers ..."
 docker-compose down > /dev/null 2>&1
 
 # Set permissions
 cd web/sites
 chmod 0755 default
 
 # Remove settings file
 cd default
 chmod 0777 settings.php
 rm settings.php
 cp default.settings.php settings.php
 chmod 0777 settings.php
 echo "Settings reset ..."
 
 # Reset files folder
 chmod 0777 files
 rm -rf files
 mkdir files
 chmod 0777 files
 echo "Files folder reset ..."
 
 # Start docker containers
 cd ../../..
 echo "Starting docker containers ..."
 docker-compose up -d > /dev/null 2>&1
 
 # Wait for containers to be accessible
 sleep 5
 
 # Reinstall drupal
 echo "Installing Drupal ..."
 make drush "si -y youvo_platform \
  --locale=en \
  --db-url=mysql://drupal:drupal@mariadb:3306/youvo_test \
  --site-name=youvo.org \
  --site-mail=hello@youvo.org \
  --account-name=admin \
  --account-mail=hello@youvo.org \
  --account-pass=admin" > /dev/null 2>&1
 
 # Rebuild Cache
 echo "Rebuilding Cache ..."
 make drush cr > /dev/null 2>&1
 
 # Bye bye
 echo "Success!"
 sleep 1
 
 
