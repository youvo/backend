# youvo Backend Repository

Provides the backend for `data.youvo.org` based on `Drupal 9`.

## Getting started locally

### Prerequisites

For the local development environment we use DDEV based on Docker. For the system requirements please see [DDEV docs](https://ddev.readthedocs.io/en/stable/). 

Tested on Ubuntu with `Ubuntu 20.04.3 LTS` `composer 2.2.1` `docker 20.10.10` `ddev 1.18.2`.    
Tested on macOS with `macOS Monterey 12.3` `composer 2.2.1` `docker desktop 4.6.0` `ddev 1.18.2`.

Further installation steps may be required to setup SSH agent and XDebug, see [DDEV troubleshooting](https://ddev.readthedocs.io/en/stable/users/troubleshooting/).

### Project Initialization and Drupal Setup

```bash
mkdir youvo-backend
cd youvo-backend
git clone git@github.com:youvo/backend.git .
ddev config --auto
ddev composer install --no-interaction --no-progress
./scripts/install-local-ddev.sh
```

Navigate to https://youvo.ddev.site:844/user/login and login with `admin@youvo.org:admin`.

## Local Configuration and Development Setup

### Configuration

- DDEV configuration `.ddev/config.yml`
- Drupal setup parameters `config/.env.local-ddev`
- XDebug port `.ddev/php/xdebug_client_port.ini` 

### DDEV Commands

```bash
ddev help # show commands help
ddev drush foo # execute drush commands
ddev status # show status of containers
ddev start # start containers
ddev stop # destroy containers
ddev logs # show php logs
```

### phpMyAdmin

Navigate to https://youvo.ddev.site:8037 and login with `db:db`. The database is `youvo_local`.

### Folder Permissions Troubleshooting

In some system configurations dummy content folders require further permissions.

```
cd web/sites/default/files
chmod 0666 -R academy projects creatives organizations
```
### PHPStorm connection to database

- Host: `localhost`
- Port: run `ddev status` for database port
- Database: `db`
- User: `db`
- Password: `db`

### PHPStorm CodeSniffer configuration

* https://www.drupal.org/node/1419988
* Set in idea configuration file `<option name="CODING_STANDARD" value="Drupal,DrupalPractice,PHPCompatibility" />`

## Distribution

### Development 

To set up a Drupal distribution with the `youvo_development` profile, do the following:

```bash
git clone git@github.com:youvo/backend.git
cd backend
cp config/.env.example config/.env.development # and adjust settings
composer install --no-interaction --no-progress
./scripts/install-development.sh
```

This includes development modules such as `devel`, `coder`, `phpcodesniffer`, `phpunit`, `admin_toolbar` and more. Also, it will install dummy content from the (`dummy_`) sub-modules of `youvo`, `creatives`, `organizations`, `projects` and `academy`.

The database is `youvo_dev`.

### Production

To set up a Drupal distribution with the `youvo_platform` profile, do the following:

```bash
git clone git@github.com:youvo/backend.git
cd backend
cp config/.env.example config/.env.production # and adjust settings
composer install --no-interaction --no-progress
./scripts/install-production.sh
```

This provides a clean-state installation. For the login you may need to navigate to `/user/login`, because the site is in blocker mode (see `/admin/config/development/blockermode`). The administration can be found under `/admin/index` and `/admin/configuration`.

The database is `youvo_prod`.

### Make commands

- Help: `make help`
- Drush: `make drush`
- Maintenance mode on: `make mm-on`
- Maintenance mode off: `make mm-off`
- Clear caches: `make cr`
- Warm caches: `make warm`
- (Re-)install Drupal: `make install` (only available in Development)
- Calculate rebuild token: `make rebuild`
- Restart Uberspace PHP: `make restart-php`

### Notes

**Deploy** is defined in the script `scripts/deploy.sh`.

**Composer** is based on the package `drupal/core-recommended`. The configuration can be found in `composer.json`. Patches are defined in `composer.patches.json`. The patch files are located in the folder `patches`.
