# OpenEuropa Authentication

[![Build Status](https://drone.fpfis.eu/api/badges/openeuropa/oe_authentication/status.svg?branch=master)](https://drone.fpfis.eu/openeuropa/oe_authentication)
[![Packagist](https://img.shields.io/packagist/v/openeuropa/oe_authentication.svg)](https://packagist.org/packages/openeuropa/oe_authentication)

The OpenEuropa Authentication module allows authentication against EU Login, the European Commission login service.

**Table of contents:**

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Development](#development)
  - [Project setup](#project-setup)
  - [Using Docker Compose](#using-docker-compose)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contribution)
- [Versioning](#versioning)

## Requirements

This module requires the following modules:
 - [Cas](https://www.drupal.org/project/cas)

## Installation

The recommended way of installing the OpenEuropa Authentication module is via [Composer][2].

```bash
composer require openeuropa/oe_authentication
```

### Enable the module

In order to enable the module in your project run:

```bash
./vendor/bin/drush en oe_authentication
```

EU Login service parameters are already set by default when installing the module. Please refer to the EU Login documentation for the available options that can
be specified. You can see Project setup section on how to override these parameters.

## Configuration

EU Login service parameters are already set by default when installing the module. Please refer to the EU Login
documentation for the available options that can be specified. You can see Project setup section on how to override
these parameters.

### Settings overrides

In the Drupal `settings.php` you can override CAS parameters such as the ones below, corresponding to the
`cas.settings` and `oe_authentication.settings` configuration objects.

```php
$config['cas.settings']['server']['hostname'] = 'authentication';
$config['cas.settings']['server']['port'] = '7002';
$config['cas.settings']['server']['path'] = '/cas';
$config['oe_authentication.settings']['register_path'] = 'register';
$config['oe_authentication.settings']['validation_path'] = 'TicketValidationService';
```

By default, the development setup is configured via Task Runner to use the demo CAS server provided in the
`docker-compose.yml.dist`, i.e. `https://authentication:7002`.

If you want to test the module with the actual EU Login service, comment out all the lines above in your `settings.php`
and clear the cache.

### Account Handling & Auto Registration

The module enables the option that if a user attempts to login with an account that is not already
registered, the account will automatically be created.

See the [Cas module](https://www.drupal.org/project/cas) for more information.

### Forced Login

The module enables the Forced Login feature to force anonymous users to
authenticate via CAS when they hit all or some of the pages on your site.

See the [Cas module](https://www.drupal.org/project/cas) for more information.

### SSL Verification Setting

The EU Login Authentication server must be accessed over HTTPS and the drupal site will verify the SSL/TLS certificate
of the server to be sure it is authentic.

For development, you can configure the module to disable this verification:
```php
$config['cas.settings']['server']['verify'] = '2';
```
_NOTE: DO NOT USE IN PRODUCTION!_

See the [Cas module](https://www.drupal.org/project/cas) for more information.

### Proxy

You can configure the module to "Initialize this client as a proxy" which allows
authentication requests to 3rd party services (e.g. ePOETRY).

```php
$config['cas.settings']['proxy']['initialize'] = TRUE;
```

See the [Cas module](https://www.drupal.org/project/cas) for more information.

## Development setup

You can build the development site by running the following steps:

* Install the Composer dependencies:

```bash
composer install
```

A post command hook (`drupal:site-setup`) is triggered automatically after `composer install`.
This will symlink the module in the proper directory within the test site and perform token substitution in test configuration files such as `behat.yml.dist`.

**Please note:** project files and directories are symlinked within the test site by using the
[OpenEuropa Task Runner's Drupal project symlink](https://github.com/openeuropa/task-runner-drupal-project-symlink) command.

If you add a new file or directory in the root of the project, you need to re-run `drupal:site-setup` in order to make
sure they are be correctly symlinked.

If you don't want to re-run a full site setup for that, you can simply run:

```
$ ./vendor/bin/run drupal:symlink-project
```

* Install test site by running:

```bash
$ ./vendor/bin/run drupal:site-install
```

The development site web root should be available in the `build` directory.

This will:

- Install the test site
- Enable the OpenEuropa Authentication module

### Using Docker Compose

Alternatively, you can build a development site using [Docker](https://www.docker.com/get-docker) and
[Docker Compose](https://docs.docker.com/compose/) with the provided configuration.

Docker provides the necessary services and tools such as a web server and a database server to get the site running,
regardless of your local host configuration.

#### Requirements:

- [Docker](https://www.docker.com/get-docker)
- [Docker Compose](https://docs.docker.com/compose/)

#### Configuration

By default, Docker Compose reads two files, a `docker-compose.yml` and an optional `docker-compose.override.yml` file.
By convention, the `docker-compose.yml` contains your base configuration and it's provided by default.
The override file, as its name implies, can contain configuration overrides for existing services or entirely new
services.
If a service is defined in both files, Docker Compose merges the configurations.

Find more information on Docker Compose extension mechanism on [the official Docker Compose documentation](https://docs.docker.com/compose/extends/).

#### Usage

To start, run:

```bash
docker-compose up
```

It's advised to not daemonize `docker-compose` so you can turn it off (`CTRL+C`) quickly when you're done working.
However, if you'd like to daemonize it, you have to add the flag `-d`:

```bash
docker-compose up -d
```

Then:

```bash
docker-compose exec web composer install
docker-compose exec web ./vendor/bin/run drupal:site-install
```

To be able to interact with the EULogin Mock Service container you need to add the internal container hostname to the hosts file in your _OS_.

```bash
echo "127.0.1.1       authentication" >> /etc/hosts
```

Using default configuration, the development site files should be available in the `build` directory and the development site should be available at: [http://127.0.0.1:8080/build](http://127.0.0.1:8080/build).

#### Running the tests

To run the grumphp checks:

```bash
docker-compose exec web ./vendor/bin/grumphp run
```

To run the phpunit tests:

```bash
docker-compose exec web ./vendor/bin/phpunit
```

To run the behat tests:

```bash
docker-compose exec web ./vendor/bin/behat
```

### Troubleshooting

#### Disable Drupal 8 caching

Manually disabling Drupal 8 caching is a laborious process that is well described [here][10].

Alternatively you can use the following Drupal Console commands to disable/enable Drupal 8 caching:

```bash
./vendor/bin/drupal site:mode dev  # Disable all caches.
./vendor/bin/drupal site:mode prod # Enable all caches.
```

Note: to fully disable Twig caching the following additional manual steps are required:

1. Open `./build/sites/default/services.yml`
2. Set `cache: false` in `twig.config:` property. E.g.:

```yaml
parameters:
 twig.config:
   cache: false
```

3. Rebuild Drupal cache: `./vendor/bin/drush cr`

This is due to the following [Drupal Console issue][11].

### Contributing

Please read [the full documentation](https://github.com/openeuropa/openeuropa) for details on our code of conduct, and the process for submitting pull requests to us.

### Versioning

We use [SemVer](http://semver.org/) for versioning. For the available versions, see the [tags on this repository](https://github.com/openeuropa/oe_authentication/tags).

[1]: https://github.com/openeuropa/oe_theme
[2]: https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies#managing-contributed
[3]: https://github.com/openeuropa/task-runner
[4]: https://docs.docker.com/compose
[5]: https://github.com/openeuropa/oe_theme#project-setup
[6]: https://nodejs.org/en
[7]: https://www.drupal.org/project/config_devel
[8]: https://www.docker.com/get-docker
[9]: https://docs.docker.com/compose
[10]: https://www.drupal.org/node/2598914
[11]: https://github.com/hechoendrupal/drupal-console/issues/3854
[12]: https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules
[13]: https://www.drush.org/
