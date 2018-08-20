<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * Discovery for the OE Authentication library settings.
 */
class OeAuthenticationServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    parent::alter($container);
    // Disable the cookie authentication provider (default login provider).
    $container->removeDefinition('user.authentication.cookie');
  }

}
