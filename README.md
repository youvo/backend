# Add to /etc/hosts
127.0.0.1		youvo.localhost
127.0.0.1       portainer.youvo.localhost
127.0.0.1       adminer.youvo.localhost
127.0.0.1       mailhog.youvo.localhost

# Start docker containers
docker-compose up -d

# Stop docker containers
docker-compose stop

# Access local site
http://youvo.docker.localhost:8000

# Access docker status
http://portainer.youvo.docker.localhost:8000

