# youvo Backend Repository

Provides the backend for `data.youvo.org` based on `Drupal 9`.

## Setup and usage for distribution

### Development

To set up a Drupal distribution with the `youvo_development` profile, do the following:

1. Clone repository to your server.
2. Run `composer install`.
3. Copy or configure `config/.env.development`.
4. Run `scripts/install-development.sh`.

This includes development modules such as `devel`, `coder`, `phpcodesniffer`, `phpunit`, `admin_toolbar` and more.
Also, it will install dummy content with the modules `youvo_dummy`, `project_dummy` and `academy_dummy`.

The database is `youvo_dev`.

### Production

To set up a Drupal distribution with the `youvo_platform` profile, do the following:

1. Clone repository to your server.
2. Run `composer install --no-dev`.
3. Copy or configure `config/.env.production`.
4. Run `scripts/install-production.sh`.

This provides a clean-state installation. This administrator login can be found in the environment file. The
administration can be found under `/admin/index` and `/admin/configuration`.

The database is `youvo_prod`.

### Notes

Drush is available via `/vendor/bin/drush`.

The deploy-script can be found in `scripts/deploy.sh`.

## Setup and usage for local development

### System requirements

This setup was tested with `Ubuntu 20.04.3 LTS`. The following software is required:

`composer 2.1.5`
`docker 20.10.10`
`make 4.2.1`

### Add to /etc/hosts

| IP            | Host                      |
| ------------- | ------------------------- |
| 127.0.0.1     | youvo.localhost           |
| 127.0.0.1     | adminer.youvo.localhost   |
| 127.0.0.1     | mailhog.youvo.localhost   |

### Docker and Composer configuration

**Docker** uses the following images:

`wodby/drupal-php:8.0-dev-4.27.1`
`wodby/nginx:1.20-5.15.0`
`wodby/mariadb:10.5-3.13.2`
`wodby/adminer:4-3.15.1`
`traefik:v2.0`

The configuration can be found in `docker-compose.yml`.

**Composer** is based on the package:

`drupal/core-recommended`

The configuration can be found in `composer.json`. Patches are defined in `composer.patches.json`. The patch files are
located in the folder `patches`.

### Installation

To set up a local Drupal distribution with the `youvo_development` profile, do the following:

1. Clone repository to your system.
2. Run `composer install --no-interaction --no-progress`.
3. Consult and configure `config/.env.local`.
4. Run `scripts/install-local.sh`.

Provides development modules as described above. You may need to adjust folder permissions for
dummy content folders `academy` and `projects`. Use

`chmod 0666 -R academy`
`chmod 0666 -R projects`

in the `sites/default/files` folder to grant permissions.

### Make commands

> Start up containers: `make up`

> Start containers without updating: `make start`

> Stop containers: `make stop`

> Destroy containers: `make down`

> Display running containers: `make ps`

> Show PHP logs: `make logs php`

> Executing drush command in php container: `make drush "foo"`

## Notes

### URLs of interest local site

| URL                                   | Description          |
| ------------------------------------- | -------------------- |
| http://youvo.localhost:8000           | Website              |
| http://mailhog.youvo.localhost:8000   | Mailhog*             |
| http://adminer.youvo.localhost:8000   | Adminer              |
| http://youvo.localhost:8080           | Traefik Dashboard    |

> (*) currently disabled

### TCP connection to database

Note that the docker image for MariaDB serves the database via the host `mariadb`. We enable an additional entrypoint in traefik for PHPStorm.

|               |                           |
| ------------- | ------------------------- |
| Host          | localhost                 |
| Port          | 3306                      |
| Database      | youvo_local               |
| User          | drupal                    |
| Password      | drupal                    |

### CodeSniffer configuration
* https://www.drupal.org/node/1419988
* Set in idea configuration file `<option name="CODING_STANDARD" value="Drupal,DrupalPractice,PHPCompatibility" />`
