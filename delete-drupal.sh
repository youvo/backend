 #!/bin/bash 
 docker-compose down
 cd web
 bash reset-sites.sh
 cd ..
 docker-compose up -d 
