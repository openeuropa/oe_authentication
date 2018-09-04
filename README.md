# OpenEuropa Authentication

[![Build Status](https://drone.fpfis.eu/api/badges/openeuropa/oe_authentication/status.svg?branch=master)](https://drone.fpfis.eu/openeuropa/oe_authentication)

The OpenEuropa Authentication module allows to authenticate against the European Commission login service.

**Table of contents:**

- [Installation](#installation)
- [Development](#development)
  - [Project setup](#project-setup)
  - [Using Docker Compose](#using-docker-compose)
  - [Disable Drupal 8 caching](#disable-drupal-8-caching)
- [Demo module](#demo-module)

## Installation

The recommended way of installing the OpenEuropa Authentication module is via a [Composer][2].

```bash
composer require openeuropa/oe_authentication
```

Before being able to use the module, you will need to specify the ECAS service parameters
in your Drupal installation's `./sites/default/settings.php` file as shown below:

```
$config['cas.settings']['server']['hostname'] = 'ecas.ec.europa.eu';
$config['cas.settings']['server']['port'] = NULL;
$config['cas.settings']['server']['path'] = '/cas';
$config['cas.settings']['server']['protocol'] = 'https';
```

For more information about how to override service parameters in Drupal 8
check the [related documentation page](https://www.drupal.org/docs/8/api/services-and-dependency-injection/services-and-dependency-injection-in-drupal-8)

### Enable the module

In order to enable the module in your project run:

```
$ ./vendor/bin/drush en oe_authentication
```

## Development

The OpenEuropa Authentication project contains all the necessary code and tools for an effective development process,
such as:

- All PHP development dependencies (Drupal core included) are required by [composer.json](composer.json)
- Project setup and installation can be easily handled thanks to the integration with the [Task Runner][3] project.
- All system requirements are containerized using [Docker Composer][4]
- A mock server for testing.

### Project setup

Download all required PHP code by running:

```
$ composer install
```

This will build a fully functional Drupal test site in the `./build` directory that can be used to develop and showcase
the module's functionality.

Before setting up and installing the site make sure to customize default configuration values by copying [runner.yml.dist](runner.yml.dist)
to `./runner.yml` and overriding relevant properties.

To set up the project run:

```
$ ./vendor/bin/run drupal:site-setup
```

This will:

- Symlink the theme in  `./build/modules/custom/oe_authentication` so that it's available for the test site
- Setup Drush and Drupal's settings using values from `./runner.yml.dist`
- Setup PHPUnit and Behat configuration files using values from `./runner.yml.dist`

After a successful setup install the site by running:

```
$ ./vendor/bin/run drupal:site-install
```

This will:

- Install the test site
- Enable the OpenEuropa Authentication module

Then setup the mock servers dependencies by running:
```
$ ./vendor/bin/composer --no-ansi --working-dir=/var/www/html/vendor/openeuropa/pcas/demo-server/ install
```

### Using Docker Compose

The setup procedure described above can be sensitively simplified by using Docker Compose.

Requirements:

- [Docker][8]
- [Docker-compose][9]

Copy docker-compose.yml.dist into docker-compose.yml.

You can make any alterations you need for your local Docker setup. However, the defaults should be enough to set the project up.

Run:

```
$ docker-compose up -d
```

Then:

```
$ docker-compose exec web composer install
$ docker-compose exec web ./vendor/bin/run drupal:site-install
```

To be able to interact with the OpenEuropa Authentication mock container you need to add the internal container hostname to the hosts file _of your host OS_.
```
$ echo "127.0.1.1       authentication" >> /etc/hosts
```

Your test site will be available at [http://localhost:8080/build](http://localhost:8080/build).

Run tests as follows:

```
$ docker-compose exec web ./vendor/bin/phpunit
$ docker-compose exec web ./vendor/bin/behat
```

### Disable Drupal 8 caching

Manually disabling Drupal 8 caching is a laborious process that is well described [here][10].

Alternatively you can use the following Drupal Console commands to disable/enable Drupal 8 caching:

```
$ ./vendor/bin/drupal site:mode dev  # Disable all caches.
$ ./vendor/bin/drupal site:mode prod # Enable all caches.
```

Note: to fully disable Twig caching the following additional manual steps are required:

1. Open `./build/sites/default/services.yml`
2. Set `cache: false` in `twig.config:` property. E.g.:
```
parameters:
 twig.config:
   cache: false
```
3. Rebuild Drupal cache: `./vendor/bin/drush cr`

This is due to the following [Drupal Console issue][11].

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
