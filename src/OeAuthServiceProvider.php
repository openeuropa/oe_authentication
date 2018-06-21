<?php

declare(strict_types = 1);

namespace Drupal\oe_auth;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * Discovery for the OE Auth library settings.
 */
class OeAuthServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    parent::alter($container);
    // Disable the cookie authentication provider (default login provider).
    $container->removeDefinition('user.authentication.cookie');
  }

}
