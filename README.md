# youvo Backend Repository

Provides the backend for `data.youvo.org` based on `Drupal 9`.

## Setup and usage for distribution

### Development

To set up a Drupal distribution with the `youvo_development` profile, do the following:

1. Clone repository to your server.
2. Navigate to the project folder and run `composer install`.
3. Copy or configure `config/.env.development`.
4. Run `scripts/install-development.sh`.

This includes development modules such as `devel`, `coder`, `phpcodesniffer`, `phpunit`, `admin_toolbar` and more. Also, it will install dummy content from the (`dummy_`) sub-modules of `youvo`, `creatives`, `organizations`, `projects` and `academy`.

The database is `youvo_dev`.

### Production

To set up a Drupal distribution with the `youvo_platform` profile, do the following:

1. Clone repository to your server.
2. Run `composer install --no-dev`.
3. Copy or configure `config/.env.production`.
4. Run `scripts/install-production.sh`.

This provides a clean-state installation. This administrator login can be found in the environment file. For the login you may need to navigate to `/user/login`, because the site is in blocker mode (see `/admin/config/development/blockermode`). The administration can be found under `/admin/index` and `/admin/configuration`.

The database is `youvo_prod`.

### Make commands

> Help: `make help`
> Drush: `make drush`
> Maintenance mode on: `make mm-on`
> Maintenance mode off: `make mm-off`
> Clear caches: `make cr`
> Warm caches: `make warm`
> (Re-)install Drupal: `make install` (only available in Development)
> Calculate rebuild token: `make rebuild`
> Restart Uberspace PHP: `make restart-php`

### Notes

**Deploy** is defined in the script `scripts/deploy.sh`.

**Composer** is based on the package `drupal/core-recommended`. The configuration can be found in `composer.json`. Patches are defined in `composer.patches.json`. The patch files are located in the folder `patches`.

## Setup and usage for local development

### System requirements

We use DDEV based on Docker. For system requirements please see [DDEV docs](https://ddev.readthedocs.io/en/stable/).
This setup was tested with `Ubuntu 20.04.3 LTS` and the following software:

`composer 2.1.5` `docker 20.10.10` `make 4.2.1`

Can also be run on MacOS with [Docker Desktop for Mac](https://docs.docker.com/desktop/mac/install/).

Further installation steps may be required for SSH agent and XDebug (see `.ddev/php/xdebug_client_port.ini` for port). Please consult [DDEV troubleshooting](https://ddev.readthedocs.io/en/stable/users/troubleshooting/).

### DDEV configuration

The configuration can be found in `.ddev/config.yml`.

### Installation

To set up a local Drupal distribution with the `youvo_development` profile, do the following:

1. Clone repository to your system.
2. Navigate to the project folder and run `ddev config --auto`.
3. Run `composer install --no-interaction --no-progress`.
4. Consult and configure `config/.env.local`.
5. Run `scripts/install-local.sh`.

Provides development modules as described above. You may need to adjust folder permissions for dummy content folders. Use

`chmod 0666 -R academy`
`chmod 0666 -R projects`
`chmod 0666 -R creatives`
`chmod 0666 -R organizations`

in the `web/sites/default/files` folder to grant permissions.

### DDEV commands

> Help: `ddev help`
> Drush: `ddev drush`
> Container status: `ddev status`
> Start containers: `ddev start`
> Destroy containers: `ddev stop`
> Show PHP logs: `ddev logs`

## Notes

### URLs of interest for local site


| URL                          | Description |
| ------------------------------ | ------------- |
| https://youvo.ddev.site:8443 | Website     |
| https://youvo.ddev.site:8037 | phpMyAdmin  |

### PHPStorm connection to database


|          |                                     |
| ---------- | ------------------------------------- |
| Host     | localhost                           |
| Port     | run `ddev status` and find db port |
| Database | db                                  |
| User     | db                                  |
| Password | db                                  |

### PHPStorm CodeSniffer configuration

* https://www.drupal.org/node/1419988
* Set in idea configuration file `<option name="CODING_STANDARD" value="Drupal,DrupalPractice,PHPCompatibility" />`
