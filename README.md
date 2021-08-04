# Setup and Usage for local development

## System

### Requirements
`php7.4` `php7.4-mbstring` `composer 2.1.5` `docker 20.10.7`

### Add to /etc/hosts

| IP            | Host                      |
| ------------- | ------------------------- | 
| 127.0.0.1     | youvo.localhost           |
| 127.0.0.1     | portainer.youvo.localhost |
| 127.0.0.1     | adminer.youvo.localhost   |
| 127.0.0.1     | mailhog.youvo.localhost   |

## Setup

### Composer initialisation

`composer install --no-dev --no-interaction --no-progress`

### Docker commands

`docker-compose up -d` 

`docker-compose stop` 

`docker-compose down`

## Notes

### URLs of interest local site

http://youvo.localhost:8000

http://maihog.youvo.localhost:8000

http://adminer.youvo.localhost:8000

http://portainer.youvo.localhost:8000

### Delete current drupal installation
`/scripts/delete-drupal.sh`

### Deploy scripts for webhook
`/scripts/deploy.sh`

### TCP connection to database

|               |                           |
| ------------- | ------------------------- | 
| Host          | localhost                 |
| Port          | 3306                      |
| Database      | youvodb                   |
| User          | drupal                    |
| Password      | drupal                    |	
