<?php

declare(strict_types = 1);


namespace Drupal\oe_authentication;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Class ServiceProvider.
 */
class OeAuthenticationServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // We provide a custom ECAS validator.
    $definition = $container->getDefinition('cas.validator');
    $definition->setClass('Drupal\oe_authentication\Service\ECasValidator');
  }

}
