<h1 align="center">Backend</h1>
<p align="center">
  <img width="200" src="/web/assets/logo.png" alt="youvo Logo">
</p>

## :wave: &nbsp;Introduction

Backend repository for youvo based on Drupal 10.

### What is happening here?

We are building the new backend for [youvo.org](https://www.youvo.org) - a platform that connects social organizations with creatives for skill-based volunteering projects. The backend of the main platform runs on Drupal 7. We are migrating to a decoupled stack with Drupal 10 and Remix. The current development phase is until the end of June. Most of the features are still in early development and highly unstable. The Academy is running as a beta on [beta.youvo.org](https://beta.youvo.org/academy) (after registration).

### How to contact us?

We are not using the issue queue in this repository, yet. Just write an email to simon@youvo.org

## :whale: &nbsp;Getting started locally

### Prerequisites

For the local development environment, we use DDEV based on Docker. For the system requirements, please see [DDEV docs](https://ddev.readthedocs.io/en/stable/).

Tested on Linux with `Ubuntu 22.04.2 LTS` `composer 2.8.4` `docker 27.4.0` `ddev 1.24.0`.
Tested on macOS with `macOS Sequoia 15.1.1` `composer 2.8.4` `docker desktop 4.36.0` `ddev 1.24.0`.

Further installation steps may be required to set up SSH agent and XDebug, see [DDEV troubleshooting](https://ddev.readthedocs.io/en/stable/users/troubleshooting/).

### Composer

**Composer** is based on the package `drupal/core-recommended`. The configuration can be found in `composer.json`. Patches are defined in `composer.patches.json`. The patch files are located in the folder `patches`.

### Project Initialization and Drupal Setup

```bash
mkdir youvo-backend
cd youvo-backend
git clone git@github.com:youvo/backend.git .
# You may need to set up some configuration - see below.
ddev config --auto
# Optional crontab for local development.
ddev add-on get ddev/ddev-cron
ddev composer install
./scripts/install-local.sh
```

Navigate to https://youvo.ddev.site:8443/user/login and login with `admin@youvo.org:admin`.

## :hammer_and_wrench: &nbsp;Local Configuration and Development Setup

### Configuration

- DDEV configuration `.ddev/config.yml`
- Drupal setup parameters `config/.env.local`
- Consumers configuration `config/.env.consumers.development`
- OAuth Remote configuration `config/.env.oauth_remote.development`
- API configuration `config/.env.api`
- XDebug port `.ddev/php/xdebug_client_port.ini`
- Simple OAuth certificates, see `config/certs/README.md`

Adjust the config path in `web/sites/default/settings.php` such that `$settings['config_sync_directory'] = '../config/sync'`;.

Set `$settings['file_public_path'] = 'files';`.
Set `$settings['file_assets_path'] = 'files';`.
Set `$settings['file_private_path'] = '../private';`.

Note that some of the environment variables files will be merged in the future. We entertain some separation at the moment for development purposes.

### DDEV Commands

```bash
ddev help # show commands help
ddev drush foo # execute Drush commands
ddev status # show status of containers
ddev start # start containers
ddev stop # destroy containers
ddev logs # show php logs
```

### phpMyAdmin

Install via `ddev add-on get ddev/ddev-phpmyadmin` and navigate to https://youvo.ddev.site:8037 and login with `db:db`. The database is `youvo_local`.

### Folder permissions Troubleshooting

In some system configurations dummy content folders require further permissions.

```
cd web/sites/default/files
chmod 0666 -R academy projects creatives organizations
```

### PHPStorm Connection to Database

You may use the DDEV Integration plugin or configure the database connection as follows.

- Host: `localhost`
- Port: `59002`
- Database: `db`
- User: `db`
- Password: `db`

### PHPStorm CodeSniffer configuration

* https://www.drupal.org/node/1419988
* Set in idea configuration file `<option name="CODING_STANDARD" value="Drupal,DrupalPractice,PHPCompatibility" />`

## :globe_with_meridians: &nbsp;Distribution

### Development

To set up a Drupal distribution with the `youvo_development` profile, do the following:

```bash
git clone git@github.com:youvo/backend.git
cd backend
cp config/.env.example config/.env.development # and adjust settings
cp config/.env.api.example config/.env.api # and adjust settings
cp config/.env.consumers.example config/.env.consumers.development # and adjust settings
cp config/.env.oauth_remote.example config/.env.oauth_remote.development # and adjust settings
composer install
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
cp config/.env.api.example config/.env.api # and adjust settings
cp config/.env.consumers.example config/.env.consumers.development # and adjust settings
cp config/.env.oauth_remote.example config/.env.oauth_remote.development # and adjust settings
composer install
./scripts/install-production.sh
```

This provides a clean-state installation. For the login you may need to navigate to `/user/login`, because the site is in blocker mode (see `/admin/config/development/blockermode`). The administration can be found under `/admin/index` and `/admin/configuration`.

The database is `youvo_prod`.

### Make commands

```bash
make help # show commands help
make mm-on # maintenance mode on
make mm-off # maintenance mode off
make cr # clear caches
make warm # warm caches
make warm-images # warm up image styles
make gi # generate queued image styles
make fis # flush all image styles
make install # (re-)install Drupal (only available in development)
make rebuild # calculate rebuild token
make restart-php # restart Uberspace php
make phpstan %PATH # run PHPStan analysis at specified path
make phpcs # run PHP_CodeSniffer analysis for custom modules
```

### Notes

**Deploy** is defined in the script `scripts/deploy.sh`.
