# Setup and Usage for local development

## System

### Requirements
`php 7.4` `apache2 2.4.41` `composer 2.1.5` `docker 20.10.7` `make 4.2.1`

### Requirements for dev dependencies
`php7.4-mbstring` `php7.4-mysql`

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

### Make commands

Start up containers: `make up` 

Start containers without updating: `make start`

Stop containers: `make stop` 

Destroy containers: `make down`

Display running containers: `make ps`

Executing drush command in php container: `make drush "foo"`

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


### Reset current drupal installation

Currently installs Drupal with the profile `youvo_development`. For production we will use the installation profile `youvo_platform`.

For online enviroment: `scripts/reset-drupal.sh`

For local enviroment: `scripts/reset-drupal-local.sh`

### Deploy script

`scripts/deploy.sh`

### TCP connection to database

Note that the docker image for MariaDB serves the database via the host `mariadb`. We enable an additional entrypoint in traefik for PHPStorm.

|               |                           |
| ------------- | ------------------------- | 
| Host          | localhost                 |
| Port          | 3306                      |
| Database      | youvo_test                   |
| User          | drupal                    |
| Password      | drupal                    |	

### CodeSniffer configuration
* https://www.drupal.org/node/1419988
* Set in idea configuration file `<option name="CODING_STANDARD" value="Drupal,DrupalPractice,PHPCompatibility" />`
