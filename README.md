# Setup and Usage for local development

## System

### Requirements
`php 7.4` `apache2 2.4.41` `composer 2.1.5` `docker 20.10.7`

### Requirements for dev dependencies
`php7.4-mbstring`

### Add to /etc/hosts

| IP            | Host                      |
| ------------- | ------------------------- | 
| 127.0.0.1     | youvo.localhost           |
| 127.0.0.1     | portainer.youvo.localhost |
| 127.0.0.1     | adminer.youvo.localhost   |
| 127.0.0.1     | mailhog.youvo.localhost   |

## Setup

### Composer initialisation

`composer install --no-interaction --no-progress`

### Docker commands

`docker-compose up -d` 

`docker-compose stop` 

`docker-compose down`

## Notes

### URLs of interest local site

|                                       |                      |
| ------------------------------------- | -------------------- | 
| http://youvo.localhost:8000           | Website              |
| http://mailhog.youvo.localhost:8000   | Mailhog              |
| http://adminer.youvo.localhost:8000   | Adminer              |
| http://portainer.youvo.localhost:8000 | Portainer Dashboard* |
| http://youvo.localhost:8080           | Traefik Dashboard*   |	

> (*) currently disabled


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

### CodeSniffer configuration
* https://www.drupal.org/node/1419988
* Set in idea configuration file `<option name="CODING_STANDARD" value="Drupal,DrupalPractice,PHPCompatibility" />`
