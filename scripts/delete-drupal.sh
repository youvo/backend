 #!/bin/bash 
 
 # Delete database
 mysql -e "DROP DATABASE IF EXISTS youvo_test"
 mysql -e "CREATE DATABASE youvo_test"
 echo $'\033[0;32m\033[1mDatabase reset!\033[0m\n'
 
 # Set permissions
 cd ../web/sites
 chmod 0755 default
 
 # Remove settings file
 cd default
 chmod 0777 settings.php
 rm settings.php
 echo $'\033[0;32m\033[1mSettings deleted!\033[0m\n'
 
 # Reset files folder
 rm -rf files
 mkdir files
 chmod 0777 files
 echo $'\033[0;32m\033[1mFiles folder reinitialized!\033[0m\n'
 
