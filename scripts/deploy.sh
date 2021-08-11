 #!/bin/bash

 # Exit on error.
 set -e

 # Sync working branch with origin.
 git fetch
 git reset --hard origin/$(git branch | grep \* | cut -d ' ' -f2)
 git clean -df

 # Install Composer dependecies.
 composer install --no-interaction --no-progress

 # Rebuild Cache.
 drush cr > /dev/null 2>&1
