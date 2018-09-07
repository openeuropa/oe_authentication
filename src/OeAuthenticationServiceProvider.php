<?php

declare(strict_types = 1);


namespace Drupal\oe_authentication;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Custom service provider that allows the alteration of existing services.
 */
class OeAuthenticationServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // We provide a custom EU Login validator.
    // @todo remove this when OPENEUROPA-1206 gets in (patch gets created).
    $definition = $container->getDefinition('cas.validator');
    $definition->setClass('Drupal\oe_authentication\Service\EuLoginValidator');
  }

}
