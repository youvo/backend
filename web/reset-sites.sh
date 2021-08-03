 #!/bin/bash 
 cd sites/default
 rm settings.php
 cp default.settings.php settings.php
 chmod a+w settings.php
 sudo rm -rf files
 mkdir files
 chmod 0777 files
